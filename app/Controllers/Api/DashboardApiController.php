<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\AuditLog;
use App\Models\User;

class DashboardApiController extends Controller
{
    /**
     * Lightweight JSON endpoint polled by the dashboard to keep counters fresh.
     * Phase 2+ will extend this with lead/queue/engineer counts.
     */
    public function summary(): void
    {
        $this->json([
            'active_users' => User::countActive(),
            'recent_activity_count' => count(AuditLog::recent(5)),
            'server_time' => date('c'),
        ]);
    }
}
