<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class EngineerAssignment extends Model
{
    protected static string $table = 'engineer_assignments';

    /** Statuses that still count as "open" work for an engineer. */
    public const OPEN_STATUSES = ['pending', 'accepted', 'in_progress', 'waiting'];

    /** Statuses that close an assignment out (excluded from the default list unless requested). */
    public const CLOSED_STATUSES = ['rejected', 'returned'];

    private const SORTABLE = [
        'assignment_code' => 'engineer_assignments.assignment_code',
        'priority' => 'engineer_assignments.priority',
        'status' => 'engineer_assignments.status',
        'deadline' => 'engineer_assignments.deadline',
        'assigned_at' => 'engineer_assignments.assigned_at',
        'engineer_name' => 'engineer.name',
        'customer_name' => 'leads.customer_name',
    ];

    private static function baseSelect(): string
    {
        return "SELECT engineer_assignments.*,
                       leads.lead_code, leads.customer_name, leads.company_name,
                       leads.phone AS lead_phone, leads.address AS lead_address,
                       leads.needs_description AS lead_needs_description,
                       leads.sales_id AS lead_sales_id,
                       sales.name AS sales_name,
                       engineer.name AS engineer_name,
                       requester.name AS assigned_by_name
                FROM engineer_assignments
                INNER JOIN leads ON leads.id = engineer_assignments.lead_id
                LEFT JOIN users sales ON sales.id = leads.sales_id
                LEFT JOIN users engineer ON engineer.id = engineer_assignments.engineer_id
                LEFT JOIN users requester ON requester.id = engineer_assignments.assigned_by";
    }

    public static function withRelations(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE engineer_assignments.id = ?', [$id]);
    }

    /** The one still-open assignment for a lead, if any — used to block duplicate requests. */
    public static function activeForLead(int $leadId): ?array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));

        return Database::fetch(
            self::baseSelect() . " WHERE engineer_assignments.lead_id = ? AND engineer_assignments.status IN ({$placeholders})
             ORDER BY engineer_assignments.id DESC LIMIT 1",
            array_merge([$leadId], self::OPEN_STATUSES)
        );
    }

    /** Most recent assignment for a lead regardless of status — used to show a closed/returned result on the Lead page. */
    public static function latestForLead(int $leadId): ?array
    {
        return Database::fetch(
            self::baseSelect() . ' WHERE engineer_assignments.lead_id = ? ORDER BY engineer_assignments.id DESC LIMIT 1',
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

        if (!empty($filters['scope_engineer_id'])) {
            $where[] = 'engineer_assignments.engineer_id = ?';
            $params[] = (int) $filters['scope_engineer_id'];
        }

        if (!empty($filters['scope_sales_id'])) {
            $where[] = 'leads.sales_id = ?';
            $params[] = (int) $filters['scope_sales_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(engineer_assignments.assignment_code LIKE ? OR leads.lead_code LIKE ? OR leads.customer_name LIKE ? OR leads.company_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if (!empty($filters['status'])) {
            $where[] = 'engineer_assignments.status = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['include_closed'])) {
            $where[] = "engineer_assignments.status NOT IN ('rejected','returned')";
        }

        if (!empty($filters['priority'])) {
            $where[] = 'engineer_assignments.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['engineer_id'])) {
            $where[] = 'engineer_assignments.engineer_id = ?';
            $params[] = (int) $filters['engineer_id'];
        }

        if (!empty($filters['overdue'])) {
            $where[] = "engineer_assignments.deadline IS NOT NULL AND engineer_assignments.deadline < CURDATE() AND engineer_assignments.status NOT IN ('rejected','completed','returned')";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM engineer_assignments INNER JOIN leads ON leads.id = engineer_assignments.lead_id {$whereSql}",
            $params
        )['total'] ?? 0);

        $sortKey = self::SORTABLE[$filters['sort'] ?? ''] ?? self::SORTABLE['assigned_at'];
        $dir = strtolower($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $perPage = max(1, (int) ($filters['per_page'] ?? 15));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            self::baseSelect() . " {$whereSql} ORDER BY {$sortKey} {$dir}, engineer_assignments.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    /**
     * @return array{pending:int,accepted:int,in_progress:int,waiting:int,completed:int,rejected:int,returned:int,overdue:int}
     */
    public static function dashboardCounts(?int $scopeEngineerId = null, ?int $scopeSalesId = null): array
    {
        $joinLeads = $scopeSalesId !== null ? 'INNER JOIN leads ON leads.id = engineer_assignments.lead_id' : '';
        $where = [];
        $params = [];

        if ($scopeEngineerId !== null) {
            $where[] = 'engineer_assignments.engineer_id = ?';
            $params[] = $scopeEngineerId;
        }

        if ($scopeSalesId !== null) {
            $where[] = 'leads.sales_id = ?';
            $params[] = $scopeSalesId;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = Database::fetchAll(
            "SELECT engineer_assignments.status, COUNT(*) AS total FROM engineer_assignments {$joinLeads} {$whereSql} GROUP BY engineer_assignments.status",
            $params
        );

        $counts = array_fill_keys(['pending', 'accepted', 'in_progress', 'waiting', 'completed', 'rejected', 'returned'], 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        $overdueWhere = array_merge(["engineer_assignments.deadline IS NOT NULL AND engineer_assignments.deadline < CURDATE() AND engineer_assignments.status NOT IN ('rejected','completed','returned')"], $where);
        $counts['overdue'] = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM engineer_assignments {$joinLeads} WHERE " . implode(' AND ', $overdueWhere),
            $params
        )['total'] ?? 0);

        return $counts;
    }

    /** Open assignments (pending/accepted/in_progress/waiting) needing this engineer's attention, newest first. */
    public static function openForEngineer(int $engineerId, int $limit = 6): array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $limit = max(1, $limit);

        return Database::fetchAll(
            self::baseSelect() . " WHERE engineer_assignments.engineer_id = ? AND engineer_assignments.status IN ({$placeholders})
             ORDER BY FIELD(engineer_assignments.status, 'pending','waiting','in_progress','accepted'), engineer_assignments.assigned_at ASC
             LIMIT {$limit}",
            array_merge([$engineerId], self::OPEN_STATUSES)
        );
    }

    /** Phase 13 — Engineer Report: full (unpaginated) filtered set for the report table + CSV export. */
    public static function report(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'engineer_assignments.assigned_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'engineer_assignments.assigned_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['engineer_id'])) {
            $where[] = 'engineer_assignments.engineer_id = ?';
            $params[] = (int) $filters['engineer_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'engineer_assignments.status = ?';
            $params[] = $filters['status'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return Database::fetchAll(
            self::baseSelect() . " {$whereSql} ORDER BY engineer_assignments.assigned_at DESC",
            $params
        );
    }

    /** Active-assignment count per Engineer — feeds the Manager Dashboard's team workload table. */
    public static function workloadByEngineer(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));

        $rows = Database::fetchAll(
            "SELECT u.id, u.name,
                    SUM(CASE WHEN ea.status IN ({$placeholders}) THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN ea.status = 'completed' THEN 1 ELSE 0 END) AS completed_count
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id AND r.slug = 'engineer-sales'
             LEFT JOIN engineer_assignments ea ON ea.engineer_id = u.id
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
     * Insert then stamp assignment_code = EA-000001 (derived from the row's
     * own id) inside one transaction — same race-safe pattern as
     * Lead::createWithCode() / SalesQueue::createForLead().
     */
    public static function createForLead(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $placeholder = 'TMP-' . bin2hex(random_bytes(8));
            $id = self::insert(['assignment_code' => $placeholder] + $data);
            $code = sprintf('EA-%06d', $id);
            self::update($id, ['assignment_code' => $code]);

            return ['id' => $id, 'assignment_code' => $code];
        });
    }
}
