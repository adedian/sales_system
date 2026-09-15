<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ProposalStatusHistory extends Model
{
    protected static string $table = 'proposal_status_history';

    public static function record(int $proposalId, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $notes = null): int
    {
        return static::insert([
            'proposal_id' => $proposalId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forProposal(int $proposalId): array
    {
        return Database::fetchAll(
            "SELECT h.*, u.name AS changed_by_name
             FROM proposal_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.proposal_id = ?
             ORDER BY h.created_at DESC, h.id DESC",
            [$proposalId]
        );
    }
}
