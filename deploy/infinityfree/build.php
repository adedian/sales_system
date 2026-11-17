<?php
/**
 * Bangun paket deploy InfinityFree.
 *
 *   php deploy/infinityfree/build.php [--url=https://x.infinityfreeapp.com]
 *        [--db-host=sqlXXX.infinityfree.com] [--db-name=if0_123_sales]
 *        [--db-user=if0_123] [--db-pass=...]
 *
 * Hasil di deploy/_out/: htdocs/ (upload isinya), install.sql (impor via phpMyAdmin),
 * CREDENTIALS.txt (password superadmin acak), sales-system-htdocs.zip.
 * Data lokal TIDAK ikut: DB dibuat dari schema.sql + seed di database sementara.
 */

$root = dirname(__DIR__, 2);
$out = $root . '/deploy/_out';
$opt = getopt('', ['url::', 'db-host::', 'db-name::', 'db-user::', 'db-pass::']);

function rrmdir(string $d): void
{
    if (!is_dir($d)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($d);
}

function rcopy(string $src, string $dst): void
{
    @mkdir($dst, 0775, true);
    foreach (new DirectoryIterator($src) as $f) {
        if ($f->isDot()) {
            continue;
        }
        $t = $dst . '/' . $f->getFilename();
        $f->isDir() ? rcopy($f->getPathname(), $t) : copy($f->getPathname(), $t);
    }
}

// ---- kredensial DB lokal (hanya untuk DB sementara) ----
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if ($l[0] === '#' || !str_contains($l, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $l, 2);
    $env[trim($k)] = trim($v, " \"'");
}
$tmp = 'sales_deploy_tmp';
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']}", $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `$tmp`");
$pdo->exec("CREATE DATABASE `$tmp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// ---- schema (tanpa CREATE DATABASE / USE) ----
$schema = file_get_contents($root . '/database/schema.sql');
$schema = preg_replace('/^\s*(CREATE DATABASE[^;]*;|USE\s+`[^`]+`;)\s*$/mi', '', $schema);
$pdo->exec("USE `$tmp`");
$pdo->exec($schema);
echo "schema OK\n";

// ---- seed lewat wrapper (Env di-set manual agar tidak memuat .env lokal) ----
$wrapper = sys_get_temp_dir() . '/seed_wrapper_' . getmypid() . '.php';
file_put_contents(
    $wrapper,
    '<?php require ' . var_export($root . '/vendor/autoload.php', true) . ';'
    . '\App\Core\Env::load("/nonexistent");'
    . '$_ENV["DB_HOST"]=' . var_export($env['DB_HOST'], true) . ';$_ENV["DB_PORT"]=' . var_export($env['DB_PORT'], true) . ';'
    . '$_ENV["DB_DATABASE"]=' . var_export($tmp, true) . ';$_ENV["DB_USERNAME"]=' . var_export($env['DB_USERNAME'], true) . ';'
    . '$_ENV["DB_PASSWORD"]=' . var_export($env['DB_PASSWORD'], true) . ';'
    . 'require ' . var_export($root . '/database/seeders/seed.php', true) . ';'
);
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($wrapper) . ' > ' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'), $rc);
unlink($wrapper);
if ($rc !== 0) {
    fwrite(STDERR, "seed gagal\n");
    exit(1);
}
echo "seed OK\n";

// ---- amankan akun: password default ChangeMe@123 TIDAK boleh ke internet ----
$pass = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Ax'), '=') . '#9';
$pdo->prepare("UPDATE users SET password=?, must_change_password=1, is_active=1 WHERE username='superadmin'")
    ->execute([password_hash($pass, PASSWORD_DEFAULT)]);
$pdo->exec("UPDATE users SET is_active=0 WHERE username<>'superadmin'");
$pdo->exec('TRUNCATE TABLE login_attempts');

// ---- dump ----
@mkdir($out, 0775, true);
rrmdir($out . '/htdocs');
@unlink($out . '/install.sql');
$dump = 'C:/xampp/mysql/bin/mysqldump.exe';
$cmd = escapeshellarg($dump) . " -h{$env['DB_HOST']} -P{$env['DB_PORT']} -u{$env['DB_USERNAME']}"
    . ($env['DB_PASSWORD'] !== '' ? ' -p' . escapeshellarg($env['DB_PASSWORD']) : '')
    . " --default-character-set=utf8mb4 --skip-comments --skip-add-locks --skip-lock-tables --no-tablespaces $tmp > " . escapeshellarg($out . '/install.sql');
passthru($cmd, $rc);
if ($rc !== 0 || filesize($out . '/install.sql') < 1000) {
    fwrite(STDERR, "mysqldump gagal\n");
    exit(1);
}
$pdo->exec("DROP DATABASE `$tmp`");
echo 'dump OK (' . round(filesize($out . '/install.sql') / 1024) . " KB)\n";

// ---- stage file aplikasi ----
$h = $out . '/htdocs';
mkdir($h, 0775, true);
rcopy($root . '/app', $h . '/app');
rcopy($root . '/routes', $h . '/routes');
rcopy($root . '/vendor', $h . '/vendor');
rcopy($root . '/public', $h . '/public');
copy($root . '/bootstrap.php', $h . '/bootstrap.php');
copy(__DIR__ . '/htaccess-root', $h . '/.htaccess');
foreach (['logs', 'uploads'] as $d) {
    mkdir("$h/storage/$d", 0775, true);
    file_put_contents("$h/storage/$d/.gitkeep", '');
}
file_put_contents($h . '/storage/.htaccess', "Require all denied\n");

$vars = [
    '__APP_URL__' => rtrim($opt['url'] ?? 'https://GANTI-DOMAIN-ANDA.infinityfreeapp.com', '/'),
    '__DB_HOST__' => $opt['db-host'] ?? 'sqlXXX.infinityfree.com',
    '__DB_NAME__' => $opt['db-name'] ?? 'if0_XXXXXXXX_sales',
    '__DB_USER__' => $opt['db-user'] ?? 'if0_XXXXXXXX',
    '__DB_PASS__' => $opt['db-pass'] ?? 'GANTI_PASSWORD_DB',
];
file_put_contents($h . '/.env', strtr(file_get_contents(__DIR__ . '/env.production.template'), $vars));

file_put_contents($out . '/CREDENTIALS.txt', "Login pertama: superadmin / $pass\n(wajib ganti password saat login pertama; akun lain dinonaktifkan, aktifkan lewat menu Pengguna)\n");

// ---- zip ----
$zipPath = $out . '/sales-system-htdocs.zip';
@unlink($zipPath);
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE);
$len = strlen($h) + 1;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($h, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->isFile()) {
        $zip->addFile($f->getPathname(), str_replace('\\', '/', substr($f->getPathname(), $len)));
    }
}
$zip->close();
echo 'ZIP OK (' . round(filesize($zipPath) / 1048576, 1) . " MB)\nSelesai: $out\n";
