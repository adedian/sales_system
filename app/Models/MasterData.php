<?php

namespace App\Models;

use App\Core\Database;

/**
 * Table-parameterized data access shared by every Master Data type
 * (lead_sources, lead_categories, lead_statuses, priorities, need_types,
 * queue_statuses, engineer_statuses — see app/Config/master_data.php).
 *
 * Unlike the other Models, this one is NOT bound to a single table: every
 * method takes the physical table name as its first argument so one class
 * can serve all seven types identically.
 */
class MasterData
{
    private static function assertTable(string $table): void
    {
        static $allowed = null;
        $allowed ??= array_column(config('master_data'), 'table');

        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException("Tabel master data tidak dikenal: {$table}");
        }
    }

    public static function find(string $table, int $id): ?array
    {
        self::assertTable($table);

        return Database::fetch("SELECT * FROM {$table} WHERE id = ?", [$id]);
    }

    public static function findByCode(string $table, string $code): ?array
    {
        self::assertTable($table);

        return Database::fetch("SELECT * FROM {$table} WHERE code = ?", [$code]);
    }

    /**
     * All rows keyed by `code`, e.g. for badge/label lookups (status/priority
     * colors) or populating a <select> — avoids N+1 queries when a list of
     * records each need their master-data label resolved.
     *
     * @return array<string, array>
     */
    public static function allAsMap(string $table, bool $activeOnly = false): array
    {
        self::assertTable($table);

        $sql = "SELECT * FROM {$table}" . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, name ASC';
        $rows = Database::fetchAll($sql);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['code']] = $row;
        }

        return $map;
    }

    /**
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(string $table, array $filters): array
    {
        self::assertTable($table);

        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(name LIKE ? OR code LIKE ? OR description LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }

        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'is_active = 1';
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM {$table} {$whereSql}",
            $params
        )['total'] ?? 0);

        $perPage = max(1, (int) ($filters['per_page'] ?? 10));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT * FROM {$table} {$whereSql} ORDER BY sort_order ASC, name ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    public static function insert(string $table, array $data): int
    {
        self::assertTable($table);

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', $placeholders));

        return Database::insertGetId($sql, array_values($data));
    }

    public static function update(string $table, int $id, array $data): int
    {
        self::assertTable($table);

        $assignments = implode(', ', array_map(fn ($col) => "{$col} = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;

        return Database::execute("UPDATE {$table} SET {$assignments} WHERE id = ?", $params);
    }

    public static function delete(string $table, int $id): int
    {
        self::assertTable($table);

        return Database::execute("DELETE FROM {$table} WHERE id = ?", [$id]);
    }

    public static function codeExists(string $table, string $code, ?int $excludeId = null): bool
    {
        self::assertTable($table);

        $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE code = ?";
        $params = [$code];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        return (int) (Database::fetch($sql, $params)['total'] ?? 0) > 0;
    }
}
