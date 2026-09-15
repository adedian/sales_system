<?php

namespace App\Core;

use App\Models\AuditLog;

class AuditLogger
{
    public static function log(
        ?int $userId,
        string $action,
        string $module,
        ?int $recordId = null,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        $request = new Request();

        AuditLog::insert([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'old_data' => $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data' => $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
