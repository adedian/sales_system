<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ProposalItem extends Model
{
    protected static string $table = 'proposal_items';

    public static function forProposal(int $proposalId): array
    {
        return Database::fetchAll(
            'SELECT * FROM proposal_items WHERE proposal_id = ? ORDER BY sort_order ASC, id ASC',
            [$proposalId]
        );
    }

    public static function nextSortOrder(int $proposalId): int
    {
        $max = Database::fetch('SELECT MAX(sort_order) AS max_order FROM proposal_items WHERE proposal_id = ?', [$proposalId]);

        return ((int) ($max['max_order'] ?? 0)) + 10;
    }

    /**
     * Seeds proposal_items from a procurement request's priced items — the
     * usual starting point for a proposal (unit_cost = procurement's
     * purchase_price, unit_price defaults to the same value so Sales can
     * then mark it up).
     */
    public static function copyFromProcurementItems(int $proposalId, array $procurementItems): void
    {
        $order = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($procurementItems as $item) {
            $order += 10;
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) ($item['purchase_price'] ?? 0);

            static::insert([
                'proposal_id' => $proposalId,
                'procurement_item_id' => (int) $item['id'],
                'item_name' => $item['item_name'],
                'specification' => $item['specification'],
                'quantity' => $quantity,
                'unit' => $item['unit'],
                'unit_cost' => $item['purchase_price'],
                'unit_price' => $unitPrice,
                'subtotal' => round($quantity * $unitPrice, 2),
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
