<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class PrelimDocument extends Model
{
    protected static string $table = 'prelim_documents';

    public static function add(array $data): int
    {
        return static::insert($data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public static function forPrelim(int $prelimId): array
    {
        return Database::fetchAll(
            "SELECT d.*, u.name AS uploaded_by_name
             FROM prelim_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.prelim_id = ?
             ORDER BY d.created_at DESC, d.id DESC",
            [$prelimId]
        );
    }
}
