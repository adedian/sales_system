<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    public static function recent(int $limit = 20): array
    {
        $limit = max(1, $limit);
        $sql = "SELECT audit_logs.*, users.name AS user_name
                FROM audit_logs
                LEFT JOIN users ON users.id = audit_logs.user_id
                ORDER BY audit_logs.created_at DESC
                LIMIT {$limit}";

        return Database::fetchAll($sql);
    }

    public static function recentForUser(int $userId, int $limit = 8): array
    {
        $limit = max(1, $limit);

        return Database::fetchAll(
            "SELECT audit_logs.*, users.name AS user_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE audit_logs.user_id = ?
             ORDER BY audit_logs.created_at DESC
             LIMIT {$limit}",
            [$userId]
        );
    }

    public static function forRecord(string $module, int $recordId): array
    {
        return Database::fetchAll(
            "SELECT audit_logs.*, users.name AS user_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE audit_logs.module = ? AND audit_logs.record_id = ?
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC",
            [$module, $recordId]
        );
    }
}
