<?php

namespace App\Controllers\Api;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Models\EngineerAssignment;
use App\Models\EngineerAssignmentStatusHistory;
use App\Models\MasterData;
use App\Models\Notification;
use App\Models\User;

class EngineerApiController extends Controller
{
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** Polled by the engineer dashboard to keep the tab counts live. */
    public function summary(Request $request): void
    {
        $actor = Auth::user();
        $inEngineerPool = $actor && ((int) ($actor['is_engineer'] ?? 0) === 1 || (int) ($actor['is_sales_engineer'] ?? 0) === 1);
        $scopeEngineerId = (Acl::hasRole('engineer-sales') || $inEngineerPool) ? Auth::id() : null;
        $scopeSalesId = (!$inEngineerPool && Acl::hasRole('sales')) ? Auth::id() : null;

        $this->json([
            'counts' => EngineerAssignment::dashboardCounts($scopeEngineerId, $scopeSalesId),
            'server_time' => date('c'),
        ]);
    }

    /** Polled by the assignment detail page to notice out-of-band changes. */
    public function ping(Request $request, array $params): void
    {
        $assignment = $this->authorizedAssignment((int) $params['id']);
        if ($assignment === null) {
            return;
        }

        $this->json([
            'status' => $assignment['status'],
            'priority' => $assignment['priority'],
            'engineer_id' => (int) $assignment['engineer_id'],
            'engineer_name' => $assignment['engineer_name'],
            'updated_at' => $assignment['updated_at'],
        ]);
    }

    /** Admin-only correction — mirrors QueueApiController::updatePriority(). */
    public function updatePriority(Request $request, array $params): void
    {
        if (!Acl::can('engineer.manage')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $assignment = EngineerAssignment::withRelations((int) $params['id']);
        if ($assignment === null) {
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

        $oldPriority = $assignment['priority'];
        $now = date('Y-m-d H:i:s');

        EngineerAssignment::update((int) $assignment['id'], ['priority' => $newPriority, 'updated_at' => $now]);
        AuditLogger::log((int) Auth::id(), 'engineer_assignment_priority_changed', 'engineer_assignment', (int) $assignment['id'], ['priority' => $oldPriority], ['priority' => $newPriority]);

        $priorityMap = MasterData::allAsMap('priorities');

        $this->json([
            'success' => true,
            'priority' => $newPriority,
            'label' => $priorityMap[$newPriority]['name'] ?? $newPriority,
            'color' => $priorityMap[$newPriority]['color'] ?? 'muted',
            'updated_at' => $now,
        ]);
    }

    /** Admin-only correction — mirrors QueueApiController::updateDeadline(). */
    public function updateDeadline(Request $request, array $params): void
    {
        if (!Acl::can('engineer.manage')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $assignment = EngineerAssignment::withRelations((int) $params['id']);
        if ($assignment === null) {
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
        EngineerAssignment::update((int) $assignment['id'], ['deadline' => $date ?: null, 'updated_at' => $now]);
        AuditLogger::log((int) Auth::id(), 'engineer_assignment_deadline_changed', 'engineer_assignment', (int) $assignment['id'], ['deadline' => $assignment['deadline']], ['deadline' => $date ?: null]);

        $this->json([
            'success' => true,
            'deadline' => $date ?: null,
            'is_overdue' => $date !== '' && $date < date('Y-m-d'),
            'updated_at' => $now,
        ]);
    }

    /** Admin-only: hand the assignment to a different engineer, resetting it back to pending. */
    public function reassign(Request $request, array $params): void
    {
        if (!Acl::can('engineer.manage')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $assignment = EngineerAssignment::withRelations((int) $params['id']);
        if ($assignment === null) {
            $this->json(['error' => 'not_found'], 404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $engineerId = (int) $request->input('engineer_id');
        $engineer = $engineerId ? User::find($engineerId) : null;
        $flagColumn = $assignment['assignment_type'] === 'engineer' ? 'is_engineer' : 'is_sales_engineer';

        if (!$engineerId || $engineer === null || (int) $engineer['is_active'] !== 1 || (int) ($engineer[$flagColumn] ?? 0) !== 1) {
            $this->json(['error' => 'Engineer tidak valid atau tidak aktif.'], 422);

            return;
        }

        $oldEngineerId = (int) $assignment['engineer_id'];
        $oldStatus = $assignment['status'];
        $now = date('Y-m-d H:i:s');

        EngineerAssignment::update((int) $assignment['id'], [
            'engineer_id' => $engineerId,
            'status' => 'pending',
            'responded_at' => null,
            'accepted_at' => null,
            'rejection_reason' => null,
            'updated_at' => $now,
        ]);

        EngineerAssignmentStatusHistory::record((int) $assignment['id'], $oldStatus, 'pending', Auth::id(), 'Dialihkan ke engineer lain.');
        AuditLogger::log((int) Auth::id(), 'engineer_assignment_reassigned', 'engineer_assignment', (int) $assignment['id'], [
            'engineer_id' => $oldEngineerId,
        ], [
            'engineer_id' => $engineerId,
            'engineer_name' => $engineer['name'],
        ]);

        Notification::create(
            $engineerId,
            'engineer_assignment_new',
            'Assignment Baru',
            $assignment['assignment_type'] === 'engineer'
                ? "Lead {$assignment['lead_code']} ({$assignment['customer_name']}) menunggu survey teknis/desain Anda."
                : "Lead {$assignment['lead_code']} ({$assignment['customer_name']}) menunggu analisa teknis Anda.",
            '/engineer/' . $assignment['id']
        );

        $this->json([
            'success' => true,
            'engineer_id' => $engineerId,
            'engineer_name' => $engineer['name'],
            'status' => 'pending',
            'updated_at' => $now,
        ]);
    }

    private function authorizedAssignment(int $id): ?array
    {
        $assignment = EngineerAssignment::withRelations($id);

        if ($assignment === null) {
            $this->json(['error' => 'not_found'], 404);

            return null;
        }

        $isAssignee = (int) $assignment['engineer_id'] === Auth::id();
        $actor = Auth::user();
        $inEngineerPool = $actor && ((int) ($actor['is_engineer'] ?? 0) === 1 || (int) ($actor['is_sales_engineer'] ?? 0) === 1 || Acl::hasRole('engineer-sales'));

        if ($inEngineerPool && !$isAssignee && !Acl::can('engineer.manage')) {
            $this->json(['error' => 'forbidden'], 403);

            return null;
        }

        if (Acl::hasRole('sales') && !$isAssignee && (int) $assignment['lead_sales_id'] !== Auth::id()) {
            $this->json(['error' => 'forbidden'], 403);

            return null;
        }

        return $assignment;
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
