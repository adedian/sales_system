<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Vendor extends Model
{
    protected static string $table = 'vendors';
    protected static bool $softDeletes = true;

    /**
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(array $filters): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(vendor_code LIKE ? OR name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'is_active = 1';
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = (int) (Database::fetch("SELECT COUNT(*) AS total FROM vendors {$whereSql}", $params)['total'] ?? 0);

        $perPage = max(1, (int) ($filters['per_page'] ?? 15));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT * FROM vendors {$whereSql} ORDER BY name ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    public static function activeList(): array
    {
        return Database::fetchAll('SELECT * FROM vendors WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC');
    }

    public static function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) AS total FROM vendors WHERE vendor_code = ?';
        $params = [$code];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        return (int) (Database::fetch($sql, $params)['total'] ?? 0) > 0;
    }

    /**
     * Insert then stamp vendor_code = VN-000001 inside one transaction —
     * same race-safe pattern as Lead::createWithCode().
     */
    public static function createWithCode(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $placeholder = 'TMP-' . bin2hex(random_bytes(8));
            $id = self::insert(['vendor_code' => $placeholder] + $data);
            $code = sprintf('VN-%04d', $id);
            self::update($id, ['vendor_code' => $code]);

            return ['id' => $id, 'vendor_code' => $code];
        });
    }
}
