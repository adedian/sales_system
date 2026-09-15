<?php

namespace App\Middleware;

use App\Core\Auth;

class GuestMiddleware
{
    public function handle(?string $arg = null): bool
    {
        if (Auth::check()) {
            header('Location: ' . url('/dashboard'));
            exit;
        }

        return true;
    }
}
