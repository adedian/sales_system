<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Foto dokumentasi Survey. File asli disimpan utuh di
 * storage/uploads/survey/<id>/ (di luar folder publik, tidak bisa dibuka
 * lewat URL langsung). Dibuat juga "versi web" JPEG maks 1600px untuk
 * pratinjau dan PDF supaya PDF ringan dan gambar tidak pecah/terpotong
 * (rasio asli dipertahankan). Foto asli tetap menjadi lampiran Lead.
 */
class SurveyPhoto extends Model
{
    protected static string $table = 'survey_photos';

    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_PER_SURVEY = 80;
    public const MAX_PIXELS = 60_000_000;
    public const WEB_MAX_SIDE = 1600;

    private const MIME_EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function baseDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/';
    }

    public static function absolute(string $relative): string
    {
        return self::baseDir() . $relative;
    }

    /** @return array<int,array<string,mixed>> */
    public static function forSurvey(int $surveyId, ?string $category = null): array
    {
        $sql = 'SELECT sp.*, u.name AS uploaded_by_name FROM survey_photos sp
                LEFT JOIN users u ON u.id = sp.uploaded_by WHERE sp.survey_id = ?';
        $params = [$surveyId];
        if ($category !== null) {
            $sql .= ' AND sp.category = ?';
            $params[] = $category;
        }

        return Database::fetchAll($sql . ' ORDER BY FIELD(sp.category, \'site_overview\', \'layout\', \'notes\'), sp.sort_order, sp.id', $params);
    }

    public static function countForSurvey(int $surveyId): int
    {
        return (int) (Database::fetch('SELECT COUNT(*) AS c FROM survey_photos WHERE survey_id = ?', [$surveyId])['c'] ?? 0);
    }

    /**
     * Simpan satu file hasil upload. @return array{ok:bool,message:string,id?:int}
     *
     * @param array{name:string,tmp_name:string,error:int,size:int} $file
     */
    public static function addFromUpload(int $surveyId, array $file, string $category, ?int $userId): array
    {
        $name = (string) ($file['name'] ?? 'foto');

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => "{$name}: gagal diunggah (kode {$file['error']})."];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'message' => "{$name}: berkas tidak valid."];
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'message' => "{$name}: ukuran melebihi 10MB."];
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::MIME_EXT[$mime])) {
            return ['ok' => false, 'message' => "{$name}: hanya foto JPG, PNG, atau WEBP yang diizinkan."];
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return ['ok' => false, 'message' => "{$name}: bukan gambar yang valid."];
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            return ['ok' => false, 'message' => "{$name}: resolusi terlalu besar."];
        }

        $dirRel = 'survey/' . $surveyId;
        $dir = self::absolute($dirRel);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'Gagal menyiapkan folder penyimpanan foto.'];
        }

        $token = bin2hex(random_bytes(12));
        $origRel = $dirRel . '/' . $token . '.' . self::MIME_EXT[$mime];
        $webRel = $dirRel . '/' . $token . '_web.jpg';

        if (!move_uploaded_file($file['tmp_name'], self::absolute($origRel))) {
            return ['ok' => false, 'message' => "{$name}: gagal menyimpan file."];
        }

        $web = self::makeWebVersion(self::absolute($origRel), $mime, self::absolute($webRel));
        if ($web === null) {
            @unlink(self::absolute($origRel));

            return ['ok' => false, 'message' => "{$name}: gambar tidak dapat diproses."];
        }

        $id = self::insert([
            'survey_id' => $surveyId,
            'category' => $category,
            'caption' => null,
            'sort_order' => (int) (Database::fetch('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM survey_photos WHERE survey_id = ? AND category = ?', [$surveyId, $category])['n'] ?? 1),
            'original_name' => mb_substr($name, 0, 255),
            'original_path' => $origRel,
            'web_path' => $webRel,
            'mime_type' => $mime,
            'file_size' => (int) $file['size'],
            'width' => $web['w'],
            'height' => $web['h'],
            'uploaded_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'message' => $name, 'id' => $id];
    }

    /**
     * JPEG maks 1600px, orientasi EXIF dikoreksi, latar putih untuk PNG
     * transparan. @return array{w:int,h:int}|null
     */
    private static function makeWebVersion(string $src, string $mime, string $dest): ?array
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png' => @imagecreatefrompng($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default => false,
        };
        if ($img === false) {
            return null;
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($src);
            $rot = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($rot !== 0) {
                $rotated = imagerotate($img, $rot, 0);
                if ($rotated !== false) {
                    $img = $rotated;
                }
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1.0, self::WEB_MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagejpeg($out, $dest, 82);
        imagedestroy($img);
        imagedestroy($out);

        return $ok ? ['w' => $nw, 'h' => $nh] : null;
    }

    public static function updateMeta(int $id, ?string $caption, string $category): void
    {
        Database::execute('UPDATE survey_photos SET caption = ?, category = ? WHERE id = ?', [$caption, $category, $id]);
    }

    public static function remove(array $photo): void
    {
        foreach (['original_path', 'web_path'] as $col) {
            $path = self::absolute((string) $photo[$col]);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        Database::execute('DELETE FROM survey_photos WHERE id = ?', [(int) $photo['id']]);
    }

    /** Data URI versi web — dompdf tidak mengambil gambar dari URL (isRemoteEnabled = false). */
    public static function dataUri(array $photo): ?string
    {
        $path = self::absolute((string) $photo['web_path']);
        if (!is_file($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($path));
    }
}
