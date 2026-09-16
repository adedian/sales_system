<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Lead extends Model
{
    protected static string $table = 'leads';
    protected static bool $softDeletes = true;

    private const SORTABLE = [
        'lead_code' => 'leads.lead_code',
        'customer_name' => 'leads.customer_name',
        'company_name' => 'leads.company_name',
        'status' => 'leads.status',
        'priority' => 'leads.priority',
        'follow_up_date' => 'leads.follow_up_date',
        'created_at' => 'leads.created_at',
        'sales_name' => 'sales.name',
    ];

    /**
     * Full record joined with everything a list row or detail page needs to
     * display without extra queries (source/category/need type names,
     * assigned sales name).
     */
    public static function withRelations(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE leads.id = ? AND leads.deleted_at IS NULL', [$id]);
    }

    public static function withRelationsIncludingTrashed(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE leads.id = ?', [$id]);
    }

    private static function baseSelect(): string
    {
        return "SELECT leads.*,
                       sales.name AS sales_name,
                       source.name AS source_name,
                       category.name AS category_name,
                       need_type.name AS need_type_name,
                       lead_type.name AS type_name,
                       lead_system.name AS system_name,
                       funding.name AS funding_name,
                       creator.name AS created_by_name
                FROM leads
                LEFT JOIN users sales ON sales.id = leads.sales_id
                LEFT JOIN lead_sources source ON source.id = leads.source_id
                LEFT JOIN lead_categories category ON category.id = leads.category_id
                LEFT JOIN need_types need_type ON need_type.id = leads.need_type_id
                LEFT JOIN lead_types lead_type ON lead_type.id = leads.type_id
                LEFT JOIN lead_systems lead_system ON lead_system.id = leads.system_id
                LEFT JOIN funding_sources funding ON funding.id = leads.funding_id
                LEFT JOIN users creator ON creator.id = leads.created_by";
    }

    /**
     * Non-destructive presentation-layer simplification of the 9-stage
     * pipeline status into the reference sheet's Proses/Deal/Cancel — the
     * underlying `status` column and its business logic (markWon/markLost/
     * reopenDeal) are untouched.
     */
    public static function simplifiedStatus(string $status): string
    {
        return match ($status) {
            'won' => 'deal',
            'lost' => 'cancel',
            default => 'proses',
        };
    }

    /**
     * Revisi Sub-Fase 4 — "Current Process": rewritten to derive the label
     * purely from the REAL state of each stage's own module (Engineer/Sales
     * Engineer assignment, Procurement request, Direktur price validation),
     * not from the old `sales_queue.stage_id` ("Prioritas") field — that
     * field mixed urgency values (Urgent/Medium/Slow) with process-stage
     * values in one dropdown, which the new business-flow document
     * explicitly requires to be separate concepts (Priority = urgency only,
     * Current Process = position only). The `stage_id` column itself is
     * left untouched in the DB (no data loss, no migration) — it's just no
     * longer read here. Takes already-resolved data (not queried here) so
     * it works identically for a single lead (detail page) or a batch/report
     * row without N+1 — see Lead::monitoringReport() for the batched query.
     *
     * Priority order mirrors the flow: Sales -> Survey -> Data Antrian ->
     * Engineer -> Sales Engineer -> Procurement -> Approval Harga ->
     * (back to Procurement/Sales) -> Proposal -> Deal/Cancel. Checked from
     * the "latest" stage backwards so whichever module currently holds an
     * open item wins.
     *
     * @param array $lead the leads.* row (needs 'status')
     * @param array|null $activeQueue shape: ['survey_status_code','status'] or null
     * @param array $leadStatusMap MasterData::allAsMap('lead_statuses') (unused fallback labels, kept for signature compatibility)
     * @param array $queueStatusMap MasterData::allAsMap('queue_statuses') (unused fallback labels, kept for signature compatibility)
     * @param array $extra optional: ['validation' => ?array, 'procurement' => ?array, 'salesEngineer' => ?array, 'engineer' => ?array]
     *              each an active/open row from its own module, or null/absent if none.
     * @return array{label:string,color:string}
     */
    public static function currentPosition(array $lead, ?array $activeQueue, array $leadStatusMap, array $queueStatusMap, array $extra = []): array
    {
        if ($lead['status'] === 'won') {
            return ['label' => 'Deal', 'color' => 'emerald'];
        }
        if ($lead['status'] === 'lost') {
            return ['label' => 'Cancel', 'color' => 'danger'];
        }

        if (!empty($extra['validation'])) {
            return ['label' => 'Approval Harga', 'color' => 'amber'];
        }

        if (!empty($extra['procurement'])) {
            return ['label' => 'Procurement', 'color' => 'indigo'];
        }

        if (!empty($extra['salesEngineer'])) {
            return ['label' => 'Sales Engineer', 'color' => 'indigo'];
        }

        if (!empty($extra['engineer'])) {
            return ['label' => 'Engineer', 'color' => 'indigo'];
        }

        if ($activeQueue !== null) {
            $surveyCode = $activeQueue['survey_status_code'] ?? null;
            if ($surveyCode === null || $surveyCode === 'prelim') {
                return ['label' => 'Survey', 'color' => 'amber'];
            }

            return ['label' => 'Data Antrian', 'color' => 'indigo'];
        }

        if ($lead['status'] === 'proposal') {
            return ['label' => 'Proposal', 'color' => 'indigo'];
        }

        return ['label' => 'Sales', 'color' => 'muted'];
    }

    /**
     * @param array{q?:string,status?:string,priority?:string,source_id?:int,
     *              category_id?:int,sales_id?:int,follow_up?:string,
     *              sort?:string,dir?:string,page?:int,per_page?:int,
     *              trashed?:bool,scope_sales_id?:int} $filters
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(array $filters): array
    {
        $where = [];
        $params = [];

        $where[] = !empty($filters['trashed']) ? 'leads.deleted_at IS NOT NULL' : 'leads.deleted_at IS NULL';

        // Row-level scoping: a Sales user only ever sees their own leads.
        if (!empty($filters['scope_sales_id'])) {
            $where[] = 'leads.sales_id = ?';
            $params[] = (int) $filters['scope_sales_id'];
        }

        if (!empty($filters['q'])) {
            // Revisi Sub-Fase 5 — token/partial search (dokumen requirement
            // §39): "am 01 sup" harus match "AM.0001.SUP". Setiap token
            // di-AND-kan (semua token harus ketemu di SALAH SATU kolom),
            // tiap token sendiri di-OR-kan lintas kolom.
            $tokens = preg_split('/\s+/', trim((string) $filters['q']), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($tokens as $token) {
                $where[] = '(leads.lead_code LIKE ? OR leads.customer_name LIKE ? OR leads.company_name LIKE ? OR leads.phone LIKE ? OR leads.email LIKE ?)';
                $like = '%' . $token . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
        }

        if (!empty($filters['status'])) {
            $where[] = 'leads.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where[] = 'leads.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['source_id'])) {
            $where[] = 'leads.source_id = ?';
            $params[] = (int) $filters['source_id'];
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'leads.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }

        if (!empty($filters['type_id'])) {
            $where[] = 'leads.type_id = ?';
            $params[] = (int) $filters['type_id'];
        }

        if (!empty($filters['system_id'])) {
            $where[] = 'leads.system_id = ?';
            $params[] = (int) $filters['system_id'];
        }

        if (!empty($filters['funding_id'])) {
            $where[] = 'leads.funding_id = ?';
            $params[] = (int) $filters['funding_id'];
        }

        if (($filters['simple_status'] ?? '') === 'deal') {
            $where[] = "leads.status = 'won'";
        } elseif (($filters['simple_status'] ?? '') === 'cancel') {
            $where[] = "leads.status = 'lost'";
        } elseif (($filters['simple_status'] ?? '') === 'proses') {
            $where[] = "leads.status NOT IN ('won','lost')";
        }

        if (!empty($filters['sales_id'])) {
            $where[] = 'leads.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }

        if (($filters['follow_up'] ?? '') === 'overdue') {
            $where[] = 'leads.follow_up_date IS NOT NULL AND leads.follow_up_date < CURDATE()';
        } elseif (($filters['follow_up'] ?? '') === 'today') {
            $where[] = 'leads.follow_up_date = CURDATE()';
        } elseif (($filters['follow_up'] ?? '') === 'upcoming') {
            $where[] = 'leads.follow_up_date IS NOT NULL AND leads.follow_up_date >= CURDATE()';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM leads {$whereSql}",
            $params
        )['total'] ?? 0);

        $sortKey = self::SORTABLE[$filters['sort'] ?? ''] ?? self::SORTABLE['created_at'];
        $dir = strtolower($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $perPage = max(1, (int) ($filters['per_page'] ?? 15));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            self::baseSelect() . " {$whereSql} ORDER BY {$sortKey} {$dir}, leads.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    public static function countByStatus(?int $scopeSalesId = null): array
    {
        $sql = "SELECT status, COUNT(*) AS total FROM leads WHERE deleted_at IS NULL";
        $params = [];
        if ($scopeSalesId !== null) {
            $sql .= ' AND sales_id = ?';
            $params[] = $scopeSalesId;
        }
        $sql .= ' GROUP BY status';

        $rows = Database::fetchAll($sql, $params);

        $counts = array_fill_keys(['new', 'in_queue', 'follow_up', 'engineering', 'won', 'lost'], 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Open leads (not Won/Lost) that have never entered Antrian — the
     * "belum masuk antrian" bucket for the Dashboard's Current Process
     * distribution widget, complementing Lead::currentProcessDistribution().
     */
    public static function countNotYetQueued(): int
    {
        return (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM leads
             WHERE deleted_at IS NULL AND status NOT IN ('won','lost')
             AND id NOT IN (SELECT lead_id FROM sales_queue WHERE status NOT IN ('done','cancelled'))"
        )['total'] ?? 0);
    }

    /**
     * Leads whose own follow_up_date has arrived or passed — used by the
     * Sales Workspace's unified follow-up task list. Independent of Sales
     * Queue's followup_date: a lead not queued yet still needs chasing.
     */
    public static function pendingFollowUps(int $salesId): array
    {
        return Database::fetchAll(
            "SELECT id, lead_code, customer_name, company_name, follow_up_date, status
             FROM leads
             WHERE sales_id = ? AND deleted_at IS NULL AND follow_up_date IS NOT NULL AND follow_up_date <= CURDATE()
             ORDER BY follow_up_date ASC",
            [$salesId]
        );
    }

    public static function recentForSales(int $salesId, int $limit = 6): array
    {
        $limit = max(1, $limit);

        return Database::fetchAll(
            self::baseSelect() . " WHERE leads.sales_id = ? AND leads.deleted_at IS NULL
             ORDER BY leads.created_at DESC LIMIT {$limit}",
            [$salesId]
        );
    }

    /**
     * Insert the row then stamp it with a race-safe LD-000001 style code
     * derived from the row's own auto-increment id (wrapped in one
     * transaction so the temporary placeholder never becomes visible).
     */
    public static function createWithCode(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $placeholder = 'TMP-' . bin2hex(random_bytes(8));
            $id = self::insert(['lead_code' => $placeholder] + $data);
            $code = sprintf('%s-%06d', Setting::get('numbering_lead_prefix', 'LD'), $id);
            self::update($id, ['lead_code' => $code]);

            return ['id' => $id, 'lead_code' => $code];
        });
    }

    public static function restore(int $id): int
    {
        return static::update($id, ['deleted_at' => null]);
    }

    // ------------------------------------------------------------------
    // Phase 12 — Manager/Admin analytics dashboard. All figures are computed
    // live from the current table state (no caching/snapshot table) since
    // the dataset size in this app doesn't warrant one.
    // ------------------------------------------------------------------

    /** Sum of estimated_value for leads still open (not yet closed Won/Lost). */
    public static function pipelineValue(): float
    {
        return (float) (Database::fetch(
            "SELECT COALESCE(SUM(estimated_value), 0) AS total FROM leads WHERE deleted_at IS NULL AND status NOT IN ('won','lost')"
        )['total'] ?? 0);
    }

    /** Sum of the recorded deal_value for every Won lead (Phase 10). */
    public static function wonValue(): float
    {
        return (float) (Database::fetch(
            "SELECT COALESCE(SUM(deal_value), 0) AS total FROM leads WHERE deleted_at IS NULL AND status = 'won'"
        )['total'] ?? 0);
    }

    /** Sum of estimated_value for every Lost lead — the pipeline value that didn't convert. */
    public static function lostValue(): float
    {
        return (float) (Database::fetch(
            "SELECT COALESCE(SUM(estimated_value), 0) AS total FROM leads WHERE deleted_at IS NULL AND status = 'lost'"
        )['total'] ?? 0);
    }

    /** Won / (Won + Lost) as a percentage — 0 when nothing has closed yet. */
    public static function conversionRate(): float
    {
        $row = Database::fetch(
            "SELECT
                SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) AS won,
                SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) AS lost
             FROM leads WHERE deleted_at IS NULL"
        );
        $won = (int) ($row['won'] ?? 0);
        $lost = (int) ($row['lost'] ?? 0);

        return ($won + $lost) > 0 ? round(($won / ($won + $lost)) * 100, 1) : 0.0;
    }

    /**
     * Per-sales breakdown: active leads, Won/Lost counts + value, conversion
     * rate. Every active Sales user appears even with zero leads, so the
     * Manager can see who's idle as well as who's busy.
     */
    public static function salesPerformance(): array
    {
        $rows = Database::fetchAll(
            "SELECT
                u.id, u.name,
                COUNT(l.id) AS total_leads,
                SUM(CASE WHEN l.status NOT IN ('won','lost') THEN 1 ELSE 0 END) AS open_leads,
                SUM(CASE WHEN l.status = 'won' THEN 1 ELSE 0 END) AS won_count,
                SUM(CASE WHEN l.status = 'won' THEN l.deal_value ELSE 0 END) AS won_value,
                SUM(CASE WHEN l.status = 'lost' THEN 1 ELSE 0 END) AS lost_count
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id AND r.slug = 'sales'
             LEFT JOIN leads l ON l.sales_id = u.id AND l.deleted_at IS NULL
             WHERE u.is_active = 1
             GROUP BY u.id, u.name
             ORDER BY won_value DESC, total_leads DESC"
        );

        foreach ($rows as &$row) {
            $row['total_leads'] = (int) $row['total_leads'];
            $row['open_leads'] = (int) $row['open_leads'];
            $row['won_count'] = (int) $row['won_count'];
            $row['won_value'] = (float) $row['won_value'];
            $row['lost_count'] = (int) $row['lost_count'];
            $closed = $row['won_count'] + $row['lost_count'];
            $row['conversion_rate'] = $closed > 0 ? round(($row['won_count'] / $closed) * 100, 1) : 0.0;
        }

        return $rows;
    }

    /**
     * Average age (days since last update) and count per open pipeline
     * status — surfaces where leads are piling up (the "bottleneck").
     */
    public static function agingByStatus(): array
    {
        $rows = Database::fetchAll(
            "SELECT status, COUNT(*) AS total, AVG(DATEDIFF(NOW(), updated_at)) AS avg_age_days
             FROM leads
             WHERE deleted_at IS NULL AND status NOT IN ('won','lost')
             GROUP BY status
             ORDER BY total DESC"
        );

        foreach ($rows as &$row) {
            $row['total'] = (int) $row['total'];
            $row['avg_age_days'] = round((float) $row['avg_age_days'], 1);
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Phase 13 — Reporting. Deliberately separate from search() (used by
    // the Leads list page) so report filters/columns can evolve without
    // touching that already-tested query.
    // ------------------------------------------------------------------

    /** @return array{where:string,params:array} shared by the Lead Report's table view and its CSV export. */
    private static function reportWhere(array $filters): array
    {
        $where = ['leads.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'leads.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'leads.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['sales_id'])) {
            $where[] = 'leads.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'leads.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            // Revisi Sub-Fase 5 — same token/partial search as Lead::search().
            $tokens = preg_split('/\s+/', trim((string) $filters['q']), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($tokens as $token) {
                $where[] = '(leads.lead_code LIKE ? OR leads.customer_name LIKE ? OR leads.company_name LIKE ?)';
                $like = '%' . $token . '%';
                array_push($params, $like, $like, $like);
            }
        }

        return ['where' => implode(' AND ', $where), 'params' => $params];
    }

    public static function report(array $filters): array
    {
        $w = self::reportWhere($filters);

        return Database::fetchAll(
            self::baseSelect() . " WHERE {$w['where']} ORDER BY leads.created_at DESC",
            $w['params']
        );
    }

    /**
     * Phase C — Lead Monitoring report: every lead plus its active Antrian
     * row (if any) in one query via a ROW_NUMBER()-ranked subquery, so a
     * lead with no queue row still appears (LEFT JOIN) and a lead with
     * several historical queue rows only contributes its most recent
     * active one. The queue's own `priority` is aliased distinctly
     * (`queue_priority`) so it isn't shadowed by leads.priority.
     */
    public static function monitoringReport(array $filters): array
    {
        $w = self::reportWhere($filters);

        // Revisi Sub-Fase 4 — same ROW_NUMBER()-ranked-subquery pattern as
        // the existing `aq` (active Antrian) join below, extended with one
        // subquery per stage module Lead::currentPosition() now needs
        // (Engineer/Sales Engineer assignment, Procurement request, pending
        // Direktur validation) so the whole report stays one query, no N+1.
        $engineerOpen = "'" . implode("','", \App\Models\EngineerAssignment::OPEN_STATUSES) . "'";
        $procurementOpen = "'" . implode("','", \App\Models\ProcurementRequest::OPEN_STATUSES) . "'";

        return Database::fetchAll(
            "SELECT leads.*,
                    sales.name AS sales_name,
                    lead_type.name AS type_name,
                    lead_system.name AS system_name,
                    funding.name AS funding_name,
                    aq.id AS queue_id,
                    aq.status AS queue_status,
                    aq.priority AS queue_priority,
                    stage.id AS stage_id, stage.name AS stage_name, stage.color AS stage_color,
                    survey_status.code AS survey_status_code,
                    survey_status.name AS survey_status_name,
                    estimator.name AS estimator_name,
                    surveyor.name AS surveyor_name,
                    current_pic.name AS current_pic_name,
                    eng.id AS active_engineer_id,
                    seng.id AS active_sales_engineer_id,
                    preq.id AS active_procurement_id,
                    pval.id AS pending_validation_id
             FROM leads
             LEFT JOIN users sales ON sales.id = leads.sales_id
             LEFT JOIN lead_types lead_type ON lead_type.id = leads.type_id
             LEFT JOIN lead_systems lead_system ON lead_system.id = leads.system_id
             LEFT JOIN funding_sources funding ON funding.id = leads.funding_id
             LEFT JOIN (
                 SELECT sq.*, ROW_NUMBER() OVER (PARTITION BY sq.lead_id ORDER BY sq.id DESC) AS rn
                 FROM sales_queue sq
                 WHERE sq.status NOT IN ('done','cancelled')
             ) aq ON aq.lead_id = leads.id AND aq.rn = 1
             LEFT JOIN queue_stages stage ON stage.id = aq.stage_id
             LEFT JOIN survey_statuses survey_status ON survey_status.id = aq.survey_status_id
             LEFT JOIN users estimator ON estimator.id = aq.estimator_id
             LEFT JOIN users surveyor ON surveyor.id = aq.surveyor_id
             LEFT JOIN users current_pic ON current_pic.id = aq.current_pic_id
             LEFT JOIN (
                 SELECT ea.*, ROW_NUMBER() OVER (PARTITION BY ea.lead_id ORDER BY ea.id DESC) AS rn
                 FROM engineer_assignments ea
                 WHERE ea.assignment_type = 'engineer' AND ea.status IN ({$engineerOpen})
             ) eng ON eng.lead_id = leads.id AND eng.rn = 1
             LEFT JOIN (
                 SELECT ea.*, ROW_NUMBER() OVER (PARTITION BY ea.lead_id ORDER BY ea.id DESC) AS rn
                 FROM engineer_assignments ea
                 WHERE ea.assignment_type = 'sales_engineer' AND ea.status IN ({$engineerOpen})
             ) seng ON seng.lead_id = leads.id AND seng.rn = 1
             LEFT JOIN (
                 SELECT pr.*, ROW_NUMBER() OVER (PARTITION BY pr.lead_id ORDER BY pr.id DESC) AS rn
                 FROM procurement_requests pr
                 WHERE pr.status IN ({$procurementOpen})
             ) preq ON preq.lead_id = leads.id AND preq.rn = 1
             LEFT JOIN (
                 SELECT pv.*, ROW_NUMBER() OVER (PARTITION BY pv.procurement_request_id ORDER BY pv.id DESC) AS rn
                 FROM procurement_price_validations pv
                 WHERE pv.status = 'pending'
             ) pval ON pval.procurement_request_id = preq.id AND pval.rn = 1
             WHERE {$w['where']}
             ORDER BY leads.created_at DESC",
            $w['params']
        );
    }

    /**
     * Revisi Sub-Fase 4 — Dashboard's "Distribusi Posisi Lead" widget: was
     * grouped by the raw `sales_queue.stage_id` (the old mixed
     * urgency+stage "Prioritas" field); now tallies the same computed
     * Current Process used everywhere else (Lead detail page, Lead
     * Monitoring report), so all three stay in sync. Won/Lost leads are
     * excluded (Deal/Cancel are end-states already shown by the KPI cards
     * above this widget, not an active pipeline position).
     *
     * @return array<int,array{label:string,color:string,total:int}>
     */
    public static function currentProcessDistribution(): array
    {
        $rows = self::monitoringReport([]);
        $statusMap = MasterData::allAsMap('lead_statuses');
        $queueStatusMap = MasterData::allAsMap('queue_statuses');

        $tally = [];
        foreach ($rows as $r) {
            if (in_array($r['status'], ['won', 'lost'], true)) {
                continue;
            }

            $activeQueue = empty($r['queue_id']) ? null : [
                'stage_id' => $r['stage_id'],
                'stage_name' => $r['stage_name'],
                'stage_color' => $r['stage_color'],
                'status' => $r['queue_status'],
                'survey_status_code' => $r['survey_status_code'],
            ];

            $position = self::currentPosition($r, $activeQueue, $statusMap, $queueStatusMap, [
                'validation' => !empty($r['pending_validation_id']),
                'procurement' => !empty($r['active_procurement_id']),
                'salesEngineer' => !empty($r['active_sales_engineer_id']),
                'engineer' => !empty($r['active_engineer_id']),
            ]);

            $key = $position['label'];
            if (!isset($tally[$key])) {
                $tally[$key] = ['label' => $key, 'color' => $position['color'], 'total' => 0];
            }
            $tally[$key]['total']++;
        }

        usort($tally, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_values($tally);
    }

    /** @return array{where:string,params:array} shared by the Deal Report's table view and its CSV export. */
    private static function dealReportWhere(array $filters): array
    {
        $where = ["leads.deleted_at IS NULL", "leads.status IN ('won','lost')"];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'leads.closing_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'leads.closing_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['sales_id'])) {
            $where[] = 'leads.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['won', 'lost'], true)) {
            $where[] = 'leads.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            // Revisi Sub-Fase 5 — same token/partial search as Lead::search().
            $tokens = preg_split('/\s+/', trim((string) $filters['q']), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($tokens as $token) {
                $where[] = '(leads.customer_name LIKE ? OR leads.company_name LIKE ?)';
                $like = '%' . $token . '%';
                array_push($params, $like, $like);
            }
        }

        return ['where' => implode(' AND ', $where), 'params' => $params];
    }

    /** Won/Lost leads only — the Deal Report. */
    public static function dealReport(array $filters): array
    {
        $w = self::dealReportWhere($filters);

        return Database::fetchAll(
            "SELECT leads.*, sales.name AS sales_name, wp.proposal_code AS wp_proposal_code
             FROM leads
             LEFT JOIN users sales ON sales.id = leads.sales_id
             LEFT JOIN proposals wp ON wp.id = leads.won_proposal_id
             WHERE {$w['where']} ORDER BY leads.closing_date DESC",
            $w['params']
        );
    }

    /**
     * Per-sales scorecard for the Performance Report: same shape as
     * salesPerformance() (Phase 12) plus follow-up and proposal-sent counts,
     * and an optional date range on when the lead was created.
     */
    public static function performanceReport(array $filters): array
    {
        $joinWhere = ['l.deleted_at IS NULL'];
        $joinParams = [];

        if (!empty($filters['date_from'])) {
            $joinWhere[] = 'l.created_at >= ?';
            $joinParams[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $joinWhere[] = 'l.created_at <= ?';
            $joinParams[] = $filters['date_to'] . ' 23:59:59';
        }
        $joinSql = implode(' AND ', $joinWhere);

        $outerWhere = ['u.is_active = 1'];
        $outerParams = [];
        if (!empty($filters['sales_id'])) {
            $outerWhere[] = 'u.id = ?';
            $outerParams[] = (int) $filters['sales_id'];
        }
        $outerSql = implode(' AND ', $outerWhere);

        $rows = Database::fetchAll(
            "SELECT
                u.id, u.name,
                COUNT(l.id) AS total_leads,
                SUM(CASE WHEN l.status NOT IN ('won','lost') THEN 1 ELSE 0 END) AS open_leads,
                SUM(CASE WHEN l.status = 'won' THEN 1 ELSE 0 END) AS won_count,
                SUM(CASE WHEN l.status = 'won' THEN l.deal_value ELSE 0 END) AS won_value,
                SUM(CASE WHEN l.status = 'lost' THEN 1 ELSE 0 END) AS lost_count,
                (SELECT COUNT(*) FROM followups f WHERE f.sales_id = u.id) AS followup_count,
                (SELECT COUNT(*) FROM proposals p WHERE p.sales_id = u.id AND p.deleted_at IS NULL AND p.status <> 'draft') AS proposal_count
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id AND r.slug = 'sales'
             LEFT JOIN leads l ON l.sales_id = u.id AND {$joinSql}
             WHERE {$outerSql}
             GROUP BY u.id, u.name
             ORDER BY won_value DESC, total_leads DESC",
            array_merge($joinParams, $outerParams)
        );

        foreach ($rows as &$row) {
            $row['total_leads'] = (int) $row['total_leads'];
            $row['open_leads'] = (int) $row['open_leads'];
            $row['won_count'] = (int) $row['won_count'];
            $row['won_value'] = (float) $row['won_value'];
            $row['lost_count'] = (int) $row['lost_count'];
            $row['followup_count'] = (int) $row['followup_count'];
            $row['proposal_count'] = (int) $row['proposal_count'];
            $closed = $row['won_count'] + $row['lost_count'];
            $row['conversion_rate'] = $closed > 0 ? round(($row['won_count'] / $closed) * 100, 1) : 0.0;
        }

        return $rows;
    }
}
