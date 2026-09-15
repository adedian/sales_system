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

        return true;
    }
}
