<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class EngineerAssignmentNote extends Model
{
    protected static string $table = 'engineer_assignment_notes';

    public static function add(int $assignmentId, ?int $userId, string $note): int
    {
        return static::insert([
            'assignment_id' => $assignmentId,
            'user_id' => $userId,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forAssignment(int $assignmentId): array
    {
        return Database::fetchAll(
            "SELECT n.*, u.name AS user_name
             FROM engineer_assignment_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.assignment_id = ?
             ORDER BY n.created_at DESC, n.id DESC",
            [$assignmentId]
        );
    }
}
