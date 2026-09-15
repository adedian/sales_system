<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ProcurementRequest extends Model
{
    protected static string $table = 'procurement_requests';

    /** Statuses that still count as "open" work for procurement. */
    public const OPEN_STATUSES = ['waiting', 'in_progress', 'quotation_requested', 'need_revision'];

    /** Statuses that close a request out (excluded from the default list unless requested). */
    public const CLOSED_STATUSES = ['pricing_completed', 'cancelled'];

    private const SORTABLE = [
        'request_code' => 'procurement_requests.request_code',
        'priority' => 'procurement_requests.priority',
        'status' => 'procurement_requests.status',
        'deadline' => 'procurement_requests.deadline',
        'requested_at' => 'procurement_requests.requested_at',
        'assigned_to_name' => 'assignee.name',
        'customer_name' => 'leads.customer_name',
    ];

    private static function baseSelect(): string
    {
        return "SELECT procurement_requests.*,
                       leads.lead_code, leads.customer_name, leads.company_name,
                       leads.phone AS lead_phone, leads.sales_id AS lead_sales_id,
                       sales.name AS sales_name,
                       assignee.name AS assigned_to_name,
                       requester.name AS requested_by_name,
                       ea.assignment_code AS engineer_assignment_code,
                       ea.result_notes AS engineer_result_notes
                FROM procurement_requests
                INNER JOIN leads ON leads.id = procurement_requests.lead_id
                LEFT JOIN users sales ON sales.id = leads.sales_id
                LEFT JOIN users assignee ON assignee.id = procurement_requests.assigned_to
                LEFT JOIN users requester ON requester.id = procurement_requests.requested_by
                LEFT JOIN engineer_assignments ea ON ea.id = procurement_requests.engineer_assignment_id";
    }

    public static function withRelations(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE procurement_requests.id = ?', [$id]);
    }

    /** The one still-open request for a lead, if any — used to block duplicate requests. */
    public static function activeForLead(int $leadId): ?array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));

        return Database::fetch(
            self::baseSelect() . " WHERE procurement_requests.lead_id = ? AND procurement_requests.status IN ({$placeholders})
             ORDER BY procurement_requests.id DESC LIMIT 1",
            array_merge([$leadId], self::OPEN_STATUSES)
        );
    }

    /** Most recent request for a lead regardless of status — used to show a closed/completed result on the Lead page. */
    public static function latestForLead(int $leadId): ?array
    {
        return Database::fetch(
            self::baseSelect() . ' WHERE procurement_requests.lead_id = ? ORDER BY procurement_requests.id DESC LIMIT 1',
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

        if (!empty($filters['scope_assigned_to'])) {
            $where[] = 'procurement_requests.assigned_to = ?';
            $params[] = (int) $filters['scope_assigned_to'];
        }

        if (!empty($filters['scope_sales_id'])) {
            $where[] = 'leads.sales_id = ?';
            $params[] = (int) $filters['scope_sales_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(procurement_requests.request_code LIKE ? OR leads.lead_code LIKE ? OR leads.customer_name LIKE ? OR leads.company_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if (!empty($filters['status'])) {
            $where[] = 'procurement_requests.status = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['include_closed'])) {
            $where[] = "procurement_requests.status NOT IN ('pricing_completed','cancelled')";
        }

        if (!empty($filters['priority'])) {
            $where[] = 'procurement_requests.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = 'procurement_requests.assigned_to = ?';
            $params[] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['overdue'])) {
            $where[] = "procurement_requests.deadline IS NOT NULL AND procurement_requests.deadline < CURDATE() AND procurement_requests.status NOT IN ('pricing_completed','cancelled')";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM procurement_requests INNER JOIN leads ON leads.id = procurement_requests.lead_id {$whereSql}",
            $params
        )['total'] ?? 0);

        $sortKey = self::SORTABLE[$filters['sort'] ?? ''] ?? self::SORTABLE['requested_at'];
        $dir = strtolower($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $perPage = max(1, (int) ($filters['per_page'] ?? 15));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            self::baseSelect() . " {$whereSql} ORDER BY {$sortKey} {$dir}, procurement_requests.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    /**
     * @return array{waiting:int,in_progress:int,quotation_requested:int,pricing_completed:int,need_revision:int,cancelled:int,overdue:int}
     */
    public static function dashboardCounts(?int $scopeAssignedTo = null, ?int $scopeSalesId = null): array
    {
        $joinLeads = $scopeSalesId !== null ? 'INNER JOIN leads ON leads.id = procurement_requests.lead_id' : '';
        $where = [];
        $params = [];

        if ($scopeAssignedTo !== null) {
            $where[] = 'procurement_requests.assigned_to = ?';
            $params[] = $scopeAssignedTo;
        }

        if ($scopeSalesId !== null) {
            $where[] = 'leads.sales_id = ?';
            $params[] = $scopeSalesId;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = Database::fetchAll(
            "SELECT procurement_requests.status, COUNT(*) AS total FROM procurement_requests {$joinLeads} {$whereSql} GROUP BY procurement_requests.status",
            $params
        );

        $counts = array_fill_keys(['waiting', 'in_progress', 'quotation_requested', 'pricing_completed', 'need_revision', 'cancelled'], 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        $overdueWhere = array_merge(["procurement_requests.deadline IS NOT NULL AND procurement_requests.deadline < CURDATE() AND procurement_requests.status NOT IN ('pricing_completed','cancelled')"], $where);
        $counts['overdue'] = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM procurement_requests {$joinLeads} WHERE " . implode(' AND ', $overdueWhere),
            $params
        )['total'] ?? 0);

        return $counts;
    }

    /** Open requests needing this procurement staff's attention, newest first. */
    public static function openForAssignee(int $assignedTo, int $limit = 6): array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $limit = max(1, $limit);

        return Database::fetchAll(
            self::baseSelect() . " WHERE procurement_requests.assigned_to = ? AND procurement_requests.status IN ({$placeholders})
             ORDER BY FIELD(procurement_requests.status, 'need_revision','waiting','quotation_requested','in_progress'), procurement_requests.requested_at ASC
             LIMIT {$limit}",
            array_merge([$assignedTo], self::OPEN_STATUSES)
        );
    }

    /** Phase 13 — Procurement Report: full (unpaginated) filtered set, with each request's total purchase value, for the report table + CSV export. */
    public static function report(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'procurement_requests.requested_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'procurement_requests.requested_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['procurement_id'])) {
            $where[] = 'procurement_requests.assigned_to = ?';
            $params[] = (int) $filters['procurement_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'procurement_requests.status = ?';
            $params[] = $filters['status'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = Database::fetchAll(
            "SELECT procurement_requests.*,
                    leads.lead_code, leads.customer_name, leads.company_name,
                    leads.phone AS lead_phone, leads.sales_id AS lead_sales_id,
                    sales.name AS sales_name,
                    assignee.name AS assigned_to_name,
                    requester.name AS requested_by_name,
                    (SELECT COALESCE(SUM(purchase_price * quantity), 0) FROM procurement_items WHERE procurement_request_id = procurement_requests.id) AS total_purchase_value
             FROM procurement_requests
             INNER JOIN leads ON leads.id = procurement_requests.lead_id
             LEFT JOIN users sales ON sales.id = leads.sales_id
             LEFT JOIN users assignee ON assignee.id = procurement_requests.assigned_to
             LEFT JOIN users requester ON requester.id = procurement_requests.requested_by
             {$whereSql} ORDER BY procurement_requests.requested_at DESC",
            $params
        );

        foreach ($rows as &$row) {
            $row['total_purchase_value'] = (float) $row['total_purchase_value'];
        }

        return $rows;
    }

    /** Active-request count per Procurement staff — feeds the Manager Dashboard's team workload table. */
    public static function workloadByAssignee(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));

        $rows = Database::fetchAll(
            "SELECT u.id, u.name,
                    SUM(CASE WHEN pr.status IN ({$placeholders}) THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN pr.status = 'pricing_completed' THEN 1 ELSE 0 END) AS completed_count
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id AND r.slug = 'procurement'
             LEFT JOIN procurement_requests pr ON pr.assigned_to = u.id
             WHERE u.is_active = 1
             GROUP BY u.id, u.name
             ORDER BY active_count DESC",
            self::OPEN_STATUSES
        );

        foreach ($rows as &$row) {
            $row['active_count'] = (int) $row['active_count'];
            $row['completed_count'] = (int) $row['completed_count'];
        }

        return $rows;
    }

    /**
     * Insert then stamp request_code = PR-000001 inside one transaction —
     * same race-safe pattern as EngineerAssignment::createForLead().
     */
    public static function createForLead(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $placeholder = 'TMP-' . bin2hex(random_bytes(8));
            $id = self::insert(['request_code' => $placeholder] + $data);
            $code = sprintf('PR-%06d', $id);
            self::update($id, ['request_code' => $code]);

            return ['id' => $id, 'request_code' => $code];
        });
    }
}
