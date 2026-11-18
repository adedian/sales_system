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
            'SELECT users.* FROM users
             WHERE users.is_active = 1 AND users.deleted_at IS NULL
               AND EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = users.id AND ur.role_id = ?)
             ORDER BY users.name ASC',
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
     * Surveyor/Engineer/Sales Engineer/Direktur/Sales adalah ROLE (tabel
     * user_roles). Kolom is_* di tabel users hanyalah cache turunan yang
     * diselaraskan oleh syncRoles()/syncFlags() — jangan diisi manual.
     */
    public static function activeSurveyors(): array
    {
        return self::activeByFlag('is_surveyor');
    }

    /**
     * Calon petugas Survey (pilihan di form pengajuan Survey dan validasi backend):
     * pemegang role $roleSlug ('surveyor' atau 'engineer') yang aktif dan TIDAK
     * memiliki role Sales Engineer. Sales Engineer tidak pernah menjadi petugas
     * Survey, baik sebagai Surveyor maupun personel pendukung.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function surveyStaff(string $roleSlug): array
    {
        if (!in_array($roleSlug, ['surveyor', 'engineer'], true)) {
            throw new \InvalidArgumentException("Role petugas Survey tidak dikenal: {$roleSlug}");
        }

        return Database::fetchAll(
            "SELECT users.* FROM users
             WHERE users.is_active = 1 AND users.deleted_at IS NULL
               AND EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = users.id AND r.slug = ?)
               AND NOT EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = users.id AND r.slug = 'sales-engineer')
             ORDER BY users.name ASC",
            [$roleSlug]
        );
    }

    /** Pengguna ini boleh dipilih sebagai petugas Survey dengan role $roleSlug ('surveyor'/'engineer')? */
    public static function isSurveyStaff(int $userId, string $roleSlug): bool
    {
        foreach (self::surveyStaff($roleSlug) as $row) {
            if ((int) $row['id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    /** Pengguna memiliki penugasan Survey sebagai personel pendukung (menentukan tampilnya menu Dukungan Survey). */
    public static function hasSurveySupportAssignment(int $userId): bool
    {
        return Database::fetch(
            'SELECT sp.id FROM survey_participants sp
             INNER JOIN surveys s ON s.id = sp.survey_id AND s.deleted_at IS NULL
             WHERE sp.user_id = ? LIMIT 1',
            [$userId]
        ) !== null;
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

    /**
     * Redesign UI/UX Enterprise — `is_sales` is the explicit "operationally
     * Sales" flag, independent of the `sales` login role that Surveyor/
     * Engineer/Sales Engineer staff also share as their generic login role.
     * Use this (not activeByRole(salesRoleId)) anywhere that needs the real
     * Sales roster — dropdowns, dashboard performance, assignment validation.
     */
    public static function activeSales(): array
    {
        return self::activeByFlag('is_sales');
    }

    private static function activeByFlag(string $flagColumn): array
    {
        $allowed = ['is_surveyor', 'is_engineer', 'is_sales_engineer', 'is_director', 'is_sales'];
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

        $user = Database::fetch($sql, [$id]);
        if ($user === null) {
            return null;
        }

        // Seluruh role pengguna (multi-role). Role utama (users.role_id) ikut sebagai
        // pengaman bila relasi user_roles kosong.
        $roles = self::rolesOf($id);
        if ($roles === [] && !empty($user['role_id'])) {
            $roles = [['id' => (int) $user['role_id'], 'slug' => (string) $user['role_slug'], 'name' => (string) $user['role_name']]];
        }
        $user['role_ids'] = array_column($roles, 'id');
        $user['role_slugs'] = array_column($roles, 'slug');
        $user['role_names'] = array_column($roles, 'name');

        return $user;
    }

    /** @return array<int,array{id:int,slug:string,name:string}> */
    public static function rolesOf(int $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT roles.id, roles.slug, roles.name FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE user_roles.user_id = ? ORDER BY roles.id ASC',
            [$userId]
        );

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'slug' => $r['slug'], 'name' => $r['name']], $rows);
    }

    /** Pemetaan kolom flag turunan -> slug role. */
    private const FLAG_ROLES = [
        'is_sales' => 'sales',
        'is_surveyor' => 'surveyor',
        'is_engineer' => 'engineer',
        'is_sales_engineer' => 'sales-engineer',
        'is_director' => 'direktur',
    ];

    /**
     * Mengganti seluruh role pengguna. Role utama (users.role_id) = role
     * pertama di $roleIds, lalu flag turunan disinkronkan. Pemanggil wajib
     * memvalidasi $roleIds (role ada, tidak menambah Super Admin tanpa hak).
     *
     * @param int[] $roleIds
     */
    public static function syncRoles(int $userId, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            throw new \InvalidArgumentException('Pengguna harus memiliki minimal satu role.');
        }

        Database::transaction(function () use ($userId, $roleIds): void {
            Database::execute('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
            foreach ($roleIds as $roleId) {
                Database::execute('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
            }
            Database::execute('UPDATE users SET role_id = ?, updated_at = ? WHERE id = ?', [$roleIds[0], date('Y-m-d H:i:s'), $userId]);
            self::syncFlags($userId);
        });
    }

    /** Menyelaraskan flag is_* dengan isi user_roles (is_estimator selalu 0). */
    public static function syncFlags(int $userId): void
    {
        $slugs = array_column(self::rolesOf($userId), 'slug');
        $sets = ['is_estimator = 0'];
        foreach (self::FLAG_ROLES as $column => $slug) {
            $sets[] = $column . ' = ' . (in_array($slug, $slugs, true) ? 1 : 0);
        }
        Database::execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', [$userId]);
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
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = users.id AND ur.role_id = ?)';
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

        foreach ($rows as &$row) {
            $row['role_names'] = array_column(self::rolesOf((int) $row['id']), 'name');
        }
        unset($row);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }
}
