<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Notification;

/**
 * Phase 11 — the full-history page behind the topbar bell's "Lihat Semua
 * Notifikasi" link. The dropdown itself (10 most recent, mark-read,
 * mark-all-read) is backed by NotificationApiController + app.js; this page
 * reuses those same JSON endpoints for its actions so there's one source of
 * truth for read/unread state.
 */
class NotificationController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'status' => $request->input('status', ''),
            'page' => (int) $request->input('page', 1),
        ];

        $result = Notification::paginate((int) Auth::id(), $filters);

        $this->view('notifications/index', [
            'pageTitle' => 'Notifikasi',
            'notifications' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'unreadCount' => Notification::unreadCount((int) Auth::id()),
        ]);
    }
}
