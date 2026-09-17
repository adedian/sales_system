<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Revisi Alur Bisnis — Prelim: penawaran awal ke Client sebelum Proposal+BOQ.
 * Mirrors Proposal's header/status-history/notes shape closely (see
 * ProposalController/Proposal) but deliberately has NO item/BOQ table —
 * "Prelim BUKAN BOQ" per the business definition. Revision loop follows the
 * same "reopen the same row, log via status_history" pattern as
 * ProposalController::reviseFromNegotiation() rather than versioned rows.
 */
class Prelim extends Model
{
    protected static string $table = 'prelims';
    protected static bool $softDeletes = true;

    /** Statuses that still count as "open"/actionable for Sales. */
    public const OPEN_STATUSES = ['draft', 'ready_to_send', 'sent', 'client_revision'];

    private const SORTABLE = [
        'prelim_code' => 'prelims.prelim_code',
        'status' => 'prelims.status',
        'estimated_value' => 'prelims.estimated_value',
        'created_at' => 'prelims.created_at',
        'sales_name' => 'sales.name',
        'customer_name' => 'leads.customer_name',
    ];

    private static function baseSelect(): string
    {
        return "SELECT prelims.*,
                       leads.lead_code, leads.customer_name, leads.company_name,
                       leads.phone AS lead_phone, leads.email AS lead_email, leads.sales_id AS lead_sales_id,
                       sales.name AS sales_name
                FROM prelims
                INNER JOIN leads ON leads.id = prelims.lead_id
                LEFT JOIN users sales ON sales.id = prelims.sales_id";
    }

    public static function withRelations(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE prelims.id = ? AND prelims.deleted_at IS NULL', [$id]);
    }

    /** All prelims for a lead (a lead may accumulate several over time only if the earlier one was deleted while draft), newest first. */
    public static function forLead(int $leadId): array
    {
        return Database::fetchAll(
            self::baseSelect() . ' WHERE prelims.lead_id = ? AND prelims.deleted_at IS NULL ORDER BY prelims.id DESC',
            [$leadId]
        );
    }

    public static function latestForLead(int $leadId): ?array
    {
        return Database::fetch(
            self::baseSelect() . ' WHERE prelims.lead_id = ? AND prelims.deleted_at IS NULL ORDER BY prelims.id DESC LIMIT 1',
            [$leadId]
        );
    }

    /** The gate `EngineerController`/`ProcurementController` check before allowing post-ACC BOQ work to start. */
    public static function hasApprovedForLead(int $leadId): bool
    {
        return Database::fetch(
            "SELECT id FROM prelims WHERE lead_id = ? AND status = 'approved' AND deleted_at IS NULL LIMIT 1",
            [$leadId]
        ) !== null;
    }

    /**
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(array $filters): array
    {
        $where = ['prelims.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['scope_sales_id'])) {
            $where[] = 'prelims.sales_id = ?';
            $params[] = (int) $filters['scope_sales_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(prelims.prelim_code LIKE ? OR leads.lead_code LIKE ? OR leads.customer_name LIKE ? OR leads.company_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if (!empty($filters['status'])) {
            $where[] = 'prelims.status = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['include_closed'])) {
            $where[] = "prelims.status <> 'approved'";
        }

        if (!empty($filters['sales_id'])) {
            $where[] = 'prelims.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM prelims INNER JOIN leads ON leads.id = prelims.lead_id {$whereSql}",
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
            self::baseSelect() . " {$whereSql} ORDER BY {$sortKey} {$dir}, prelims.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    /** @return array{draft:int,ready_to_send:int,sent:int,client_revision:int,approved:int} */
    public static function dashboardCounts(?int $scopeSalesId = null): array
    {
        $where = 'WHERE deleted_at IS NULL';
        $params = [];
        if ($scopeSalesId !== null) {
            $where .= ' AND sales_id = ?';
            $params[] = $scopeSalesId;
        }

        $rows = Database::fetchAll("SELECT status, COUNT(*) AS total FROM prelims {$where} GROUP BY status", $params);

        $counts = array_fill_keys(['draft', 'ready_to_send', 'sent', 'client_revision', 'approved'], 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Insert then stamp prelim_code = PRE-000001 inside one transaction —
     * same race-safe pattern as Proposal::createForLead()/Lead::createWithCode().
     */
    public static function createForLead(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $placeholder = 'TMP-' . bin2hex(random_bytes(8));
            $id = self::insert(['prelim_code' => $placeholder] + $data);
            $code = sprintf('%s-%06d', Setting::get('numbering_prelim_prefix', 'PRE'), $id);
            self::update($id, ['prelim_code' => $code]);

            return ['id' => $id, 'prelim_code' => $code];
        });
    }
}
