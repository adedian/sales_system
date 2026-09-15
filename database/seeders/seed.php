<?php

/**
 * Idempotent database seeder for Phase 1.
 * Usage: php database/seeders/seed.php
 */

use App\Core\Database;
use App\Core\Env;

require_once __DIR__ . '/../../vendor/autoload.php';
Env::load(__DIR__ . '/../../.env');

$permissionGroups = require __DIR__ . '/../../app/Config/permissions.php';

echo "== Sistem Internal Sales — Database Seeder ==\n";

$pdo = Database::connection();

/* ------------------------------------------------------------------
 * 1) Permissions
 * ---------------------------------------------------------------- */
$permissionIds = [];

foreach ($permissionGroups as $module => $permissions) {
    foreach ($permissions as $slug => $description) {
        $existing = Database::fetch('SELECT id FROM permissions WHERE slug = ?', [$slug]);

        if ($existing) {
            $permissionIds[$slug] = (int) $existing['id'];
            continue;
        }

        $id = Database::insertGetId(
            'INSERT INTO permissions (slug, module, description, created_at) VALUES (?, ?, ?, NOW())',
            [$slug, $module, $description]
        );
        $permissionIds[$slug] = $id;
        echo "  + permission created: {$slug}\n";
    }
}

/* ------------------------------------------------------------------
 * 2) Roles + role_permissions mapping
 * ---------------------------------------------------------------- */
$allSlugs = array_keys($permissionIds);

$rolesDefinition = [
    'super-admin' => [
        'name' => 'Super Admin',
        'description' => 'Akses penuh ke seluruh sistem',
        'permissions' => $allSlugs,
    ],
    'admin-sales' => [
        'name' => 'Admin Sales',
        'description' => 'Mengelola lead, antrian, follow up, dan user',
        'permissions' => array_values(array_filter($allSlugs, function ($slug) {
            return ((str_starts_with($slug, 'lead.') && $slug !== 'lead.delete')
                || str_starts_with($slug, 'queue.')
                || str_starts_with($slug, 'engineer.')
                || str_starts_with($slug, 'procurement.')
                || str_starts_with($slug, 'vendor.')
                || str_starts_with($slug, 'proposal.')
                || str_starts_with($slug, 'followup.')
                || $slug === 'report.view'
                || $slug === 'user.manage'
                || $slug === 'master_data.manage'
                || $slug === 'dashboard.view');
        })),
    ],
    'sales' => [
        'name' => 'Sales',
        'description' => 'Menangani lead dan follow up pelanggan',
        'permissions' => [
            'dashboard.view', 'lead.view', 'lead.create', 'lead.edit', 'queue.view',
            'engineer.view', 'procurement.view',
            'proposal.view', 'proposal.create', 'proposal.edit', 'proposal.send',
            'followup.view', 'followup.create', 'followup.edit',
        ],
    ],
    'engineer-sales' => [
        'name' => 'Engineer Sales',
        'description' => 'Menangani analisa teknis lead yang ditugaskan',
        'permissions' => ['dashboard.view', 'engineer.view', 'procurement.view', 'lead.view'],
    ],
    'procurement' => [
        'name' => 'Procurement',
        'description' => 'Mencari vendor, quotation, dan menentukan harga beli',
        'permissions' => ['dashboard.view', 'procurement.view', 'lead.view'],
    ],
    'manager' => [
        'name' => 'Manager/Supervisor',
        'description' => 'Memantau seluruh modul dan laporan',
        'permissions' => array_values(array_filter($allSlugs, function ($slug) {
            return str_ends_with($slug, '.view') || $slug === 'report.export' || $slug === 'dashboard.view' || $slug === 'proposal.approve';
        })),
    ],
];

$roleIds = [];

foreach ($rolesDefinition as $slug => $definition) {
    $existing = Database::fetch('SELECT id FROM roles WHERE slug = ?', [$slug]);

    if ($existing) {
        $roleIds[$slug] = (int) $existing['id'];
    } else {
        $roleIds[$slug] = Database::insertGetId(
            'INSERT INTO roles (name, slug, description, is_system, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())',
            [$definition['name'], $slug, $definition['description']]
        );
        echo "  + role created: {$definition['name']}\n";
    }

    foreach ($definition['permissions'] as $permSlug) {
        if (!isset($permissionIds[$permSlug])) {
            continue;
        }

        $exists = Database::fetch(
            'SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$roleIds[$slug], $permissionIds[$permSlug]]
        );

        if (!$exists) {
            Database::execute(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$roleIds[$slug], $permissionIds[$permSlug]]
            );
        }
    }
}

/* ------------------------------------------------------------------
 * 3) Satu akun demo per role (Super Admin, Admin Sales, Sales,
 *    Engineer Sales, Procurement, Manager) — password default sama
 *    untuk semua, tidak dipaksa ganti saat login pertama.
 * ---------------------------------------------------------------- */
$defaultPassword = 'ChangeMe@123';

$demoUsers = [
    ['username' => 'superadmin', 'name' => 'Super Administrator', 'email' => 'superadmin@internal.local', 'role' => 'super-admin'],
    ['username' => 'adminsales', 'name' => 'Admin Sales', 'email' => 'adminsales@internal.local', 'role' => 'admin-sales'],
    ['username' => 'sales1', 'name' => 'Sales Satu', 'email' => 'sales1@internal.local', 'role' => 'sales'],
    ['username' => 'engineer1', 'name' => 'Engineer Satu', 'email' => 'engineer1@internal.local', 'role' => 'engineer-sales'],
    ['username' => 'procurement1', 'name' => 'Procurement Satu', 'email' => 'procurement1@internal.local', 'role' => 'procurement'],
    ['username' => 'manager1', 'name' => 'Manager Satu', 'email' => 'manager1@internal.local', 'role' => 'manager'],
];

$createdUsers = [];

foreach ($demoUsers as $demo) {
    $existing = Database::fetch('SELECT id FROM users WHERE username = ?', [$demo['username']]);

    if ($existing) {
        echo "  = user '{$demo['username']}' already exists, skipped.\n";
        continue;
    }

    Database::insertGetId(
        'INSERT INTO users (name, email, username, password, role_id, is_active, must_change_password, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 1, 0, NOW(), NOW())',
        [
            $demo['name'],
            $demo['email'],
            $demo['username'],
            password_hash($defaultPassword, PASSWORD_DEFAULT),
            $roleIds[$demo['role']],
        ]
    );

    $createdUsers[] = $demo;
    echo "  + user created: {$demo['username']} ({$demo['role']})\n";
}

if ($createdUsers) {
    echo "\n  Akun baru dibuat (password default sama untuk semua):\n";
    foreach ($createdUsers as $u) {
        echo "      {$u['username']} / {$defaultPassword}  — {$u['role']}\n";
    }
}

/* ------------------------------------------------------------------
 * 4) Master Data (Phase 4) — satu tabel per tipe, semua kolom seragam.
 *    is_system => code dipakai sebagai nilai ENUM tetap di modul lain,
 *    tidak boleh dihapus/diganti code-nya (lihat app/Config/master_data.php).
 * ---------------------------------------------------------------- */
$masterDataSeed = [
    'lead_sources' => [
        ['code' => 'website', 'name' => 'Website', 'color' => null, 'is_system' => 0],
        ['code' => 'referral', 'name' => 'Referral', 'color' => null, 'is_system' => 0],
        ['code' => 'cold_call', 'name' => 'Cold Call', 'color' => null, 'is_system' => 0],
        ['code' => 'event', 'name' => 'Pameran / Event', 'color' => null, 'is_system' => 0],
        ['code' => 'social_media', 'name' => 'Media Sosial', 'color' => null, 'is_system' => 0],
    ],
    'lead_categories' => [
        ['code' => 'new_installation', 'name' => 'Instalasi Baru', 'color' => null, 'is_system' => 0],
        ['code' => 'maintenance', 'name' => 'Maintenance', 'color' => null, 'is_system' => 0],
        ['code' => 'upgrade', 'name' => 'Upgrade / Renovasi', 'color' => null, 'is_system' => 0],
        ['code' => 'consultation', 'name' => 'Konsultasi', 'color' => null, 'is_system' => 0],
    ],
    'lead_statuses' => [
        ['code' => 'new', 'name' => 'Baru', 'color' => 'primary', 'is_system' => 1],
        ['code' => 'in_queue', 'name' => 'Masuk Antrian', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'follow_up', 'name' => 'Follow Up', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'engineering', 'name' => 'Analisa Teknis', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'procurement', 'name' => 'Procurement', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'pricing_ready', 'name' => 'Harga Siap', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'proposal', 'name' => 'Proposal', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'won', 'name' => 'Menang (Won)', 'color' => 'emerald', 'is_system' => 1],
        ['code' => 'lost', 'name' => 'Kalah (Lost)', 'color' => 'danger', 'is_system' => 1],
    ],
    'priorities' => [
        ['code' => 'low', 'name' => 'Rendah', 'color' => 'muted', 'is_system' => 1],
        ['code' => 'medium', 'name' => 'Sedang', 'color' => 'primary', 'is_system' => 1],
        ['code' => 'high', 'name' => 'Tinggi', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'urgent', 'name' => 'Mendesak', 'color' => 'danger', 'is_system' => 1],
    ],
    'need_types' => [
        ['code' => 'survey', 'name' => 'Survey', 'color' => null, 'is_system' => 0],
        ['code' => 'design', 'name' => 'Desain', 'color' => null, 'is_system' => 0],
        ['code' => 'calculation', 'name' => 'Perhitungan / Kalkulasi', 'color' => null, 'is_system' => 0],
        ['code' => 'installation', 'name' => 'Instalasi', 'color' => null, 'is_system' => 0],
        ['code' => 'consultation', 'name' => 'Konsultasi', 'color' => null, 'is_system' => 0],
    ],
    'queue_statuses' => [
        ['code' => 'new', 'name' => 'Baru', 'color' => 'primary', 'is_system' => 1],
        ['code' => 'waiting_followup', 'name' => 'Menunggu Follow Up', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'in_progress', 'name' => 'Sedang Diproses', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'waiting_engineer', 'name' => 'Menunggu Engineer', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'done', 'name' => 'Selesai', 'color' => 'emerald', 'is_system' => 1],
        ['code' => 'cancelled', 'name' => 'Dibatalkan', 'color' => 'muted', 'is_system' => 1],
    ],
    'engineer_statuses' => [
        ['code' => 'pending', 'name' => 'Assignment Baru', 'color' => 'muted', 'is_system' => 1],
        ['code' => 'accepted', 'name' => 'Diterima', 'color' => 'primary', 'is_system' => 1],
        ['code' => 'in_progress', 'name' => 'Sedang Dikerjakan', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'waiting', 'name' => 'Menunggu', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'completed', 'name' => 'Selesai', 'color' => 'emerald', 'is_system' => 1],
        ['code' => 'rejected', 'name' => 'Ditolak', 'color' => 'danger', 'is_system' => 1],
        ['code' => 'returned', 'name' => 'Dikembalikan ke Sales', 'color' => 'muted', 'is_system' => 1],
    ],
    'procurement_statuses' => [
        ['code' => 'waiting', 'name' => 'Request Baru', 'color' => 'muted', 'is_system' => 1],
        ['code' => 'in_progress', 'name' => 'Sedang Diproses', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'quotation_requested', 'name' => 'Menunggu Quotation', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'pricing_completed', 'name' => 'Selesai', 'color' => 'emerald', 'is_system' => 1],
        ['code' => 'need_revision', 'name' => 'Butuh Revisi', 'color' => 'danger', 'is_system' => 1],
        ['code' => 'cancelled', 'name' => 'Dibatalkan', 'color' => 'muted', 'is_system' => 1],
    ],
    'proposal_statuses' => [
        ['code' => 'draft', 'name' => 'Draft', 'color' => 'muted', 'is_system' => 1],
        ['code' => 'internal_review', 'name' => 'Review Internal', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'revision', 'name' => 'Perlu Revisi', 'color' => 'danger', 'is_system' => 1],
        ['code' => 'approved', 'name' => 'Disetujui', 'color' => 'primary', 'is_system' => 1],
        ['code' => 'sent', 'name' => 'Terkirim ke Customer', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'viewed', 'name' => 'Dilihat Customer', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'negotiation', 'name' => 'Negosiasi', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'accepted', 'name' => 'Diterima', 'color' => 'emerald', 'is_system' => 1],
        ['code' => 'rejected', 'name' => 'Ditolak', 'color' => 'danger', 'is_system' => 1],
        ['code' => 'expired', 'name' => 'Kadaluarsa', 'color' => 'muted', 'is_system' => 1],
    ],
];

foreach ($masterDataSeed as $table => $rows) {
    $order = 0;
    foreach ($rows as $row) {
        $order += 10;
        $exists = Database::fetch("SELECT id FROM {$table} WHERE code = ?", [$row['code']]);

        if ($exists) {
            continue;
        }

        Database::insertGetId(
            "INSERT INTO {$table} (code, name, color, sort_order, is_active, is_system, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, NOW(), NOW())",
            [$row['code'], $row['name'], $row['color'], $order, $row['is_system']]
        );
        echo "  + master data [{$table}]: {$row['name']}\n";
    }
}

echo "\nSeeding selesai.\n";
