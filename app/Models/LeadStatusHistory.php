<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class LeadStatusHistory extends Model
{
    protected static string $table = 'lead_status_history';

    public static function record(int $leadId, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $notes = null): int
    {
        return static::insert([
            'lead_id' => $leadId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forLead(int $leadId): array
    {
        return Database::fetchAll(
            "SELECT h.*, u.name AS changed_by_name
             FROM lead_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.lead_id = ?
             ORDER BY h.created_at DESC, h.id DESC",
            [$leadId]
        );
    }

    /** The most recent transition INTO a given status — used by reopenDeal() to know which stage to restore to. */
    public static function mostRecentTransitionTo(int $leadId, string $toStatus): ?array
    {
        return Database::fetch(
            "SELECT * FROM lead_status_history WHERE lead_id = ? AND to_status = ? ORDER BY id DESC LIMIT 1",
            [$leadId, $toStatus]
        );
    }
}
