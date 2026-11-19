<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Baris Data Referensi BoQ (tabel boq_ref_items): satu tabel generik untuk seluruh katalog
 * dari Excel BoQ. `attrs` berisi JSON; bentuknya per kategori didefinisikan di
 * app/Config/boq_reference.php.
 */
class BoqRefItem extends Model
{
    protected static string $table = 'boq_ref_items';

    public static function decode(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $row['attrs'] = json_decode((string) ($row['attrs'] ?? ''), true) ?: [];

        return $row;
    }

    public static function findIn(string $category, int $id): ?array
    {
        return self::decode(Database::fetch('SELECT * FROM boq_ref_items WHERE id = ? AND category = ?', [$id, $category]));
    }

    public static function codeExists(string $category, string $code, ?int $exceptId = null): bool
    {
        $row = Database::fetch('SELECT id FROM boq_ref_items WHERE category = ? AND code = ?', [$category, $code]);

        return $row !== null && ($exceptId === null || (int) $row['id'] !== $exceptId);
    }

    /** @return array<string,array{total:int,active:int}> */
    public static function counts(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT category, COUNT(*) AS total, SUM(is_active) AS active FROM boq_ref_items GROUP BY category') as $r) {
            $out[$r['category']] = ['total' => (int) $r['total'], 'active' => (int) $r['active']];
        }

        return $out;
    }

    /** @return array{rows:array,total:int,page:int,perPage:int,totalPages:int} */
    public static function search(string $category, array $filters): array
    {
        $where = ['category = ?'];
        $params = [$category];
        if (!empty($filters['q'])) {
            $where[] = '(code LIKE ? OR label LIKE ? OR attrs LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = (int) (Database::fetch("SELECT COUNT(*) AS total FROM boq_ref_items {$whereSql}", $params)['total'] ?? 0);
        $perPage = max(1, (int) ($filters['per_page'] ?? 25));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT * FROM boq_ref_items {$whereSql} ORDER BY sort_order ASC, id ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => array_map([self::class, 'decode'], $rows), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    /** Nilai turunan yang harus ikut konsisten saat baris diubah (rumus total di Excel). */
    public static function derive(string $category, array $attrs): array
    {
        if ($category === 'jasa_tier') {
            $attrs['engineering_total'] = (float) ($attrs['survey'] ?? 0) + (float) ($attrs['drawing'] ?? 0) + (float) ($attrs['blueprint'] ?? 0)
                + (float) ($attrs['om'] ?? 0) + (float) ($attrs['troubleshooting'] ?? 0);
            $attrs['training_total'] = (float) ($attrs['training_day'] ?? 0) * (float) ($attrs['training_man'] ?? 0) * (float) ($attrs['training_rate'] ?? 0);
        }

        return $attrs;
    }

    public static function nextSortOrder(string $category): int
    {
        return (int) (Database::fetch('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM boq_ref_items WHERE category = ?', [$category])['n'] ?? 1);
    }
}
