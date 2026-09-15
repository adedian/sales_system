<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class EngineerAssignmentStatusHistory extends Model
{
    protected static string $table = 'engineer_assignment_status_history';

    public static function record(int $assignmentId, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $notes = null): int
    {
        return static::insert([
            'assignment_id' => $assignmentId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forAssignment(int $assignmentId): array
    {
        return Database::fetchAll(
            "SELECT h.*, u.name AS changed_by_name
             FROM engineer_assignment_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.assignment_id = ?
             ORDER BY h.created_at DESC, h.id DESC",
            [$assignmentId]
        );
    }
}
