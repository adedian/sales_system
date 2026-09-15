<?php

namespace App\Middleware;

use App\Core\Acl;
use App\Core\View;

class PermissionMiddleware
{
    public function handle(?string $permission = null): bool
    {
        if ($permission === null || Acl::can($permission)) {
            return true;
        }

        http_response_code(403);
        View::render('errors/403');

        return false;
    }
}
