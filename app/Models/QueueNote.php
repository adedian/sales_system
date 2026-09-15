<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class QueueNote extends Model
{
    protected static string $table = 'queue_notes';

    public static function add(int $queueId, ?int $userId, string $note): int
    {
        return static::insert([
            'queue_id' => $queueId,
            'user_id' => $userId,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forQueue(int $queueId): array
    {
        return Database::fetchAll(
            "SELECT n.*, u.name AS user_name
             FROM queue_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.queue_id = ?
             ORDER BY n.created_at DESC, n.id DESC",
            [$queueId]
        );
    }
}
