<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static bool $softDeletes = true;

    public static function findByUsername(string $username): ?array
    {
        return static::whereFirst('username', $username);
    }

    public static function findByEmail(string $email): ?array
    {
        return static::whereFirst('email', $email);
    }

    public static function activeByRole(int $roleId): array
    {
        return Database::fetchAll(
            'SELECT * FROM users WHERE role_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC',
            [$roleId]
        );
    }

    /** Phase C — Current PIC picker: any active user, not flag-restricted (could be Sales, Estimator, Surveyor, or anyone else currently responsible). */
    public static function allActive(): array
    {
        return Database::fetchAll(
            'SELECT * FROM users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC'
        );
    }

    /**
     * Phase B — Estimator/Surveyor are capability flags independent of
     * role_id (a user keeps their normal login role, e.g. sales, and can
     * additionally be flagged available for either/both), not RBAC roles.
     */
    public static function activeEstimators(): array
    {
        return self::activeByFlag('is_estimator');
    }

    public static function activeSurveyors(): array
    {
        return self::activeByFlag('is_surveyor');
    }

    /**
     * Revisi Sub-Fase 1 — same capability-flag pattern as Estimator/Surveyor
     * above (a user keeps their normal login role and can additionally be
     * flagged available for one or more of these).
     */
    public static function activeEngineers(): array
    {
        return self::activeByFlag('is_engineer');
    }

    public static function activeSalesEngineers(): array
    {
        return self::activeByFlag('is_sales_engineer');
    }

    public static function activeDirectors(): array
    {
        return self::activeByFlag('is_director');
    }

    private static function activeByFlag(string $flagColumn): array
    {
        $allowed = ['is_estimator', 'is_surveyor', 'is_engineer', 'is_sales_engineer', 'is_director'];
        if (!in_array($flagColumn, $allowed, true)) {
            throw new \InvalidArgumentException("Kolom flag tidak dikenal: {$flagColumn}");
        }

        return Database::fetchAll(
            "SELECT * FROM users WHERE {$flagColumn} = 1 AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC"
        );
    }

    public static function withRole(int $id): ?array
    {
        $sql = "SELECT users.*, roles.name AS role_name, roles.slug AS role_slug
                FROM users
                LEFT JOIN roles ON roles.id = users.role_id
                WHERE users.id = ? AND users.deleted_at IS NULL";

        return Database::fetch($sql, [$id]);
    }

    public static function countActive(): int
    {
        return (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM users WHERE deleted_at IS NULL AND is_active = 1"
        )['total'] ?? 0);
    }

    /**
     * List + search + filter + pagination for the User Management screen.
     *
     * @param array{q?:string,role_id?:int,status?:string,page?:int,per_page?:int} $filters
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(array $filters): array
    {
        $where = ['users.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(users.name LIKE ? OR users.username LIKE ? OR users.email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }

        if (!empty($filters['role_id'])) {
            $where[] = 'users.role_id = ?';
            $params[] = (int) $filters['role_id'];
        }

        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'users.is_active = 1';
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $where[] = 'users.is_active = 0';
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM users WHERE {$whereSql}",
            $params
        )['total'] ?? 0);

        $perPage = max(1, (int) ($filters['per_page'] ?? 20));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT users.*, roles.name AS role_name, roles.slug AS role_slug
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE {$whereSql}
             ORDER BY users.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }
}
