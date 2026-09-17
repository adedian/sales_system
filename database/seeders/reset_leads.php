<?php

/**
 * One-time destructive reset (Revisi Alur Bisnis: Prelim/Data Awal) —
 * requested explicitly by the user so the new Prelim-gated workflow can
 * apply uniformly to every lead with no grandfather/legacy-exception
 * logic, after a full `mysqldump` backup (see database/backups/).
 *
 * Deletes every row in `leads`. All downstream tables cascade via their
 * existing `ON DELETE CASCADE` FKs to `leads.id` (verified in
 * database/schema.sql): lead_sales, lead_status_history, sales_queue
 * (+ queue_status_history, queue_notes), engineer_assignments
 * (+ engineer_assignment_status_history, engineer_assignment_notes,
 * engineer_documents), procurement_requests (+ procurement_items,
 * procurement_status_history, procurement_request_notes,
 * procurement_price_validations), proposals (+ proposal_items,
 * proposal_status_history, proposal_notes, proposal_negotiations),
 * followups.
 *
 * Users, roles, permissions, master data, vendors, products, settings
 * are never touched. `notifications`/`audit_logs` rows referencing the
 * deleted leads are left in place (no FK — they simply become inert,
 * same as the rest of this codebase treats ON DELETE SET NULL cases).
 *
 * NOT idempotent by design (it deletes everything, every time it's run
 * — running it twice is a no-op the second time only because there's
 * nothing left to delete). Intended to run exactly once.
 *
 * Usage: php database/seeders/reset_leads.php
 */

use App\Core\Database;
use App\Core\Env;

require_once __DIR__ . '/../../vendor/autoload.php';
Env::load(__DIR__ . '/../../.env');

echo "== Reset Lead & data turunannya (Revisi Alur Bisnis) ==\n";

$tables = ['leads', 'lead_sales', 'lead_status_history', 'sales_queue', 'queue_status_history',
    'queue_notes', 'engineer_assignments', 'engineer_assignment_status_history',
    'engineer_assignment_notes', 'engineer_documents', 'procurement_requests',
    'procurement_items', 'procurement_status_history', 'procurement_request_notes',
    'procurement_price_validations', 'proposals', 'proposal_items', 'proposal_status_history',
    'proposal_notes', 'proposal_negotiations', 'followups'];

echo "\n-- Jumlah baris SEBELUM reset --\n";
$before = [];
foreach ($tables as $table) {
    $before[$table] = (int) (Database::fetch("SELECT COUNT(*) AS total FROM {$table}")['total'] ?? 0);
    echo "  {$table}: {$before[$table]}\n";
}

$engineerDocs = Database::fetchAll('SELECT file_path FROM engineer_documents');
$quotationFiles = Database::fetchAll("SELECT quotation_file_path FROM procurement_items WHERE quotation_file_path IS NOT NULL AND quotation_file_path <> ''");

Database::transaction(function () {
    Database::execute('DELETE FROM leads');
});

echo "\n-- DELETE FROM leads dieksekusi (cascade ke seluruh tabel turunan) --\n";

foreach ($tables as $table) {
    Database::execute("ALTER TABLE {$table} AUTO_INCREMENT = 1");
}
echo "-- AUTO_INCREMENT direset ke 1 untuk seluruh tabel di atas --\n";

$uploadsRoot = dirname(__DIR__, 2) . '/storage/uploads/';
$deletedFiles = 0;
foreach (array_merge(array_column($engineerDocs, 'file_path'), array_column($quotationFiles, 'quotation_file_path')) as $relativePath) {
    $fullPath = $uploadsRoot . $relativePath;
    if (is_file($fullPath) && @unlink($fullPath)) {
        $deletedFiles++;
    }
}
echo "-- File upload engineer/procurement yang di-orphan dihapus: {$deletedFiles} --\n";

echo "\n-- Jumlah baris SESUDAH reset --\n";
foreach ($tables as $table) {
    $after = (int) (Database::fetch("SELECT COUNT(*) AS total FROM {$table}")['total'] ?? 0);
    echo "  {$table}: {$after}\n";
}

echo "\n== Reset selesai. Users/roles/permissions/master data/vendors/products/settings TIDAK disentuh. ==\n";
