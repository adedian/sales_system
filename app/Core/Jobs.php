<?php

namespace App\Core;

/**
 * Pemetaan "job" (tanggung jawab pekerjaan) pengguna.
 *
 * Job diturunkan dari role pengguna (tabel user_roles; satu pengguna boleh
 * punya banyak role). Job dipakai untuk menyusun menu dan memilih dashboard.
 * Otorisasi tetap divalidasi di backend (permission + kepemilikan data) —
 * job BUKAN pengganti permission.
 *
 * Tidak ada nama karyawan yang di-hard-code: semuanya dari relasi role.
 */
class Jobs
{
    public const LABELS = [
        'super_admin' => 'Super Admin',
        'admin_sales' => 'Admin Sales',
        'manager' => 'Manager',
        'sales' => 'Sales',
        'sales_engineer' => 'Sales Engineer',
        'engineer' => 'Engineer',
        'surveyor' => 'Surveyor',
        'procurement' => 'Procurement',
        'director' => 'Direktur',
    ];

    /** Slug role -> kunci job. */
    private const ROLE_JOBS = [
        'super-admin' => 'super_admin',
        'admin-sales' => 'admin_sales',
        'manager' => 'manager',
        'sales' => 'sales',
        'sales-engineer' => 'sales_engineer',
        'engineer' => 'engineer',
        'surveyor' => 'surveyor',
        'procurement' => 'procurement',
        'direktur' => 'director',
    ];

    /**
     * @return string[] kunci job milik pengguna (bisa lebih dari satu), dari tabel user_roles
     */
    public static function of(?array $user = null): array
    {
        $user ??= Auth::user();
        if ($user === null) {
            return [];
        }

        $jobs = [];
        foreach (Acl::roleSlugs($user) as $slug) {
            if (isset(self::ROLE_JOBS[$slug])) {
                $jobs[] = self::ROLE_JOBS[$slug];
            }
        }

        return array_values(array_unique($jobs));
    }
    public static function has(string ...$keys): bool
    {
        return array_intersect($keys, self::of()) !== [];
    }

    /**
     * Label ringkas untuk ditampilkan di bawah nama pengguna.
     */
    public static function label(?array $user = null): string
    {
        $labels = array_map(static fn (string $j): string => self::LABELS[$j] ?? $j, self::of($user));

        return $labels === [] ? '-' : implode(' · ', $labels);
    }
}
