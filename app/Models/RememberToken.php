<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class RememberToken extends Model
{
    protected static string $table = 'remember_tokens';

    public static function findBySelector(string $selector): ?array
    {
        return static::whereFirst('selector', $selector);
    }

    public static function deleteBySelector(string $selector): void
    {
        Database::execute('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
    }

    public static function deleteAllForUser(int $userId): void
    {
        Database::execute('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
    }

    public static function purgeExpired(): void
    {
        Database::execute('DELETE FROM remember_tokens WHERE expires_at < NOW()');
    }
}
