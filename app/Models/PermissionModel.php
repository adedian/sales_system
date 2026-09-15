<?php

namespace App\Models;

use App\Core\Model;

class PermissionModel extends Model
{
    protected static string $table = 'permissions';

    public static function findBySlug(string $slug): ?array
    {
        return static::whereFirst('slug', $slug);
    }
}
