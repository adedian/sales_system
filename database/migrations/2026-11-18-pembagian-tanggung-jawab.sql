-- =============================================================================
-- Revisi final pembagian tanggung jawab Sales / Surveyor / Engineer / Sales Engineer
-- (2026-11-18). Idempotent: aman dijalankan ulang. Butuh MariaDB (ADD COLUMN IF NOT EXISTS).
--
--  * Survey: Sales pemilik Lead dan lokasi dicatat saat pengajuan; riwayat boleh
--    memuat catatan Sales (entity 'note').
--  * Personel pendukung Survey hanya Engineer: baris Sales Engineer dibuang.
--  * Izin: Sales Engineer tidak punya izin Survey dan Lead. Engineer, Surveyor,
--    Procurement, dan Direktur tidak punya izin Lead (Lead Management = Sales).
--    Surveyor tidak lagi memakai Antrian lama. Guard role (AreaMiddleware)
--    menjaga batas yang sama di backend walau izin diubah manual.
-- =============================================================================

ALTER TABLE `surveys`
    ADD COLUMN IF NOT EXISTS `owner_sales_id` INT UNSIGNED NULL AFTER `requested_by`,
    ADD COLUMN IF NOT EXISTS `location` VARCHAR(500) NULL AFTER `owner_sales_id`;

ALTER TABLE `surveys` ADD INDEX IF NOT EXISTS `idx_surveys_owner_sales` (`owner_sales_id`);

ALTER TABLE `survey_status_history`
    MODIFY `entity` ENUM('survey','report','note') NOT NULL DEFAULT 'survey';

-- Isi data pengajuan lama: pemilik lead & lokasi diambil dari lead.
UPDATE `surveys` s JOIN `leads` l ON l.`id` = s.`lead_id`
   SET s.`owner_sales_id` = COALESCE(s.`owner_sales_id`, l.`sales_id`),
       s.`location` = COALESCE(s.`location`, NULLIF(l.`site_location`, ''), NULLIF(l.`address`, ''))
 WHERE s.`owner_sales_id` IS NULL OR s.`location` IS NULL;

-- Sales Engineer bukan petugas Survey.
DELETE FROM `survey_participants` WHERE `role` = 'sales_engineer';

-- ---- Izin ---------------------------------------------------------------------
DELETE rp FROM `role_permissions` rp
  JOIN `roles` r ON r.`id` = rp.`role_id`
  JOIN `permissions` p ON p.`id` = rp.`permission_id`
 WHERE (r.`slug` = 'sales-engineer' AND p.`slug` IN ('lead.view', 'survey.view', 'survey.review'))
    OR (r.`slug` IN ('engineer', 'procurement', 'direktur') AND p.`slug` = 'lead.view')
    OR (r.`slug` = 'surveyor' AND p.`slug` IN ('lead.view', 'queue.view'));
