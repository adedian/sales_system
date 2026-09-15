<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Phase 9 — negotiation log for a proposal that's out with the customer:
 * customer feedback / counter-offer, Sales' response, or a revision request
 * (the entry that accompanies reopening the proposal back to draft — see
 * ProposalController::reviseFromNegotiation()).
 */
class ProposalNegotiation extends Model
{
    protected static string $table = 'proposal_negotiations';

    public static function add(int $proposalId, string $type, string $message, ?float $requestedTotal, ?int $createdBy): int
    {
        return static::insert([
            'proposal_id' => $proposalId,
            'type' => $type,
            'message' => $message,
            'requested_total' => $requestedTotal,
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forProposal(int $proposalId): array
    {
        return Database::fetchAll(
            "SELECT n.*, u.name AS user_name
             FROM proposal_negotiations n
             LEFT JOIN users u ON u.id = n.created_by
             WHERE n.proposal_id = ?
             ORDER BY n.created_at DESC, n.id DESC",
            [$proposalId]
        );
    }
}
