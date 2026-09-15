<?php

/**
 * Canonical permission list. This is the single source of truth used by the
 * database seeder and by any future permission-matrix UI (user.manage).
 *
 * Structure: module => [ 'slug' => 'human readable description' ]
 */
return [
    'dashboard' => [
        'dashboard.view' => 'Melihat dashboard',
    ],
    'lead' => [
        'lead.view' => 'Melihat data lead',
        'lead.create' => 'Membuat lead baru',
        'lead.edit' => 'Mengubah data lead',
        'lead.delete' => 'Menghapus data lead',
        'lead.assign' => 'Menugaskan lead ke sales/engineer',
    ],
    'queue' => [
        'queue.view' => 'Melihat antrian sales',
        'queue.manage' => 'Mengelola antrian sales',
    ],
    'engineer' => [
        'engineer.view' => 'Melihat assignment engineer',
        'engineer.manage' => 'Mengelola assignment engineer',
    ],
    'procurement' => [
        'procurement.view' => 'Melihat request procurement',
        'procurement.manage' => 'Mengelola request procurement',
    ],
    'vendor' => [
        'vendor.manage' => 'Mengelola data vendor',
    ],
    'proposal' => [
        'proposal.view' => 'Melihat proposal',
        'proposal.create' => 'Membuat proposal',
        'proposal.edit' => 'Mengubah proposal',
        'proposal.approve' => 'Menyetujui/menolak proposal (internal review)',
        'proposal.send' => 'Mengirim proposal ke customer',
    ],
    'followup' => [
        'followup.view' => 'Melihat follow up',
        'followup.create' => 'Membuat follow up',
        'followup.edit' => 'Mengubah follow up',
    ],
    'report' => [
        'report.view' => 'Melihat laporan',
        'report.export' => 'Mengekspor laporan',
    ],
    'user' => [
        'user.manage' => 'Mengelola user, role, dan permission',
    ],
    'master_data' => [
        'master_data.manage' => 'Mengelola master data (sumber, kategori, status, prioritas, dsb.)',
    ],
    'system' => [
        'system.manage' => 'Mengelola pengaturan sistem',
    ],
];
