-- =============================================================================
-- Migrasi multi-role (2026-11-17)
--
--  * Tabel user_roles: satu pengguna boleh punya banyak role. Izin = gabungan.
--  * Role baru: Surveyor, Sales Engineer, Direktur. Role "Engineer Sales"
--    diubah nama/slug menjadi "Engineer" (id, izin, dan penggunanya tetap).
--  * Estimator dihapus sebagai pekerjaan aktif. Kolom users.is_estimator dan
--    sales_queue.estimator_id TIDAK dihapus (histori tetap terbaca).
--  * Kolom flag is_sales/is_surveyor/is_engineer/is_sales_engineer/is_director
--    sekarang DITURUNKAN dari user_roles (cache untuk kode lama), bukan
--    sumber kebenaran.
--  * Wewenang approval pindah ke role Direktur (price.validate, proposal.approve);
--    proposal.approve dicabut dari Manager dan Admin Sales.
--
-- Pemetaan nama -> role di bawah hanyalah konfigurasi awal (sekali jalan);
-- aplikasi tidak membaca nama. Aman dijalankan ulang di database yang BELUM
-- diubah manual lewat menu Pengguna (menjalankan ulang mengembalikan nama-nama
-- terpetakan ke pemetaan awal).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `idx_user_roles_role` (`role_id`),
    CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Role -------------------------------------------------------------------
UPDATE `roles` SET `name` = 'Engineer', `slug` = 'engineer',
    `description` = 'Analisis teknis, pemeriksaan, perhitungan sistem, dan spesifikasi'
 WHERE `slug` = 'engineer-sales'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `roles` WHERE `slug` = 'engineer') AS x);

INSERT IGNORE INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES
    ('Surveyor', 'surveyor', 'Survey lapangan, dokumentasi foto, dan Survey Report', 1),
    ('Sales Engineer', 'sales-engineer', 'Analisis kebutuhan, BOQ, dan pembuatan/revisi proposal', 1),
    ('Direktur', 'direktur', 'Approval harga dan penawaran', 1),
    ('Engineer', 'engineer', 'Analisis teknis, pemeriksaan, perhitungan sistem, dan spesifikasi', 1);

-- ---- Izin per role (hanya menambah; tidak menghapus izin yang sudah diatur) ----
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p
  ON p.`slug` IN ('dashboard.view','lead.view','survey.view','queue.view')
WHERE r.`slug` = 'surveyor';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p
  ON p.`slug` IN ('dashboard.view','lead.view','survey.view','survey.review','engineer.view','procurement.view',
                  'prelim.view','proposal.view','proposal.create','proposal.edit')
WHERE r.`slug` = 'sales-engineer';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p
  ON p.`slug` IN ('dashboard.view','lead.view','survey.view','survey.review','engineer.view','procurement.view')
WHERE r.`slug` = 'engineer';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p
  ON p.`slug` IN ('dashboard.view','lead.view','survey.view','survey.review','engineer.view','prelim.view',
                  'proposal.view','proposal.approve','procurement.view','price.validate','followup.view',
                  'queue.view','report.view','report.export')
WHERE r.`slug` = 'direktur';

-- Procurement: menu Vendor dan Katalog Produk memang bagian pekerjaannya.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p
  ON p.`slug` IN ('vendor.manage','product.manage')
WHERE r.`slug` = 'procurement';

-- Approval penawaran hanya Direktur (Super Admin tetap memiliki semuanya).
DELETE rp FROM `role_permissions` rp
  JOIN `roles` r ON r.`id` = rp.`role_id`
  JOIN `permissions` p ON p.`id` = rp.`permission_id`
 WHERE p.`slug` = 'proposal.approve' AND r.`slug` IN ('manager', 'admin-sales');

-- ---- Backfill relasi user-role ------------------------------------------------
-- 1) role utama yang sudah ada
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT `id`, `role_id` FROM `users` WHERE `deleted_at` IS NULL AND `role_id` IS NOT NULL;

-- 2) role dari flag pekerjaan lama (Estimator diabaikan)
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id` FROM `users` u JOIN `roles` r ON r.`slug` = 'sales'          WHERE u.`is_sales` = 1          AND u.`deleted_at` IS NULL;
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id` FROM `users` u JOIN `roles` r ON r.`slug` = 'surveyor'       WHERE u.`is_surveyor` = 1       AND u.`deleted_at` IS NULL;
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id` FROM `users` u JOIN `roles` r ON r.`slug` = 'engineer'        WHERE u.`is_engineer` = 1       AND u.`deleted_at` IS NULL;
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id` FROM `users` u JOIN `roles` r ON r.`slug` = 'sales-engineer'  WHERE u.`is_sales_engineer` = 1  AND u.`deleted_at` IS NULL;
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id` FROM `users` u JOIN `roles` r ON r.`slug` = 'direktur'        WHERE u.`is_director` = 1       AND u.`deleted_at` IS NULL;

-- 3) "sales" hanyalah role login generik bagi staf yang pekerjaannya bukan Sales:
--    lepas bila pengguna punya pekerjaan lain tetapi tidak berflag Sales.
DELETE ur FROM `user_roles` ur
  JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'sales'
  JOIN `users` u ON u.`id` = ur.`user_id`
 WHERE u.`is_sales` = 0
   AND (u.`is_surveyor` = 1 OR u.`is_engineer` = 1 OR u.`is_sales_engineer` = 1 OR u.`is_director` = 1);

-- ---- Pemetaan awal sesuai daftar (menimpa hasil backfill untuk nama-nama ini) ----
-- (tanpa tabel sementara: akun hosting bersama tidak punya hak CREATE TEMPORARY TABLES)
DELETE ur FROM `user_roles` ur JOIN `users` u ON u.`id` = ur.`user_id`
 WHERE u.`username` IN ('victor', 'charles', 'vicky', 'vega', 'ronny', 'rika', 'fita', 'ali', 'tio', 'rian', 'naufal', 'sandi', 'magang');

INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id` FROM (
    SELECT 'victor' AS `username`, 'sales' AS `role_slug`
    UNION ALL SELECT 'charles', 'sales'
    UNION ALL SELECT 'vicky', 'sales'
    UNION ALL SELECT 'vega', 'sales'
    UNION ALL SELECT 'ronny', 'sales'
    UNION ALL SELECT 'ronny', 'direktur'
    UNION ALL SELECT 'rika', 'sales-engineer'
    UNION ALL SELECT 'fita', 'sales-engineer'
    UNION ALL SELECT 'ali', 'surveyor'
    UNION ALL SELECT 'tio', 'surveyor'
    UNION ALL SELECT 'rian', 'surveyor'
    UNION ALL SELECT 'rian', 'engineer'
    UNION ALL SELECT 'naufal', 'surveyor'
    UNION ALL SELECT 'naufal', 'engineer'
    UNION ALL SELECT 'sandi', 'engineer'
    UNION ALL SELECT 'magang', 'sales'
) m
  JOIN `users` u ON u.`username` = m.`username` AND u.`deleted_at` IS NULL
  JOIN `roles` r ON r.`slug` = m.`role_slug`;

-- ---- Role utama (users.role_id) = role berprioritas tertinggi milik pengguna ----
-- Prioritas: super-admin > admin-sales > manager > sales > sales-engineer > surveyor > engineer > procurement > direktur.
-- Diterapkan dari prioritas terendah ke tertinggi agar yang tertinggi menang.
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'direktur'       SET u.`role_id` = r.`id`;
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'procurement'    SET u.`role_id` = r.`id`;
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'engineer'       SET u.`role_id` = r.`id`;
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'surveyor'       SET u.`role_id` = r.`id`;
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'sales-engineer'  SET u.`role_id` = r.`id`;
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'sales'           SET u.`role_id` = r.`id`;
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'manager'         SET u.`role_id` = r.`id`;
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'admin-sales'     SET u.`role_id` = r.`id`;
UPDATE `users` u JOIN `user_roles` ur ON ur.`user_id` = u.`id` JOIN `roles` r ON r.`id` = ur.`role_id` AND r.`slug` = 'super-admin'     SET u.`role_id` = r.`id`;

-- ---- Flag turunan (cache untuk kode lama) -----------------------------------------
UPDATE `users` u SET
    u.`is_estimator`      = 0,
    u.`is_sales`          = EXISTS (SELECT 1 FROM `user_roles` ur JOIN `roles` r ON r.`id` = ur.`role_id` WHERE ur.`user_id` = u.`id` AND r.`slug` = 'sales'),
    u.`is_surveyor`       = EXISTS (SELECT 1 FROM `user_roles` ur JOIN `roles` r ON r.`id` = ur.`role_id` WHERE ur.`user_id` = u.`id` AND r.`slug` = 'surveyor'),
    u.`is_engineer`       = EXISTS (SELECT 1 FROM `user_roles` ur JOIN `roles` r ON r.`id` = ur.`role_id` WHERE ur.`user_id` = u.`id` AND r.`slug` = 'engineer'),
    u.`is_sales_engineer` = EXISTS (SELECT 1 FROM `user_roles` ur JOIN `roles` r ON r.`id` = ur.`role_id` WHERE ur.`user_id` = u.`id` AND r.`slug` = 'sales-engineer'),
    u.`is_director`       = EXISTS (SELECT 1 FROM `user_roles` ur JOIN `roles` r ON r.`id` = ur.`role_id` WHERE ur.`user_id` = u.`id` AND r.`slug` = 'direktur');
