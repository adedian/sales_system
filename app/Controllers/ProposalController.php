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
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\MasterData;
use App\Models\Notification;
use App\Models\ProcurementItem;
use App\Models\ProcurementRequest;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ProposalNegotiation;
use App\Models\ProposalNote;
use App\Models\ProposalStatusHistory;
use App\Models\Setting;
use App\Models\Role;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;

class ProposalController extends Controller
{
    public function index(Request $request): void
    {
        $scope = $this->scopeFilters();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', ''),
            'sales_id' => $request->input('sales_id', ''),
            'include_closed' => $request->input('include_closed') ? true : false,
            'sort' => $request->input('sort', 'created_at'),
            'dir' => $request->input('dir', 'desc'),
            'page' => (int) $request->input('page', 1),
        ];

        $result = Proposal::search(array_merge($filters, $scope));

        $this->view('proposals/index', [
            'pageTitle' => 'Proposal',
            'proposals' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'dashboardCounts' => Proposal::dashboardCounts($scope['scope_sales_id'] ?? null),
            'statusMap' => MasterData::allAsMap('proposal_statuses'),
            'salesUsers' => ($id = $this->salesRoleId()) ? User::activeByRole($id) : [],
            'canApprove' => Acl::can('proposal.approve'),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        $this->view('proposals/show', [
            'pageTitle' => $proposal['proposal_code'],
            'proposal' => $proposal,
            'items' => ProposalItem::forProposal((int) $proposal['id']),
            'timeline' => $this->buildTimeline((int) $proposal['id']),
            'statusMap' => MasterData::allAsMap('proposal_statuses'),
            'canOperate' => $this->canOperate($proposal),
            'canApprove' => $this->canApprove($proposal),
            'negotiations' => ProposalNegotiation::forProposal((int) $proposal['id']),
            'canNegotiate' => $this->canOperate($proposal) && in_array($proposal['status'], ['sent', 'viewed', 'negotiation'], true),
            'products' => \App\Models\Product::activeList(),
        ]);
    }

    /** "Procurement -> Buat Proposal": copies priced items in as the starting point. */
    public function createFromProcurement(Request $request, array $params): void
    {
        $pr = ProcurementRequest::withRelations((int) $params['id']);
        if ($pr === null) {
            $this->abort(404);

            return;
        }

        if (Acl::hasRole('procurement') && (int) $pr['assigned_to'] !== Auth::id()) {
            $this->abort(403);

            return;
        }

        if (Acl::hasRole('sales') && (int) $pr['lead_sales_id'] !== Auth::id()) {
            $this->abort(403);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!Acl::can('proposal.create')) {
            $this->abort(403);

            return;
        }

        if ($pr['status'] !== 'pricing_completed') {
            Session::flash('error', 'Pricing harus selesai terlebih dahulu sebelum membuat proposal.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        $existing = \App\Core\Database::fetch(
            "SELECT id FROM proposals WHERE procurement_request_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1",
            [$pr['id']]
        );
        if ($existing !== null) {
            $this->redirect('/proposals/' . $existing['id']);

            return;
        }

        $lead = Lead::find((int) $pr['lead_id']);
        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');
        $salesId = (int) ($lead['sales_id'] ?? $actor['id']);

        $proposal = Proposal::createForLead([
            'lead_id' => (int) $pr['lead_id'],
            'procurement_request_id' => (int) $pr['id'],
            'sales_id' => $salesId,
            'project_name' => $lead['needs_description'] ? mb_substr($lead['needs_description'], 0, 200) : null,
            'status' => 'draft',
            'tax_percent' => 11,
            'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'created_by' => $actor['id'],
            'updated_by' => $actor['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ProposalItem::copyFromProcurementItems((int) $proposal['id'], ProcurementItem::forRequest((int) $pr['id']));
        Proposal::recalculateTotals((int) $proposal['id']);

        ProposalStatusHistory::record($proposal['id'], null, 'draft', (int) $actor['id'], 'Proposal dibuat dari hasil procurement.');
        AuditLogger::log((int) $actor['id'], 'proposal_created', 'proposal', $proposal['id'], null, [
            'proposal_code' => $proposal['proposal_code'],
            'lead_code' => $pr['lead_code'],
        ]);

        $this->syncLeadStatus((int) $pr['lead_id'], (int) $actor['id'], $now);

        Session::flash('success', "Proposal {$proposal['proposal_code']} berhasil dibuat dari hasil procurement.");
        $this->redirect('/proposals/' . $proposal['id']);
    }

    /** "Lead -> Buat Proposal": for leads that skip procurement entirely (pure service/consulting). */
    public function createFromLead(Request $request, array $params): void
    {
        $lead = $this->findAuthorizedLead((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (!Acl::can('proposal.create') || $lead['deleted_at']) {
            $this->abort(403);

            return;
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');
        $salesId = (int) ($lead['sales_id'] ?? $actor['id']);

        $proposal = Proposal::createForLead([
            'lead_id' => (int) $lead['id'],
            'procurement_request_id' => null,
            'sales_id' => $salesId,
            'project_name' => $lead['needs_description'] ? mb_substr($lead['needs_description'], 0, 200) : null,
            'status' => 'draft',
            'tax_percent' => 11,
            'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'created_by' => $actor['id'],
            'updated_by' => $actor['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ProposalStatusHistory::record($proposal['id'], null, 'draft', (int) $actor['id'], 'Proposal dibuat langsung dari lead.');
        AuditLogger::log((int) $actor['id'], 'proposal_created', 'proposal', $proposal['id'], null, [
            'proposal_code' => $proposal['proposal_code'],
            'lead_code' => $lead['lead_code'],
        ]);

        $this->syncLeadStatus((int) $lead['id'], (int) $actor['id'], $now);

        Session::flash('success', "Proposal {$proposal['proposal_code']} berhasil dibuat.");
        $this->redirect('/proposals/' . $proposal['id']);
    }

    /** Edits the proposal header (project info, commercial terms) — always allowed while not yet sent. */
    public function update(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), [
            'discount_percent' => 'numeric',
            'tax_percent' => 'numeric',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Diskon dan pajak harus berupa angka.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $newData = [
            'customer_pic' => trim((string) $request->input('customer_pic', '')) ?: null,
            'project_name' => trim((string) $request->input('project_name', '')) ?: null,
            'scope_description' => trim((string) $request->input('scope_description', '')) ?: null,
            'discount_percent' => $request->input('discount_percent') !== '' ? (float) $request->input('discount_percent') : null,
            'tax_percent' => $request->input('tax_percent') !== '' ? (float) $request->input('tax_percent') : null,
            'timeline_text' => trim((string) $request->input('timeline_text', '')) ?: null,
            'payment_terms' => trim((string) $request->input('payment_terms', '')) ?: null,
            'warranty' => trim((string) $request->input('warranty', '')) ?: null,
            'terms_conditions' => trim((string) $request->input('terms_conditions', '')) ?: null,
            'valid_until' => $request->input('valid_until') ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'updated_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Proposal::update((int) $proposal['id'], $newData);
        Proposal::recalculateTotals((int) $proposal['id']);

        AuditLogger::log((int) Auth::id(), 'proposal_updated', 'proposal', (int) $proposal['id']);

        Session::flash('success', 'Detail proposal tersimpan.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    public function destroy(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal) || $proposal['status'] !== 'draft') {
            $this->abort(403);

            return;
        }

        Proposal::softDelete((int) $proposal['id']);
        AuditLogger::log((int) Auth::id(), 'proposal_deleted', 'proposal', (int) $proposal['id'], ['proposal_code' => $proposal['proposal_code']], null);

        Session::flash('success', "Proposal {$proposal['proposal_code']} dihapus.");
        $this->redirect('/proposals');
    }

    // ------------------------------------------------------------------
    // Line items
    // ------------------------------------------------------------------

    public function addItem(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), [
            'item_name' => 'required|max:200',
            'quantity' => 'required|numeric',
            'unit_price' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Nama item, kuantitas, dan harga jual wajib diisi dengan benar.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $quantity = (float) $request->input('quantity');
        $unitPrice = (float) $request->input('unit_price');
        $now = date('Y-m-d H:i:s');

        ProposalItem::insert([
            'proposal_id' => (int) $proposal['id'],
            'item_name' => trim((string) $request->input('item_name')),
            'specification' => trim((string) $request->input('specification', '')) ?: null,
            'quantity' => $quantity,
            'unit' => trim((string) $request->input('unit', '')) ?: null,
            'unit_cost' => $request->input('unit_cost') !== '' && $request->input('unit_cost') !== null ? (float) $request->input('unit_cost') : null,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
            'sort_order' => ProposalItem::nextSortOrder((int) $proposal['id']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Proposal::recalculateTotals((int) $proposal['id']);
        AuditLogger::log((int) Auth::id(), 'proposal_item_added', 'proposal', (int) $proposal['id'], null, ['item_name' => $request->input('item_name')]);

        Session::flash('success', 'Item berhasil ditambahkan.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    public function updateItem(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);
        $item = $this->findItem($proposal, (int) $params['itemId']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), [
            'item_name' => 'required|max:200',
            'quantity' => 'required|numeric',
            'unit_price' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Nama item, kuantitas, dan harga jual wajib diisi dengan benar.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $quantity = (float) $request->input('quantity');
        $unitPrice = (float) $request->input('unit_price');

        ProposalItem::update((int) $item['id'], [
            'item_name' => trim((string) $request->input('item_name')),
            'specification' => trim((string) $request->input('specification', '')) ?: null,
            'quantity' => $quantity,
            'unit' => trim((string) $request->input('unit', '')) ?: null,
            'unit_cost' => $request->input('unit_cost') !== '' && $request->input('unit_cost') !== null ? (float) $request->input('unit_cost') : null,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Proposal::recalculateTotals((int) $proposal['id']);
        AuditLogger::log((int) Auth::id(), 'proposal_item_updated', 'proposal', (int) $proposal['id'], null, ['item_name' => $item['item_name']]);

        Session::flash('success', 'Item berhasil diperbarui.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    public function deleteItem(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);
        $item = $this->findItem($proposal, (int) $params['itemId']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal)) {
            $this->abort(403);

            return;
        }

        ProposalItem::delete((int) $item['id']);
        Proposal::recalculateTotals((int) $proposal['id']);

        AuditLogger::log((int) Auth::id(), 'proposal_item_deleted', 'proposal', (int) $proposal['id'], ['item_name' => $item['item_name']], null);

        Session::flash('success', 'Item dihapus.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    // ------------------------------------------------------------------
    // Status flow: draft/revision -> internal_review -> approved/revision -> sent -> viewed/negotiation -> accepted/rejected/expired
    // ------------------------------------------------------------------

    public function submitForReview(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal)) {
            $this->abort(403);

            return;
        }

        if (!in_array($proposal['status'], ['draft', 'revision'], true)) {
            Session::flash('error', 'Proposal ini tidak dalam status yang bisa diajukan review.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (empty(ProposalItem::forProposal((int) $proposal['id']))) {
            Session::flash('error', 'Tambahkan minimal 1 item sebelum mengajukan review.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $oldStatus = $proposal['status'];
        $now = date('Y-m-d H:i:s');

        Proposal::update((int) $proposal['id'], ['status' => 'internal_review', 'submitted_at' => $now, 'updated_at' => $now]);
        ProposalStatusHistory::record((int) $proposal['id'], $oldStatus, 'internal_review', Auth::id());
        AuditLogger::log((int) Auth::id(), 'proposal_submitted', 'proposal', (int) $proposal['id']);

        $managerRole = Role::findBySlug('manager');
        if ($managerRole !== null) {
            foreach (User::activeByRole((int) $managerRole['id']) as $manager) {
                Notification::create(
                    (int) $manager['id'],
                    'proposal_review_requested',
                    'Proposal Menunggu Review',
                    "Proposal {$proposal['proposal_code']} untuk lead {$proposal['lead_code']} ({$proposal['customer_name']}) menunggu review Anda.",
                    '/proposals/' . $proposal['id']
                );
            }
        }

        Session::flash('success', 'Proposal diajukan untuk review internal.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    public function approve(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canApprove($proposal)) {
            $this->abort(403);

            return;
        }

        if ($proposal['status'] !== 'internal_review') {
            Session::flash('error', 'Proposal ini tidak sedang dalam review.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $now = date('Y-m-d H:i:s');
        Proposal::update((int) $proposal['id'], [
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => $now,
            'updated_at' => $now,
        ]);
        ProposalStatusHistory::record((int) $proposal['id'], 'internal_review', 'approved', Auth::id());
        AuditLogger::log((int) Auth::id(), 'proposal_approved', 'proposal', (int) $proposal['id']);

        $this->notifyOwner($proposal, 'proposal_approved', 'Proposal Disetujui', "Proposal {$proposal['proposal_code']} untuk lead {$proposal['lead_code']} telah disetujui dan siap dikirim ke customer.");

        Session::flash('success', 'Proposal disetujui.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    public function requestRevision(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canApprove($proposal)) {
            $this->abort(403);

            return;
        }

        if ($proposal['status'] !== 'internal_review') {
            Session::flash('error', 'Proposal ini tidak sedang dalam review.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $validator = new Validator($request->all(), ['revision_reason' => 'required|max:1000']);
        if ($validator->fails()) {
            Session::flash('error', 'Alasan revisi wajib diisi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $reason = trim((string) $request->input('revision_reason'));
        Proposal::update((int) $proposal['id'], ['status' => 'revision', 'updated_at' => date('Y-m-d H:i:s')]);
        ProposalStatusHistory::record((int) $proposal['id'], 'internal_review', 'revision', Auth::id(), $reason);
        AuditLogger::log((int) Auth::id(), 'proposal_revision_requested', 'proposal', (int) $proposal['id'], null, ['reason' => $reason]);

        $this->notifyOwner($proposal, 'proposal_revision_requested', 'Proposal Perlu Revisi', "Proposal {$proposal['proposal_code']} perlu direvisi: {$reason}");

        Session::flash('success', 'Permintaan revisi terkirim ke sales.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    public function send(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal) || !Acl::can('proposal.send')) {
            $this->abort(403);

            return;
        }

        if (!in_array($proposal['status'], ['draft', 'revision', 'approved'], true)) {
            Session::flash('error', 'Proposal ini tidak dalam status yang bisa dikirim.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (empty(ProposalItem::forProposal((int) $proposal['id']))) {
            Session::flash('error', 'Tambahkan minimal 1 item sebelum mengirim proposal.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $oldStatus = $proposal['status'];
        $now = date('Y-m-d H:i:s');

        Proposal::update((int) $proposal['id'], ['status' => 'sent', 'sent_at' => $now, 'updated_at' => $now]);
        ProposalStatusHistory::record((int) $proposal['id'], $oldStatus, 'sent', Auth::id());
        AuditLogger::log((int) Auth::id(), 'proposal_sent', 'proposal', (int) $proposal['id']);

        Session::flash('success', 'Proposal ditandai sudah dikirim ke customer.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    /** Self-reported customer response, logged manually by Sales after real-world contact (no customer portal in this system). */
    public function updateStatus(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal)) {
            $this->abort(403);

            return;
        }

        $allowedFrom = ['sent', 'viewed', 'negotiation'];
        $allowedTo = ['viewed', 'negotiation', 'accepted', 'rejected', 'expired'];
        $newStatus = (string) $request->input('status');

        if (!in_array($proposal['status'], $allowedFrom, true) || !in_array($newStatus, $allowedTo, true)) {
            Session::flash('error', 'Perubahan status tidak valid untuk kondisi proposal saat ini.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $reason = null;
        if ($newStatus === 'rejected') {
            $validator = new Validator($request->all(), ['reason' => 'required|max:1000']);
            if ($validator->fails()) {
                Session::flash('error', 'Alasan penolakan wajib diisi.');
                $this->redirect('/proposals/' . $proposal['id']);

                return;
            }
            $reason = trim((string) $request->input('reason'));
        }

        $oldStatus = $proposal['status'];
        $now = date('Y-m-d H:i:s');
        $updateData = ['status' => $newStatus, 'updated_at' => $now];

        if (in_array($newStatus, ['accepted', 'rejected'], true)) {
            $updateData['responded_at'] = $now;
        }
        if ($newStatus === 'rejected') {
            $updateData['rejection_reason'] = $reason;
        }

        Proposal::update((int) $proposal['id'], $updateData);
        ProposalStatusHistory::record((int) $proposal['id'], $oldStatus, $newStatus, Auth::id(), $reason);
        AuditLogger::log((int) Auth::id(), 'proposal_status_changed', 'proposal', (int) $proposal['id'], ['status' => $oldStatus], ['status' => $newStatus]);

        Session::flash('success', 'Status proposal diperbarui.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    // ------------------------------------------------------------------
    // Phase 9 — Negotiation log
    // ------------------------------------------------------------------

    /** Logs one round of the back-and-forth (customer's ask, or Sales' reply) while the proposal is out with the customer. */
    public function addNegotiation(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal) || !in_array($proposal['status'], ['sent', 'viewed', 'negotiation'], true)) {
            $this->abort(403);

            return;
        }

        $type = (string) $request->input('type');
        if (!in_array($type, ['customer_feedback', 'sales_response'], true)) {
            $type = 'customer_feedback';
        }

        $validator = new Validator($request->all(), ['message' => 'required|max:2000']);
        if ($validator->fails()) {
            Session::flash('error', 'Catatan negosiasi wajib diisi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $requestedTotal = $request->input('requested_total') !== '' && $request->input('requested_total') !== null
            ? (float) $request->input('requested_total')
            : null;

        ProposalNegotiation::add(
            (int) $proposal['id'],
            $type,
            trim((string) $request->input('message')),
            $requestedTotal,
            Auth::id()
        );

        // The first customer counter-offer moves the proposal into "negotiation" if it hasn't already.
        if ($type === 'customer_feedback' && $proposal['status'] !== 'negotiation') {
            $oldStatus = $proposal['status'];
            Proposal::update((int) $proposal['id'], ['status' => 'negotiation', 'updated_at' => date('Y-m-d H:i:s')]);
            ProposalStatusHistory::record((int) $proposal['id'], $oldStatus, 'negotiation', Auth::id(), 'Negosiasi dimulai.');
        }

        AuditLogger::log((int) Auth::id(), 'proposal_negotiation_logged', 'proposal', (int) $proposal['id'], null, ['type' => $type]);

        Session::flash('success', 'Catatan negosiasi tersimpan.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    /**
     * "Revisi Harga & Kirim Ulang": reopens a proposal that's in negotiation
     * back to draft, so Sales can adjust items/discount and resend through
     * the normal draft -> ... -> sent flow. Logged both as a negotiation
     * entry (customer's side of why) and a status-history entry.
     */
    public function reviseFromNegotiation(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal) || $proposal['status'] !== 'negotiation') {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), ['reason' => 'required|max:1000']);
        if ($validator->fails()) {
            Session::flash('error', 'Jelaskan permintaan revisi dari customer terlebih dahulu.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $reason = trim((string) $request->input('reason'));
        $requestedTotal = $request->input('requested_total') !== '' && $request->input('requested_total') !== null
            ? (float) $request->input('requested_total')
            : null;

        ProposalNegotiation::add((int) $proposal['id'], 'revision_request', $reason, $requestedTotal, Auth::id());

        Proposal::update((int) $proposal['id'], ['status' => 'draft', 'updated_at' => date('Y-m-d H:i:s')]);
        ProposalStatusHistory::record((int) $proposal['id'], 'negotiation', 'draft', Auth::id(), 'Revisi berdasarkan negosiasi customer: ' . $reason);
        AuditLogger::log((int) Auth::id(), 'proposal_revision_from_negotiation', 'proposal', (int) $proposal['id'], null, ['reason' => $reason]);

        Session::flash('success', 'Proposal dibuka kembali untuk revisi harga. Perbarui item lalu kirim ulang.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    public function addNote(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        if (!$this->canOperate($proposal) && !$this->canApprove($proposal)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), ['note' => 'required|max:2000']);
        if ($validator->fails()) {
            Session::flash('error', 'Catatan wajib diisi.');
            $this->redirect('/proposals/' . $proposal['id']);

            return;
        }

        $note = trim((string) $request->input('note'));
        ProposalNote::add((int) $proposal['id'], Auth::id(), $note);
        AuditLogger::log((int) Auth::id(), 'proposal_note_added', 'proposal', (int) $proposal['id'], null, ['note' => $note]);

        Session::flash('success', 'Catatan ditambahkan.');
        $this->redirect('/proposals/' . $proposal['id']);
    }

    // ------------------------------------------------------------------
    // PDF
    // ------------------------------------------------------------------

    public function pdf(Request $request, array $params): void
    {
        $proposal = $this->findAuthorized((int) $params['id']);
        $items = ProposalItem::forProposal((int) $proposal['id']);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->buildPdfHtml($proposal, $items));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $download = (bool) $request->input('download');
        $filename = $proposal['proposal_code'] . '.pdf';

        AuditLogger::log((int) Auth::id(), 'proposal_pdf_generated', 'proposal', (int) $proposal['id'], null, ['download' => $download]);

        $dompdf->stream($filename, ['Attachment' => $download]);
        exit;
    }

    private function buildPdfHtml(array $proposal, array $items): string
    {
        $rows = '';
        $no = 0;
        foreach ($items as $item) {
            $no++;
            $rows .= '<tr>'
                . '<td class="c">' . $no . '</td>'
                . '<td>' . e($item['item_name']) . ($item['specification'] ? '<br><span class="spec">' . e($item['specification']) . '</span>' : '') . '</td>'
                . '<td class="c">' . e(rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.')) . ' ' . e($item['unit']) . '</td>'
                . '<td class="r">' . e(number_format((float) $item['unit_price'], 0, ',', '.')) . '</td>'
                . '<td class="r">' . e(number_format((float) $item['subtotal'], 0, ',', '.')) . '</td>'
                . '</tr>';
        }

        $validUntil = $proposal['valid_until'] ? date('d M Y', strtotime($proposal['valid_until'])) : '-';
        $createdDate = date('d M Y', strtotime($proposal['created_at']));

        $companyName = Setting::get('company_name', '');
        $companyLine = trim(implode(' &middot; ', array_filter([
            Setting::get('company_address', ''),
            Setting::get('company_phone', ''),
            Setting::get('company_email', ''),
        ])));
        $companyBlock = $companyName !== ''
            ? '<div class="company-block"><strong>' . $this->e($companyName) . '</strong>' . ($companyLine !== '' ? '<br>' . $companyLine : '') . '</div>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1b2430; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    h2 { font-size: 13px; margin: 0 0 10px; color: #6b7686; font-weight: normal; }
    .header { border-bottom: 2px solid #2952e3; padding-bottom: 10px; margin-bottom: 16px; }
    .company-block { font-size: 10.5px; color: #6b7686; margin-bottom: 8px; }
    .meta-table { width: 100%; margin-bottom: 14px; }
    .meta-table td { vertical-align: top; padding: 2px 0; }
    .meta-label { color: #6b7686; width: 110px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.items th { background: #f4f6f9; border: 1px solid #d7dce5; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
    table.items td { border: 1px solid #d7dce5; padding: 6px 8px; font-size: 11px; }
    .c { text-align: center; }
    .r { text-align: right; }
    .spec { color: #6b7686; font-size: 9.5px; }
    table.totals { width: 260px; margin-left: auto; border-collapse: collapse; }
    table.totals td { padding: 4px 8px; font-size: 11px; }
    table.totals tr.grand td { border-top: 2px solid #1b2430; font-weight: bold; font-size: 13px; }
    .section-title { font-size: 12px; font-weight: bold; margin: 16px 0 4px; border-bottom: 1px solid #d7dce5; padding-bottom: 3px; }
    .terms { font-size: 10.5px; white-space: pre-line; color: #333; }
    .footer { margin-top: 30px; font-size: 9.5px; color: #9aa4b2; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        {$companyBlock}
        <h1>PROPOSAL PENAWARAN</h1>
        <h2>{$this->e($proposal['proposal_code'])}</h2>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width:50%">
                <table class="meta-table">
                    <tr><td class="meta-label">Customer</td><td>: {$this->e($proposal['customer_name'])}</td></tr>
                    <tr><td class="meta-label">Perusahaan</td><td>: {$this->e($proposal['company_name'] ?: '-')}</td></tr>
                    <tr><td class="meta-label">PIC</td><td>: {$this->e($proposal['customer_pic'] ?: '-')}</td></tr>
                    <tr><td class="meta-label">Telepon</td><td>: {$this->e($proposal['lead_phone'] ?: '-')}</td></tr>
                </table>
            </td>
            <td style="width:50%">
                <table class="meta-table">
                    <tr><td class="meta-label">Tanggal</td><td>: {$createdDate}</td></tr>
                    <tr><td class="meta-label">Berlaku Sampai</td><td>: {$validUntil}</td></tr>
                    <tr><td class="meta-label">Sales</td><td>: {$this->e($proposal['sales_name'] ?: '-')}</td></tr>
                    <tr><td class="meta-label">Project</td><td>: {$this->e($proposal['project_name'] ?: '-')}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {$this->pdfSection('Ruang Lingkup Pekerjaan', $proposal['scope_description'])}

    <div class="section-title">Rincian Penawaran</div>
    <table class="items">
        <thead>
            <tr><th style="width:24px">No</th><th>Item</th><th style="width:90px">Qty</th><th style="width:90px">Harga Satuan</th><th style="width:100px">Subtotal</th></tr>
        </thead>
        <tbody>{$rows}</tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="r">Rp {$this->e(number_format((float) $proposal['subtotal'], 0, ',', '.'))}</td></tr>
        {$this->pdfDiscountRow($proposal)}
        <tr><td>PPN ({$this->e(rtrim(rtrim(number_format((float) ($proposal['tax_percent'] ?? 0), 2, '.', ''), '0'), '.'))}%)</td><td class="r">Rp {$this->e(number_format((float) $proposal['tax_amount'], 0, ',', '.'))}</td></tr>
        <tr class="grand"><td>Total</td><td class="r">Rp {$this->e(number_format((float) $proposal['total'], 0, ',', '.'))}</td></tr>
    </table>

    {$this->pdfSection('Timeline Pengerjaan', $proposal['timeline_text'])}
    {$this->pdfSection('Syarat Pembayaran', $proposal['payment_terms'])}
    {$this->pdfSection('Garansi', $proposal['warranty'])}
    {$this->pdfSection('Syarat &amp; Ketentuan', $proposal['terms_conditions'])}

    <div class="footer">Dokumen ini dibuat otomatis oleh Sistem Internal Sales &middot; {$this->e($proposal['proposal_code'])}</div>
</body>
</html>
HTML;
    }

    private function pdfSection(string $title, ?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        return '<div class="section-title">' . $this->e($title) . '</div><div class="terms">' . $this->e($content) . '</div>';
    }

    private function pdfDiscountRow(array $proposal): string
    {
        if (empty($proposal['discount_amount']) || (float) $proposal['discount_amount'] <= 0) {
            return '';
        }

        $percent = $proposal['discount_percent'] !== null ? ' (' . rtrim(rtrim(number_format((float) $proposal['discount_percent'], 2, '.', ''), '0'), '.') . '%)' : '';

        return '<tr><td>Diskon' . $this->e($percent) . '</td><td class="r">- Rp ' . $this->e(number_format((float) $proposal['discount_amount'], 0, ',', '.')) . '</td></tr>';
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    public function findAuthorized(int $id): array
    {
        $proposal = Proposal::withRelations($id);

        if ($proposal === null) {
            $this->abort(404);
        }

        if (Acl::hasRole('sales') && (int) $proposal['sales_id'] !== Auth::id()) {
            $this->abort(403, 'Anda hanya dapat mengakses proposal milik Anda sendiri.');
        }

        return $proposal;
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

    private function findItem(array $proposal, int $itemId): array
    {
        $item = ProposalItem::find($itemId);

        if ($item === null || (int) $item['proposal_id'] !== (int) $proposal['id']) {
            $this->abort(404);
        }

        return $item;
    }

    private function scopeFilters(): array
    {
        if (Acl::hasRole('sales')) {
            return ['scope_sales_id' => Auth::id()];
        }

        return [];
    }

    /** Who may edit header/items, submit for review, send, and log customer response. */
    private function canOperate(array $proposal): bool
    {
        return Acl::can('proposal.edit') && (!Acl::hasRole('sales') || (int) $proposal['sales_id'] === Auth::id());
    }

    /** Who may approve / request revision — an oversight action, not ownership-gated. */
    private function canApprove(array $proposal): bool
    {
        return Acl::can('proposal.approve');
    }

    private function notifyOwner(array $proposal, string $type, string $title, string $message): void
    {
        $recipientId = (int) ($proposal['lead_sales_id'] ?: $proposal['sales_id']);
        if ($recipientId > 0) {
            Notification::create($recipientId, $type, $title, $message, '/proposals/' . $proposal['id']);
        }
    }

    private function syncLeadStatus(int $leadId, int $actorId, string $now): void
    {
        $lead = Lead::find($leadId);
        if ($lead !== null && in_array($lead['status'], ['engineering', 'procurement', 'pricing_ready'], true)) {
            Lead::update($leadId, ['status' => 'proposal', 'updated_by' => $actorId, 'updated_at' => $now]);
            LeadStatusHistory::record($leadId, $lead['status'], 'proposal', $actorId, 'Proposal sedang disusun.');
        }
    }

    private function salesRoleId(): ?int
    {
        static $id = null;
        if ($id === null) {
            $role = Role::findBySlug('sales');
            $id = $role ? (int) $role['id'] : 0;
        }

        return $id;
    }

    private function buildTimeline(int $proposalId): array
    {
        $statusEvents = array_map(function ($row) {
            return [
                'type' => 'status',
                'created_at' => $row['created_at'],
                'actor' => $row['changed_by_name'] ?? 'Sistem',
                'from' => $row['from_status'],
                'to' => $row['to_status'],
                'notes' => $row['notes'],
            ];
        }, ProposalStatusHistory::forProposal($proposalId));

        $noteEvents = array_map(function ($row) {
            return [
                'type' => 'note',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'note' => $row['note'],
            ];
        }, ProposalNote::forProposal($proposalId));

        $auditLabels = [
            'proposal_item_added' => 'Item ditambahkan',
            'proposal_item_updated' => 'Item diperbarui',
            'proposal_item_deleted' => 'Item dihapus',
            'proposal_pdf_generated' => 'PDF dibuat/diunduh',
        ];

        $auditEvents = array_values(array_filter(array_map(function ($row) use ($auditLabels) {
            if (!isset($auditLabels[$row['action']])) {
                return null;
            }

            $itemName = $row['old_data'] ? (json_decode($row['old_data'], true)['item_name'] ?? null) : null;
            $itemName ??= $row['new_data'] ? (json_decode($row['new_data'], true)['item_name'] ?? null) : null;

            return [
                'type' => 'audit',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'label' => $auditLabels[$row['action']] . ($itemName ? ": {$itemName}" : ''),
            ];
        }, AuditLog::forRecord('proposal', $proposalId))));

        $merged = array_merge($statusEvents, $noteEvents, $auditEvents);
        usort($merged, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $merged;
    }
}
