<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Generic, module-agnostic notification row polled by the topbar bell (see
 * app/Views/partials/topbar.php + public/assets/js/app.js). No websockets in
 * this stack — "realtime" here means short-interval polling, same pattern as
 * the live status/priority fields on Lead/Queue/Engineer detail pages.
 */
class Notification extends Model
{
    protected static string $table = 'notifications';

    /**
     * Phase 14: gated by the notify.<module> toggle in Settings (see
     * SettingController). $type's prefix up to the first underscore picks
     * the module (lead_won -> notify.lead, proposal_approved -> notify.proposal,
     * etc.) — an unrecognized prefix is never blocked, so this fails open.
     */
    public static function create(int $userId, string $type, string $title, ?string $message = null, ?string $link = null): int
    {
        $module = explode('_', $type, 2)[0];
        if (in_array($module, ['lead', 'prelim', 'proposal', 'engineer', 'procurement'], true) && !Setting::getBool("notify_{$module}", true)) {
            return 0;
        }

        return static::insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) (Database::fetch(
            'SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        )['total'] ?? 0);
    }

    public static function recentForUser(int $userId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT {$limit}",
            [$userId]
        );
    }

    /**
     * Full paginated history for the Notification Center page (`/notifications`)
     * — the topbar dropdown only ever shows the 10 most recent via recentForUser().
     *
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function paginate(int $userId, array $filters = []): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];

        if (($filters['status'] ?? '') === 'unread') {
            $where[] = 'is_read = 0';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS total FROM notifications {$whereSql}",
            $params
        )['total'] ?? 0);

        $perPage = max(1, (int) ($filters['per_page'] ?? 20));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT * FROM notifications {$whereSql} ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    public static function markRead(int $id, int $userId): int
    {
        return Database::execute(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public static function markAllRead(int $userId): int
    {
        return Database::execute(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
    }
}
