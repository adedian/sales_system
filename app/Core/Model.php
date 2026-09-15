<?php

namespace App\Core;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $softDeletes = false;

    public static function find(int $id): ?array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?";
        if (static::$softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }

        return Database::fetch($sql, [$id]);
    }

    public static function all(string $orderBy = 'id ASC'): array
    {
        $sql = "SELECT * FROM " . static::$table;
        if (static::$softDeletes) {
            $sql .= " WHERE deleted_at IS NULL";
        }
        $sql .= " ORDER BY {$orderBy}";

        return Database::fetchAll($sql);
    }

    public static function where(string $column, mixed $value): array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE {$column} = ?";
        if (static::$softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }

        return Database::fetchAll($sql, [$value]);
    }

    public static function whereFirst(string $column, mixed $value): ?array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE {$column} = ?";
        if (static::$softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        $sql .= " LIMIT 1";

        return Database::fetch($sql, [$value]);
    }

    public static function count(): int
    {
        $sql = "SELECT COUNT(*) AS total FROM " . static::$table;
        if (static::$softDeletes) {
            $sql .= " WHERE deleted_at IS NULL";
        }

        return (int) (Database::fetch($sql)['total'] ?? 0);
    }

    public static function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::$table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        return Database::insertGetId($sql, array_values($data));
    }

    public static function update(int $id, array $data): int
    {
        $assignments = implode(', ', array_map(fn ($col) => "{$col} = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;

        $sql = "UPDATE " . static::$table . " SET {$assignments} WHERE " . static::$primaryKey . " = ?";

        return Database::execute($sql, $params);
    }

    public static function softDelete(int $id): int
    {
        return static::update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public static function delete(int $id): int
    {
        $sql = "DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?";

        return Database::execute($sql, [$id]);
    }
}
