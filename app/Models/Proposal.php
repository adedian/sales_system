<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Proposal extends Model
{
    protected static string $table = 'proposals';
    protected static bool $softDeletes = true;

    /** Statuses that still count as "open"/actionable for Sales. */
    public const OPEN_STATUSES = ['draft', 'internal_review', 'revision', 'approved', 'sent', 'viewed', 'negotiation'];

    /** Statuses that close a proposal out. */
    public const CLOSED_STATUSES = ['accepted', 'rejected', 'expired'];

    private const SORTABLE = [
        'proposal_code' => 'proposals.proposal_code',
        'status' => 'proposals.status',
        'total' => 'proposals.total',
        'valid_until' => 'proposals.valid_until',
        'created_at' => 'proposals.created_at',
        'sales_name' => 'sales.name',
        'customer_name' => 'leads.customer_name',
    ];

    private static function baseSelect(): string
    {
        return "SELECT proposals.*,
                       leads.lead_code, leads.customer_name, leads.company_name,
                       leads.phone AS lead_phone, leads.email AS lead_email, leads.sales_id AS lead_sales_id,
                       sales.name AS sales_name,
                       approver.name AS approved_by_name,
                       pr.request_code AS procurement_request_code
                FROM proposals
                INNER JOIN leads ON leads.id = proposals.lead_id
                LEFT JOIN users sales ON sales.id = proposals.sales_id
                LEFT JOIN users approver ON approver.id = proposals.approved_by
                LEFT JOIN procurement_requests pr ON pr.id = proposals.procurement_request_id";
    }

    public static function withRelations(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE proposals.id = ? AND proposals.deleted_at IS NULL', [$id]);
    }

    public static function withRelationsIncludingTrashed(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE proposals.id = ?', [$id]);
    }

    /** All proposals for a lead (a lead may have several over time), newest first — shown on the Lead page. */
    public static function forLead(int $leadId): array
    {
        return Database::fetchAll(
            self::baseSelect() . ' WHERE proposals.lead_id = ? AND proposals.deleted_at IS NULL ORDER BY proposals.id DESC',
            [$leadId]
        );
    }

    /**
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(array $filters): array
    {
        $where = ['proposals.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['scope_sales_id'])) {
            $where[] = 'proposals.sales_id = ?';
            $params[] = (int) $filters['scope_sales_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(proposals.proposal_code LIKE ? OR leads.lead_code LIKE ? OR leads.customer_name LIKE ? OR leads.company_name LIKE ? OR proposals.project_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if (!empty($filters['status'])) {
            $where[] = 'proposals.status = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['include_closed'])) {
            $where[] = "proposals.status NOT IN ('accepted','rejected','expired')";
        }

        if (!empty($filters['sales_id'])) {
            $where[] = 'proposals.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM proposals INNER JOIN leads ON leads.id = proposals.lead_id {$whereSql}",
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
            self::baseSelect() . " {$whereSql} ORDER BY {$sortKey} {$dir}, proposals.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    /**
     * @return array{draft:int,internal_review:int,revision:int,approved:int,sent:int,viewed:int,negotiation:int,accepted:int,rejected:int,expired:int}
     */
    public static function dashboardCounts(?int $scopeSalesId = null): array
    {
        $where = 'WHERE deleted_at IS NULL';
        $params = [];
        if ($scopeSalesId !== null) {
            $where .= ' AND sales_id = ?';
            $params[] = $scopeSalesId;
        }

        $rows = Database::fetchAll("SELECT status, COUNT(*) AS total FROM proposals {$where} GROUP BY status", $params);

        $counts = array_fill_keys(['draft', 'internal_review', 'revision', 'approved', 'sent', 'viewed', 'negotiation', 'accepted', 'rejected', 'expired'], 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** Open proposals needing this Sales rep's attention, newest first. */
    public static function openForSales(int $salesId, int $limit = 6): array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $limit = max(1, $limit);

        return Database::fetchAll(
            self::baseSelect() . " WHERE proposals.deleted_at IS NULL AND proposals.sales_id = ? AND proposals.status IN ({$placeholders})
             ORDER BY FIELD(proposals.status, 'revision','draft','internal_review','approved','sent','viewed','negotiation'), proposals.updated_at DESC
             LIMIT {$limit}",
            array_merge([$salesId], self::OPEN_STATUSES)
        );
    }

    /** Phase 13 — Proposal Report: full (unpaginated) filtered set for the report table + CSV export. */
    public static function report(array $filters): array
    {
        $where = ['proposals.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'proposals.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'proposals.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['sales_id'])) {
            $where[] = 'proposals.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'proposals.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(leads.customer_name LIKE ? OR leads.company_name LIKE ? OR proposals.project_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return Database::fetchAll(
            self::baseSelect() . " {$whereSql} ORDER BY proposals.created_at DESC",
            $params
        );
    }

    /** Sum of `total` for every proposal still in flight (not yet accepted/rejected/expired) — Phase 12 dashboard. */
    public static function openValue(): float
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));

        return (float) (Database::fetch(
            "SELECT COALESCE(SUM(total), 0) AS total FROM proposals WHERE deleted_at IS NULL AND status IN ({$placeholders})",
            self::OPEN_STATUSES
        )['total'] ?? 0);
    }

    /** Open proposals awaiting this Manager's approval, newest first. */
    public static function pendingApproval(int $limit = 10): array
    {
        $limit = max(1, $limit);

        return Database::fetchAll(
            self::baseSelect() . " WHERE proposals.deleted_at IS NULL AND proposals.status = 'internal_review'
             ORDER BY proposals.submitted_at ASC LIMIT {$limit}"
        );
    }

    /**
     * Insert then stamp proposal_code = PRO-000001 inside one transaction —
     * same race-safe pattern as ProcurementRequest::createForLead().
     */
    public static function createForLead(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $placeholder = 'TMP-' . bin2hex(random_bytes(8));
            $id = self::insert(['proposal_code' => $placeholder] + $data);
            $code = sprintf('PRO-%06d', $id);
            self::update($id, ['proposal_code' => $code]);

            return ['id' => $id, 'proposal_code' => $code];
        });
    }

    /** Recomputes subtotal/discount/tax/total from the current proposal_items rows and persists them. */
    public static function recalculateTotals(int $proposalId): void
    {
        $proposal = self::find($proposalId);
        if ($proposal === null) {
            return;
        }

        $subtotal = (float) (Database::fetch(
            'SELECT COALESCE(SUM(subtotal), 0) AS total FROM proposal_items WHERE proposal_id = ?',
            [$proposalId]
        )['total'] ?? 0);

        $discountPercent = $proposal['discount_percent'] !== null ? (float) $proposal['discount_percent'] : 0.0;
        $discountAmount = round($subtotal * ($discountPercent / 100), 2);

        $taxPercent = $proposal['tax_percent'] !== null ? (float) $proposal['tax_percent'] : 0.0;
        $taxableBase = $subtotal - $discountAmount;
        $taxAmount = round($taxableBase * ($taxPercent / 100), 2);

        $total = $taxableBase + $taxAmount;

        self::update($proposalId, [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
