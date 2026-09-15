<?php

/**
 * Registry of Master Data types served by the single generic
 * MasterDataController + Views/master-data views. Every table listed here
 * MUST share the exact same column shape:
 *   id, code, name, description, color, sort_order, is_active, is_system,
 *   created_by, updated_by, created_at, updated_at
 *
 * `locked` (is_system rows) means: the row's `code` is relied on elsewhere
 * as a fixed ENUM value (see database/schema.sql "Lead pipeline" comment)
 * — those rows can't be renamed (code) or deleted, only re-labelled.
 *
 * Add a new master data type by adding one entry here (plus a migration
 * for its table) — no new controller or view is needed.
 */
return [
    'lead-sources' => [
        'table' => 'lead_sources',
        'label' => 'Sumber Lead',
        'singular' => 'Sumber Lead',
        'description' => 'Asal masuknya lead (Website, Referral, dsb). Bebas dikelola.',
        'has_color' => false,
        'icon' => 'bi-signpost-2',
    ],
    'lead-categories' => [
        'table' => 'lead_categories',
        'label' => 'Kategori Lead',
        'singular' => 'Kategori Lead',
        'description' => 'Klasifikasi kebutuhan lead. Bebas dikelola.',
        'has_color' => false,
        'icon' => 'bi-tags',
    ],
    'lead-statuses' => [
        'table' => 'lead_statuses',
        'label' => 'Status Lead',
        'singular' => 'Status Lead',
        'description' => 'Tahapan status lead. Code baris bawaan terkunci karena dipakai sebagai nilai tetap di modul Lead.',
        'has_color' => true,
        'icon' => 'bi-flag',
    ],
    'priorities' => [
        'table' => 'priorities',
        'label' => 'Prioritas',
        'singular' => 'Prioritas',
        'description' => 'Tingkat prioritas — dipakai bersama oleh Lead, Antrian, dan Engineer.',
        'has_color' => true,
        'icon' => 'bi-exclamation-diamond',
    ],
    'need-types' => [
        'table' => 'need_types',
        'label' => 'Jenis Kebutuhan',
        'singular' => 'Jenis Kebutuhan',
        'description' => 'Jenis pekerjaan/kebutuhan customer (survey, desain, dsb). Bebas dikelola.',
        'has_color' => false,
        'icon' => 'bi-clipboard-check',
    ],
    'queue-statuses' => [
        'table' => 'queue_statuses',
        'label' => 'Status Antrian',
        'singular' => 'Status Antrian',
        'description' => 'Status antrian sales. Code baris bawaan terkunci karena dipakai sebagai nilai tetap di modul Antrian.',
        'has_color' => true,
        'icon' => 'bi-list-ol',
    ],
    'engineer-statuses' => [
        'table' => 'engineer_statuses',
        'label' => 'Status Engineer',
        'singular' => 'Status Engineer',
        'description' => 'Status assignment engineer. Code baris bawaan terkunci karena dipakai sebagai nilai tetap di modul Engineer Sales.',
        'has_color' => true,
        'icon' => 'bi-tools',
    ],
    'procurement-statuses' => [
        'table' => 'procurement_statuses',
        'label' => 'Status Procurement',
        'singular' => 'Status Procurement',
        'description' => 'Status request procurement. Code baris bawaan terkunci karena dipakai sebagai nilai tetap di modul Procurement.',
        'has_color' => true,
        'icon' => 'bi-truck',
    ],
    'proposal-statuses' => [
        'table' => 'proposal_statuses',
        'label' => 'Status Proposal',
        'singular' => 'Status Proposal',
        'description' => 'Status dokumen proposal. Code baris bawaan terkunci karena dipakai sebagai nilai tetap di modul Proposal.',
        'has_color' => true,
        'icon' => 'bi-file-earmark-text',
    ],
];
