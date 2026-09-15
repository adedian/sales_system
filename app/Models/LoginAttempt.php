<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class LoginAttempt extends Model
{
    protected static string $table = 'login_attempts';

    public static function forIdentifier(string $identifier): ?array
    {
        return static::whereFirst('identifier', $identifier);
    }

    public static function isLocked(string $identifier): bool
    {
        $row = static::forIdentifier($identifier);

        if ($row === null || $row['locked_until'] === null) {
            return false;
        }

        return strtotime($row['locked_until']) > time();
    }

    public static function lockedUntil(string $identifier): ?string
    {
        $row = static::forIdentifier($identifier);

        return $row['locked_until'] ?? null;
    }

    public static function registerFailure(string $identifier, int $maxAttempts, int $lockoutMinutes): void
    {
        $row = static::forIdentifier($identifier);

        if ($row === null) {
            static::insert([
                'identifier' => $identifier,
                'attempts' => 1,
                'locked_until' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return;
        }

        $attempts = (int) $row['attempts'] + 1;
        $lockedUntil = null;

        if ($attempts >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
            $attempts = 0;
        }

        Database::execute(
            "UPDATE login_attempts SET attempts = ?, locked_until = ?, updated_at = ? WHERE identifier = ?",
            [$attempts, $lockedUntil, date('Y-m-d H:i:s'), $identifier]
        );
    }

    public static function clear(string $identifier): void
    {
        Database::execute("DELETE FROM login_attempts WHERE identifier = ?", [$identifier]);
    }
}
