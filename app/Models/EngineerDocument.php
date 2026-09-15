<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class EngineerDocument extends Model
{
    protected static string $table = 'engineer_documents';

    public static function add(array $data): int
    {
        return static::insert($data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public static function forAssignment(int $assignmentId): array
    {
        return Database::fetchAll(
            "SELECT d.*, u.name AS uploaded_by_name
             FROM engineer_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.assignment_id = ?
             ORDER BY d.created_at DESC, d.id DESC",
            [$assignmentId]
        );
    }
}
