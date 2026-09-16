<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Product extends Model
{
    protected static string $table = 'products';
    protected static bool $softDeletes = true;

    private static function baseSelect(): string
    {
        return "SELECT products.*, category.name AS category_name, unit.name AS unit_name, unit.code AS unit_code
                FROM products
                LEFT JOIN product_categories category ON category.id = products.category_id
                LEFT JOIN units unit ON unit.id = products.unit_id";
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE products.id = ? AND products.deleted_at IS NULL', [$id]);
    }

    /**
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(array $filters): array
    {
        $where = ['products.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(products.product_code LIKE ? OR products.name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like);
        }

        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'products.is_active = 1';
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $where[] = 'products.is_active = 0';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = (int) (Database::fetch("SELECT COUNT(*) AS total FROM products {$whereSql}", $params)['total'] ?? 0);

        $perPage = max(1, (int) ($filters['per_page'] ?? 15));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            self::baseSelect() . " {$whereSql} ORDER BY products.name ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    /** Active products for the "Pilih dari Katalog" quick-fill on Proposal/Procurement item forms. */
    public static function activeList(): array
    {
        return Database::fetchAll(self::baseSelect() . ' WHERE products.is_active = 1 AND products.deleted_at IS NULL ORDER BY products.name ASC');
    }

    /**
     * Insert then stamp product_code = PRD-000001 inside one transaction —
     * same race-safe pattern as Vendor::createWithCode().
     */
    public static function createWithCode(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $placeholder = 'TMP-' . bin2hex(random_bytes(8));
            $id = self::insert(['product_code' => $placeholder] + $data);
            $code = sprintf('PRD-%06d', $id);
            self::update($id, ['product_code' => $code]);

            return ['id' => $id, 'product_code' => $code];
        });
    }
}
