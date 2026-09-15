<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ProposalNote extends Model
{
    protected static string $table = 'proposal_notes';

    public static function add(int $proposalId, ?int $userId, string $note): int
    {
        return static::insert([
            'proposal_id' => $proposalId,
            'user_id' => $userId,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forProposal(int $proposalId): array
    {
        return Database::fetchAll(
            "SELECT n.*, u.name AS user_name
             FROM proposal_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.proposal_id = ?
             ORDER BY n.created_at DESC, n.id DESC",
            [$proposalId]
        );
    }
}
