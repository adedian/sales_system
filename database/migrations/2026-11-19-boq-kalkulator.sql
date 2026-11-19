-- =============================================================================
-- Kalkulator BoQ On-Grid + Data Referensi BoQ (2026-11-19).
-- Idempotent: aman dijalankan ulang. Butuh MariaDB.
--
--  * boq_ref_items     : seluruh tabel katalog dari Excel "Detailed Calculation BoQ"
--                        (modul PV, inverter, breaker, kabel, skun, gland, tarif jasa, dst).
--                        Satu tabel generik: category + code + attrs (JSON).
--  * boq_calculations  : satu hasil kalkulasi BoQ per tugas Engineer (inputs + result JSON).
--  * Izin boq.view / boq.calculate / boq.manage + pemetaan ke role.
--
-- Isi boq_ref_items: php database/seeders/boq_reference.php (atau berkas
-- 2026-11-19-boq-referensi-data.sql yang dibuat tools/boq/build_reference_sql.php).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `boq_ref_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(30) NOT NULL,
    `code` VARCHAR(150) NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `attrs` LONGTEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_boq_ref_category_code` (`category`, `code`),
    KEY `idx_boq_ref_category_sort` (`category`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `boq_calculations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `engineer_assignment_id` INT UNSIGNED NOT NULL,
    `lead_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NULL,
    `inputs` LONGTEXT NOT NULL,
    `result` LONGTEXT NULL,
    `system_wp` DECIMAL(14,2) NULL,
    `inverter_w` DECIMAL(14,2) NULL,
    `dc_ac_ratio` DECIMAL(8,3) NULL,
    `status` ENUM('draft','final') NOT NULL DEFAULT 'draft',
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_boq_calc_assignment` (`engineer_assignment_id`),
    KEY `idx_boq_calc_lead` (`lead_id`),
    CONSTRAINT `fk_boq_calc_assignment` FOREIGN KEY (`engineer_assignment_id`) REFERENCES `engineer_assignments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_boq_calc_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Izin ---------------------------------------------------------------------
INSERT IGNORE INTO `permissions` (`slug`, `module`, `description`) VALUES
    ('boq.view', 'boq', 'Melihat Kalkulator BoQ dan Data Referensi BoQ'),
    ('boq.calculate', 'boq', 'Menjalankan dan menyimpan Kalkulator BoQ pada tugas teknis'),
    ('boq.manage', 'boq', 'Mengelola Data Referensi BoQ (modul, inverter, breaker, kabel, tarif jasa)');

-- Super Admin, Admin Sales: semua izin BoQ. Sales Engineer dan Engineer: lihat + hitung.
-- Manager, Direktur, Procurement: hanya lihat.
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p ON p.`slug` IN ('boq.view', 'boq.calculate', 'boq.manage')
 WHERE r.`slug` IN ('super-admin', 'admin-sales');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p ON p.`slug` IN ('boq.view', 'boq.calculate')
 WHERE r.`slug` IN ('sales-engineer', 'engineer');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r JOIN `permissions` p ON p.`slug` = 'boq.view'
 WHERE r.`slug` IN ('manager', 'direktur', 'procurement');
