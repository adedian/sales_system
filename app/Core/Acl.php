<?php

namespace App\Core;

use App\Models\Role;

/**
 * Otorisasi berbasis role. Satu pengguna boleh memiliki BANYAK role
 * (tabel user_roles); izin yang berlaku adalah GABUNGAN izin semua role itu.
 * Memiliki dua role tidak pernah otomatis menjadi Super Admin.
 */
class Acl
{
    /**
     * Role "pengawas": pemegangnya melihat data lintas pemilik, sehingga
     * pembatasan "hanya data milik sendiri" (Sales, Procurement, Engineer)
     * tidak diterapkan kepadanya.
     */
    public const BROAD_ROLES = ['super-admin', 'admin-sales', 'manager', 'direktur'];

    /** Role pengawas untuk data Sales: Direktur TIDAK termasuk (Sales+Direktur tetap dibatasi ke lead miliknya). */
    public const SALES_OVERSIGHT_ROLES = ['super-admin', 'admin-sales', 'manager'];

    /**
     * Satu-satunya role yang boleh MENGELOLA Lead (halaman /leads, /api/leads, pembuatan,
     * perubahan, penghapusan, perubahan status). Role lain hanya melihat ringkasan Lead
     * sebagai konteks di halaman tugasnya. Izin (lead.*) tetap berlaku di atas ini.
     */
    public const LEAD_ROLES = ['super-admin', 'admin-sales', 'manager', 'sales'];

    /**
     * Role yang boleh menyentuh modul Survey. Sales Engineer sengaja TIDAK ada:
     * ia tidak mengajukan, menerima, atau mengelola Survey, apa pun izin yang
     * tercatat pada role-nya.
     */
    public const SURVEY_ROLES = ['super-admin', 'admin-sales', 'manager', 'sales', 'surveyor', 'engineer'];

    /**
     * Cached for the lifetime of the current request only (static, not
     * session). Role permissions can be edited at runtime via Role
     * Management, so caching across requests would let a logged-in user
     * keep acting on a stale permission set until they log in again.
     *
     * @var array<int, string[]>
     */
    private static array $requestCache = [];

    public static function can(string $permission): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        return in_array($permission, self::permissionsForUser($user), true);
    }

    /** True bila pengguna memiliki role tersebut (salah satu dari banyak role). */
    public static function hasRole(string $roleSlug): bool
    {
        return in_array($roleSlug, self::roleSlugs(), true);
    }

    public static function hasAnyRole(string ...$roleSlugs): bool
    {
        return array_intersect($roleSlugs, self::roleSlugs()) !== [];
    }

    /**
     * Pembatasan "hanya data milik sendiri" untuk role tertentu: berlaku bila
     * pengguna memiliki role itu DAN tidak memiliki role pengawas.
     */
    public static function scoped(string $roleSlug): bool
    {
        $oversight = $roleSlug === 'sales' ? self::SALES_OVERSIGHT_ROLES : self::BROAD_ROLES;

        return self::hasRole($roleSlug) && !self::hasAnyRole(...$oversight);
    }

    /** Boleh mengelola Lead (menu Lead Management, /leads, /api/leads). */
    public static function managesLeads(): bool
    {
        return self::hasAnyRole(...self::LEAD_ROLES);
    }

    /** Boleh menyentuh modul Survey (lihat SURVEY_ROLES). */
    public static function touchesSurvey(): bool
    {
        return self::hasAnyRole(...self::SURVEY_ROLES);
    }

    /**
     * Pengguna yang hanya boleh mengakses lead yang ia TANGANI (survey/penugasan engineer):
     * bukan role pengawas, bukan Sales (dibatasi lewat kepemilikan lead), dan bukan
     * Engineer/Procurement (yang sejak awal melihat seluruh lead). Contoh: Surveyor, Sales Engineer.
     */
    public static function requiresInvolvement(): bool
    {
        // Sales dibatasi lewat kepemilikan lead. Sales+Direktur tetap Sales: scoped('sales') yang berlaku.
        return Auth::user() !== null && !self::hasAnyRole(...self::BROAD_ROLES, ...['sales', 'engineer', 'procurement']);
    }

    /** @return string[] */
    public static function roleSlugs(?array $user = null): array
    {
        $user ??= Auth::user();
        if ($user === null) {
            return [];
        }

        $slugs = $user['role_slugs'] ?? (isset($user['role_slug']) ? [$user['role_slug']] : []);

        return array_values((array) $slugs);
    }

    /**
     * @return string[] gabungan izin dari seluruh role pengguna
     */
    public static function permissionsForUser(array $user): array
    {
        $roleIds = array_map('intval', (array) ($user['role_ids'] ?? [$user['role_id'] ?? 0]));
        $all = [];
        foreach ($roleIds as $roleId) {
            if ($roleId > 0) {
                $all = array_merge($all, self::permissions($roleId));
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * @return string[]
     */
    public static function permissions(int $roleId): array
    {
        if (!isset(self::$requestCache[$roleId])) {
            self::$requestCache[$roleId] = Role::permissionSlugs($roleId);
        }

        return self::$requestCache[$roleId];
    }

    public static function forgetCache(): void
    {
        self::$requestCache = [];
    }
}
