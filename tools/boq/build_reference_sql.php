<?php

/**
 * Membuat database/migrations/2026-11-19-boq-referensi-data.sql dari boq_reference.json:
 * INSERT IGNORE ke boq_ref_items (data yang sudah ada / sudah diubah pengguna TIDAK ditimpa).
 * Dipakai deploy/infinityfree/build-update.php untuk hosting tanpa SSH (impor lewat phpMyAdmin).
 *
 *   php tools/boq/build_reference_sql.php
 */

use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$pdo = Database::connection();

$data = json_decode((string) file_get_contents($root . '/database/seeders/boq_reference.json'), true);
if (!is_array($data)) {
    fwrite(STDERR, "boq_reference.json tidak terbaca.\n");
    exit(1);
}

$sql = "-- Data Referensi BoQ (dibuat tools/boq/build_reference_sql.php dari Excel BoQ). INSERT IGNORE: aman diulang,\n"
    . "-- tidak menimpa baris yang sudah ada atau sudah diubah lewat menu Data Referensi BoQ.\n";
$total = 0;
foreach ($data as $category => $rows) {
    $seen = [];
    $values = [];
    foreach ($rows as $row) {
        if (isset($seen[$row['code']])) {
            continue; // kode ganda di Excel: baris pertama dipakai (sama dengan seeder PHP)
        }
        $seen[$row['code']] = true;
        $values[] = '(' . implode(', ', [
            $pdo->quote($category), $pdo->quote($row['code']), $pdo->quote($row['label']),
            $pdo->quote(json_encode($row['attrs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), (int) $row['sort_order'], 1,
        ]) . ')';
    }
    foreach (array_chunk($values, 100) as $chunk) {
        $sql .= "INSERT IGNORE INTO `boq_ref_items` (`category`, `code`, `label`, `attrs`, `sort_order`, `is_active`) VALUES\n    " . implode(",\n    ", $chunk) . ";\n";
    }
    $total += count($values);
}

$file = $root . '/database/migrations/2026-11-19-boq-referensi-data.sql';
file_put_contents($file, $sql);
echo 'Tulis ' . basename($file) . ' (' . $total . ' baris, ' . round(strlen($sql) / 1024) . " KB)\n";
