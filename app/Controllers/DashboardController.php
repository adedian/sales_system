<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Jobs;
use App\Models\AuditLog;
use App\Models\ProcurementPriceValidation;
use App\Models\EngineerAssignment;
use App\Models\FollowUp;
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
        // Role dengan visibilitas seluruh perusahaan tetap memakai dashboard analitik.
        if (Acl::hasRole('super-admin') || Acl::hasRole('admin-sales') || Acl::hasRole('manager')) {
            $this->renderAnalyticsDashboard();

            return;
        }

        // Selain itu dashboard mengikuti ROLE pengguna (bisa lebih dari satu). Setiap
        // role punya ringkasan pekerjaan sendiri; pengguna multi-role melihat
        // ringkasan semua role-nya, dikelompokkan dan diberi label per role.
        $jobs = Jobs::of();

        // Urutan = urutan tampil. Engineer dan Sales Engineer memakai ringkasan tugas teknis yang sama.
        $blocks = [];
        if (in_array('sales', $jobs, true)) {
            $blocks[] = ['label' => Jobs::LABELS['sales'], 'render' => fn () => $this->renderSalesWorkspace()];
        }
        if (array_intersect(['sales_engineer', 'engineer'], $jobs) !== []) {
            $blocks[] = [
                'label' => implode(' / ', array_map(fn ($j) => Jobs::LABELS[$j], array_values(array_intersect(['sales_engineer', 'engineer'], $jobs)))),
                'render' => fn () => $this->renderEngineerWorkspace(),
            ];
        }
        if (in_array('surveyor', $jobs, true)) {
            $blocks[] = ['label' => Jobs::LABELS['surveyor'], 'render' => fn () => $this->renderSurveyorWorkspace()];
        }
        if (in_array('procurement', $jobs, true)) {
            $blocks[] = ['label' => Jobs::LABELS['procurement'], 'render' => fn () => $this->renderProcurementWorkspace()];
        }
        if (in_array('director', $jobs, true)) {
            $blocks[] = ['label' => 'Approval ' . Jobs::LABELS['director'], 'render' => fn () => $this->renderApprovalWorkspace()];
        }

        if ($blocks === []) {
            $this->renderAnalyticsDashboard();

            return;
        }

        if (count($blocks) === 1) {
            ($blocks[0]['render'])();

            return;
        }

        // Multi-role: tiap ringkasan dirender ke buffer lalu disatukan dengan label role.
        $groups = [];
        $this->collected = [];
        foreach ($blocks as $block) {
            $this->collected = [];
            ($block['render'])();
            $groups[] = ['label' => $block['label'], 'id' => 'dash-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($block['label'])), '-'), 'html' => implode('', $this->collected)];
        }
        $this->collected = null;

        $this->view('dashboard/multi', ['pageTitle' => 'Dashboard', 'groups' => $groups]);
    }

    /** Bila tidak null, view() menampung HTML (tanpa layout) alih-alih mencetaknya — dipakai dashboard multi-role. */
    private ?array $collected = null;

    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        if ($this->collected !== null) {
            ob_start();
            \App\Core\View::render($view, $data, null);
            $this->collected[] = (string) ob_get_clean();

            return;
        }

        parent::view($view, $data, $layout);
    }
    /**
     * Dashboard Surveyor: penugasan survey, jadwal, dan Survey Report yang
     * belum selesai.
     */
    private function renderSurveyorWorkspace(): void
    {
        $userId = (int) Auth::id();

        $this->view('dashboard/surveyor_workspace', [
            'pageTitle' => 'Workspace Surveyor',
            'counts' => \App\Models\Survey::countsForSurveyor($userId),
            'openSurveys' => \App\Models\Survey::openForSurveyor($userId, 10),
            'recentActivity' => AuditLog::recentForUser($userId, 8),
        ]);
    }

    /**
     * Dashboard Direktur: hanya pekerjaan yang butuh keputusan Direktur.
     */
    private function renderApprovalWorkspace(): void
    {
        $queue = ProcurementPriceValidation::pendingQueue();
        $total = 0.0;
        foreach ($queue as $row) {
            $total += (float) $row['total_price'];
        }

        $this->view('dashboard/approval_workspace', [
            'pageTitle' => 'Workspace Approval',
            'queue' => $queue,
            'queueTotal' => $total,
            'recentActivity' => AuditLog::recentForUser((int) Auth::id(), 8),
        ]);
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
        $positionCounts = Lead::currentProcessDistribution();

        // % perubahan; null = sebelumnya 0 dan sekarang ada (tidak bisa dihitung persen).
        $pct = function (float $cur, float $prev): ?int {
            if ($prev > 0) {
                return (int) round((($cur - $prev) / $prev) * 100);
            }

            return $cur > 0 ? null : 0;
        };

        // Kartu "Pertumbuhan Lead": lead baru per periode vs periode sebelumnya yang sama panjang.
        $growth = [];
        foreach ([7 => '7 Hari', 30 => '30 Hari', 90 => '90 Hari'] as $days => $label) {
            $cur = Lead::createdCountInWindow($days - 1, 0);
            $prev = Lead::createdCountInWindow(($days * 2) - 1, $days);
            $growth[$days] = ['label' => $label, 'count' => $cur, 'delta' => $pct($cur, $prev)];
        }

        // Grafik mingguan: 14 hari terakhir -> 7 hari ini + 7 hari sebelumnya (untuk pembanding).
        $series = [
            'leads' => ['label' => 'Lead Masuk', 'noun' => 'lead baru', 'data' => Lead::createdPerDay(14)],
            'won' => ['label' => 'Deal Won', 'noun' => 'deal won', 'data' => Lead::wonPerDay(14)],
            'followup' => ['label' => 'Follow Up', 'noun' => 'follow up', 'data' => FollowUp::loggedPerDay(14)],
        ];
        $week = ['days' => [], 'metrics' => []];
        foreach ($series as $key => $s) {
            $values = array_values($s['data']);
            $dates = array_keys($s['data']);
            $thisWeek = array_slice($values, 7);
            $lastWeek = array_slice($values, 0, 7);
            $week['days'] = array_slice($dates, 7);
            $week['metrics'][$key] = [
                'label' => $s['label'],
                'noun' => $s['noun'],
                'values' => $thisWeek,
                'total' => array_sum($thisWeek),
                'prev_total' => array_sum($lastWeek),
                'delta' => $pct(array_sum($thisWeek), array_sum($lastWeek)),
            ];
        }
        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $week['day_labels'] = array_map(fn ($d) => $dayNames[(int) date('w', strtotime($d))], $week['days']);

        $wonThisMonth = Lead::wonValueInMonth(0);
        $wonLastMonth = Lead::wonValueInMonth(1);

        // Alur proses: tahap berurutan seperti urutan kerja di lapangan.
        $tally = array_column($positionCounts, 'total', 'label');
        $sum = fn (array $labels) => array_sum(array_map(fn ($l) => (int) ($tally[$l] ?? 0), $labels));
        $isDirector = can('price.validate');
        $flowStages = [
            ['label' => 'Di Sales', 'hint' => 'Belum masuk antrian', 'total' => $sum(['Sales']), 'url' => '/leads', 'can' => true],
            ['label' => 'Survey & Data', 'hint' => 'Kelengkapan data awal', 'total' => $sum(['Survey', 'Data Antrian']), 'url' => '/queue', 'can' => can('queue.view')],
            ['label' => 'Prelim', 'hint' => 'Penawaran awal ke client', 'total' => $sum(['Prelim']), 'url' => '/prelims', 'can' => can('prelim.view')],
            ['label' => 'Engineering', 'hint' => 'Analisa teknis & BOQ', 'total' => $sum(['Sales Engineer', 'Engineer']), 'url' => '/engineer', 'can' => can('engineer.view')],
            ['label' => 'Procurement', 'hint' => 'Harga vendor', 'total' => $sum(['Procurement']), 'url' => '/procurement', 'can' => can('procurement.view')],
            ['label' => 'Approval Harga', 'hint' => 'Validasi Direktur', 'total' => $sum(['Approval Harga']), 'url' => $isDirector ? '/procurement/validation' : '/procurement', 'can' => can('procurement.view') || $isDirector],
            ['label' => 'Proposal', 'hint' => 'Penawaran final', 'total' => $sum(['Proposal']), 'url' => '/proposals', 'can' => can('proposal.view')],
        ];
        $flowMax = max(1, ...array_column($flowStages, 'total'));

        $this->view('dashboard/index', [
            'growth' => $growth,
            'week' => $week,
            'wonMonth' => ['this' => $wonThisMonth, 'last' => $wonLastMonth, 'delta' => $pct($wonThisMonth, $wonLastMonth)],
            'newToday' => Lead::createdCountInWindow(0, 0),
            'flowStages' => $flowStages,
            'flowMax' => $flowMax,
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
            'positionCounts' => $positionCounts,
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
            'statusMap' => MasterData::allAsMap('lead_statuses'),
            'recentActivity' => AuditLog::recentForUser($salesId, 8),
            // Survey dari Lead milik sendiri (Sales memantau, bukan petugas Survey).
            'mySurveys' => \App\Models\Survey::search(['scope_user_id' => $salesId, 'status' => 'ongoing', 'per_page' => 5])['rows'],
            'openProposals' => Proposal::openForSales($salesId, 6),
            'proposalStatusMap' => MasterData::allAsMap('proposal_statuses'),
            // Sales yang juga Direktur (mis. Pak Ronny) melihat approval harga yang menunggu.
            'pendingApprovals' => Jobs::has('director') ? count(ProcurementPriceValidation::pendingQueue()) : 0,
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
            // Dukungan Survey: hanya Survey tempat Engineer ini ditugaskan secara eksplisit.
            'supportSurveys' => Jobs::has('engineer') ? \App\Models\Survey::openForSupport($engineerId, 6) : [],
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
