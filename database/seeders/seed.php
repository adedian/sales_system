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
                || str_starts_with($slug, 'product.')
                || str_starts_with($slug, 'prelim.')
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
            'prelim.view', 'prelim.create', 'prelim.edit', 'prelim.send',
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
    // Phase A (Leads revision) — roster Sales nyata dari referensi spreadsheet.
    ['username' => 'charles', 'name' => 'Charles', 'email' => 'charles@internal.local', 'role' => 'sales'],
    ['username' => 'ronny', 'name' => 'Pak Ronny', 'email' => 'ronny@internal.local', 'role' => 'sales'],
    ['username' => 'vega', 'name' => 'Vega', 'email' => 'vega@internal.local', 'role' => 'sales'],
    ['username' => 'victor', 'name' => 'Victor', 'email' => 'victor@internal.local', 'role' => 'sales'],
    ['username' => 'fita', 'name' => 'Fita', 'email' => 'fita@internal.local', 'role' => 'sales'],
    ['username' => 'vicky', 'name' => 'Vicky', 'email' => 'vicky@internal.local', 'role' => 'sales'],
    // Phase B (Antrian revision) — roster Estimator/Surveyor baru dari referensi spreadsheet.
    ['username' => 'rika', 'name' => 'Rika', 'email' => 'rika@internal.local', 'role' => 'sales'],
    ['username' => 'naufal', 'name' => 'Naufal', 'email' => 'naufal@internal.local', 'role' => 'sales'],
    ['username' => 'ali', 'name' => 'Ali', 'email' => 'ali@internal.local', 'role' => 'sales'],
    ['username' => 'rian', 'name' => 'Rian', 'email' => 'rian@internal.local', 'role' => 'sales'],
    ['username' => 'tio', 'name' => 'Tio', 'email' => 'tio@internal.local', 'role' => 'sales'],
    ['username' => 'magang', 'name' => 'Magang', 'email' => 'magang@internal.local', 'role' => 'sales'],
    // Revisi Sub-Fase 1 (Engineer/Sales Engineer/Direktur) — roster baru dari dokumen requirement.
    ['username' => 'sandi', 'name' => 'Sandi', 'email' => 'sandi@internal.local', 'role' => 'sales'],
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
 * 3b) Estimator/Surveyor capability flags (Phase B) — plain attribute
 *     flags on `users`, independent of role_id (see app/Models/User.php
 *     activeEstimators()/activeSurveyors()). Idempotent UPDATE, safe to
 *     re-run; a name can hold both flags (e.g. Victor is Sales + both).
 * ---------------------------------------------------------------- */
$estimatorUsernames = ['victor', 'rika', 'fita', 'magang'];
$surveyorUsernames = ['victor', 'rika', 'vega', 'naufal', 'ali', 'rian', 'tio'];

foreach ($estimatorUsernames as $username) {
    Database::execute('UPDATE users SET is_estimator = 1 WHERE username = ?', [$username]);
}
foreach ($surveyorUsernames as $username) {
    Database::execute('UPDATE users SET is_surveyor = 1 WHERE username = ?', [$username]);
}
echo "  = estimator/surveyor flags applied.\n";

/* ------------------------------------------------------------------
 * 3c) Engineer/Sales Engineer/Direktur capability flags (Revisi
 *     Sub-Fase 1) — same pattern as 3b above. A name can hold only one
 *     of is_engineer/is_sales_engineer in practice per the reference
 *     roster, but the columns are independent (not mutually exclusive).
 * ---------------------------------------------------------------- */
$engineerUsernames = ['sandi', 'naufal', 'rian'];
$salesEngineerUsernames = ['fita', 'rika'];
$directorUsernames = ['ronny'];

foreach ($engineerUsernames as $username) {
    Database::execute('UPDATE users SET is_engineer = 1 WHERE username = ?', [$username]);
}
foreach ($salesEngineerUsernames as $username) {
    Database::execute('UPDATE users SET is_sales_engineer = 1 WHERE username = ?', [$username]);
}
foreach ($directorUsernames as $username) {
    Database::execute('UPDATE users SET is_director = 1 WHERE username = ?', [$username]);
}
echo "  = engineer/sales-engineer/director flags applied.\n";

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
    'lead_types' => [
        ['code' => 'industrial', 'name' => 'Industrial', 'color' => null, 'is_system' => 0],
        ['code' => 'commercial', 'name' => 'Commercial', 'color' => null, 'is_system' => 0],
        ['code' => 'residential', 'name' => 'Residential', 'color' => null, 'is_system' => 0],
    ],
    'lead_systems' => [
        ['code' => 'on_grid', 'name' => 'On-Grid', 'color' => null, 'is_system' => 0],
        ['code' => 'off_grid', 'name' => 'Off-Grid', 'color' => null, 'is_system' => 0],
        ['code' => 'hybrid', 'name' => 'Hybrid', 'color' => null, 'is_system' => 0],
    ],
    'funding_sources' => [
        ['code' => 'hme', 'name' => 'HME', 'color' => null, 'is_system' => 0],
        ['code' => 'hijau', 'name' => 'Hijau', 'color' => null, 'is_system' => 0],
        ['code' => 'modena', 'name' => 'Modena', 'color' => null, 'is_system' => 0],
        ['code' => 'iforte', 'name' => 'Iforte', 'color' => null, 'is_system' => 0],
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
    'survey_statuses' => [
        ['code' => 'prelim', 'name' => 'Prelim', 'color' => 'muted', 'is_system' => 1],
        ['code' => 'sudah_survey', 'name' => 'Sudah survey', 'color' => 'emerald', 'is_system' => 1],
        ['code' => 'sudah_survey_tapi_prelim', 'name' => 'Sudah survey tapi prelim', 'color' => 'amber', 'is_system' => 1],
    ],
    'queue_stages' => [
        ['code' => 'urgent', 'name' => 'Urgent', 'color' => 'danger', 'is_system' => 1],
        ['code' => 'medium', 'name' => 'Medium', 'color' => 'primary', 'is_system' => 1],
        ['code' => 'slow', 'name' => 'Slow', 'color' => 'muted', 'is_system' => 1],
        ['code' => 'done_proposal', 'name' => 'Done Proposal', 'color' => 'emerald', 'is_system' => 1],
        ['code' => 'hold', 'name' => 'Hold', 'color' => 'muted', 'is_system' => 1],
        ['code' => 'revisi', 'name' => 'Revisi', 'color' => 'danger', 'is_system' => 1],
        ['code' => 'masuk_procurment', 'name' => 'Masuk Procurment', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'approval', 'name' => 'Approval', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'review', 'name' => 'Review', 'color' => 'amber', 'is_system' => 1],
        ['code' => 'proses', 'name' => 'Proses', 'color' => 'indigo', 'is_system' => 1],
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
        ['code' => 'pending_validation', 'name' => 'Menunggu Validasi Direktur', 'color' => 'amber', 'is_system' => 1],
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
    'prelim_statuses' => [
        ['code' => 'draft', 'name' => 'Draft', 'color' => 'muted', 'is_system' => 1],
        ['code' => 'ready_to_send', 'name' => 'Siap Dikirim', 'color' => 'primary', 'is_system' => 1],
        ['code' => 'sent', 'name' => 'Terkirim ke Client', 'color' => 'indigo', 'is_system' => 1],
        ['code' => 'client_revision', 'name' => 'Client Minta Revisi', 'color' => 'danger', 'is_system' => 1],
        ['code' => 'approved', 'name' => 'Disetujui Client (ACC)', 'color' => 'emerald', 'is_system' => 1],
    ],
    'units' => [
        ['code' => 'pcs', 'name' => 'Pcs', 'color' => null, 'is_system' => 0],
        ['code' => 'unit', 'name' => 'Unit', 'color' => null, 'is_system' => 0],
        ['code' => 'meter', 'name' => 'Meter', 'color' => null, 'is_system' => 0],
        ['code' => 'roll', 'name' => 'Roll', 'color' => null, 'is_system' => 0],
        ['code' => 'set', 'name' => 'Set', 'color' => null, 'is_system' => 0],
        ['code' => 'paket', 'name' => 'Paket', 'color' => null, 'is_system' => 0],
    ],
    'product_categories' => [
        ['code' => 'jaringan', 'name' => 'Jaringan', 'color' => null, 'is_system' => 0],
        ['code' => 'cctv', 'name' => 'CCTV', 'color' => null, 'is_system' => 0],
        ['code' => 'access_control', 'name' => 'Access Control', 'color' => null, 'is_system' => 0],
        ['code' => 'jasa', 'name' => 'Jasa / Instalasi', 'color' => null, 'is_system' => 0],
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

/* ------------------------------------------------------------------
 * 5) System Settings (Phase 14) — company profile, document numbering
 *    prefixes, SLA threshold, and per-module notification toggles. All
 *    editable afterward via /settings (see SettingController).
 * ---------------------------------------------------------------- */
$settingsSeed = [
    'company_name' => 'Nama Perusahaan Anda',
    'company_address' => '',
    'company_phone' => '',
    'company_email' => '',
    'numbering_lead_prefix' => 'LD',
    'numbering_engineer_prefix' => 'EA',
    'numbering_procurement_prefix' => 'PR',
    'numbering_proposal_prefix' => 'PRO',
    'sla_lead_aging_days' => '7',
    'notify_lead' => '1',
    'notify_prelim' => '1',
    'notify_proposal' => '1',
    'notify_engineer' => '1',
    'notify_procurement' => '1',
];

foreach ($settingsSeed as $key => $value) {
    $exists = Database::fetch('SELECT id FROM settings WHERE `key` = ?', [$key]);

    if ($exists) {
        continue;
    }

    Database::execute(
        'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, NOW())',
        [$key, $value]
    );
    echo "  + setting created: {$key}\n";
}

echo "\nSeeding selesai.\n";
