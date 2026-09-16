<?php

namespace App\Models;

use App\Core\Database;

/**
 * Phase 14 — generic key/value system settings (company profile, document
 * numbering prefixes, SLA threshold, per-module notification toggles). Read
 * by SettingController (the /settings page), Lead/EngineerAssignment/
 * ProcurementRequest/Proposal code-generation, the Phase 12 dashboard's
 * aging table, and Notification::create()'s per-module toggle check.
 */
class Setting
{
    /** @var array<string,string>|null in-process cache — settings are read far more often than written */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::loadCache();

        return self::$cache[$key] ?? $default;
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return $value === '1' || strtolower($value) === 'true';
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value !== null && $value !== '' ? (int) $value : $default;
    }

    /** @return array<string,string> every setting whose key starts with $prefix, key unprefixed-stripped kept as-is */
    public static function group(string $prefix): array
    {
        self::loadCache();

        $result = [];
        foreach (self::$cache as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public static function set(string $key, string $value, ?int $updatedBy = null): void
    {
        $exists = Database::fetch('SELECT id FROM settings WHERE `key` = ?', [$key]);

        if ($exists) {
            Database::execute('UPDATE settings SET `value` = ?, updated_by = ?, updated_at = NOW() WHERE `key` = ?', [$value, $updatedBy, $key]);
        } else {
            Database::execute('INSERT INTO settings (`key`, `value`, updated_by, updated_at) VALUES (?, ?, ?, NOW())', [$key, $value, $updatedBy]);
        }

        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    /** Persists several settings at once (one form submit from /settings) — same cache-refresh guarantee as set(). */
    public static function setMany(array $values, ?int $updatedBy = null): void
    {
        foreach ($values as $key => $value) {
            self::set($key, (string) $value, $updatedBy);
        }
    }

    private static function loadCache(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cache = [];
        foreach (Database::fetchAll('SELECT `key`, `value` FROM settings') as $row) {
            self::$cache[$row['key']] = $row['value'];
        }
    }
}
