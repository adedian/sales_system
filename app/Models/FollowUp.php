<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Phase 9 — a logged contact attempt with a lead (call/whatsapp/email/visit),
 * independent of leads.follow_up_date (the single "next reminder" pointer
 * that Lead::pendingFollowUps()/the Sales Workspace already read — see
 * Phase 7). Logging an entry here also advances that pointer via
 * FollowUpController::store().
 */
class FollowUp extends Model
{
    protected static string $table = 'followups';

    public const METHODS = ['call', 'whatsapp', 'email', 'visit', 'other'];

    public const CUSTOMER_RESPONSES = ['interested', 'negotiating', 'need_info', 'not_interested', 'no_answer'];

    public static function record(array $data): int
    {
        return static::insert($data);
    }

    public static function forLead(int $leadId): array
    {
        return Database::fetchAll(
            "SELECT f.*, u.name AS sales_name
             FROM followups f
             LEFT JOIN users u ON u.id = f.sales_id
             WHERE f.lead_id = ?
             ORDER BY f.followup_date DESC, f.id DESC",
            [$leadId]
        );
    }

    /** Phase 13 — Follow-up Report: full (unpaginated) filtered set for the report table + CSV export. */
    public static function report(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'f.followup_date >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'f.followup_date <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['sales_id'])) {
            $where[] = 'f.sales_id = ?';
            $params[] = (int) $filters['sales_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(l.customer_name LIKE ? OR l.company_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return Database::fetchAll(
            "SELECT f.*, u.name AS sales_name, l.lead_code, l.customer_name, l.company_name
             FROM followups f
             LEFT JOIN users u ON u.id = f.sales_id
             INNER JOIN leads l ON l.id = f.lead_id
             {$whereSql}
             ORDER BY f.followup_date DESC",
            $params
        );
    }

    /** Most recent log entries across leads, newest first — feeds the follow-up calendar page. */
    public static function recent(?int $scopeSalesId = null, int $limit = 20): array
    {
        $where = '';
        $params = [];
        if ($scopeSalesId !== null) {
            $where = 'WHERE f.sales_id = ?';
            $params[] = $scopeSalesId;
        }
        $limit = max(1, $limit);

        return Database::fetchAll(
            "SELECT f.*, u.name AS sales_name, l.lead_code, l.customer_name, l.company_name
             FROM followups f
             LEFT JOIN users u ON u.id = f.sales_id
             INNER JOIN leads l ON l.id = f.lead_id
             {$where}
             ORDER BY f.created_at DESC, f.id DESC
             LIMIT {$limit}",
            $params
        );
    }
}
