<?php

namespace App\Controllers\Api;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Models\MasterData;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\ProcurementStatusHistory;
use App\Models\Role;
use App\Models\User;

class ProcurementApiController extends Controller
{
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** Polled by the procurement dashboard to keep the tab counts live. */
    public function summary(Request $request): void
    {
        $scopeAssignedTo = Acl::hasRole('procurement') ? Auth::id() : null;
        $scopeSalesId = Acl::hasRole('sales') ? Auth::id() : null;

        $this->json([
            'counts' => ProcurementRequest::dashboardCounts($scopeAssignedTo, $scopeSalesId),
            'server_time' => date('c'),
        ]);
    }

    /** Polled by the request detail page to notice out-of-band changes. */
    public function ping(Request $request, array $params): void
    {
        $pr = $this->authorizedRequest((int) $params['id']);
        if ($pr === null) {
            return;
        }

        $this->json([
            'status' => $pr['status'],
            'priority' => $pr['priority'],
            'assigned_to' => (int) $pr['assigned_to'],
            'assigned_to_name' => $pr['assigned_to_name'],
            'updated_at' => $pr['updated_at'],
        ]);
    }

    public function updatePriority(Request $request, array $params): void
    {
        if (!Acl::can('procurement.manage')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $pr = ProcurementRequest::withRelations((int) $params['id']);
        if ($pr === null) {
            $this->json(['error' => 'not_found'], 404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $newPriority = (string) $request->input('priority');
        if (!in_array($newPriority, self::PRIORITIES, true)) {
            $this->json(['error' => 'Prioritas tidak valid.'], 422);

            return;
        }

        $oldPriority = $pr['priority'];
        $now = date('Y-m-d H:i:s');

        ProcurementRequest::update((int) $pr['id'], ['priority' => $newPriority, 'updated_at' => $now]);
        AuditLogger::log((int) Auth::id(), 'procurement_priority_changed', 'procurement_request', (int) $pr['id'], ['priority' => $oldPriority], ['priority' => $newPriority]);

        $priorityMap = MasterData::allAsMap('priorities');

        $this->json([
            'success' => true,
            'priority' => $newPriority,
            'label' => $priorityMap[$newPriority]['name'] ?? $newPriority,
            'color' => $priorityMap[$newPriority]['color'] ?? 'muted',
            'updated_at' => $now,
        ]);
    }

    public function updateDeadline(Request $request, array $params): void
    {
        if (!Acl::can('procurement.manage')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $pr = ProcurementRequest::withRelations((int) $params['id']);
        if ($pr === null) {
            $this->json(['error' => 'not_found'], 404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $date = $this->validateDate((string) $request->input('deadline', ''));
        if ($date === false) {
            $this->json(['error' => 'Format tanggal tidak valid.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        ProcurementRequest::update((int) $pr['id'], ['deadline' => $date ?: null, 'updated_at' => $now]);
        AuditLogger::log((int) Auth::id(), 'procurement_deadline_changed', 'procurement_request', (int) $pr['id'], ['deadline' => $pr['deadline']], ['deadline' => $date ?: null]);

        $this->json([
            'success' => true,
            'deadline' => $date ?: null,
            'is_overdue' => $date !== '' && $date < date('Y-m-d'),
            'updated_at' => $now,
        ]);
    }

    /** Admin-only: hand the request to a different procurement staff, resetting it back to waiting. */
    public function reassign(Request $request, array $params): void
    {
        if (!Acl::can('procurement.manage')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $pr = ProcurementRequest::withRelations((int) $params['id']);
        if ($pr === null) {
            $this->json(['error' => 'not_found'], 404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $userId = (int) $request->input('assigned_to');
        $role = Role::findBySlug('procurement');
        $user = $userId ? User::find($userId) : null;

        if (!$userId || $user === null || (int) $user['is_active'] !== 1 || (int) $user['role_id'] !== (int) ($role['id'] ?? 0)) {
            $this->json(['error' => 'Staff procurement tidak valid atau tidak aktif.'], 422);

            return;
        }

        $oldAssignedTo = (int) $pr['assigned_to'];
        $oldStatus = $pr['status'];
        $now = date('Y-m-d H:i:s');

        ProcurementRequest::update((int) $pr['id'], [
            'assigned_to' => $userId,
            'status' => 'waiting',
            'revision_reason' => null,
            'updated_at' => $now,
        ]);

        ProcurementStatusHistory::record((int) $pr['id'], $oldStatus, 'waiting', Auth::id(), 'Dialihkan ke staff procurement lain.');
        AuditLogger::log((int) Auth::id(), 'procurement_reassigned', 'procurement_request', (int) $pr['id'], [
            'assigned_to' => $oldAssignedTo,
        ], [
            'assigned_to' => $userId,
            'assigned_to_name' => $user['name'],
        ]);

        Notification::create(
            $userId,
            'procurement_request_new',
            'Request Procurement Baru',
            "Lead {$pr['lead_code']} ({$pr['customer_name']}) menunggu pencarian vendor & pricing.",
            '/procurement/' . $pr['id']
        );

        $this->json([
            'success' => true,
            'assigned_to' => $userId,
            'assigned_to_name' => $user['name'],
            'status' => 'waiting',
            'updated_at' => $now,
        ]);
    }

    private function authorizedRequest(int $id): ?array
    {
        $pr = ProcurementRequest::withRelations($id);

        if ($pr === null) {
            $this->json(['error' => 'not_found'], 404);

            return null;
        }

        // Revisi Sub-Fase 3 — Direktur (is_director flag, role stays `sales`)
        // needs to poll/view any request regardless of lead ownership.
        $actor = Auth::user();
        if ($actor && (int) ($actor['is_director'] ?? 0) === 1) {
            return $pr;
        }

        if (Acl::hasRole('procurement') && (int) $pr['assigned_to'] !== Auth::id()) {
            $this->json(['error' => 'forbidden'], 403);

            return null;
        }

        if (Acl::hasRole('sales') && (int) $pr['lead_sales_id'] !== Auth::id()) {
            $this->json(['error' => 'forbidden'], 403);

            return null;
        }

        return $pr;
    }

    /** @return string|false '' means "clear the date" */
    private function validateDate(string $date): string|false
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : false;
    }
}
