-- =============================================================================
-- Sistem Internal Sales — Database Schema
-- Phase 1 menggunakan: roles, permissions, role_permissions, users,
--                       login_attempts, audit_logs, settings
-- Tabel lain disiapkan sekarang agar Phase 2+ tidak perlu migrasi struktural.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `sales_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sales_system`;

-- -----------------------------------------------------------------------------
-- RBAC
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_permissions_slug` (`slug`),
    KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Users & security
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `phone` VARCHAR(30) NULL,
    `avatar` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `last_login_at` DATETIME NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uq_users_email` (`email`),
    UNIQUE KEY `uq_users_username` (`username`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(150) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_login_attempts_identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `record_id` INT UNSIGNED NULL,
    `old_data` JSON NULL,
    `new_data` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_audit_logs_user` (`user_id`),
    KEY `idx_audit_logs_module` (`module`),
    KEY `idx_audit_logs_created_at` (`created_at`),
    CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `selector` CHAR(24) NOT NULL,
    `validator_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_remember_tokens_selector` (`selector`),
    KEY `idx_remember_tokens_user` (`user_id`),
    CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_settings_key` (`key`),
    CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Master Data (Phase 4) — semua tabel berikut berbagi satu bentuk kolom yang
-- sama persis (code, name, description, color, sort_order, is_active,
-- is_system, created_by/updated_by, timestamps) supaya bisa dilayani oleh
-- SATU Controller + SATU View generik (lihat app/Config/master_data.php).
--
-- `is_system = 1` menandai baris yang code-nya dikunci karena dipakai sebagai
-- nilai ENUM tetap pada leads/sales_queue/engineer_assignments (lihat blok
-- "Lead pipeline" di bawah) — baris ini boleh diubah nama/deskripsi/warnanya
-- tapi tidak boleh diganti code-nya atau dihapus. Baris is_system = 0 adalah
-- master data bebas kelola (sumber lead, kategori, jenis kebutuhan).
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lead_sources` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_lead_sources_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_lead_categories_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_statuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_lead_statuses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `priorities` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_priorities_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `need_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_need_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_lead_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_systems` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_lead_systems_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `funding_sources` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_funding_sources_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `queue_statuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_queue_statuses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `survey_statuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_survey_statuses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `queue_stages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_queue_stages_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `engineer_statuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_engineer_statuses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `procurement_statuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_procurement_statuses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `units` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_units_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_product_categories_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_statuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(20) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_proposal_statuses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Lead pipeline (dipakai mulai Phase 2)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `leads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_code` VARCHAR(30) NOT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `company_name` VARCHAR(150) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(150) NULL,
    `address` TEXT NULL,
    `source_id` INT UNSIGNED NULL,
    `category_id` INT UNSIGNED NULL,
    `need_type_id` INT UNSIGNED NULL,
    `needs_description` TEXT NULL,
    `estimated_value` DECIMAL(18,2) NULL,
    `deal_value` DECIMAL(18,2) NULL,
    `won_proposal_id` INT UNSIGNED NULL,
    `closing_date` DATE NULL,
    `customer_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
    `confirmation_notes` TEXT NULL,
    `lost_reason` TEXT NULL,
    `notes` TEXT NULL,
    `status` ENUM('new','in_queue','follow_up','engineering','procurement','pricing_ready','proposal','won','lost') NOT NULL DEFAULT 'new',
    `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `sales_id` INT UNSIGNED NULL,
    `follow_up_date` DATE NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uq_leads_lead_code` (`lead_code`),
    KEY `idx_leads_status` (`status`),
    KEY `idx_leads_priority` (`priority`),
    KEY `idx_leads_sales` (`sales_id`),
    KEY `idx_leads_source` (`source_id`),
    KEY `idx_leads_category` (`category_id`),
    KEY `idx_leads_need_type` (`need_type_id`),
    KEY `idx_leads_follow_up_date` (`follow_up_date`),
    KEY `idx_leads_deleted_at` (`deleted_at`),
    KEY `idx_leads_won_proposal` (`won_proposal_id`),
    CONSTRAINT `fk_leads_source` FOREIGN KEY (`source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leads_category` FOREIGN KEY (`category_id`) REFERENCES `lead_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leads_need_type` FOREIGN KEY (`need_type_id`) REFERENCES `need_types` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leads_sales` FOREIGN KEY (`sales_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leads_won_proposal` FOREIGN KEY (`won_proposal_id`) REFERENCES `proposals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase A (Leads/Antrian revision) — additive columns on an existing table,
-- so ADD COLUMN/ADD KEY use MariaDB's IF NOT EXISTS guard (10.0.2+, this
-- project runs 10.4.32) to stay safe to re-apply. ADD CONSTRAINT has NO
-- IF NOT EXISTS guard in MariaDB — this block is applied once via mysql.exe
-- CLI against the live dev DB (same operational practice as every other
-- schema change in this project), not re-run wholesale; do not re-run this
-- specific ADD CONSTRAINT block against a DB where it has already succeeded.
ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `type_id` INT UNSIGNED NULL AFTER `category_id`,
    ADD COLUMN IF NOT EXISTS `system_id` INT UNSIGNED NULL AFTER `type_id`,
    ADD COLUMN IF NOT EXISTS `funding_id` INT UNSIGNED NULL AFTER `system_id`,
    ADD COLUMN IF NOT EXISTS `size_kwp` DECIMAL(10,2) NULL AFTER `estimated_value`,
    ADD COLUMN IF NOT EXISTS `site_location` VARCHAR(150) NULL AFTER `address`,
    ADD COLUMN IF NOT EXISTS `note2` TEXT NULL AFTER `notes`,
    ADD COLUMN IF NOT EXISTS `note_updated_at` DATETIME NULL AFTER `note2`;

ALTER TABLE `leads`
    ADD KEY IF NOT EXISTS `idx_leads_type` (`type_id`),
    ADD KEY IF NOT EXISTS `idx_leads_system` (`system_id`),
    ADD KEY IF NOT EXISTS `idx_leads_funding` (`funding_id`);

ALTER TABLE `leads`
    ADD CONSTRAINT `fk_leads_type` FOREIGN KEY (`type_id`) REFERENCES `lead_types` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_leads_system` FOREIGN KEY (`system_id`) REFERENCES `lead_systems` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_leads_funding` FOREIGN KEY (`funding_id`) REFERENCES `funding_sources` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `lead_sales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_lead_sales_lead_user` (`lead_id`, `user_id`),
    KEY `idx_lead_sales_lead` (`lead_id`),
    KEY `idx_lead_sales_user` (`user_id`),
    CONSTRAINT `fk_lead_sales_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lead_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_status_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(30) NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `changed_by` INT UNSIGNED NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_lead_status_history_lead` (`lead_id`),
    CONSTRAINT `fk_lead_status_history_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lead_status_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT UNSIGNED NOT NULL,
    `sales_id` INT UNSIGNED NOT NULL,
    `queue_number` INT UNSIGNED NOT NULL,
    `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `status` ENUM('new','waiting_followup','in_progress','waiting_engineer','done','cancelled') NOT NULL DEFAULT 'new',
    `entered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deadline` DATE NULL,
    `followup_date` DATE NULL,
    `notes` TEXT NULL,
    `last_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_sales_queue_lead` (`lead_id`),
    KEY `idx_sales_queue_sales` (`sales_id`),
    KEY `idx_sales_queue_status` (`status`),
    KEY `idx_sales_queue_deadline` (`deadline`),
    CONSTRAINT `fk_sales_queue_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sales_queue_sales` FOREIGN KEY (`sales_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase B (Antrian revision) — additive columns, same idempotent-apply
-- convention as Phase A's `leads` ALTER block above: ADD COLUMN/ADD KEY use
-- MariaDB's IF NOT EXISTS guard; ADD CONSTRAINT has no such guard, so this
-- FK block is applied once via mysql.exe CLI, not re-run wholesale.
ALTER TABLE `sales_queue`
    ADD COLUMN IF NOT EXISTS `task_name` VARCHAR(150) NULL AFTER `sales_id`,
    ADD COLUMN IF NOT EXISTS `estimator_id` INT UNSIGNED NULL AFTER `task_name`,
    ADD COLUMN IF NOT EXISTS `surveyor_id` INT UNSIGNED NULL AFTER `estimator_id`,
    ADD COLUMN IF NOT EXISTS `stage_id` INT UNSIGNED NULL AFTER `priority`,
    ADD COLUMN IF NOT EXISTS `survey_status_id` INT UNSIGNED NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `engineering_start_date` DATE NULL AFTER `followup_date`,
    ADD COLUMN IF NOT EXISTS `engineering_end_date` DATE NULL AFTER `engineering_start_date`,
    ADD COLUMN IF NOT EXISTS `procurement_start_date` DATE NULL AFTER `engineering_end_date`,
    ADD COLUMN IF NOT EXISTS `procurement_end_date` DATE NULL AFTER `procurement_start_date`;

ALTER TABLE `sales_queue`
    ADD KEY IF NOT EXISTS `idx_sales_queue_estimator` (`estimator_id`),
    ADD KEY IF NOT EXISTS `idx_sales_queue_surveyor` (`surveyor_id`),
    ADD KEY IF NOT EXISTS `idx_sales_queue_stage` (`stage_id`),
    ADD KEY IF NOT EXISTS `idx_sales_queue_survey_status` (`survey_status_id`);

ALTER TABLE `sales_queue`
    ADD CONSTRAINT `fk_sales_queue_estimator` FOREIGN KEY (`estimator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_sales_queue_surveyor` FOREIGN KEY (`surveyor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_sales_queue_stage` FOREIGN KEY (`stage_id`) REFERENCES `queue_stages` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_sales_queue_survey_status` FOREIGN KEY (`survey_status_id`) REFERENCES `survey_statuses` (`id`) ON DELETE SET NULL;

-- Phase B — Estimator/Surveyor are capability flags on `users`, not RBAC
-- roles: any user (usually role `sales`) can be flagged available for
-- either/both, independent of their login role. Plain columns, no FK,
-- safe under the normal idempotent ADD COLUMN IF NOT EXISTS path.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `is_estimator` TINYINT(1) NOT NULL DEFAULT 0 AFTER `must_change_password`,
    ADD COLUMN IF NOT EXISTS `is_surveyor` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_estimator`;

-- Revisi Sub-Fase 1 (Sales Engineer/Engineer/Direktur) — same capability-flag
-- pattern as Estimator/Surveyor above: a user keeps their normal login role
-- (usually `sales`) and can additionally be flagged for one or more of these,
-- since this RBAC is strictly single-role-per-user. `is_engineer` = field
-- survey lanjutan/desain teknis (Sandi/Naufal/Rian); `is_sales_engineer` =
-- analisa teknis & koordinasi (Fita/Rika) — the pre-existing
-- `engineer_assignments` module, now split by `assignment_type`;
-- `is_director` = price validation gate (Pak Ronny).
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `is_engineer` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_surveyor`,
    ADD COLUMN IF NOT EXISTS `is_sales_engineer` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_engineer`,
    ADD COLUMN IF NOT EXISTS `is_director` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_sales_engineer`;

-- Phase C (Current Position / Current PIC) — additive column, same
-- idempotent-apply convention as Phase A/B above: ADD COLUMN/ADD KEY use
-- MariaDB's IF NOT EXISTS guard; ADD CONSTRAINT has no such guard, so this
-- FK block is applied once via mysql.exe CLI, not re-run wholesale.
ALTER TABLE `sales_queue`
    ADD COLUMN IF NOT EXISTS `current_pic_id` INT UNSIGNED NULL AFTER `surveyor_id`;

ALTER TABLE `sales_queue`
    ADD KEY IF NOT EXISTS `idx_sales_queue_current_pic` (`current_pic_id`);

ALTER TABLE `sales_queue`
    ADD CONSTRAINT `fk_sales_queue_current_pic` FOREIGN KEY (`current_pic_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `queue_status_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(30) NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `changed_by` INT UNSIGNED NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_queue_status_history_queue` (`queue_id`),
    CONSTRAINT `fk_queue_status_history_queue` FOREIGN KEY (`queue_id`) REFERENCES `sales_queue` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_queue_status_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `queue_notes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `note` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_queue_notes_queue` (`queue_id`),
    CONSTRAINT `fk_queue_notes_queue` FOREIGN KEY (`queue_id`) REFERENCES `sales_queue` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_queue_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `engineer_assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assignment_code` VARCHAR(30) NOT NULL,
    `lead_id` INT UNSIGNED NOT NULL,
    `engineer_id` INT UNSIGNED NOT NULL,
    `assigned_by` INT UNSIGNED NULL,
    `status` ENUM('pending','accepted','rejected','in_progress','waiting','completed','returned') NOT NULL DEFAULT 'pending',
    `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `deadline` DATE NULL,
    `notes_from_sales` TEXT NULL,
    `rejection_reason` TEXT NULL,
    `result_notes` TEXT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `responded_at` DATETIME NULL,
    `accepted_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_engineer_assignments_code` (`assignment_code`),
    KEY `idx_engineer_assignments_lead` (`lead_id`),
    KEY `idx_engineer_assignments_engineer` (`engineer_id`),
    KEY `idx_engineer_assignments_status` (`status`),
    CONSTRAINT `fk_engineer_assignments_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_engineer_assignments_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_engineer_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `engineer_assignment_status_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(30) NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `changed_by` INT UNSIGNED NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_engineer_assignment_status_history_assignment` (`assignment_id`),
    CONSTRAINT `fk_engineer_assignment_status_history_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `engineer_assignments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_engineer_assignment_status_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `engineer_assignment_notes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `note` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_engineer_assignment_notes_assignment` (`assignment_id`),
    CONSTRAINT `fk_engineer_assignment_notes_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `engineer_assignments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_engineer_assignment_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `engineer_documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT UNSIGNED NOT NULL,
    `uploaded_by` INT UNSIGNED NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_engineer_documents_assignment` (`assignment_id`),
    CONSTRAINT `fk_engineer_documents_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `engineer_assignments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_engineer_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Revisi Sub-Fase 2 (Engineer vs Sales Engineer) — one table serves both
-- assignment types instead of duplicating engineer_assignments (dokumen
-- requirement §44 melarang tabel duplicate). Default 'sales_engineer' so
-- every pre-existing row (which always meant "analisa teknis" — what the
-- new spec calls Sales Engineer) stays semantically correct with zero data
-- migration.
ALTER TABLE `engineer_assignments`
    ADD COLUMN IF NOT EXISTS `assignment_type` ENUM('engineer','sales_engineer') NOT NULL DEFAULT 'sales_engineer' AFTER `lead_id`;

ALTER TABLE `engineer_assignments`
    ADD KEY IF NOT EXISTS `idx_engineer_assignments_type` (`assignment_type`);

-- -----------------------------------------------------------------------------
-- Procurement (Phase 7) — dedicated `vendors` table (bukan generic master
-- data: butuh kontak/alamat) + procurement_requests sebagai container per
-- lead (mirror engineer_assignments), dengan procurement_items sebagai baris
-- BOQ (item/spec/qty/unit/vendor/harga/quotation) yang diisi Procurement
-- sendiri berdasarkan hasil teknis Engineer.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vendors` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `vendor_code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(150) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(150) NULL,
    `address` TEXT NULL,
    `category` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uq_vendors_code` (`vendor_code`),
    KEY `idx_vendors_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Product catalog (Phase 14) — quick-fill source for Proposal/Procurement item
-- rows (see ProposalController/ProcurementController "Pilih dari Katalog").
-- Not part of the generic Master Data shape (needs category/unit/price), so it
-- gets its own dedicated Controller/Model like `vendors` did in Phase 7.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `category_id` INT UNSIGNED NULL,
    `unit_id` INT UNSIGNED NULL,
    `default_price` DECIMAL(18,2) NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uq_products_code` (`product_code`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_unit` (`unit_id`),
    KEY `idx_products_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_products_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `procurement_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_code` VARCHAR(30) NOT NULL,
    `lead_id` INT UNSIGNED NOT NULL,
    `engineer_assignment_id` INT UNSIGNED NULL,
    `assigned_to` INT UNSIGNED NOT NULL,
    `requested_by` INT UNSIGNED NULL,
    `status` ENUM('waiting','in_progress','quotation_requested','pending_validation','pricing_completed','need_revision','cancelled') NOT NULL DEFAULT 'waiting',
    `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `deadline` DATE NULL,
    `notes` TEXT NULL,
    `revision_reason` TEXT NULL,
    `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_procurement_requests_code` (`request_code`),
    KEY `idx_procurement_requests_lead` (`lead_id`),
    KEY `idx_procurement_requests_engineer_assignment` (`engineer_assignment_id`),
    KEY `idx_procurement_requests_assigned_to` (`assigned_to`),
    KEY `idx_procurement_requests_status` (`status`),
    CONSTRAINT `fk_procurement_requests_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_procurement_requests_engineer_assignment` FOREIGN KEY (`engineer_assignment_id`) REFERENCES `engineer_assignments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_procurement_requests_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_procurement_requests_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Revisi Sub-Fase 3 (Validasi Harga Direktur) — MariaDB has no
-- "ADD VALUE IF NOT EXISTS" for ENUM, so this MODIFY is only safe to run
-- once against a DB created before `pending_validation` existed in the
-- CREATE TABLE above (same one-time-apply-via-mysql.exe-CLI convention as
-- every other ALTER block in this file — do not re-run wholesale).
ALTER TABLE `procurement_requests`
    MODIFY COLUMN `status` ENUM('waiting','in_progress','quotation_requested','pending_validation','pricing_completed','need_revision','cancelled') NOT NULL DEFAULT 'waiting';

CREATE TABLE IF NOT EXISTS `procurement_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `procurement_request_id` INT UNSIGNED NOT NULL,
    `item_name` VARCHAR(200) NOT NULL,
    `specification` TEXT NULL,
    `quantity` DECIMAL(14,2) NOT NULL DEFAULT 1,
    `unit` VARCHAR(30) NULL,
    `vendor_id` INT UNSIGNED NULL,
    `supplier_name` VARCHAR(150) NULL,
    `purchase_price` DECIMAL(18,2) NULL,
    `quotation_number` VARCHAR(100) NULL,
    `quotation_date` DATE NULL,
    `quotation_file_name` VARCHAR(255) NULL,
    `quotation_stored_filename` VARCHAR(255) NULL,
    `quotation_file_path` VARCHAR(255) NULL,
    `validity_date` DATE NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_procurement_items_request` (`procurement_request_id`),
    KEY `idx_procurement_items_vendor` (`vendor_id`),
    CONSTRAINT `fk_procurement_items_request` FOREIGN KEY (`procurement_request_id`) REFERENCES `procurement_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_procurement_items_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `procurement_status_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `procurement_request_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(30) NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `changed_by` INT UNSIGNED NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_procurement_status_history_request` (`procurement_request_id`),
    CONSTRAINT `fk_procurement_status_history_request` FOREIGN KEY (`procurement_request_id`) REFERENCES `procurement_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_procurement_status_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `procurement_request_notes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `procurement_request_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `note` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_procurement_request_notes_request` (`procurement_request_id`),
    CONSTRAINT `fk_procurement_request_notes_request` FOREIGN KEY (`procurement_request_id`) REFERENCES `procurement_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_procurement_request_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Revisi Sub-Fase 3 (Validasi Harga Direktur) — audit trail for the new
-- gate between Procurement finishing pricing and it reaching Sales, mirrors
-- procurement_status_history/procurement_request_notes shape. `status` is a
-- plain ENUM (not generic Master Data) matching procurement_requests.status's
-- own convention. `total_price` is a snapshot at submission time so the
-- Direktur validates against the price actually submitted, even if items
-- are edited later.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `procurement_price_validations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `procurement_request_id` INT UNSIGNED NOT NULL,
    `submitted_by` INT UNSIGNED NULL,
    `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `total_price` DECIMAL(18,2) NOT NULL DEFAULT 0,
    `status` ENUM('pending','approved','revision_required') NOT NULL DEFAULT 'pending',
    `validated_by` INT UNSIGNED NULL,
    `validated_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_procurement_price_validations_request` (`procurement_request_id`),
    KEY `idx_procurement_price_validations_status` (`status`),
    CONSTRAINT `fk_procurement_price_validations_request` FOREIGN KEY (`procurement_request_id`) REFERENCES `procurement_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_procurement_price_validations_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_procurement_price_validations_validated_by` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Proposal (Phase 8) — header (proposals) mirrors procurement_requests, with
-- proposal_items as the repeatable priced line items (usually copied in from
-- procurement_items as a starting point, then marked up by Sales).
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `proposals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proposal_code` VARCHAR(30) NOT NULL,
    `lead_id` INT UNSIGNED NOT NULL,
    `procurement_request_id` INT UNSIGNED NULL,
    `sales_id` INT UNSIGNED NOT NULL,
    `customer_pic` VARCHAR(150) NULL,
    `project_name` VARCHAR(200) NULL,
    `scope_description` TEXT NULL,
    `status` ENUM('draft','internal_review','approved','sent','viewed','negotiation','revision','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
    `subtotal` DECIMAL(18,2) NOT NULL DEFAULT 0,
    `discount_percent` DECIMAL(5,2) NULL,
    `discount_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
    `tax_percent` DECIMAL(5,2) NULL DEFAULT 11,
    `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(18,2) NOT NULL DEFAULT 0,
    `timeline_text` VARCHAR(255) NULL,
    `payment_terms` TEXT NULL,
    `warranty` VARCHAR(255) NULL,
    `terms_conditions` TEXT NULL,
    `valid_until` DATE NULL,
    `notes` TEXT NULL,
    `rejection_reason` TEXT NULL,
    `submitted_at` DATETIME NULL,
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `sent_at` DATETIME NULL,
    `responded_at` DATETIME NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uq_proposals_code` (`proposal_code`),
    KEY `idx_proposals_lead` (`lead_id`),
    KEY `idx_proposals_procurement_request` (`procurement_request_id`),
    KEY `idx_proposals_sales` (`sales_id`),
    KEY `idx_proposals_status` (`status`),
    KEY `idx_proposals_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_proposals_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_proposals_procurement_request` FOREIGN KEY (`procurement_request_id`) REFERENCES `procurement_requests` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_proposals_sales` FOREIGN KEY (`sales_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_proposals_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_proposals_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proposal_id` INT UNSIGNED NOT NULL,
    `procurement_item_id` INT UNSIGNED NULL,
    `item_name` VARCHAR(200) NOT NULL,
    `specification` TEXT NULL,
    `quantity` DECIMAL(14,2) NOT NULL DEFAULT 1,
    `unit` VARCHAR(30) NULL,
    `unit_cost` DECIMAL(18,2) NULL,
    `unit_price` DECIMAL(18,2) NOT NULL DEFAULT 0,
    `subtotal` DECIMAL(18,2) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_proposal_items_proposal` (`proposal_id`),
    KEY `idx_proposal_items_procurement_item` (`procurement_item_id`),
    CONSTRAINT `fk_proposal_items_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_proposal_items_procurement_item` FOREIGN KEY (`procurement_item_id`) REFERENCES `procurement_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_status_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proposal_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(30) NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `changed_by` INT UNSIGNED NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_proposal_status_history_proposal` (`proposal_id`),
    CONSTRAINT `fk_proposal_status_history_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_proposal_status_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_notes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proposal_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `note` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_proposal_notes_proposal` (`proposal_id`),
    CONSTRAINT `fk_proposal_notes_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_proposal_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` VARCHAR(255) NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_notifications_user` (`user_id`, `is_read`),
    KEY `idx_notifications_created_at` (`created_at`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `followups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT UNSIGNED NOT NULL,
    `sales_id` INT UNSIGNED NOT NULL,
    `followup_date` DATETIME NOT NULL,
    `method` ENUM('call','email','visit','whatsapp','other') NOT NULL DEFAULT 'call',
    `notes` TEXT NULL,
    `customer_response` ENUM('interested','not_interested','need_info','negotiating','no_answer') NULL,
    `next_followup_date` DATE NULL,
    `next_action` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_followups_lead` (`lead_id`),
    KEY `idx_followups_sales` (`sales_id`),
    CONSTRAINT `fk_followups_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_followups_sales` FOREIGN KEY (`sales_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Negotiation log (Phase 9) — structured entries tied to a proposal while it
-- is out with the customer (sent/viewed/negotiation): each row is either
-- customer feedback, Sales' response, or a revision request that reopens the
-- proposal for editing (see ProposalController::reviseFromNegotiation()).
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `proposal_negotiations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proposal_id` INT UNSIGNED NOT NULL,
    `type` ENUM('customer_feedback','sales_response','revision_request') NOT NULL DEFAULT 'customer_feedback',
    `message` TEXT NOT NULL,
    `requested_total` DECIMAL(18,2) NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_proposal_negotiations_proposal` (`proposal_id`),
    CONSTRAINT `fk_proposal_negotiations_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_proposal_negotiations_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
