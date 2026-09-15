<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ProcurementItem extends Model
{
    protected static string $table = 'procurement_items';

    public static function forRequest(int $procurementRequestId): array
    {
        return Database::fetchAll(
            "SELECT pi.*, v.name AS vendor_name
             FROM procurement_items pi
             LEFT JOIN vendors v ON v.id = pi.vendor_id
             WHERE pi.procurement_request_id = ?
             ORDER BY pi.id ASC",
            [$procurementRequestId]
        );
    }

    /** True once every line item has a purchase price filled in — gate for marking pricing complete. */
    public static function allPriced(int $procurementRequestId): bool
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN purchase_price IS NULL THEN 1 ELSE 0 END) AS unpriced
             FROM procurement_items WHERE procurement_request_id = ?",
            [$procurementRequestId]
        );

        return $row !== null && (int) $row['total'] > 0 && (int) $row['unpriced'] === 0;
    }

    public static function totalPurchasePrice(int $procurementRequestId): float
    {
        return (float) (Database::fetch(
            "SELECT COALESCE(SUM(purchase_price * quantity), 0) AS total FROM procurement_items WHERE procurement_request_id = ?",
            [$procurementRequestId]
        )['total'] ?? 0);
    }
}
