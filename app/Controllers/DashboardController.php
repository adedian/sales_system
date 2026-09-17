<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\AuditLog;
use App\Models\EngineerAssignment;
use App\Models\Lead;
use App\Models\MasterData;
use App\Models\ProcurementRequest;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\SalesQueue;
use App\Models\Setting;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(): void
    {
        if (Acl::hasRole('sales')) {
            $this->renderSalesWorkspace();

            return;
        }

        if (Acl::hasRole('engineer-sales')) {
            $this->renderEngineerWorkspace();

            return;
        }

        if (Acl::hasRole('procurement')) {
            $this->renderProcurementWorkspace();

            return;
        }

        $this->renderAnalyticsDashboard();
    }

    /**
     * Phase 12 — the dashboard for every role with company-wide visibility
     * (Super Admin, Admin Sales, Manager/Supervisor): pipeline/proposal/won/
     * lost value, conversion rate, per-sales performance, aging/bottleneck,
     * and team workload — all computed live from the current tables (no
     * dummy data, no snapshot/cache table — the dataset is small enough).
     */
    private function renderAnalyticsDashboard(): void
    {
        $statusMap = MasterData::allAsMap('lead_statuses');
        $statusCounts = Lead::countByStatus();
        $pipelineByStatus = array_map(function ($code, $total) use ($statusMap) {
            return [
                'code' => $code,
                'label' => $statusMap[$code]['name'] ?? $code,
                'total' => $total,
            ];
        }, array_keys($statusCounts), $statusCounts);
        // Only the open pipeline stages belong in the funnel chart — Won/Lost are outcomes, shown as KPIs instead.
        $pipelineByStatus = array_values(array_filter($pipelineByStatus, fn ($row) => !in_array($row['code'], ['won', 'lost'], true)));

        $aging = Lead::agingByStatus();
        $bottleneck = null;
        foreach ($aging as $row) {
            if ($bottleneck === null || $row['total'] > $bottleneck['total']) {
                $bottleneck = $row;
            }
        }

        $slaDays = Setting::getInt('sla_lead_aging_days', 7);

        $this->view('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'stats' => [
                'active_users' => User::countActive(),
                'total_roles' => Role::count(),
                'audit_events' => AuditLog::count(),
            ],
            'kpi' => [
                'total_leads' => array_sum($statusCounts),
                'proses_count' => array_sum($statusCounts) - $statusCounts['won'] - $statusCounts['lost'],
                'deal_count' => $statusCounts['won'],
                'cancel_count' => $statusCounts['lost'],
                'pipeline_value' => Lead::pipelineValue(),
                'proposal_value' => Proposal::openValue(),
                'won_value' => Lead::wonValue(),
                'lost_value' => Lead::lostValue(),
                'conversion_rate' => Lead::conversionRate(),
            ],
            'pipelineByStatus' => $pipelineByStatus,
            'statusMap' => $statusMap,
            'priorityMap' => MasterData::allAsMap('priorities'),
            'salesPerformance' => Lead::salesPerformance(),
            'aging' => $aging,
            'bottleneck' => $bottleneck,
            'slaDays' => $slaDays,
            'activeLeads' => Lead::activeLeadsOverview(8),
            'attentionRequired' => Lead::attentionRequired($slaDays, 5),
            // NB: EngineerAssignment::workloadByEngineer() defaults to
            // assignment_type 'sales_engineer' — this dashboard previously
            // called it with no argument for the "Engineer" card, which
            // actually rendered Sales Engineer staff (Fita/Rika) under an
            // "Engineer" label. Both are now explicit and separate, plus
            // the previously-missing Surveyor workload.
            'workload' => [
                'sales_engineer' => EngineerAssignment::workloadByEngineer('sales_engineer'),
                'engineer' => EngineerAssignment::workloadByEngineer('engineer'),
                'surveyor' => SalesQueue::workloadBySurveyor(),
                'procurement' => ProcurementRequest::workloadByAssignee(),
            ],
            'recentActivity' => AuditLog::recent(8),
            'positionCounts' => Lead::currentProcessDistribution(),
            'notYetQueuedCount' => Lead::countNotYetQueued(),
        ]);
    }

    /**
     * Phase 7: a Sales rep's personal landing page — replaces the generic
     * admin dashboard for this role only, so "Dashboard" (the first,
     * always-visible sidebar item) already IS their workspace. No new nav
     * entry, no extra click.
     */
    private function renderSalesWorkspace(): void
    {
        $salesId = (int) Auth::id();

        $leadFollowUps = array_map(function ($row) {
            $row['source'] = 'lead';
            return $row;
        }, Lead::pendingFollowUps($salesId));

        $queueFollowUps = array_map(function ($row) {
            $row['source'] = 'queue';
            return $row;
        }, SalesQueue::pendingFollowUps($salesId));

        $followUps = array_merge($leadFollowUps, $queueFollowUps);
        usort($followUps, fn ($a, $b) => strcmp($a['follow_up_date'] ?? $a['followup_date'], $b['follow_up_date'] ?? $b['followup_date']));

        $leadCounts = Lead::countByStatus($salesId);
        $queueCounts = SalesQueue::dashboardCounts($salesId);
        $myLeadsTotal = array_sum($leadCounts);
        $myQueueActive = $queueCounts['new'] + $queueCounts['waiting_followup'] + $queueCounts['in_progress'] + $queueCounts['waiting_engineer'];

        $this->view('dashboard/sales_workspace', [
            'pageTitle' => 'Workspace Saya',
            'summary' => [
                'my_leads' => $myLeadsTotal,
                'my_queue' => $myQueueActive,
                'follow_up_today' => count($followUps),
                'overdue' => $queueCounts['overdue'],
                'waiting_engineer' => $queueCounts['waiting_engineer'],
                'completed' => $queueCounts['done'],
            ],
            'followUps' => array_slice($followUps, 0, 10),
            'recentLeads' => Lead::recentForSales($salesId, 6),
            'recentActivity' => AuditLog::recentForUser($salesId, 8),
            'openProposals' => Proposal::openForSales($salesId, 6),
            'proposalStatusMap' => MasterData::allAsMap('proposal_statuses'),
        ]);
    }

    /**
     * Phase 8: an Engineer Sales rep's personal landing page — same pattern
     * as renderSalesWorkspace() above, replacing the generic admin
     * dashboard for this role only.
     */
    private function renderEngineerWorkspace(): void
    {
        $engineerId = (int) Auth::id();
        $counts = EngineerAssignment::dashboardCounts($engineerId);

        $this->view('dashboard/engineer_workspace', [
            'pageTitle' => 'Workspace Saya',
            'summary' => [
                'new_assignment' => $counts['pending'],
                'accepted' => $counts['accepted'],
                'in_progress' => $counts['in_progress'],
                'waiting' => $counts['waiting'],
                'completed' => $counts['completed'],
                'rejected' => $counts['rejected'],
                'overdue' => $counts['overdue'],
            ],
            'openAssignments' => EngineerAssignment::openForEngineer($engineerId, 8),
            'statusMap' => MasterData::allAsMap('engineer_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'recentActivity' => AuditLog::recentForUser($engineerId, 8),
        ]);
    }

    /**
     * Phase 7: a Procurement staff's personal landing page — same pattern as
     * renderSalesWorkspace()/renderEngineerWorkspace() above.
     */
    private function renderProcurementWorkspace(): void
    {
        $userId = (int) Auth::id();
        $counts = ProcurementRequest::dashboardCounts($userId);

        $this->view('dashboard/procurement_workspace', [
            'pageTitle' => 'Workspace Saya',
            'summary' => [
                'new_requests' => $counts['waiting'],
                'in_progress' => $counts['in_progress'],
                'quotation_requested' => $counts['quotation_requested'],
                'need_revision' => $counts['need_revision'],
                'completed' => $counts['pricing_completed'],
                'overdue' => $counts['overdue'],
            ],
            'openRequests' => ProcurementRequest::openForAssignee($userId, 8),
            'statusMap' => MasterData::allAsMap('procurement_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'recentActivity' => AuditLog::recentForUser($userId, 8),
        ]);
    }
}
