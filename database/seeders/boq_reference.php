<?php

/**
 * Mengisi tabel boq_ref_items dari database/seeders/boq_reference.json
 * (dibuat tools/boq/extract_reference.py dari Excel BoQ).
 *
 * Idempotent: baris yang sudah ada (category + code) DILEWATI secara default supaya
 * perubahan yang dibuat lewat menu Data Referensi BoQ tidak tertimpa.
 * Pakai --overwrite untuk menimpa SEMUA nilai dengan isi Excel.
 *
 *   php database/seeders/boq_reference.php [--overwrite]
 */

use App\Core\Database;
use App\Core\Env;

require_once __DIR__ . '/../../vendor/autoload.php';
Env::load(__DIR__ . '/../../.env');

$overwrite = in_array('--overwrite', $argv ?? [], true);
$file = __DIR__ . '/boq_reference.json';
$data = json_decode((string) file_get_contents($file), true);
if (!is_array($data)) {
    fwrite(STDERR, "boq_reference.json tidak terbaca.\n");
    exit(1);
}

$pdo = Database::connection();
$insert = $pdo->prepare(
    'INSERT INTO boq_ref_items (category, code, label, attrs, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)'
    . ($overwrite ? ' ON DUPLICATE KEY UPDATE label = VALUES(label), attrs = VALUES(attrs), sort_order = VALUES(sort_order)' : ' ON DUPLICATE KEY UPDATE id = id')
);

echo "== Data Referensi BoQ ==\n";
$pdo->beginTransaction();
foreach ($data as $category => $rows) {
    $before = (int) Database::fetch('SELECT COUNT(*) AS n FROM boq_ref_items WHERE category = ?', [$category])['n'];
    $seen = [];
    $dup = 0;
    foreach ($rows as $row) {
        $key = $row['code'];
        if (isset($seen[$key])) {
            $dup++;
            continue; // kode ganda di Excel: baris pertama dipakai
        }
        $seen[$key] = true;
        $insert->execute([
            $category,
            $row['code'],
            $row['label'],
            json_encode($row['attrs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (int) $row['sort_order'],
        ]);
    }
    $after = (int) Database::fetch('SELECT COUNT(*) AS n FROM boq_ref_items WHERE category = ?', [$category])['n'];
    printf("  %-18s %4d baris di Excel, +%d baru, total %d%s\n", $category, count($rows), $after - $before, $after, $dup ? " ({$dup} kode ganda dilewati)" : '');
}
// Koreksi data Excel yang jelas salah (extract_reference.py menandai dengan attrs.phase_excel): terapkan juga ke baris
// yang sudah ada, tetapi hanya bila pengguna belum mengubah fasanya sendiri.
foreach ($data['inverter'] ?? [] as $row) {
    if (!isset($row['attrs']['phase_excel'])) {
        continue;
    }
    $cur = Database::fetch("SELECT id, attrs FROM boq_ref_items WHERE category = 'inverter' AND code = ?", [$row['code']]);
    $attrs = $cur ? (json_decode((string) $cur['attrs'], true) ?: []) : null;
    if ($attrs !== null && (int) ($attrs['phase'] ?? 0) === (int) $row['attrs']['phase_excel']) {
        $attrs['phase_excel'] = $row['attrs']['phase_excel'];
        $attrs['phase'] = $row['attrs']['phase'];
        Database::execute('UPDATE boq_ref_items SET attrs = ? WHERE id = ?', [json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $cur['id']]);
        echo "  ~ fasa {$row['code']} dikoreksi {$row['attrs']['phase_excel']} -> {$row['attrs']['phase']} (salah di Excel)\n";
    }
}
$pdo->commit();
echo "Selesai.\n";
