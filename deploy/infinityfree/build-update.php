<?php
/**
 * Bangun PAKET UPDATE untuk hosting yang sudah berjalan (bukan instalasi baru).
 *
 *   php deploy/infinityfree/build-update.php
 *
 * Hasil di deploy/_out/update/:
 *   update.sql                 impor lewat phpMyAdmin pada database produksi yang SUDAH ADA
 *                              (idempotent: aman dijalankan ulang; TIDAK menghapus data)
 *   sales-system-update.zip    kode saja (app, routes, public, bootstrap.php) — TANPA .env,
 *                              TANPA storage/uploads, TANPA vendor (tidak berubah)
 *
 * install.sql (build.php) hanya untuk instalasi pertama; mengimpornya lagi menimpa data.
 */

$root = dirname(__DIR__, 2);
$out = $root . '/deploy/_out/update';

require $root . '/vendor/autoload.php';
\App\Core\Env::load($root . '/.env');
$pdo = \App\Core\Database::connection();

@mkdir($out, 0775, true);

// ---------------------------------------------------------------- update.sql
$sql = "-- Update Sistem Internal Sales — " . date('Y-m-d H:i') . "\n"
    . "-- Impor SEKALI lewat phpMyAdmin pada database yang sudah berjalan. Aman diulang.\n"
    . "-- Isi: skema tambahan (Survey, Survey Report, versi proposal, user_roles), izin baru,\n"
    . "--      dan migrasi multi-role (role baru + pemetaan pengguna).\n\n"
    . "SET NAMES utf8mb4;\n\n";

// 1) skema: HANYA baris yang ditambahkan sejak versi yang sudah ter-deploy. schema.sql penuh tidak
//    boleh diputar ulang di produksi (bagian lama memakai ADD CONSTRAINT tanpa IF NOT EXISTS).
$since = getopt('', ['since::'])['since'] ?? '2fe4876';
$diff = [];
exec('git -C ' . escapeshellarg($root) . ' diff ' . escapeshellarg($since) . ' -- database/schema.sql', $diff, $rc);
if ($rc !== 0 || $diff === []) {
    fwrite(STDERR, "Tidak ada perubahan skema sejak $since atau git gagal.\n");
    exit(1);
}
$added = [];
foreach ($diff as $line) {
    if (str_starts_with($line, '---') || str_starts_with($line, '+++')) {
        continue;
    }
    if ($line !== '' && $line[0] === '-') {
        fwrite(STDERR, "schema.sql: ada baris yang DIHAPUS/DIUBAH sejak $since; delta otomatis tidak aman, tulis migrasi manual.\n");
        exit(1);
    }
    if ($line !== '' && $line[0] === '+') {
        $added[] = rtrim(substr($line, 1), "\r");
    }
}
$schemaDelta = trim(implode("\n", $added));
if (preg_match('/^\s*(DROP|TRUNCATE|DELETE|UPDATE|INSERT)\b/mi', $schemaDelta)
    || preg_match('/ADD\s+(CONSTRAINT|KEY|UNIQUE|INDEX)\b(?!\s+IF NOT EXISTS)/i', $schemaDelta)) {
    fwrite(STDERR, "Delta skema memuat perintah yang tidak idempotent; periksa sebelum membangun update.\n");
    exit(1);
}
$sql .= "-- ==== 1. Skema (tambahan sejak $since) ====\nSET FOREIGN_KEY_CHECKS = 0;\n" . $schemaDelta . "\nSET FOREIGN_KEY_CHECKS = 1;\n\n";
// 2) izin baru (hanya menambah)
$sql .= "-- ==== 2. Izin ====\n";
$rows = $pdo->query('SELECT slug, module, description FROM permissions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$values = [];
foreach ($rows as $r) {
    $values[] = '(' . implode(', ', [$pdo->quote($r['slug']), $pdo->quote((string) $r['module']), $pdo->quote((string) $r['description'])]) . ')';
}
$sql .= "INSERT IGNORE INTO `permissions` (`slug`, `module`, `description`) VALUES\n    " . implode(",\n    ", $values) . ";\n\n";

// 3) migrasi multi-role (role baru, izin role baru, pemetaan pengguna)
$sql .= "-- ==== 3. Migrasi multi-role ====\n" . trim((string) file_get_contents($root . '/database/migrations/2026-11-17-multi-role.sql')) . "\n\n";

// 4) izin role yang sudah ada di produksi (hanya menambah; lokal = acuan)
$sql .= "-- ==== 4. Izin role (hanya menambah) ====\n";
$pairs = $pdo->query(
    "SELECT r.slug AS rs, p.slug AS ps FROM role_permissions rp
     JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id
     WHERE r.slug IN ('super-admin', 'admin-sales', 'sales', 'manager', 'procurement', 'engineer')
     ORDER BY r.slug, p.slug"
)->fetchAll(PDO::FETCH_ASSOC);
$union = [];
foreach ($pairs as $i => $p) {
    $union[] = ($i === 0
        ? 'SELECT ' . $pdo->quote($p['rs']) . ' AS rs, ' . $pdo->quote($p['ps']) . ' AS ps'
        : 'SELECT ' . $pdo->quote($p['rs']) . ', ' . $pdo->quote($p['ps']));
}
$sql .= "INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)\n"
    . "SELECT r.`id`, p.`id` FROM (\n    " . implode("\n    UNION ALL ", $union) . "\n) m\n"
    . "JOIN `roles` r ON r.`slug` = m.`rs` JOIN `permissions` p ON p.`slug` = m.`ps`;\n\n";

// 5) migrasi menurunkan flag dari user_roles; pastikan lagi di akhir
$sql .= "-- ==== Selesai ====\n";
file_put_contents($out . '/update.sql', $sql);
echo 'update.sql OK (' . round(strlen($sql) / 1024) . " KB)\n";

// ---------------------------------------------------------------- zip kode
$zipPath = $out . '/sales-system-update.zip';
@unlink($zipPath);
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE);

$add = static function (string $abs, string $rel) use ($zip): void {
    $zip->addFile($abs, str_replace('\\', '/', $rel));
};
foreach (['app', 'routes', 'public'] as $dir) {
    $base = $root . '/' . $dir;
    $len = strlen($root) + 1;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $rel = substr($f->getPathname(), $len);
        // jangan ikut: berkas sementara/preview
        if (preg_match('#(^|[\\\\/])(_preview|\.git)([\\\\/]|$)#', $rel)) {
            continue;
        }
        $add($f->getPathname(), $rel);
    }
}
$add($root . '/bootstrap.php', 'bootstrap.php');
$zip->close();
echo 'zip OK (' . round(filesize($zipPath) / 1048576, 1) . " MB)\nSelesai: $out\n";
