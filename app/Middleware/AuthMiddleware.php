<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\View;

class AuthMiddleware
{
    public function handle(?string $arg = null): bool
    {
        $currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $isApi = str_contains($currentPath, '/api/');

        if (!Auth::check()) {
            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'unauthenticated']);
                exit;
            }

            header('Location: ' . url('/login'));
            exit;
        }

        // A password set by an admin (new account, or a manual reset) is
        // temporary — must_change_password was being flagged and shown as a
        // banner, but nothing actually stopped the user navigating past it.
        $user = Auth::user();
        $exempt = $isApi || str_ends_with($currentPath, '/change-password') || str_ends_with($currentPath, '/logout');
        if (!$exempt && $user !== null && !empty($user['must_change_password'])) {
            header('Location: ' . url('/change-password'));
            exit;
        }

        return true;
    }
}
