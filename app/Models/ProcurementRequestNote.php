<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class ProcurementRequestNote extends Model
{
    protected static string $table = 'procurement_request_notes';

    public static function add(int $procurementRequestId, ?int $userId, string $note): int
    {
        return static::insert([
            'procurement_request_id' => $procurementRequestId,
            'user_id' => $userId,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forRequest(int $procurementRequestId): array
    {
        return Database::fetchAll(
            "SELECT n.*, u.name AS user_name
             FROM procurement_request_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.procurement_request_id = ?
             ORDER BY n.created_at DESC, n.id DESC",
            [$procurementRequestId]
        );
    }
}
