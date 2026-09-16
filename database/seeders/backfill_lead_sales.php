<?php

/**
 * One-time backfill for Phase A (Leads revision): populates the new
 * `lead_sales` pivot table from every existing lead's `sales_id`, so
 * leads created before multi-sales support still show their sales
 * person in the new "Sales" multi-select/display everywhere.
 *
 * Idempotent — safe to run more than once (guarded by the
 * `uq_lead_sales_lead_user` unique key + an existence check).
 *
 * Usage: php database/seeders/backfill_lead_sales.php
 */

use App\Core\Database;
use App\Core\Env;

require_once __DIR__ . '/../../vendor/autoload.php';
Env::load(__DIR__ . '/../../.env');

echo "== Backfill lead_sales dari leads.sales_id ==\n";

$leads = Database::fetchAll('SELECT id, sales_id FROM leads WHERE sales_id IS NOT NULL');

$created = 0;
$skipped = 0;

foreach ($leads as $lead) {
    $exists = Database::fetch(
        'SELECT id FROM lead_sales WHERE lead_id = ? AND user_id = ?',
        [$lead['id'], $lead['sales_id']]
    );

    if ($exists) {
        $skipped++;
        continue;
    }

    Database::insertGetId(
        'INSERT INTO lead_sales (lead_id, user_id, created_at) VALUES (?, ?, NOW())',
        [$lead['id'], $lead['sales_id']]
    );
    $created++;
}

echo "  Lead dengan sales_id: " . count($leads) . "\n";
echo "  Baris lead_sales dibuat: {$created}\n";
echo "  Baris lead_sales sudah ada (dilewati): {$skipped}\n";
echo "== Selesai ==\n";
