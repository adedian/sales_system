<?php

/**
 * Struktur menu per job — satu sumber untuk sidebar desktop dan mobile.
 *
 * section: label grup di sidebar
 * jobs:    job yang melihat grup ini (lihat App\Core\Jobs). Pengguna dengan
 *          lebih dari satu job melihat gabungan grupnya.
 * item:    title, href, icon, match (potongan path untuk status "aktif"),
 *          perm (opsional; item disembunyikan bila pengguna tidak punya izin),
 *          query (opsional; aktif hanya bila query string sama),
 *          no_query (opsional; aktif hanya bila param ini tidak ada),
 *          exclude (opsional; path yang tidak dianggap aktif),
 *          children (opsional; submenu).
 *
 * Menu HANYA menyembunyikan tautan. Izin tetap divalidasi di backend pada
 * setiap route (middleware permission + pengecekan kepemilikan data).
 */
return [
    [
        'section' => 'Overview',
        'jobs' => ['*'],
        'items' => [
            ['title' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'bi-grid-1x2-fill', 'match' => '/dashboard'],
        ],
    ],

    // ------------------------------------------------------------------ SALES
    // Lead Management HANYA ada di kelompok ini (role lain tidak punya menu Lead).
    [
        'section' => 'Sales',
        'jobs' => ['sales', 'admin_sales'],
        'items' => [
            [
                'title' => 'Lead Management', 'icon' => 'bi-person-lines-fill', 'perm' => 'lead.view',
                'match' => '/leads',
                'children' => [
                    ['title' => 'Semua Lead Milik Saya', 'href' => '/leads', 'match' => '/leads', 'no_query' => ['status', 'simple_status', 'survey']],
                    ['title' => 'Lead Baru', 'href' => '/leads?status=new', 'match' => '/leads', 'query' => ['status' => 'new']],
                    ['title' => 'Lead Aktif', 'href' => '/leads?simple_status=proses', 'match' => '/leads', 'query' => ['simple_status' => 'proses']],
                    ['title' => 'Menunggu Tindak Lanjut', 'href' => '/leads?status=follow_up', 'match' => '/leads', 'query' => ['status' => 'follow_up']],
                    ['title' => 'Deal', 'href' => '/leads?simple_status=deal', 'match' => '/leads', 'query' => ['simple_status' => 'deal']],
                    ['title' => 'Cancel', 'href' => '/leads?simple_status=cancel', 'match' => '/leads', 'query' => ['simple_status' => 'cancel']],
                ],
            ],
            [
                'title' => 'Aktivitas Sales', 'icon' => 'bi-telephone-outbound', 'perm' => 'followup.view', 'match' => '/follow-ups',
                'children' => [
                    ['title' => 'Follow Up', 'href' => '/follow-ups', 'match' => '/follow-ups'],
                    ['title' => 'Riwayat Komunikasi', 'href' => '/follow-ups#riwayat', 'match' => '/follow-ups', 'never_active' => true],
                ],
            ],
            [
                'title' => 'Pengajuan Survey', 'icon' => 'bi-geo-alt', 'perm' => 'survey.view', 'match' => '/surveys',
                'children' => [
                    ['title' => 'Ajukan Survey', 'href' => '/leads?survey=none', 'match' => '/leads', 'query' => ['survey' => 'none'], 'perm' => 'survey.request'],
                    ['title' => 'Survey Berlangsung', 'href' => '/surveys?status=ongoing', 'match' => '/surveys', 'query' => ['status' => 'ongoing']],
                    ['title' => 'Hasil Survey', 'href' => '/surveys?status=completed', 'match' => '/surveys', 'query' => ['status' => 'completed']],
                    ['title' => 'Survey Report', 'href' => '/surveys?report_status=completed', 'match' => '/surveys', 'query' => ['report_status' => 'completed']],
                ],
            ],
            ['title' => 'Prelim', 'href' => '/prelims', 'icon' => 'bi-file-earmark-richtext', 'match' => '/prelims', 'perm' => 'prelim.view'],
            ['title' => 'Proposal', 'href' => '/proposals', 'icon' => 'bi-file-earmark-text', 'match' => '/proposals', 'no_query' => 'status', 'perm' => 'proposal.view'],
            ['title' => 'Negosiasi', 'href' => '/proposals?status=negotiation', 'icon' => 'bi-chat-left-dots', 'match' => '/proposals', 'query' => ['status' => 'negotiation'], 'perm' => 'proposal.view'],
            ['title' => 'Laporan Sales', 'href' => '/reports', 'icon' => 'bi-graph-up-arrow', 'match' => '/reports', 'perm' => 'report.view'],
        ],
    ],

    // --------------------------------------------------------- SALES ENGINEER
    // Tanpa menu Survey dan Lead Management: mulai bekerja setelah Survey + Survey Report selesai.
    [
        'section' => 'Sales Engineer',
        'jobs' => ['sales_engineer'],
        'items' => [
            [
                'title' => 'Tugas Teknis', 'icon' => 'bi-tools', 'perm' => 'engineer.view', 'match' => '/engineer',
                'children' => [
                    ['title' => 'Semua Tugas', 'href' => '/engineer', 'match' => '/engineer', 'no_query' => 'status'],
                    ['title' => 'Tugas Baru', 'href' => '/engineer?status=pending', 'match' => '/engineer', 'query' => ['status' => 'pending']],
                    ['title' => 'Sedang Dikerjakan', 'href' => '/engineer?status=in_progress', 'match' => '/engineer', 'query' => ['status' => 'in_progress']],
                    ['title' => 'Menunggu Review', 'href' => '/engineer?status=waiting', 'match' => '/engineer', 'query' => ['status' => 'waiting']],
                    ['title' => 'Selesai', 'href' => '/engineer?status=completed', 'match' => '/engineer', 'query' => ['status' => 'completed']],
                ],
            ],
            [
                'title' => 'Pembuatan Proposal', 'icon' => 'bi-file-earmark-text', 'perm' => 'proposal.view', 'match' => '/proposals',
                'children' => [
                    ['title' => 'Semua Proposal', 'href' => '/proposals', 'match' => '/proposals', 'no_query' => 'status'],
                    ['title' => 'Draft', 'href' => '/proposals?status=draft', 'match' => '/proposals', 'query' => ['status' => 'draft']],
                ],
            ],
            ['title' => 'Revisi Proposal', 'href' => '/proposals?status=revision', 'icon' => 'bi-arrow-repeat', 'match' => '/proposals', 'query' => ['status' => 'revision'], 'perm' => 'proposal.view'],
            ['title' => 'Data Referensi BoQ', 'href' => '/boq/referensi', 'icon' => 'bi-journal-text', 'match' => '/boq', 'perm' => 'boq.view'],
            ['title' => 'Riwayat Pekerjaan', 'href' => '/engineer?status=completed&include_closed=1', 'icon' => 'bi-clock-history', 'match' => '/engineer', 'query' => ['include_closed' => '1'], 'perm' => 'engineer.view'],
        ],
    ],

    // --------------------------------------------------------------- ENGINEER
    [
        'section' => 'Engineer',
        'jobs' => ['engineer'],
        'items' => [
            [
                'title' => 'Tugas Engineer', 'icon' => 'bi-tools', 'perm' => 'engineer.view', 'match' => '/engineer',
                'children' => [
                    ['title' => 'Semua Tugas', 'href' => '/engineer', 'match' => '/engineer', 'no_query' => 'status'],
                    ['title' => 'Tugas Baru', 'href' => '/engineer?status=pending', 'match' => '/engineer', 'query' => ['status' => 'pending']],
                    ['title' => 'Sedang Dikerjakan', 'href' => '/engineer?status=in_progress', 'match' => '/engineer', 'query' => ['status' => 'in_progress']],
                    ['title' => 'Menunggu Review', 'href' => '/engineer?status=waiting', 'match' => '/engineer', 'query' => ['status' => 'waiting']],
                    ['title' => 'Selesai', 'href' => '/engineer?status=completed', 'match' => '/engineer', 'query' => ['status' => 'completed']],
                ],
            ],
            // Hanya muncul bagi Engineer yang ditugaskan pada Survey tertentu (when = survey_support).
            [
                'title' => 'Dukungan Survey', 'icon' => 'bi-geo-alt', 'perm' => 'survey.view', 'when' => 'survey_support', 'match' => '/surveys',
                'children' => [
                    ['title' => 'Penugasan Lapangan', 'href' => '/surveys', 'match' => '/surveys', 'no_query' => ['status', 'report_status']],
                    ['title' => 'Jadwal Penugasan', 'href' => '/surveys?status=scheduled', 'match' => '/surveys', 'query' => ['status' => 'scheduled']],
                    ['title' => 'Hasil Pemeriksaan Teknis', 'href' => '/surveys?report_status=pending_review', 'match' => '/surveys', 'query' => ['report_status' => 'pending_review']],
                ],
            ],
            ['title' => 'Data Referensi BoQ', 'href' => '/boq/referensi', 'icon' => 'bi-journal-text', 'match' => '/boq', 'perm' => 'boq.view'],
            ['title' => 'Riwayat Pekerjaan', 'href' => '/engineer?status=completed&include_closed=1', 'icon' => 'bi-clock-history', 'match' => '/engineer', 'query' => ['include_closed' => '1'], 'perm' => 'engineer.view'],
        ],
    ],

    // --------------------------------------------------------------- SURVEYOR
    [
        'section' => 'Surveyor',
        'jobs' => ['surveyor'],
        'items' => [
            [
                'title' => 'Penugasan Survey', 'icon' => 'bi-geo-alt', 'perm' => 'survey.view', 'match' => '/surveys',
                'children' => [
                    ['title' => 'Survey Baru', 'href' => '/surveys?status=requested', 'match' => '/surveys', 'query' => ['status' => 'requested']],
                    ['title' => 'Jadwal Survey', 'href' => '/surveys?status=scheduled', 'match' => '/surveys', 'query' => ['status' => 'scheduled']],
                    ['title' => 'Survey Berlangsung', 'href' => '/surveys?status=in_progress', 'match' => '/surveys', 'query' => ['status' => 'in_progress']],
                    ['title' => 'Survey Selesai', 'href' => '/surveys?status=completed', 'match' => '/surveys', 'query' => ['status' => 'completed']],
                ],
            ],
            [
                'title' => 'Survey Report', 'icon' => 'bi-file-earmark-bar-graph', 'perm' => 'survey.view', 'match' => '/surveys',
                'children' => [
                    ['title' => 'Buat / Draft', 'href' => '/surveys?report_status=draft', 'match' => '/surveys', 'query' => ['report_status' => 'draft']],
                    ['title' => 'Menunggu Pemeriksaan', 'href' => '/surveys?report_status=pending_review', 'match' => '/surveys', 'query' => ['report_status' => 'pending_review']],
                    ['title' => 'Revisi', 'href' => '/surveys?report_status=revision', 'match' => '/surveys', 'query' => ['report_status' => 'revision']],
                    ['title' => 'Selesai', 'href' => '/surveys?report_status=completed', 'match' => '/surveys', 'query' => ['report_status' => 'completed']],
                ],
            ],
            ['title' => 'Riwayat Survey', 'href' => '/surveys', 'icon' => 'bi-clock-history', 'match' => '/surveys', 'no_query' => ['status', 'report_status'], 'perm' => 'survey.view'],
        ],
    ],

    // ------------------------------------------------------------ PROCUREMENT
    [
        'section' => 'Procurement',
        'jobs' => ['procurement', 'admin_sales'],
        'items' => [
            [
                'title' => 'Permintaan Harga', 'icon' => 'bi-truck', 'perm' => 'procurement.view', 'match' => '/procurement', 'exclude' => '/procurement/validation',
                'children' => [
                    ['title' => 'Semua Permintaan', 'href' => '/procurement', 'match' => '/procurement', 'exclude' => '/procurement/validation', 'no_query' => 'status'],
                    ['title' => 'Permintaan Baru', 'href' => '/procurement?status=waiting', 'match' => '/procurement', 'exclude' => '/procurement/validation', 'query' => ['status' => 'waiting']],
                    ['title' => 'Sedang Diproses', 'href' => '/procurement?status=in_progress', 'match' => '/procurement', 'exclude' => '/procurement/validation', 'query' => ['status' => 'in_progress']],
                    ['title' => 'Menunggu Validasi Direktur', 'href' => '/procurement?status=pending_validation', 'match' => '/procurement', 'exclude' => '/procurement/validation', 'query' => ['status' => 'pending_validation']],
                    ['title' => 'Butuh Revisi', 'href' => '/procurement?status=need_revision', 'match' => '/procurement', 'exclude' => '/procurement/validation', 'query' => ['status' => 'need_revision']],
                    ['title' => 'Selesai', 'href' => '/procurement?status=pricing_completed', 'match' => '/procurement', 'exclude' => '/procurement/validation', 'query' => ['status' => 'pricing_completed']],
                ],
            ],
            ['title' => 'Harga Vendor', 'href' => '/vendors', 'icon' => 'bi-building', 'match' => '/vendors', 'perm' => 'vendor.manage'],
            ['title' => 'Katalog Produk', 'href' => '/products', 'icon' => 'bi-box-seam', 'match' => '/products', 'perm' => 'product.manage'],
            ['title' => 'Data Referensi BoQ', 'href' => '/boq/referensi', 'icon' => 'bi-journal-text', 'match' => '/boq', 'perm' => 'boq.view'],
        ],
    ],

    // --------------------------------------------------------------- DIREKTUR
    [
        'section' => 'Direktur',
        'jobs' => ['director'],
        'items' => [
            ['title' => 'Approval Harga', 'href' => '/procurement/validation', 'icon' => 'bi-shield-check', 'match' => '/procurement/validation', 'perm' => 'price.validate'],
            ['title' => 'Approval Penawaran', 'href' => '/proposals?status=internal_review', 'icon' => 'bi-patch-check', 'match' => '/proposals', 'query' => ['status' => 'internal_review'], 'perm' => 'proposal.approve'],
            ['title' => 'Laporan', 'href' => '/reports', 'icon' => 'bi-graph-up-arrow', 'match' => '/reports', 'perm' => 'report.view'],
        ],
    ],

    // ---------------------------------------------- PENGAWASAN (Manager/Admin)
    [
        'section' => 'Monitoring',
        'jobs' => ['manager', 'super_admin', 'admin_sales'],
        'items' => [
            ['title' => 'Semua Lead', 'href' => '/leads', 'icon' => 'bi-person-lines-fill', 'match' => '/leads', 'perm' => 'lead.view'],
            ['title' => 'Antrian', 'href' => '/queue', 'icon' => 'bi-list-ol', 'match' => '/queue', 'perm' => 'queue.view'],
            ['title' => 'Survey Lapangan', 'href' => '/surveys', 'icon' => 'bi-geo-alt', 'match' => '/surveys', 'perm' => 'survey.view'],
            ['title' => 'Prelim', 'href' => '/prelims', 'icon' => 'bi-file-earmark-richtext', 'match' => '/prelims', 'perm' => 'prelim.view'],
            ['title' => 'Engineer', 'href' => '/engineer', 'icon' => 'bi-tools', 'match' => '/engineer', 'perm' => 'engineer.view'],
            ['title' => 'Procurement', 'href' => '/procurement', 'icon' => 'bi-truck', 'match' => '/procurement', 'exclude' => '/procurement/validation', 'perm' => 'procurement.view'],
            ['title' => 'Proposal', 'href' => '/proposals', 'icon' => 'bi-file-earmark-text', 'match' => '/proposals', 'perm' => 'proposal.view'],
            ['title' => 'Follow Up', 'href' => '/follow-ups', 'icon' => 'bi-telephone-outbound', 'match' => '/follow-ups', 'perm' => 'followup.view'],
            ['title' => 'Laporan', 'href' => '/reports', 'icon' => 'bi-graph-up-arrow', 'match' => '/reports', 'perm' => 'report.view'],
        ],
    ],

    // ------------------------------------------------------------------ ADMIN
    [
        'section' => 'Administrasi',
        'jobs' => ['super_admin', 'admin_sales'],
        'items' => [
            ['title' => 'Master Data', 'href' => '/master-data', 'icon' => 'bi-database-gear', 'match' => '/master-data', 'perm' => 'master_data.manage'],
            ['title' => 'Data Referensi BoQ', 'href' => '/boq/referensi', 'icon' => 'bi-journal-text', 'match' => '/boq', 'perm' => 'boq.view'],
            ['title' => 'Pengguna', 'href' => '/users', 'icon' => 'bi-people-fill', 'match' => '/users', 'perm' => 'user.manage'],
            ['title' => 'Role & Hak Akses', 'href' => '/roles', 'icon' => 'bi-shield-lock-fill', 'match' => '/roles', 'perm' => 'user.manage'],
            ['title' => 'Pengaturan Sistem', 'href' => '/settings', 'icon' => 'bi-gear-fill', 'match' => '/settings', 'perm' => 'system.manage'],
        ],
    ],

    [
        'section' => 'Akun',
        'jobs' => ['*'],
        'items' => [
            ['title' => 'Notifikasi', 'href' => '/notifications', 'icon' => 'bi-bell', 'match' => '/notifications'],
            ['title' => 'Profil Saya', 'href' => '/profile', 'icon' => 'bi-person-circle', 'match' => '/profile'],
        ],
    ],
];
