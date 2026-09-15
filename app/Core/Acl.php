<?php

namespace App\Core;

use App\Models\Role;

class Acl
{
    /**
     * Cached for the lifetime of the current request only (static, not
     * session). Role permissions can be edited at runtime via Role
     * Management, so caching across requests would let a logged-in user
     * keep acting on a stale permission set until they log in again.
     *
     * @var array<int, string[]>
     */
    private static array $requestCache = [];

    public static function can(string $permission): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        return in_array($permission, self::permissions((int) $user['role_id']), true);
    }

    public static function hasRole(string $roleSlug): bool
    {
        $user = Auth::user();

        return $user !== null && ($user['role_slug'] ?? null) === $roleSlug;
    }

    /**
     * @return string[]
     */
    public static function permissions(int $roleId): array
    {
        if (!isset(self::$requestCache[$roleId])) {
            self::$requestCache[$roleId] = Role::permissionSlugs($roleId);
        }

        return self::$requestCache[$roleId];
    }

    public static function forgetCache(): void
    {
        self::$requestCache = [];
    }
}
