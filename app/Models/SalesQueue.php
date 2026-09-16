<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class SalesQueue extends Model
{
    protected static string $table = 'sales_queue';

    private const SORTABLE = [
        'queue_number' => 'sales_queue.queue_number',
        'priority' => 'sales_queue.priority',
        'status' => 'sales_queue.status',
        'deadline' => 'sales_queue.deadline',
        'followup_date' => 'sales_queue.followup_date',
        'entered_at' => 'sales_queue.entered_at',
        'sales_name' => 'sales.name',
        'customer_name' => 'leads.customer_name',
    ];

    private static function baseSelect(): string
    {
        return "SELECT sales_queue.*,
                       leads.lead_code, leads.customer_name, leads.company_name, leads.phone AS lead_phone,
                       sales.name AS sales_name,
                       survey_status.code AS survey_status_code, survey_status.name AS survey_status_name, survey_status.color AS survey_status_color,
                       stage.name AS stage_name, stage.color AS stage_color,
                       estimator.name AS estimator_name,
                       surveyor.name AS surveyor_name,
                       current_pic.name AS current_pic_name
                FROM sales_queue
                INNER JOIN leads ON leads.id = sales_queue.lead_id
                LEFT JOIN users sales ON sales.id = sales_queue.sales_id
                LEFT JOIN survey_statuses survey_status ON survey_status.id = sales_queue.survey_status_id
                LEFT JOIN queue_stages stage ON stage.id = sales_queue.stage_id
                LEFT JOIN users estimator ON estimator.id = sales_queue.estimator_id
                LEFT JOIN users surveyor ON surveyor.id = sales_queue.surveyor_id
                LEFT JOIN users current_pic ON current_pic.id = sales_queue.current_pic_id";
    }

    /**
     * One row per distinct stage (including NULL = "belum diisi") across
     * every currently-active queue item — powers the Dashboard's Phase C
     * position-breakdown tiles.
     */
    public static function countActiveByStage(): array
    {
        return Database::fetchAll(
            "SELECT sales_queue.stage_id, COUNT(*) AS total
             FROM sales_queue
             WHERE sales_queue.status NOT IN ('done','cancelled')
             GROUP BY sales_queue.stage_id"
        );
    }

    /**
     * "Tugas" — free-text task title (Phase B). Falls back to the lead's
     * customer name for rows created before this field existed, or left
     * blank, so nothing regresses to an empty title.
     */
    public static function displayTitle(array $queue): string
    {
        return trim((string) ($queue['task_name'] ?? '')) !== ''
            ? $queue['task_name']
            : $queue['customer_name'];
    }

    public static function withRelations(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE sales_queue.id = ?', [$id]);
    }

    public static function activeForLead(int $leadId): ?array
    {
        return Database::fetch(
            self::baseSelect() . " WHERE sales_queue.lead_id = ? AND sales_queue.status NOT IN ('done','cancelled')
             ORDER BY sales_queue.id DESC LIMIT 1",
            [$leadId]
        );
    }

    /**
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['scope_sales_id'])) {
            $where[] = 'sales_queue.sales_id = ?';
            $params[] = (int) $filters['scope_sales_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(leads.lead_code LIKE ? OR leads.customer_name LIKE ? OR leads.company_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }

        if (!empty($filters['status'])) {
            $where[] = 'sales_queue.status = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['include_closed'])) {
            $where[] = "sales_queue.status NOT IN ('done','cancelled')";
        }

        if (!empty($filters['priority'])) {
            $where[] = 'sales_queue.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['survey_status_id'])) {
            $where[] = 'sales_queue.survey_status_id = ?';
            $params[] = (int) $filters['survey_status_id'];
        }

        if (!empty($filters['stage_id'])) {
            $where[] = 'sales_queue.stage_id = ?';
            $params[] = (int) $filters['stage_id'];
        }

        if (!empty($filters['sales_id'])) {
            $where[] = 'sales_queue.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }

        if (!empty($filters['overdue'])) {
            $where[] = "sales_queue.deadline IS NOT NULL AND sales_queue.deadline < CURDATE() AND sales_queue.status NOT IN ('done','cancelled')";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM sales_queue INNER JOIN leads ON leads.id = sales_queue.lead_id {$whereSql}",
            $params
        )['total'] ?? 0);

        $sortKey = self::SORTABLE[$filters['sort'] ?? ''] ?? self::SORTABLE['queue_number'];
        $dir = strtolower($filters['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $perPage = max(1, (int) ($filters['per_page'] ?? 15));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            self::baseSelect() . " {$whereSql} ORDER BY {$sortKey} {$dir}, sales_queue.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    /**
     * @return array{new:int,waiting_followup:int,in_progress:int,waiting_engineer:int,done:int,overdue:int}
     */
    public static function dashboardCounts(?int $scopeSalesId = null): array
    {
        $where = '';
        $params = [];
        if ($scopeSalesId !== null) {
            $where = 'WHERE sales_id = ?';
            $params[] = $scopeSalesId;
        }

        $rows = Database::fetchAll("SELECT status, COUNT(*) AS total FROM sales_queue {$where} GROUP BY status", $params);

        $counts = array_fill_keys(['new', 'waiting_followup', 'in_progress', 'waiting_engineer', 'done', 'cancelled'], 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        $overdueSql = "SELECT COUNT(*) AS total FROM sales_queue WHERE deadline IS NOT NULL AND deadline < CURDATE() AND status NOT IN ('done','cancelled')";
        $overdueParams = [];
        if ($scopeSalesId !== null) {
            $overdueSql .= ' AND sales_id = ?';
            $overdueParams[] = $scopeSalesId;
        }
        $counts['overdue'] = (int) (Database::fetch($overdueSql, $overdueParams)['total'] ?? 0);

        return $counts;
    }

    /**
     * Active queue items whose follow-up date has arrived or passed — see
     * Lead::pendingFollowUps() for the lead-side half of the same list on
     * the Sales Workspace.
     */
    public static function pendingFollowUps(int $salesId): array
    {
        return Database::fetchAll(
            self::baseSelect() . " WHERE sales_queue.sales_id = ?
             AND sales_queue.status NOT IN ('done','cancelled')
             AND sales_queue.followup_date IS NOT NULL AND sales_queue.followup_date <= CURDATE()
             ORDER BY sales_queue.followup_date ASC",
            [$salesId]
        );
    }

    /** Phase 13 — Queue Report: full (unpaginated) filtered set for the report table + CSV export. */
    public static function report(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'sales_queue.entered_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'sales_queue.entered_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['sales_id'])) {
            $where[] = 'sales_queue.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'sales_queue.status = ?';
            $params[] = $filters['status'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return Database::fetchAll(
            self::baseSelect() . " {$whereSql} ORDER BY sales_queue.entered_at DESC",
            $params
        );
    }

    /**
     * Insert then stamp queue_number = id inside one transaction — avoids
     * the race condition a separate MAX(queue_number)+1 lookup would have
     * under concurrent "add to queue" clicks (same pattern as Lead's
     * LD-000001 code generation).
     */
    public static function createForLead(array $data): int
    {
        return Database::transaction(function () use ($data) {
            $id = self::insert(['queue_number' => 0] + $data);
            self::update($id, ['queue_number' => $id]);

            return $id;
        });
    }
}
