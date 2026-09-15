<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class QueueStatusHistory extends Model
{
    protected static string $table = 'queue_status_history';

    public static function record(int $queueId, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $notes = null): int
    {
        return static::insert([
            'queue_id' => $queueId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forQueue(int $queueId): array
    {
        return Database::fetchAll(
            "SELECT h.*, u.name AS changed_by_name
             FROM queue_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.queue_id = ?
             ORDER BY h.created_at DESC, h.id DESC",
            [$queueId]
        );
    }
}
