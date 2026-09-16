<?php

namespace App\Models;

use App\Core\Database;

/**
 * Phase A (Leads revision) — pivot table backing multi-sales-per-lead.
 * `leads.sales_id` stays the single "primary" sales owner (every other
 * module keeps reading/writing it unchanged); this table is the
 * authoritative full membership list shown/edited in the Leads module.
 */
class LeadSales
{
    public static function forLead(int $leadId): array
    {
        return Database::fetchAll(
            "SELECT users.id, users.name
             FROM lead_sales
             INNER JOIN users ON users.id = lead_sales.user_id
             WHERE lead_sales.lead_id = ? AND users.deleted_at IS NULL
             ORDER BY lead_sales.created_at ASC",
            [$leadId]
        );
    }

    /**
     * Batch-fetch for a page of leads — one query, grouped in PHP, to
     * avoid an N+1 query per row on the Leads list.
     *
     * @param int[] $leadIds
     * @return array<int, array<int, array{id:int,name:string}>> lead_id => [ ['id'=>.., 'name'=>..], ... ]
     */
    public static function forLeads(array $leadIds): array
    {
        if (empty($leadIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $rows = Database::fetchAll(
            "SELECT lead_sales.lead_id, users.id, users.name
             FROM lead_sales
             INNER JOIN users ON users.id = lead_sales.user_id
             WHERE lead_sales.lead_id IN ({$placeholders}) AND users.deleted_at IS NULL
             ORDER BY lead_sales.created_at ASC",
            $leadIds
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['lead_id']][] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }

        return $grouped;
    }

    /**
     * Replace-all: the lead ends up assigned to exactly $userIds.
     *
     * @param int[] $userIds
     */
    public static function sync(int $leadId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        Database::transaction(function () use ($leadId, $userIds) {
            $current = array_column(self::forLead($leadId), 'id');

            foreach (array_diff($current, $userIds) as $removeId) {
                Database::execute('DELETE FROM lead_sales WHERE lead_id = ? AND user_id = ?', [$leadId, $removeId]);
            }

            foreach (array_diff($userIds, $current) as $addId) {
                Database::execute(
                    'INSERT INTO lead_sales (lead_id, user_id, created_at) VALUES (?, ?, NOW())',
                    [$leadId, $addId]
                );
            }
        });
    }

    /** Additive: adds $userId to the lead's sales list without touching existing members. */
    public static function add(int $leadId, int $userId): void
    {
        if (self::isAssigned($leadId, $userId)) {
            return;
        }

        Database::execute(
            'INSERT INTO lead_sales (lead_id, user_id, created_at) VALUES (?, ?, NOW())',
            [$leadId, $userId]
        );
    }

    public static function isAssigned(int $leadId, int $userId): bool
    {
        return Database::fetch(
            'SELECT id FROM lead_sales WHERE lead_id = ? AND user_id = ?',
            [$leadId, $userId]
        ) !== null;
    }
}
