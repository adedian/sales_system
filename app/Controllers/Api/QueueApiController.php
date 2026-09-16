<?php

namespace App\Controllers\Api;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Models\MasterData;
use App\Models\QueueStatusHistory;
use App\Models\Role;
use App\Models\SalesQueue;
use App\Models\User;

class QueueApiController extends Controller
{
    private const STATUSES = ['new', 'waiting_followup', 'in_progress', 'waiting_engineer', 'done', 'cancelled'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** Polled by the queue list/dashboard to keep the indicator tiles live. */
    public function summary(Request $request): void
    {
        $scopeSalesId = (Acl::hasRole('sales') && !Acl::can('queue.manage')) ? Auth::id() : null;

        $this->json([
            'counts' => SalesQueue::dashboardCounts($scopeSalesId),
            'server_time' => date('c'),
        ]);
    }

    /** Polled by the queue detail page to notice out-of-band changes. */
    public function ping(Request $request, array $params): void
    {
        $queue = $this->authorizedQueue((int) $params['id']);
        if ($queue === null) {
            return;
        }

        $this->json([
            'status' => $queue['status'],
            'priority' => $queue['priority'],
            'sales_id' => $queue['sales_id'] ? (int) $queue['sales_id'] : null,
            'sales_name' => $queue['sales_name'],
            'updated_at' => $queue['updated_at'],
        ]);
    }

    public function updateStatus(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $newStatus = (string) $request->input('status');
        if (!in_array($newStatus, self::STATUSES, true)) {
            $this->json(['error' => 'Status tidak valid.'], 422);

            return;
        }

        $oldStatus = $queue['status'];
        if ($newStatus === $oldStatus) {
            $this->json(['success' => true, 'unchanged' => true]);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            'status' => $newStatus,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        $notes = trim((string) $request->input('notes', '')) ?: null;
        QueueStatusHistory::record((int) $queue['id'], $oldStatus, $newStatus, Auth::id(), $notes);

        $statusMap = MasterData::allAsMap('queue_statuses');

        $this->json([
            'success' => true,
            'status' => $newStatus,
            'label' => $statusMap[$newStatus]['name'] ?? $newStatus,
            'color' => $statusMap[$newStatus]['color'] ?? 'muted',
            'updated_at' => $now,
        ]);
    }

    public function updatePriority(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
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

        $oldPriority = $queue['priority'];
        $now = date('Y-m-d H:i:s');

        SalesQueue::update((int) $queue['id'], [
            'priority' => $newPriority,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_priority_changed', 'queue', (int) $queue['id'], ['priority' => $oldPriority], ['priority' => $newPriority]);

        $priorityMap = MasterData::allAsMap('priorities');

        $this->json([
            'success' => true,
            'priority' => $newPriority,
            'label' => $priorityMap[$newPriority]['name'] ?? $newPriority,
            'color' => $priorityMap[$newPriority]['color'] ?? 'muted',
            'updated_at' => $now,
        ]);
    }

    public function assign(Request $request, array $params): void
    {
        if (!Acl::can('queue.manage')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $queue = SalesQueue::withRelations((int) $params['id']);
        if ($queue === null) {
            $this->json(['error' => 'not_found'], 404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $salesId = (int) $request->input('sales_id');
        $salesRole = Role::findBySlug('sales');
        $user = $salesId ? User::find($salesId) : null;

        if (!$salesId || $user === null || (int) $user['is_active'] !== 1 || (int) $user['role_id'] !== (int) ($salesRole['id'] ?? 0)) {
            $this->json(['error' => 'Sales tidak valid atau tidak aktif.'], 422);

            return;
        }

        $oldSalesId = (int) $queue['sales_id'];
        $oldSalesName = $queue['sales_name'];
        $now = date('Y-m-d H:i:s');

        SalesQueue::update((int) $queue['id'], [
            'sales_id' => $salesId,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_assigned', 'queue', (int) $queue['id'], [
            'sales_id' => $oldSalesId,
            'sales_name' => $oldSalesName,
        ], [
            'sales_id' => $salesId,
            'sales_name' => $user['name'],
        ]);

        $this->json([
            'success' => true,
            'sales_id' => $salesId,
            'sales_name' => $user['name'],
            'updated_at' => $now,
        ]);
    }

    public function updateDeadline(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
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
        SalesQueue::update((int) $queue['id'], [
            'deadline' => $date ?: null,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_deadline_changed', 'queue', (int) $queue['id'], ['deadline' => $queue['deadline']], ['deadline' => $date ?: null]);

        $this->json([
            'success' => true,
            'deadline' => $date ?: null,
            'is_overdue' => $date !== '' && $date < date('Y-m-d'),
            'updated_at' => $now,
        ]);
    }

    public function updateFollowUpDate(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $date = $this->validateDate((string) $request->input('follow_up_date', ''));
        if ($date === false) {
            $this->json(['error' => 'Format tanggal tidak valid.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            'followup_date' => $date ?: null,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_followup_date_changed', 'queue', (int) $queue['id'], ['followup_date' => $queue['followup_date']], ['followup_date' => $date ?: null]);

        $this->json([
            'success' => true,
            'follow_up_date' => $date ?: null,
            'updated_at' => $now,
        ]);
    }

    // ------------------------------------------------------------------
    // Phase B — Antrian revision. Each new field is its own granular
    // live-AJAX endpoint, matching the existing deadline/follow-up-date
    // pattern exactly (one field = one endpoint), so the generic
    // `.queue-live-field` JS in queue/show.php needs no changes.
    // ------------------------------------------------------------------

    public function updateTaskName(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $taskName = trim((string) $request->input('task_name', ''));
        if (mb_strlen($taskName) > 150) {
            $this->json(['error' => 'Nama tugas maksimal 150 karakter.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            'task_name' => $taskName ?: null,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_task_name_changed', 'queue', (int) $queue['id'], ['task_name' => $queue['task_name']], ['task_name' => $taskName ?: null]);

        $this->json([
            'success' => true,
            'task_name' => $taskName ?: null,
            'display_title' => $taskName !== '' ? $taskName : $queue['customer_name'],
            'updated_at' => $now,
        ]);
    }

    public function updateSurveyStatus(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $id = $this->nullableId($request->input('survey_status_id'));
        $map = MasterData::allAsMap('survey_statuses');
        $found = $id !== null ? $this->findById($map, $id) : null;

        if ($id !== null && $found === null) {
            $this->json(['error' => 'Status survey tidak valid.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            'survey_status_id' => $id,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_survey_status_changed', 'queue', (int) $queue['id'], ['survey_status_id' => $queue['survey_status_id']], ['survey_status_id' => $id]);

        $this->json([
            'success' => true,
            'survey_status_id' => $id,
            'label' => $found['name'] ?? 'Belum diisi',
            'color' => $found['color'] ?? 'muted',
            'updated_at' => $now,
        ]);
    }

    public function updateStage(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $id = $this->nullableId($request->input('stage_id'));
        $map = MasterData::allAsMap('queue_stages');
        $found = $id !== null ? $this->findById($map, $id) : null;

        if ($id !== null && $found === null) {
            $this->json(['error' => 'Prioritas tidak valid.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            'stage_id' => $id,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_stage_changed', 'queue', (int) $queue['id'], ['stage_id' => $queue['stage_id']], ['stage_id' => $id]);

        $this->json([
            'success' => true,
            'stage_id' => $id,
            'label' => $found['name'] ?? 'Belum diisi',
            'color' => $found['color'] ?? 'muted',
            'updated_at' => $now,
        ]);
    }

    public function updateEstimator(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $id = $this->nullableId($request->input('estimator_id'));
        $user = $id !== null ? User::find($id) : null;

        if ($id !== null && ($user === null || (int) $user['is_active'] !== 1 || (int) $user['is_estimator'] !== 1)) {
            $this->json(['error' => 'Estimator tidak valid atau tidak aktif.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            'estimator_id' => $id,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_estimator_changed', 'queue', (int) $queue['id'], ['estimator_id' => $queue['estimator_id']], ['estimator_id' => $id]);

        $this->json([
            'success' => true,
            'estimator_id' => $id,
            'estimator_name' => $user['name'] ?? 'None',
            'updated_at' => $now,
        ]);
    }

    public function updateSurveyor(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $id = $this->nullableId($request->input('surveyor_id'));
        $user = $id !== null ? User::find($id) : null;

        if ($id !== null && ($user === null || (int) $user['is_active'] !== 1 || (int) $user['is_surveyor'] !== 1)) {
            $this->json(['error' => 'Surveyor tidak valid atau tidak aktif.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            'surveyor_id' => $id,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_surveyor_changed', 'queue', (int) $queue['id'], ['surveyor_id' => $queue['surveyor_id']], ['surveyor_id' => $id]);

        $this->json([
            'success' => true,
            'surveyor_id' => $id,
            'surveyor_name' => $user['name'] ?? 'None',
            'updated_at' => $now,
        ]);
    }

    public function updateNotes(Request $request, array $params): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $notes = trim((string) $request->input('notes', ''));
        if (mb_strlen($notes) > 2000) {
            $this->json(['error' => 'Catatan maksimal 2000 karakter.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            'notes' => $notes ?: null,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'queue_notes_field_changed', 'queue', (int) $queue['id'], ['notes' => $queue['notes']], ['notes' => $notes ?: null]);

        $this->json(['success' => true, 'notes' => $notes ?: null, 'updated_at' => $now]);
    }

    public function updateEngineeringStartDate(Request $request, array $params): void
    {
        $this->updateDateField($request, $params, 'engineering_start_date', 'queue_engineering_start_date_changed');
    }

    public function updateEngineeringEndDate(Request $request, array $params): void
    {
        $this->updateDateField($request, $params, 'engineering_end_date', 'queue_engineering_end_date_changed');
    }

    public function updateProcurementStartDate(Request $request, array $params): void
    {
        $this->updateDateField($request, $params, 'procurement_start_date', 'queue_procurement_start_date_changed');
    }

    public function updateProcurementEndDate(Request $request, array $params): void
    {
        $this->updateDateField($request, $params, 'procurement_end_date', 'queue_procurement_end_date_changed');
    }

    /** Shared body for the 4 lightweight date-pair fields above — same shape as updateDeadline()/updateFollowUpDate(). */
    private function updateDateField(Request $request, array $params, string $field, string $action): void
    {
        $queue = $this->authorizedQueueForWrite((int) $params['id']);
        if ($queue === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $date = $this->validateDate((string) $request->input($field, ''));
        if ($date === false) {
            $this->json(['error' => 'Format tanggal tidak valid.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        SalesQueue::update((int) $queue['id'], [
            $field => $date ?: null,
            'last_updated_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), $action, 'queue', (int) $queue['id'], [$field => $queue[$field]], [$field => $date ?: null]);

        $this->json(['success' => true, $field => $date ?: null, 'updated_at' => $now]);
    }

    private function nullableId(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /** @param array<string,array<string,mixed>> $map keyed by code, each row carries its own 'id' */
    private function findById(array $map, int $id): ?array
    {
        foreach ($map as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    private function authorizedQueue(int $id): ?array
    {
        $queue = SalesQueue::withRelations($id);

        if ($queue === null) {
            $this->json(['error' => 'not_found'], 404);

            return null;
        }

        if (Acl::hasRole('sales') && !Acl::can('queue.manage') && (int) $queue['sales_id'] !== Auth::id()) {
            $this->json(['error' => 'forbidden'], 403);

            return null;
        }

        return $queue;
    }

    /**
     * Stricter than authorizedQueue(): only queue.manage holders (Admin
     * Sales, Super Admin) or the Sales rep this item is actually assigned
     * to may change it — a Manager/Engineer with mere queue.view must not
     * be able to write through this endpoint.
     */
    private function authorizedQueueForWrite(int $id): ?array
    {
        $queue = SalesQueue::withRelations($id);

        if ($queue === null) {
            $this->json(['error' => 'not_found'], 404);

            return null;
        }

        $isOwner = Acl::hasRole('sales') && (int) $queue['sales_id'] === Auth::id();

        if (!Acl::can('queue.manage') && !$isOwner) {
            $this->json(['error' => 'forbidden'], 403);

            return null;
        }

        return $queue;
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
