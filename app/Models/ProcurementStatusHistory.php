<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ProcurementStatusHistory extends Model
{
    protected static string $table = 'procurement_status_history';

    public static function record(int $procurementRequestId, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $notes = null): int
    {
        return static::insert([
            'procurement_request_id' => $procurementRequestId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forRequest(int $procurementRequestId): array
    {
        return Database::fetchAll(
            "SELECT h.*, u.name AS changed_by_name
             FROM procurement_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.procurement_request_id = ?
             ORDER BY h.created_at DESC, h.id DESC",
            [$procurementRequestId]
        );
    }
}
