<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\Request;
use App\Models\EngineerAssignment;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\MasterData;
use App\Models\ProcurementRequest;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\SalesQueue;
use App\Models\User;

/**
 * Phase 13 — Reporting. Eight report types (lead/queue/engineer/procurement/
 * proposal/follow-up/deal/performance), each built the same way: a filter
 * bar, summary cards, a column-generic table, and an optional CSV export
 * (gated separately by report.export — see routes/web.php). No pagination:
 * reports show/export the full filtered set, which is fine at this app's
 * data scale (see reports/show.php for the shared rendering).
 */
class ReportController extends Controller
{
    public function index(): void
    {
        $this->view('reports/index', ['pageTitle' => 'Laporan']);
    }

    public function leads(Request $request): void
    {
        $filters = $this->readFilters($request, ['q', 'date_from', 'date_to', 'sales_id', 'status']);
        $rows = Lead::report($filters);
        $statusMap = MasterData::allAsMap('lead_statuses');
        $priorityMap = MasterData::allAsMap('priorities');

        $won = 0;
        $lost = 0;
        $totalValue = 0.0;
        foreach ($rows as $r) {
            if ($r['status'] === 'won') $won++;
            if ($r['status'] === 'lost') $lost++;
            $totalValue += (float) ($r['estimated_value'] ?? 0);
        }

        $this->renderReport($request, [
            'title' => 'Laporan Lead',
            'filename' => 'laporan-lead',
            'path' => '/reports/leads',
            'filterDefs' => [
                'q' => ['type' => 'text', 'label' => 'Cari Customer/Perusahaan'],
                'date_from' => ['type' => 'date', 'label' => 'Dari Tanggal'],
                'date_to' => ['type' => 'date', 'label' => 'Sampai Tanggal'],
                'sales_id' => ['type' => 'select', 'label' => 'Sales', 'options' => $this->salesOptions()],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => $this->mapToOptions($statusMap)],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Total Lead', 'value' => (string) count($rows)],
                ['label' => 'Total Estimasi Nilai', 'value' => $this->rupiah($totalValue)],
                ['label' => 'Won', 'value' => (string) $won],
                ['label' => 'Lost', 'value' => (string) $lost],
            ],
            'columns' => [
                'lead_code' => 'Kode', 'customer_name' => 'Customer', 'company_name' => 'Perusahaan',
                'status_label' => 'Status', 'priority_label' => 'Prioritas', 'sales_name' => 'Sales',
                'estimated_value_fmt' => 'Estimasi Nilai', 'created_at_fmt' => 'Dibuat',
            ],
            'rows' => array_map(function ($r) use ($statusMap, $priorityMap) {
                $r['status_label'] = $statusMap[$r['status']]['name'] ?? $r['status'];
                $r['priority_label'] = $priorityMap[$r['priority']]['name'] ?? $r['priority'];
                $r['estimated_value_fmt'] = $r['estimated_value'] !== null ? $this->rupiah((float) $r['estimated_value']) : '-';
                $r['created_at_fmt'] = format_datetime($r['created_at'], 'd M Y');
                return $r;
            }, $rows),
        ]);
    }

    public function queue(Request $request): void
    {
        $filters = $this->readFilters($request, ['date_from', 'date_to', 'sales_id', 'status']);
        $rows = SalesQueue::report($filters);
        $statusMap = MasterData::allAsMap('queue_statuses');
        $priorityMap = MasterData::allAsMap('priorities');

        $done = 0;
        $overdue = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'done') $done++;
            if (!empty($r['deadline']) && $r['deadline'] < date('Y-m-d') && !in_array($r['status'], ['done', 'cancelled'], true)) $overdue++;
        }

        $this->renderReport($request, [
            'title' => 'Laporan Antrian Sales',
            'filename' => 'laporan-antrian',
            'path' => '/reports/queue',
            'filterDefs' => [
                'date_from' => ['type' => 'date', 'label' => 'Dari Tanggal'],
                'date_to' => ['type' => 'date', 'label' => 'Sampai Tanggal'],
                'sales_id' => ['type' => 'select', 'label' => 'Sales', 'options' => $this->salesOptions()],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => $this->mapToOptions($statusMap)],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Total Antrian', 'value' => (string) count($rows)],
                ['label' => 'Selesai', 'value' => (string) $done],
                ['label' => 'Overdue', 'value' => (string) $overdue],
            ],
            'columns' => [
                'queue_number' => 'No. Antrian', 'lead_code' => 'Lead', 'customer_name' => 'Customer',
                'sales_name' => 'Sales', 'status_label' => 'Status', 'priority_label' => 'Prioritas',
                'entered_at_fmt' => 'Masuk', 'deadline_fmt' => 'Deadline',
            ],
            'rows' => array_map(function ($r) use ($statusMap, $priorityMap) {
                $r['status_label'] = $statusMap[$r['status']]['name'] ?? $r['status'];
                $r['priority_label'] = $priorityMap[$r['priority']]['name'] ?? $r['priority'];
                $r['entered_at_fmt'] = format_datetime($r['entered_at'], 'd M Y');
                $r['deadline_fmt'] = $r['deadline'] ? format_datetime($r['deadline'], 'd M Y') : '-';
                return $r;
            }, $rows),
        ]);
    }

    /** Phase C — every lead plus its computed Current Position/PIC, sourced from the active Antrian row when one exists. */
    public function leadMonitoring(Request $request): void
    {
        $filters = $this->readFilters($request, ['q', 'date_from', 'date_to', 'sales_id', 'status']);
        $rows = Lead::monitoringReport($filters);
        $statusMap = MasterData::allAsMap('lead_statuses');
        $queueStatusMap = MasterData::allAsMap('queue_statuses');
        $priorityMap = MasterData::allAsMap('priorities');

        $notYetQueued = 0;
        foreach ($rows as $r) {
            if (empty($r['queue_id'])) {
                $notYetQueued++;
            }
        }

        $this->renderReport($request, [
            'title' => 'Lead Monitoring',
            'filename' => 'lead-monitoring',
            'path' => '/reports/lead-monitoring',
            'filterDefs' => [
                'q' => ['type' => 'text', 'label' => 'Cari Customer/Perusahaan'],
                'date_from' => ['type' => 'date', 'label' => 'Dari Tanggal'],
                'date_to' => ['type' => 'date', 'label' => 'Sampai Tanggal'],
                'sales_id' => ['type' => 'select', 'label' => 'Sales', 'options' => $this->salesOptions()],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => $this->mapToOptions($statusMap)],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Total Lead', 'value' => (string) count($rows)],
                ['label' => 'Belum Masuk Antrian', 'value' => (string) $notYetQueued],
            ],
            'columns' => [
                'lead_code' => 'Lead ID', 'customer_name' => 'Customer', 'type_name' => 'Type',
                'system_name' => 'System', 'sales_name' => 'Sales', 'funding_name' => 'Funding',
                'status_label' => 'Status', 'survey_status_label' => 'Status Survey',
                'priority_label' => 'Urgensi', 'estimator_name_fmt' => 'Estimator',
                'surveyor_name_fmt' => 'Surveyor', 'position_label' => 'Current Position',
                'current_pic_name_fmt' => 'Current PIC', 'updated_at_fmt' => 'Last Update',
            ],
            'rows' => array_map(function ($r) use ($statusMap, $queueStatusMap, $priorityMap) {
                $activeQueue = empty($r['queue_id']) ? null : [
                    'stage_id' => $r['stage_id'],
                    'stage_name' => $r['stage_name'],
                    'stage_color' => $r['stage_color'],
                    'status' => $r['queue_status'],
                    'survey_status_code' => $r['survey_status_code'],
                ];
                $position = Lead::currentPosition($r, $activeQueue, $statusMap, $queueStatusMap, [
                    'validation' => !empty($r['pending_validation_id']),
                    'procurement' => !empty($r['active_procurement_id']),
                    'salesEngineer' => !empty($r['active_sales_engineer_id']),
                    'engineer' => !empty($r['active_engineer_id']),
                ]);

                $r['type_name'] = $r['type_name'] ?? '-';
                $r['system_name'] = $r['system_name'] ?? '-';
                $r['funding_name'] = $r['funding_name'] ?? '-';
                $r['status_label'] = $statusMap[$r['status']]['name'] ?? $r['status'];
                $r['survey_status_label'] = $r['survey_status_name'] ?? '-';
                $r['priority_label'] = !empty($r['queue_priority']) ? ($priorityMap[$r['queue_priority']]['name'] ?? $r['queue_priority']) : '-';
                $r['estimator_name_fmt'] = $r['estimator_name'] ?? '-';
                $r['surveyor_name_fmt'] = $r['surveyor_name'] ?? '-';
                $r['position_label'] = $position['label'];
                $r['current_pic_name_fmt'] = Lead::picFromReportRow($r, $position['label']);
                $r['updated_at_fmt'] = format_datetime($r['updated_at']);

                return $r;
            }, $rows),
        ]);
    }

    public function engineer(Request $request): void
    {
        $filters = $this->readFilters($request, ['date_from', 'date_to', 'engineer_id', 'status']);
        $rows = EngineerAssignment::report($filters);
        $statusMap = MasterData::allAsMap('engineer_statuses');

        $completed = 0;
        $rejected = 0;
        $overdue = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'completed') $completed++;
            if ($r['status'] === 'rejected') $rejected++;
            if (!empty($r['deadline']) && $r['deadline'] < date('Y-m-d') && !in_array($r['status'], ['completed', 'rejected', 'returned'], true)) $overdue++;
        }

        $this->renderReport($request, [
            'title' => 'Laporan Engineer',
            'filename' => 'laporan-engineer',
            'path' => '/reports/engineer',
            'filterDefs' => [
                'date_from' => ['type' => 'date', 'label' => 'Dari Tanggal'],
                'date_to' => ['type' => 'date', 'label' => 'Sampai Tanggal'],
                'engineer_id' => ['type' => 'select', 'label' => 'Engineer', 'options' => $this->roleOptions('engineer-sales')],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => $this->mapToOptions($statusMap)],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Total Assignment', 'value' => (string) count($rows)],
                ['label' => 'Selesai', 'value' => (string) $completed],
                ['label' => 'Ditolak', 'value' => (string) $rejected],
                ['label' => 'Overdue', 'value' => (string) $overdue],
            ],
            'columns' => [
                'assignment_code' => 'Kode', 'lead_code' => 'Lead', 'customer_name' => 'Customer',
                'engineer_name' => 'Engineer', 'status_label' => 'Status', 'assigned_at_fmt' => 'Assigned',
                'deadline_fmt' => 'Deadline', 'completed_at_fmt' => 'Selesai',
            ],
            'rows' => array_map(function ($r) use ($statusMap) {
                $r['status_label'] = $statusMap[$r['status']]['name'] ?? $r['status'];
                $r['assigned_at_fmt'] = format_datetime($r['assigned_at'], 'd M Y');
                $r['deadline_fmt'] = $r['deadline'] ? format_datetime($r['deadline'], 'd M Y') : '-';
                $r['completed_at_fmt'] = $r['completed_at'] ? format_datetime($r['completed_at'], 'd M Y') : '-';
                return $r;
            }, $rows),
        ]);
    }

    public function procurement(Request $request): void
    {
        $filters = $this->readFilters($request, ['date_from', 'date_to', 'procurement_id', 'status']);
        $rows = ProcurementRequest::report($filters);
        $statusMap = MasterData::allAsMap('procurement_statuses');

        $completed = 0;
        $totalValue = 0.0;
        foreach ($rows as $r) {
            if ($r['status'] === 'pricing_completed') $completed++;
            $totalValue += (float) $r['total_purchase_value'];
        }

        $this->renderReport($request, [
            'title' => 'Laporan Procurement',
            'filename' => 'laporan-procurement',
            'path' => '/reports/procurement',
            'filterDefs' => [
                'date_from' => ['type' => 'date', 'label' => 'Dari Tanggal'],
                'date_to' => ['type' => 'date', 'label' => 'Sampai Tanggal'],
                'procurement_id' => ['type' => 'select', 'label' => 'Procurement', 'options' => $this->roleOptions('procurement')],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => $this->mapToOptions($statusMap)],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Total Request', 'value' => (string) count($rows)],
                ['label' => 'Selesai', 'value' => (string) $completed],
                ['label' => 'Total Nilai Pembelian', 'value' => $this->rupiah($totalValue)],
            ],
            'columns' => [
                'request_code' => 'Kode', 'lead_code' => 'Lead', 'customer_name' => 'Customer',
                'assigned_to_name' => 'Staff', 'status_label' => 'Status', 'requested_at_fmt' => 'Diminta',
                'total_purchase_value_fmt' => 'Nilai Pembelian',
            ],
            'rows' => array_map(function ($r) use ($statusMap) {
                $r['status_label'] = $statusMap[$r['status']]['name'] ?? $r['status'];
                $r['requested_at_fmt'] = format_datetime($r['requested_at'], 'd M Y');
                $r['total_purchase_value_fmt'] = $this->rupiah((float) $r['total_purchase_value']);
                return $r;
            }, $rows),
        ]);
    }

    public function proposals(Request $request): void
    {
        $filters = $this->readFilters($request, ['q', 'date_from', 'date_to', 'sales_id', 'status']);
        $rows = Proposal::report($filters);
        $statusMap = MasterData::allAsMap('proposal_statuses');

        $totalValue = 0.0;
        $acceptedValue = 0.0;
        $accepted = 0;
        $rejected = 0;
        foreach ($rows as $r) {
            $totalValue += (float) $r['total'];
            if ($r['status'] === 'accepted') {
                $accepted++;
                $acceptedValue += (float) $r['total'];
            }
            if ($r['status'] === 'rejected') $rejected++;
        }
        $winRate = ($accepted + $rejected) > 0 ? round($accepted / ($accepted + $rejected) * 100, 1) : 0.0;

        $this->renderReport($request, [
            'title' => 'Laporan Proposal',
            'filename' => 'laporan-proposal',
            'path' => '/reports/proposals',
            'filterDefs' => [
                'q' => ['type' => 'text', 'label' => 'Cari Customer/Project'],
                'date_from' => ['type' => 'date', 'label' => 'Dari Tanggal'],
                'date_to' => ['type' => 'date', 'label' => 'Sampai Tanggal'],
                'sales_id' => ['type' => 'select', 'label' => 'Sales', 'options' => $this->salesOptions()],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => $this->mapToOptions($statusMap)],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Total Proposal', 'value' => (string) count($rows)],
                ['label' => 'Total Nilai', 'value' => $this->rupiah($totalValue)],
                ['label' => 'Nilai Diterima', 'value' => $this->rupiah($acceptedValue)],
                ['label' => 'Win Rate', 'value' => $this->pct($winRate)],
            ],
            'columns' => [
                'proposal_code' => 'Kode', 'lead_code' => 'Lead', 'project_name' => 'Project',
                'sales_name' => 'Sales', 'status_label' => 'Status', 'total_fmt' => 'Total', 'created_at_fmt' => 'Dibuat',
            ],
            'rows' => array_map(function ($r) use ($statusMap) {
                $r['status_label'] = $statusMap[$r['status']]['name'] ?? $r['status'];
                $r['project_name'] = $r['project_name'] ?: '-';
                $r['total_fmt'] = $this->rupiah((float) $r['total']);
                $r['created_at_fmt'] = format_datetime($r['created_at'], 'd M Y');
                return $r;
            }, $rows),
        ]);
    }

    public function followUps(Request $request): void
    {
        $filters = $this->readFilters($request, ['q', 'date_from', 'date_to', 'sales_id']);
        $rows = FollowUp::report($filters);

        $responseLabels = (new FollowUpController())->responseLabels();
        $methodLabels = (new FollowUpController())->methodLabels();

        $interested = 0;
        $negotiating = 0;
        $notInterested = 0;
        foreach ($rows as $r) {
            if ($r['customer_response'] === 'interested') $interested++;
            if ($r['customer_response'] === 'negotiating') $negotiating++;
            if ($r['customer_response'] === 'not_interested') $notInterested++;
        }

        $this->renderReport($request, [
            'title' => 'Laporan Follow Up',
            'filename' => 'laporan-follow-up',
            'path' => '/reports/follow-ups',
            'filterDefs' => [
                'q' => ['type' => 'text', 'label' => 'Cari Customer'],
                'date_from' => ['type' => 'date', 'label' => 'Dari Tanggal'],
                'date_to' => ['type' => 'date', 'label' => 'Sampai Tanggal'],
                'sales_id' => ['type' => 'select', 'label' => 'Sales', 'options' => $this->salesOptions()],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Total Follow Up', 'value' => (string) count($rows)],
                ['label' => 'Tertarik', 'value' => (string) $interested],
                ['label' => 'Negosiasi', 'value' => (string) $negotiating],
                ['label' => 'Tidak Tertarik', 'value' => (string) $notInterested],
            ],
            'columns' => [
                'followup_date_fmt' => 'Tanggal', 'lead_code' => 'Lead', 'customer_name' => 'Customer',
                'sales_name' => 'Sales', 'method_label' => 'Metode', 'customer_response_label' => 'Respon',
                'next_action' => 'Next Action', 'next_followup_date_fmt' => 'Follow Up Berikutnya',
            ],
            'rows' => array_map(function ($r) use ($responseLabels, $methodLabels) {
                $r['followup_date_fmt'] = format_datetime($r['followup_date']);
                $r['method_label'] = $methodLabels[$r['method']] ?? $r['method'];
                $r['customer_response_label'] = $r['customer_response'] ? ($responseLabels[$r['customer_response']] ?? $r['customer_response']) : '-';
                $r['next_action'] = $r['next_action'] ?: '-';
                $r['next_followup_date_fmt'] = $r['next_followup_date'] ? format_datetime($r['next_followup_date'], 'd M Y') : '-';
                return $r;
            }, $rows),
        ]);
    }

    public function deals(Request $request): void
    {
        $filters = $this->readFilters($request, ['date_from', 'date_to', 'sales_id', 'status']);
        $rows = Lead::dealReport($filters);

        $wonCount = 0;
        $wonValue = 0.0;
        $lostCount = 0;
        $lostValue = 0.0;
        foreach ($rows as $r) {
            if ($r['status'] === 'won') {
                $wonCount++;
                $wonValue += (float) ($r['deal_value'] ?? 0);
            } else {
                $lostCount++;
                $lostValue += (float) ($r['estimated_value'] ?? 0);
            }
        }
        $winRate = ($wonCount + $lostCount) > 0 ? round($wonCount / ($wonCount + $lostCount) * 100, 1) : 0.0;

        $this->renderReport($request, [
            'title' => 'Laporan Deal (Won/Lost)',
            'filename' => 'laporan-deal',
            'path' => '/reports/deals',
            'filterDefs' => [
                'date_from' => ['type' => 'date', 'label' => 'Closing Dari'],
                'date_to' => ['type' => 'date', 'label' => 'Closing Sampai'],
                'sales_id' => ['type' => 'select', 'label' => 'Sales', 'options' => $this->salesOptions()],
                'status' => ['type' => 'select', 'label' => 'Status', 'options' => ['won' => 'Won', 'lost' => 'Lost']],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Won', 'value' => (string) $wonCount],
                ['label' => 'Won Value', 'value' => $this->rupiah($wonValue)],
                ['label' => 'Lost', 'value' => (string) $lostCount],
                ['label' => 'Lost Value', 'value' => $this->rupiah($lostValue)],
                ['label' => 'Win Rate', 'value' => $this->pct($winRate)],
            ],
            'columns' => [
                'lead_code' => 'Kode', 'customer_name' => 'Customer', 'sales_name' => 'Sales',
                'status_label' => 'Status', 'value_fmt' => 'Nilai', 'closing_date_fmt' => 'Closing',
                'reason' => 'Keterangan',
            ],
            'rows' => array_map(function ($r) {
                $isWon = $r['status'] === 'won';
                $r['status_label'] = $isWon ? 'Won' : 'Lost';
                $r['value_fmt'] = $this->rupiah((float) ($isWon ? ($r['deal_value'] ?? 0) : ($r['estimated_value'] ?? 0)));
                $r['closing_date_fmt'] = $r['closing_date'] ? format_datetime($r['closing_date'], 'd M Y') : '-';
                $r['reason'] = $isWon ? ($r['wp_proposal_code'] ?? '-') : ($r['lost_reason'] ?: '-');
                return $r;
            }, $rows),
        ]);
    }

    public function performance(Request $request): void
    {
        $filters = $this->readFilters($request, ['date_from', 'date_to', 'sales_id']);
        $rows = Lead::performanceReport($filters);

        $this->renderReport($request, [
            'title' => 'Laporan Performance Sales',
            'filename' => 'laporan-performance',
            'path' => '/reports/performance',
            'filterDefs' => [
                'date_from' => ['type' => 'date', 'label' => 'Dari Tanggal'],
                'date_to' => ['type' => 'date', 'label' => 'Sampai Tanggal'],
                'sales_id' => ['type' => 'select', 'label' => 'Sales', 'options' => $this->salesOptions()],
            ],
            'filters' => $filters,
            'summary' => [
                ['label' => 'Sales Aktif', 'value' => (string) count($rows)],
            ],
            'columns' => [
                'name' => 'Sales', 'total_leads' => 'Total Lead', 'open_leads' => 'Lead Aktif',
                'won_count' => 'Won', 'lost_count' => 'Lost', 'conversion_rate_fmt' => 'Conversion',
                'won_value_fmt' => 'Won Value', 'followup_count' => 'Follow Up', 'proposal_count' => 'Proposal Terkirim',
            ],
            'rows' => array_map(function ($r) {
                $r['conversion_rate_fmt'] = $this->pct($r['conversion_rate']);
                $r['won_value_fmt'] = $this->rupiah($r['won_value']);
                return $r;
            }, $rows),
        ]);
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    /** Reads and lightly trims the given filter keys from the querystring. */
    private function readFilters(Request $request, array $keys): array
    {
        $filters = [];
        foreach ($keys as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /** Renders the HTML report page, or streams CSV when ?format=csv (requires report.export). */
    private function renderReport(Request $request, array $data): void
    {
        if ($request->input('format') === 'csv') {
            if (!Acl::can('report.export')) {
                $this->abort(403);

                return;
            }

            $filename = $data['filename'] . '-' . date('Y-m-d') . '.csv';
            Csv::download($filename, $data['columns'], $data['rows']);

            return;
        }

        $data['pageTitle'] = $data['title'];
        $data['canExport'] = Acl::can('report.export');
        $this->view('reports/show', $data);
    }

    private function salesOptions(): array
    {
        $options = [];
        foreach (User::activeSales() as $user) {
            $options[$user['id']] = $user['name'];
        }

        return $options;
    }

    private function roleOptions(string $roleSlug): array
    {
        $role = Role::findBySlug($roleSlug);
        if ($role === null) {
            return [];
        }

        $options = [];
        foreach (User::activeByRole((int) $role['id']) as $user) {
            $options[$user['id']] = $user['name'];
        }

        return $options;
    }

    private function mapToOptions(array $masterDataMap): array
    {
        $options = [];
        foreach ($masterDataMap as $code => $row) {
            $options[$code] = $row['name'];
        }

        return $options;
    }

    private function rupiah(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    private function pct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    }
}
