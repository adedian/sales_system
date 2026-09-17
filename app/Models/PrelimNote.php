<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class PrelimNote extends Model
{
    protected static string $table = 'prelim_notes';

    public static function add(int $prelimId, ?int $userId, string $note): int
    {
        return static::insert([
            'prelim_id' => $prelimId,
            'user_id' => $userId,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forPrelim(int $prelimId): array
    {
        return Database::fetchAll(
            "SELECT n.*, u.name AS user_name
             FROM prelim_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.prelim_id = ?
             ORDER BY n.created_at DESC, n.id DESC",
            [$prelimId]
        );
    }
}
