<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\MasterData;
use App\Models\User;

class FollowUpController extends Controller
{
    /**
     * Phase 9 — "Follow Up Calendar": leads needing contact today/overdue,
     * what's coming up, and the most recent logged contacts. Reuses
     * Lead::search()'s existing follow_up filter (built in Phase 2/7) rather
     * than duplicating that query.
     */
    public function index(Request $request): void
    {
        $scopeSalesId = $this->scopeSalesId();
        $salesIdFilter = $this->nullableInt($request->input('sales_id'));
        $effectiveScope = $scopeSalesId ?? $salesIdFilter;

        $baseFilters = array_filter(['scope_sales_id' => $effectiveScope]);

        $overdue = Lead::search(array_merge($baseFilters, ['follow_up' => 'overdue', 'per_page' => 100]))['rows'];
        $today = Lead::search(array_merge($baseFilters, ['follow_up' => 'today', 'per_page' => 100]))['rows'];
        $upcoming = Lead::search(array_merge($baseFilters, ['follow_up' => 'upcoming', 'per_page' => 50]))['rows'];
        // "upcoming" (>= today) already includes today's leads — keep it to strictly future dates.
        $upcoming = array_values(array_filter($upcoming, fn ($row) => $row['follow_up_date'] > date('Y-m-d')));

        $this->view('follow-ups/index', [
            'pageTitle' => 'Follow Up Calendar',
            'overdue' => $overdue,
            'today' => $today,
            'upcoming' => $upcoming,
            'recentLog' => FollowUp::recent($effectiveScope, 20),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'salesUsers' => $scopeSalesId === null ? User::activeByRole($this->salesRoleId() ?? 0) : [],
            'filters' => ['sales_id' => $salesIdFilter],
            'methodLabels' => $this->methodLabels(),
            'responseLabels' => $this->responseLabels(),
        ]);
    }

    /** Logs one contact attempt against a lead and advances its next-reminder date. */
    public function store(Request $request, array $params): void
    {
        $lead = $this->findAuthorizedLead((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (!Acl::can('followup.create')) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), [
            'method' => 'required|in:' . implode(',', FollowUp::METHODS),
            'notes' => 'max:2000',
            'next_action' => 'max:255',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Metode follow up wajib dipilih.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $customerResponse = $request->input('customer_response') ?: null;
        if ($customerResponse !== null && !in_array($customerResponse, FollowUp::CUSTOMER_RESPONSES, true)) {
            $customerResponse = null;
        }

        $now = date('Y-m-d H:i:s');
        $nextFollowUpDate = $request->input('next_followup_date') ?: null;

        FollowUp::record([
            'lead_id' => (int) $lead['id'],
            'sales_id' => (int) ($lead['sales_id'] ?: Auth::id()),
            'followup_date' => $now,
            'method' => $request->input('method'),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'customer_response' => $customerResponse,
            'next_followup_date' => $nextFollowUpDate,
            'next_action' => trim((string) $request->input('next_action', '')) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $leadUpdate = [
            'follow_up_date' => $nextFollowUpDate,
            'updated_by' => Auth::id(),
            'updated_at' => $now,
        ];
        // A lead still fresh in the pipeline visibly moves to "Follow Up" once contact has been logged.
        if (in_array($lead['status'], ['new', 'in_queue'], true)) {
            $leadUpdate['status'] = 'follow_up';
        }
        Lead::update((int) $lead['id'], $leadUpdate);

        if (isset($leadUpdate['status']) && $leadUpdate['status'] !== $lead['status']) {
            \App\Models\LeadStatusHistory::record((int) $lead['id'], $lead['status'], 'follow_up', Auth::id(), 'Follow up pertama dicatat.');
        }

        AuditLogger::log((int) Auth::id(), 'followup_logged', 'lead', (int) $lead['id'], null, [
            'method' => $request->input('method'),
            'customer_response' => $customerResponse,
        ]);

        Session::flash('success', 'Follow up berhasil dicatat.');
        $this->redirect('/leads/' . $lead['id']);
    }

    private function findAuthorizedLead(int $id): array
    {
        $lead = Lead::withRelations($id);

        if ($lead === null) {
            $this->abort(404);
        }

        if (Acl::hasRole('sales') && (int) $lead['sales_id'] !== Auth::id()) {
            $this->abort(403, 'Anda hanya dapat mengakses lead yang ditugaskan kepada Anda.');
        }

        return $lead;
    }

    private function scopeSalesId(): ?int
    {
        return Acl::hasRole('sales') ? Auth::id() : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    private function salesRoleId(): ?int
    {
        static $id = null;
        if ($id === null) {
            $role = \App\Models\Role::findBySlug('sales');
            $id = $role ? (int) $role['id'] : 0;
        }

        return $id;
    }

    public function methodLabels(): array
    {
        return [
            'call' => 'Telepon',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'visit' => 'Kunjungan',
            'other' => 'Lainnya',
        ];
    }

    public function responseLabels(): array
    {
        return [
            'interested' => 'Tertarik',
            'negotiating' => 'Negosiasi',
            'need_info' => 'Butuh Info Tambahan',
            'not_interested' => 'Tidak Tertarik',
            'no_answer' => 'Tidak Ada Respon',
        ];
    }
}
