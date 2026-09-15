<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Models\Notification;

/**
 * Backs the topbar notification bell (see app/Views/partials/topbar.php).
 * Polled the same way Lead/Queue/Engineer detail pages poll for out-of-band
 * changes — there is no websocket layer in this stack.
 */
class NotificationApiController extends Controller
{
    public function summary(Request $request): void
    {
        $this->json(['unread_count' => Notification::unreadCount((int) Auth::id())]);
    }

    public function index(Request $request): void
    {
        $rows = Notification::recentForUser((int) Auth::id(), 10);

        $this->json(['notifications' => array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'message' => $row['message'],
                'link' => $row['link'],
                'is_read' => (bool) $row['is_read'],
                'created_at' => $row['created_at'],
            ];
        }, $rows)]);
    }

    public function markRead(Request $request, array $params): void
    {
        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        Notification::markRead((int) $params['id'], (int) Auth::id());

        $this->json(['success' => true, 'unread_count' => Notification::unreadCount((int) Auth::id())]);
    }

    public function markAllRead(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        Notification::markAllRead((int) Auth::id());

        $this->json(['success' => true, 'unread_count' => 0]);
    }
}
