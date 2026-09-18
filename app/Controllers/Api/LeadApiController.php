<?php

namespace App\Controllers\Api;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Models\Lead;
use App\Models\LeadSales;
use App\Models\LeadStatusHistory;
use App\Models\MasterData;
use App\Models\Notification;
use App\Models\Prelim;
use App\Models\User;

class LeadApiController extends Controller
{
    /**
     * Manually toggle-able via the lead detail page's quick-status select.
     * 'won'/'lost' are deliberately excluded — Phase 10 requires those to go
     * through LeadController::markWon()/markLost() so deal_value/closing_date/
     * lost_reason are always captured together with the status change.
     */
    private const STATUSES = ['new', 'in_queue', 'follow_up', 'engineering'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** Polled by the Leads list page (and Sales Workspace) to keep counts live. */
    public function summary(Request $request): void
    {
        $scopeSalesId = (Acl::hasRole('sales') && !Acl::can('lead.assign')) ? Auth::id() : null;

        $this->json([
            'counts' => Lead::countByStatus($scopeSalesId),
            'server_time' => date('c'),
        ]);
    }

    /**
     * Polled by the lead detail page: lets the browser notice — without a
     * full reload — that someone else changed this lead's status/priority/
     * assignment while it was open.
     */
    public function ping(Request $request, array $params): void
    {
        $lead = $this->authorizedLead((int) $params['id']);
        if ($lead === null) {
            return;
        }

        $this->json([
            'status' => $lead['status'],
            'priority' => $lead['priority'],
            'sales_id' => $lead['sales_id'] ? (int) $lead['sales_id'] : null,
            'sales_name' => $lead['sales_name'],
            'updated_at' => $lead['updated_at'],
        ]);
    }

    public function updateStatus(Request $request, array $params): void
    {
        if (!Acl::can('lead.edit')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $lead = $this->authorizedLead((int) $params['id']);
        if ($lead === null) {
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

        // Revisi Alur Bisnis (Prelim) — sama seperti gate di EngineerController/
        // ProcurementController/ProposalController: lead tidak boleh dianggap
        // masuk stage Engineering lewat quick-status ini sebelum Prelim di-ACC.
        if ($newStatus === 'engineering' && !Prelim::hasApprovedForLead((int) $lead['id'])) {
            $this->json(['error' => 'Prelim belum di-ACC oleh Client.'], 422);

            return;
        }

        $oldStatus = $lead['status'];
        if ($newStatus === $oldStatus) {
            $this->json(['success' => true, 'unchanged' => true]);

            return;
        }

        $now = date('Y-m-d H:i:s');
        Lead::update((int) $lead['id'], [
            'status' => $newStatus,
            'updated_by' => Auth::id(),
            'updated_at' => $now,
        ]);

        $notes = trim((string) $request->input('notes', '')) ?: null;
        LeadStatusHistory::record((int) $lead['id'], $oldStatus, $newStatus, Auth::id(), $notes);

        AuditLogger::log((int) Auth::id(), 'lead_status_changed', 'lead', (int) $lead['id'], ['status' => $oldStatus], ['status' => $newStatus]);

        $statusMap = MasterData::allAsMap('lead_statuses');

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
        if (!Acl::can('lead.edit')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $lead = $this->authorizedLead((int) $params['id']);
        if ($lead === null) {
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

        $oldPriority = $lead['priority'];
        $now = date('Y-m-d H:i:s');

        Lead::update((int) $lead['id'], [
            'priority' => $newPriority,
            'updated_by' => Auth::id(),
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'lead_priority_changed', 'lead', (int) $lead['id'], ['priority' => $oldPriority], ['priority' => $newPriority]);

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
        if (!Acl::can('lead.assign')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $lead = Lead::withRelations((int) $params['id']);
        if ($lead === null) {
            $this->json(['error' => 'not_found'], 404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $salesIdRaw = $request->input('sales_id');
        $salesId = ($salesIdRaw === '' || $salesIdRaw === null) ? null : (int) $salesIdRaw;

        if ($salesId !== null) {
            $user = User::find($salesId);
            if ($user === null || (int) $user['is_active'] !== 1 || (int) $user['is_sales'] !== 1) {
                $this->json(['error' => 'Sales tidak valid atau tidak aktif.'], 422);

                return;
            }
        }

        $oldSalesId = $lead['sales_id'] ? (int) $lead['sales_id'] : null;
        $oldSalesName = $lead['sales_name'];
        $now = date('Y-m-d H:i:s');

        Lead::update((int) $lead['id'], [
            'sales_id' => $salesId,
            'updated_by' => Auth::id(),
            'updated_at' => $now,
        ]);

        $newSalesName = $salesId !== null ? ($user['name'] ?? null) : null;

        // Additive only: quick-reassign here never removes anyone from the
        // multi-sales list (app/Views/leads/form.php's checkbox list is the
        // only place membership is fully edited) — it just makes sure the
        // new primary is also a recognized member.
        if ($salesId !== null) {
            LeadSales::add((int) $lead['id'], $salesId);
        }

        if ($salesId !== null && $salesId !== $oldSalesId) {
            Notification::create(
                $salesId,
                'lead_assigned',
                'Lead Baru Ditugaskan',
                "Lead {$lead['lead_code']} ({$lead['customer_name']}) telah ditugaskan kepada Anda.",
                '/leads/' . $lead['id']
            );
        }

        AuditLogger::log((int) Auth::id(), 'lead_assigned', 'lead', (int) $lead['id'], [
            'sales_id' => $oldSalesId,
            'sales_name' => $oldSalesName,
        ], [
            'sales_id' => $salesId,
            'sales_name' => $newSalesName,
        ]);

        $this->json([
            'success' => true,
            'sales_id' => $salesId,
            'sales_name' => $newSalesName ?? 'Belum ditugaskan',
            'updated_at' => $now,
        ]);
    }

    public function updateFollowUpDate(Request $request, array $params): void
    {
        if (!Acl::can('lead.edit')) {
            $this->json(['error' => 'forbidden'], 403);

            return;
        }

        $lead = $this->authorizedLead((int) $params['id']);
        if ($lead === null) {
            return;
        }

        if (!Csrf::verifyRequest()) {
            $this->json(['error' => 'csrf'], 419);

            return;
        }

        $date = trim((string) $request->input('follow_up_date', ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->json(['error' => 'Format tanggal tidak valid.'], 422);

            return;
        }

        $now = date('Y-m-d H:i:s');
        Lead::update((int) $lead['id'], [
            'follow_up_date' => $date ?: null,
            'updated_by' => Auth::id(),
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'lead_followup_date_changed', 'lead', (int) $lead['id'], ['follow_up_date' => $lead['follow_up_date']], ['follow_up_date' => $date ?: null]);

        $this->json([
            'success' => true,
            'follow_up_date' => $date ?: null,
            'formatted' => $date ? format_datetime($date, 'd M Y') : '-',
            'updated_at' => $now,
        ]);
    }

    private function authorizedLead(int $id): ?array
    {
        $lead = Lead::withRelations($id);

        if ($lead === null) {
            $this->json(['error' => 'not_found'], 404);

            return null;
        }

        if (Acl::hasRole('sales') && (int) $lead['sales_id'] !== Auth::id() && !LeadSales::isAssigned($id, Auth::id())) {
            $this->json(['error' => 'forbidden'], 403);

            return null;
        }

        return $lead;
    }
}
