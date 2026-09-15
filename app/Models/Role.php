<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Role extends Model
{
    protected static string $table = 'roles';

    public static function findBySlug(string $slug): ?array
    {
        return static::whereFirst('slug', $slug);
    }

    /**
     * @return string[] list of permission slugs for a given role id
     */
    public static function permissionSlugs(int $roleId): array
    {
        $sql = "SELECT permissions.slug
                FROM role_permissions
                INNER JOIN permissions ON permissions.id = role_permissions.permission_id
                WHERE role_permissions.role_id = ?";

        return array_column(Database::fetchAll($sql, [$roleId]), 'slug');
    }

    public static function withUserCount(): array
    {
        $sql = "SELECT roles.*,
                       (SELECT COUNT(*) FROM users WHERE users.role_id = roles.id AND users.deleted_at IS NULL) AS user_count,
                       (SELECT COUNT(*) FROM role_permissions WHERE role_permissions.role_id = roles.id) AS permission_count
                FROM roles
                ORDER BY roles.is_system DESC, roles.name ASC";

        return Database::fetchAll($sql);
    }

    public static function userCount(int $roleId): int
    {
        return (int) (Database::fetch(
            'SELECT COUNT(*) AS total FROM users WHERE role_id = ? AND deleted_at IS NULL',
            [$roleId]
        )['total'] ?? 0);
    }

    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        Database::execute('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);

        foreach ($permissionIds as $permissionId) {
            Database::execute(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$roleId, (int) $permissionId]
            );
        }
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = self::slugify($base);
        $original = $slug;
        $i = 2;

        while (self::findBySlug($slug) !== null) {
            $slug = $original . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-') ?: 'role';
    }
}
