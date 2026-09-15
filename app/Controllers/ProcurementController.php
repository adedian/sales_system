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
use App\Models\ProcurementRequestNote;
use App\Models\ProcurementStatusHistory;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;

class ProcurementController extends Controller
{
    private const UPLOAD_MAX_BYTES = 10 * 1024 * 1024; // 10MB
    private const UPLOAD_ALLOWED = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public function index(Request $request): void
    {
        $scope = $this->scopeFilters();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', ''),
            'priority' => $request->input('priority', ''),
            'assigned_to' => $request->input('assigned_to', ''),
            'overdue' => $request->input('overdue') ? true : false,
            'include_closed' => $request->input('include_closed') ? true : false,
            'sort' => $request->input('sort', 'requested_at'),
            'dir' => $request->input('dir', 'desc'),
            'page' => (int) $request->input('page', 1),
        ];

        $result = ProcurementRequest::search(array_merge($filters, $scope));

        $this->view('procurement/index', [
            'pageTitle' => 'Procurement',
            'requests' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'dashboardCounts' => ProcurementRequest::dashboardCounts($scope['scope_assigned_to'] ?? null, $scope['scope_sales_id'] ?? null),
            'statusMap' => MasterData::allAsMap('procurement_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'procurementUsers' => ($id = $this->procurementRoleId()) ? User::activeByRole($id) : [],
            'canManage' => Acl::can('procurement.manage'),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);

        $this->view('procurement/show', [
            'pageTitle' => $pr['request_code'],
            'pr' => $pr,
            'items' => ProcurementItem::forRequest((int) $pr['id']),
            'allPriced' => ProcurementItem::allPriced((int) $pr['id']),
            'totalPurchasePrice' => ProcurementItem::totalPurchasePrice((int) $pr['id']),
            'timeline' => $this->buildTimeline((int) $pr['id']),
            'statusMap' => MasterData::allAsMap('procurement_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'vendors' => Vendor::activeList(),
            'canOperate' => $this->canOperate($pr),
            'canManage' => Acl::can('procurement.manage'),
            'procurementUsers' => ($id = $this->procurementRoleId()) ? User::activeByRole($id) : [],
            'existingProposal' => \App\Core\Database::fetch(
                "SELECT id, proposal_code FROM proposals WHERE procurement_request_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1",
                [$pr['id']]
            ),
            'canCreateProposal' => Acl::can('proposal.create'),
        ]);
    }

    /**
     * "Lead -> Minta Procurement": Sales (or Admin Sales/Super Admin) sends a
     * lead straight to Procurement, e.g. for standard-catalog orders that
     * don't need an Engineer's technical analysis first. Mirrors
     * EngineerController::requestAssignment() — same "one active item at a
     * time" guard, same cross-model status sync.
     */
    public function requestFromLead(Request $request, array $params): void
    {
        $lead = $this->findAuthorizedLead((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if ($lead['deleted_at']) {
            $this->abort(404);

            return;
        }

        if (ProcurementRequest::activeForLead((int) $lead['id']) !== null) {
            Session::flash('error', 'Lead ini sudah memiliki request procurement yang masih berjalan.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $validator = new Validator($request->all(), [
            'assigned_to' => 'required',
            'priority' => 'in:low,medium,high,urgent',
            'notes' => 'max:2000',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Pilih staff procurement yang akan menangani lead ini.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $procurementUser = $this->validProcurementUser((int) $request->input('assigned_to'));
        if ($procurementUser === null) {
            Session::flash('error', 'Staff procurement tidak valid atau tidak aktif.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');

        $pr = ProcurementRequest::createForLead([
            'lead_id' => (int) $lead['id'],
            'engineer_assignment_id' => null,
            'assigned_to' => (int) $procurementUser['id'],
            'requested_by' => (int) $actor['id'],
            'status' => 'waiting',
            'priority' => $request->input('priority') ?: $lead['priority'],
            'deadline' => $request->input('deadline') ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->afterCreated($pr, $lead, $actor, $procurementUser, $now);

        Session::flash('success', "Request procurement {$pr['request_code']} berhasil dikirim.");
        $this->redirect('/procurement/' . $pr['id']);
    }

    // ------------------------------------------------------------------
    // Line items (BOQ / vendor pricing)
    // ------------------------------------------------------------------

    public function addItem(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!$this->canOperate($pr)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), [
            'item_name' => 'required|max:200',
            'quantity' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Nama item dan kuantitas wajib diisi dengan benar.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        $now = date('Y-m-d H:i:s');
        ProcurementItem::insert([
            'procurement_request_id' => (int) $pr['id'],
            'item_name' => trim((string) $request->input('item_name')),
            'specification' => trim((string) $request->input('specification', '')) ?: null,
            'quantity' => (float) $request->input('quantity'),
            'unit' => trim((string) $request->input('unit', '')) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLogger::log((int) Auth::id(), 'procurement_item_added', 'procurement_request', (int) $pr['id'], null, ['item_name' => $request->input('item_name')]);

        Session::flash('success', 'Item berhasil ditambahkan.');
        $this->redirect('/procurement/' . $pr['id']);
    }

    /** Updates one item's vendor/price/quotation fields — optionally with a new quotation file. */
    public function updateItem(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);
        $item = $this->findItem($pr, (int) $params['itemId']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!$this->canOperate($pr)) {
            $this->abort(403);

            return;
        }

        // Build the update from only the fields actually present in the request — a
        // partial submission (e.g. uploading just the quotation file) must not wipe
        // out fields it didn't include, unlike a plain "resend everything" form.
        $fields = $request->all();
        $newData = ['updated_at' => date('Y-m-d H:i:s')];

        if (array_key_exists('vendor_id', $fields)) {
            $vendorId = $this->nullableInt($fields['vendor_id']);
            if ($vendorId !== null && Vendor::find($vendorId) === null) {
                Session::flash('error', 'Vendor tidak valid.');
                $this->redirect('/procurement/' . $pr['id']);

                return;
            }
            $newData['vendor_id'] = $vendorId;
        }

        if (array_key_exists('supplier_name', $fields)) {
            $newData['supplier_name'] = trim((string) $fields['supplier_name']) ?: null;
        }

        if (array_key_exists('purchase_price', $fields)) {
            $purchasePrice = $fields['purchase_price'];
            $newData['purchase_price'] = ($purchasePrice !== null && $purchasePrice !== '') ? (float) $purchasePrice : null;
        }

        if (array_key_exists('quotation_number', $fields)) {
            $newData['quotation_number'] = trim((string) $fields['quotation_number']) ?: null;
        }

        if (array_key_exists('quotation_date', $fields)) {
            $newData['quotation_date'] = $fields['quotation_date'] ?: null;
        }

        if (array_key_exists('validity_date', $fields)) {
            $newData['validity_date'] = $fields['validity_date'] ?: null;
        }

        if (array_key_exists('notes', $fields)) {
            $newData['notes'] = trim((string) $fields['notes']) ?: null;
        }

        $file = $_FILES['quotation_attachment'] ?? null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploaded = $this->handleQuotationUpload($pr, $item, $file);
            if ($uploaded === null) {
                return; // handleQuotationUpload already flashed the error + redirected
            }
            $newData = array_merge($newData, $uploaded);
        }

        ProcurementItem::update((int) $item['id'], $newData);

        AuditLogger::log((int) Auth::id(), 'procurement_item_updated', 'procurement_request', (int) $pr['id'], null, ['item_name' => $item['item_name']]);

        Session::flash('success', 'Item berhasil diperbarui.');
        $this->redirect('/procurement/' . $pr['id']);
    }

    public function deleteItem(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);
        $item = $this->findItem($pr, (int) $params['itemId']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!$this->canOperate($pr)) {
            $this->abort(403);

            return;
        }

        $fullPath = $this->quotationFullPath($item);
        ProcurementItem::delete((int) $item['id']);

        if ($fullPath !== null && is_file($fullPath)) {
            @unlink($fullPath);
        }

        AuditLogger::log((int) Auth::id(), 'procurement_item_deleted', 'procurement_request', (int) $pr['id'], ['item_name' => $item['item_name']], null);

        Session::flash('success', 'Item dihapus.');
        $this->redirect('/procurement/' . $pr['id']);
    }

    public function downloadQuotation(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);
        $item = $this->findItem($pr, (int) $params['itemId']);

        $fullPath = $this->quotationFullPath($item);
        if ($fullPath === null || !is_file($fullPath)) {
            $this->abort(404, 'File tidak ditemukan.');

            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $item['quotation_file_name']) . '"');
        header('Content-Length: ' . (string) filesize($fullPath));
        header('X-Content-Type-Options: nosniff');
        readfile($fullPath);
        exit;
    }

    // ------------------------------------------------------------------
    // Status & notes
    // ------------------------------------------------------------------

    /** Self-service progress tracking: waiting/in_progress/quotation_requested/need_revision -> in_progress or quotation_requested. */
    public function updateStatus(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!$this->canOperate($pr)) {
            $this->abort(403);

            return;
        }

        $allowedFrom = ['waiting', 'in_progress', 'quotation_requested', 'need_revision'];
        $allowedTo = ['in_progress', 'quotation_requested'];
        $newStatus = (string) $request->input('status');

        if (!in_array($pr['status'], $allowedFrom, true) || !in_array($newStatus, $allowedTo, true)) {
            Session::flash('error', 'Perubahan status tidak valid untuk kondisi request saat ini.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        $oldStatus = $pr['status'];
        $now = date('Y-m-d H:i:s');
        $updateData = ['status' => $newStatus, 'updated_at' => $now];
        if ($pr['started_at'] === null) {
            $updateData['started_at'] = $now;
        }

        ProcurementRequest::update((int) $pr['id'], $updateData);
        ProcurementStatusHistory::record((int) $pr['id'], $oldStatus, $newStatus, Auth::id());

        Session::flash('success', 'Status request diperbarui.');
        $this->redirect('/procurement/' . $pr['id']);
    }

    public function markNeedRevision(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!$this->canOperate($pr)) {
            $this->abort(403);

            return;
        }

        if (in_array($pr['status'], ['pricing_completed', 'cancelled'], true)) {
            Session::flash('error', 'Request yang sudah selesai/dibatalkan tidak dapat ditandai butuh revisi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        $validator = new Validator($request->all(), ['revision_reason' => 'required|max:1000']);
        if ($validator->fails()) {
            Session::flash('error', 'Alasan revisi wajib diisi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        $reason = trim((string) $request->input('revision_reason'));
        $oldStatus = $pr['status'];
        $now = date('Y-m-d H:i:s');

        ProcurementRequest::update((int) $pr['id'], [
            'status' => 'need_revision',
            'revision_reason' => $reason,
            'updated_at' => $now,
        ]);

        ProcurementStatusHistory::record((int) $pr['id'], $oldStatus, 'need_revision', Auth::id(), $reason);
        AuditLogger::log((int) Auth::id(), 'procurement_need_revision', 'procurement_request', (int) $pr['id'], null, ['reason' => $reason]);

        $recipientId = (int) ($pr['lead_sales_id'] ?: $pr['requested_by']);
        if ($recipientId > 0) {
            Notification::create(
                $recipientId,
                'procurement_need_revision',
                'Procurement Butuh Revisi',
                "Request {$pr['request_code']} untuk lead {$pr['lead_code']} ({$pr['customer_name']}) butuh data tambahan: {$reason}",
                '/procurement/' . $pr['id']
            );
        }

        Session::flash('success', 'Request ditandai butuh revisi.');
        $this->redirect('/procurement/' . $pr['id']);
    }

    public function cancel(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!$this->canOperate($pr)) {
            $this->abort(403);

            return;
        }

        if (in_array($pr['status'], ['pricing_completed', 'cancelled'], true)) {
            Session::flash('error', 'Request ini sudah final.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        $oldStatus = $pr['status'];
        ProcurementRequest::update((int) $pr['id'], ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
        ProcurementStatusHistory::record((int) $pr['id'], $oldStatus, 'cancelled', Auth::id());
        AuditLogger::log((int) Auth::id(), 'procurement_cancelled', 'procurement_request', (int) $pr['id']);

        Session::flash('success', 'Request procurement dibatalkan.');
        $this->redirect('/procurement/' . $pr['id']);
    }

    public function addNote(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!$this->canOperate($pr)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), ['note' => 'required|max:2000']);
        if ($validator->fails()) {
            Session::flash('error', 'Catatan wajib diisi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        $note = trim((string) $request->input('note'));
        ProcurementRequestNote::add((int) $pr['id'], Auth::id(), $note);
        AuditLogger::log((int) Auth::id(), 'procurement_note_added', 'procurement_request', (int) $pr['id'], null, ['note' => $note]);

        Session::flash('success', 'Catatan ditambahkan.');
        $this->redirect('/procurement/' . $pr['id']);
    }

    /** "Pricing Completed -> Sales" — the single closing step of the flow (spec draws one direct arrow, unlike Engineer's two-step complete-then-return). */
    public function markCompleted(Request $request, array $params): void
    {
        $pr = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!$this->canOperate($pr)) {
            $this->abort(403);

            return;
        }

        if (in_array($pr['status'], ['pricing_completed', 'cancelled'], true)) {
            Session::flash('error', 'Request ini sudah final.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        if (!ProcurementItem::allPriced((int) $pr['id'])) {
            Session::flash('error', 'Semua item harus memiliki minimal 1 item dan harga beli terisi sebelum pricing bisa diselesaikan.');
            $this->redirect('/procurement/' . $pr['id']);

            return;
        }

        $oldStatus = $pr['status'];
        $now = date('Y-m-d H:i:s');

        ProcurementRequest::update((int) $pr['id'], [
            'status' => 'pricing_completed',
            'completed_at' => $now,
            'updated_at' => $now,
        ]);

        ProcurementStatusHistory::record((int) $pr['id'], $oldStatus, 'pricing_completed', Auth::id(), 'Pricing selesai, dikembalikan ke sales.');
        AuditLogger::log((int) Auth::id(), 'procurement_completed', 'procurement_request', (int) $pr['id']);

        $lead = Lead::find((int) $pr['lead_id']);
        if ($lead !== null && in_array($lead['status'], ['engineering', 'procurement'], true)) {
            Lead::update((int) $lead['id'], ['status' => 'pricing_ready', 'updated_by' => Auth::id(), 'updated_at' => $now]);
            LeadStatusHistory::record((int) $lead['id'], $lead['status'], 'pricing_ready', Auth::id(), 'Harga dari procurement siap, kembali ke sales.');
        }

        $recipientId = (int) ($pr['lead_sales_id'] ?: $pr['requested_by']);
        if ($recipientId > 0) {
            Notification::create(
                $recipientId,
                'procurement_pricing_completed',
                'Harga dari Procurement Siap',
                "Pricing untuk lead {$pr['lead_code']} ({$pr['customer_name']}) sudah selesai — total estimasi " . number_format(ProcurementItem::totalPurchasePrice((int) $pr['id']), 0, ',', '.') . '.',
                '/procurement/' . $pr['id']
            );
        }

        Session::flash('success', 'Pricing selesai, request dikembalikan ke sales.');
        $this->redirect('/procurement/' . $pr['id']);
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    public function findAuthorized(int $id): array
    {
        $pr = ProcurementRequest::withRelations($id);

        if ($pr === null) {
            $this->abort(404);
        }

        if (Acl::hasRole('procurement') && (int) $pr['assigned_to'] !== Auth::id()) {
            $this->abort(403, 'Anda hanya dapat mengakses request yang ditugaskan kepada Anda.');
        }

        if (Acl::hasRole('sales') && (int) $pr['lead_sales_id'] !== Auth::id()) {
            $this->abort(403, 'Anda hanya dapat mengakses request dari lead Anda sendiri.');
        }

        return $pr;
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

    private function findItem(array $pr, int $itemId): array
    {
        $item = ProcurementItem::find($itemId);

        if ($item === null || (int) $item['procurement_request_id'] !== (int) $pr['id']) {
            $this->abort(404);
        }

        return $item;
    }

    private function scopeFilters(): array
    {
        if (Acl::hasRole('procurement')) {
            return ['scope_assigned_to' => Auth::id()];
        }

        if (Acl::hasRole('sales')) {
            return ['scope_sales_id' => Auth::id()];
        }

        return [];
    }

    private function canOperate(array $pr): bool
    {
        return Acl::can('procurement.manage') || (Acl::hasRole('procurement') && (int) $pr['assigned_to'] === Auth::id());
    }

    private function procurementRoleId(): ?int
    {
        static $id = null;
        if ($id === null) {
            $role = Role::findBySlug('procurement');
            $id = $role ? (int) $role['id'] : 0;
        }

        return $id;
    }

    private function validProcurementUser(int $userId): ?array
    {
        $role = Role::findBySlug('procurement');
        $user = $userId ? User::find($userId) : null;

        if ($user === null || (int) $user['is_active'] !== 1 || (int) $user['role_id'] !== (int) ($role['id'] ?? 0)) {
            return null;
        }

        return $user;
    }

    private function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /** Shared "request created" bookkeeping: history + audit + lead status sync + notification. */
    private function afterCreated(array $pr, array $lead, array $actor, array $procurementUser, string $now): void
    {
        ProcurementStatusHistory::record($pr['id'], null, 'waiting', (int) $actor['id'], 'Request dibuat, menunggu diproses procurement.');
        AuditLogger::log((int) $actor['id'], 'procurement_requested', 'procurement_request', $pr['id'], null, [
            'request_code' => $pr['request_code'],
            'lead_code' => $lead['lead_code'],
            'assigned_to' => $procurementUser['name'],
        ]);

        $oldLeadStatus = $lead['status'];
        if ($oldLeadStatus !== 'procurement') {
            Lead::update((int) $lead['id'], ['status' => 'procurement', 'updated_by' => $actor['id'], 'updated_at' => $now]);
            LeadStatusHistory::record((int) $lead['id'], $oldLeadStatus, 'procurement', (int) $actor['id'], 'Menunggu pricing dari procurement.');
        }

        Notification::create(
            (int) $procurementUser['id'],
            'procurement_request_new',
            'Request Procurement Baru',
            "Lead {$lead['lead_code']} ({$lead['customer_name']}) menunggu pencarian vendor & pricing.",
            '/procurement/' . $pr['id']
        );
    }

    private function quotationFullPath(array $item): ?string
    {
        if (empty($item['quotation_file_path'])) {
            return null;
        }

        return dirname(__DIR__, 2) . '/storage/uploads/' . $item['quotation_file_path'];
    }

    /** @return array<string,string>|null field values to merge into the item update, or null if it already handled the error response */
    private function handleQuotationUpload(array $pr, array $item, array $file): ?array
    {
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            Session::flash('error', 'Gagal membaca file yang diunggah.');
            $this->redirect('/procurement/' . $pr['id']);

            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Gagal mengunggah file (kode error: ' . $file['error'] . ').');
            $this->redirect('/procurement/' . $pr['id']);

            return null;
        }

        if ($file['size'] > self::UPLOAD_MAX_BYTES) {
            Session::flash('error', 'Ukuran file quotation maksimal 10MB.');
            $this->redirect('/procurement/' . $pr['id']);

            return null;
        }

        $originalName = (string) $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!isset(self::UPLOAD_ALLOWED[$ext])) {
            Session::flash('error', 'Jenis file tidak diizinkan. Format yang didukung: ' . implode(', ', array_keys(self::UPLOAD_ALLOWED)) . '.');
            $this->redirect('/procurement/' . $pr['id']);

            return null;
        }

        $dir = dirname(__DIR__, 2) . '/storage/uploads/procurement/' . $pr['id'];
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Session::flash('error', 'Gagal menyiapkan folder penyimpanan.');
            $this->redirect('/procurement/' . $pr['id']);

            return null;
        }

        $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $dir . '/' . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Session::flash('error', 'Gagal menyimpan file.');
            $this->redirect('/procurement/' . $pr['id']);

            return null;
        }

        // Replace: remove the previous quotation file for this item, if any.
        $oldPath = $this->quotationFullPath($item);
        if ($oldPath !== null && is_file($oldPath)) {
            @unlink($oldPath);
        }

        return [
            'quotation_file_name' => mb_substr($originalName, 0, 255),
            'quotation_stored_filename' => $storedFilename,
            'quotation_file_path' => 'procurement/' . $pr['id'] . '/' . $storedFilename,
        ];
    }

    private function buildTimeline(int $procurementRequestId): array
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
        }, ProcurementStatusHistory::forRequest($procurementRequestId));

        $noteEvents = array_map(function ($row) {
            return [
                'type' => 'note',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'note' => $row['note'],
            ];
        }, ProcurementRequestNote::forRequest($procurementRequestId));

        $auditLabels = [
            'procurement_item_added' => 'Item ditambahkan',
            'procurement_item_updated' => 'Item diperbarui',
            'procurement_item_deleted' => 'Item dihapus',
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
        }, AuditLog::forRecord('procurement_request', $procurementRequestId))));

        $merged = array_merge($statusEvents, $noteEvents, $auditEvents);
        usort($merged, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $merged;
    }
}
