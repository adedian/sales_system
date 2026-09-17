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
use App\Models\EngineerAssignment;
use App\Models\EngineerAssignmentNote;
use App\Models\EngineerAssignmentStatusHistory;
use App\Models\EngineerDocument;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\MasterData;
use App\Models\Notification;
use App\Models\Prelim;
use App\Models\ProcurementRequest;
use App\Models\ProcurementStatusHistory;
use App\Models\Role;
use App\Models\User;

class EngineerController extends Controller
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
        'zip' => 'application/zip',
    ];

    public function index(Request $request): void
    {
        $scope = $this->scopeFilters();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', ''),
            'priority' => $request->input('priority', ''),
            'engineer_id' => $request->input('engineer_id', ''),
            'assignment_type' => $request->input('assignment_type', ''),
            'overdue' => $request->input('overdue') ? true : false,
            'include_closed' => $request->input('include_closed') ? true : false,
            'sort' => $request->input('sort', 'assigned_at'),
            'dir' => $request->input('dir', 'desc'),
            'page' => (int) $request->input('page', 1),
        ];

        $result = EngineerAssignment::search(array_merge($filters, $scope));

        $this->view('engineer/index', [
            'pageTitle' => 'Engineer Sales',
            'assignments' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'dashboardCounts' => EngineerAssignment::dashboardCounts($scope['scope_engineer_id'] ?? null, $scope['scope_sales_id'] ?? null),
            'statusMap' => MasterData::allAsMap('engineer_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            // Revisi Sub-Fase 2 — union of both flag pools for the filter dropdown (works for either assignment type).
            'engineerUsers' => $this->mergeUsersByName(User::activeEngineers(), User::activeSalesEngineers()),
            'canManage' => Acl::can('engineer.manage'),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        $this->view('engineer/show', [
            'pageTitle' => $assignment['assignment_code'],
            'assignment' => $assignment,
            'timeline' => $this->buildTimeline((int) $assignment['id']),
            'documents' => EngineerDocument::forAssignment((int) $assignment['id']),
            'statusMap' => MasterData::allAsMap('engineer_statuses'),
            'priorityMap' => MasterData::allAsMap('priorities'),
            'canOperate' => $this->canOperate($assignment),
            'canManage' => Acl::can('engineer.manage'),
            // Reassign dropdown must only offer people eligible for THIS assignment's own type.
            'engineerUsers' => $assignment['assignment_type'] === 'engineer' ? User::activeEngineers() : User::activeSalesEngineers(),
            'procurementUsers' => ($id = $this->procurementRoleId()) ? User::activeByRole($id) : [],
            'activeProcurementRequest' => ProcurementRequest::activeForLead((int) $assignment['lead_id']),
        ]);
    }

    /**
     * "Lead -> Request Engineer": Sales (or Admin Sales/Super Admin) sends a
     * lead for technical analysis. Mirrors LeadController::enqueue() — same
     * "one active item at a time" guard, same cross-model status sync.
     */
    public function requestAssignment(Request $request, array $params): void
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

        // Revisi Sub-Fase 2 — 'engineer' (survey lapangan lanjutan/desain) dan
        // 'sales_engineer' (analisa teknis, alur asli modul ini) tidak saling
        // memblokir: sebuah lead bisa punya assignment aktif untuk keduanya
        // sekaligus, berurutan.
        $type = $request->input('assignment_type', 'sales_engineer');
        if (!in_array($type, ['engineer', 'sales_engineer'], true)) {
            $type = 'sales_engineer';
        }

        // Revisi Alur Bisnis (Prelim) — the SAME assignment mechanism now
        // serves two different stages: 'survey' (Sales + SE/Engineer
        // melengkapi Data Awal SEBELUM Prelim ada) and 'engineering' (BOQ
        // work AFTER Client ACC Prelim, the pre-existing meaning of this
        // action). Default 'engineering' preserves every existing call
        // site's exact behavior.
        $purpose = $request->input('purpose', 'engineering');
        if (!in_array($purpose, ['survey', 'engineering'], true)) {
            $purpose = 'engineering';
        }

        if ($purpose === 'engineering' && !Prelim::hasApprovedForLead((int) $lead['id'])) {
            Session::flash('error', 'Assignment Sales Engineer/Engineer untuk Proposal+BOQ belum bisa dibuat karena Prelim belum di-ACC oleh Client.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (EngineerAssignment::activeForLead((int) $lead['id'], $type, $purpose) !== null) {
            $label = $type === 'engineer' ? 'Engineer' : 'Sales Engineer';
            $purposeLabel = $purpose === 'survey' ? 'Survey' : 'Proposal+BOQ';
            Session::flash('error', "Lead ini sudah memiliki assignment {$label} ({$purposeLabel}) yang masih berjalan.");
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $validator = new Validator($request->all(), [
            'engineer_id' => 'required',
            'priority' => 'in:low,medium,high,urgent',
            'notes_from_sales' => 'max:2000',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Pilih engineer yang akan menangani lead ini.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        // Eligibility is a capability flag (is_engineer/is_sales_engineer), not
        // the `engineer-sales` role — Fita/Rika keep role `sales` and are only
        // flagged is_sales_engineer, so a role check alone would wrongly
        // reject them.
        $flagColumn = $type === 'engineer' ? 'is_engineer' : 'is_sales_engineer';
        $engineerId = (int) $request->input('engineer_id');
        $engineer = User::find($engineerId);

        if ($engineer === null || (int) $engineer['is_active'] !== 1 || (int) ($engineer[$flagColumn] ?? 0) !== 1) {
            $label = $type === 'engineer' ? 'Engineer' : 'Sales Engineer';
            Session::flash('error', "{$label} tidak valid atau tidak aktif.");
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');

        $assignment = EngineerAssignment::createForLead([
            'lead_id' => (int) $lead['id'],
            'assignment_type' => $type,
            'purpose' => $purpose,
            'engineer_id' => $engineerId,
            'assigned_by' => (int) $actor['id'],
            'status' => 'pending',
            'priority' => $request->input('priority') ?: $lead['priority'],
            'deadline' => $request->input('deadline') ?: null,
            'notes_from_sales' => trim((string) $request->input('notes_from_sales', '')) ?: null,
            'assigned_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        EngineerAssignmentStatusHistory::record($assignment['id'], null, 'pending', (int) $actor['id'], 'Assignment dibuat, menunggu respon engineer.');
        AuditLogger::log((int) $actor['id'], 'engineer_assignment_requested', 'engineer_assignment', $assignment['id'], null, [
            'assignment_code' => $assignment['assignment_code'],
            'assignment_type' => $type,
            'lead_code' => $lead['lead_code'],
            'engineer_name' => $engineer['name'],
        ]);

        // Revisi Alur Bisnis (Prelim) — a 'survey' assignment happens BEFORE
        // Prelim exists, entirely within Sales' own early stage (new/in_queue/
        // follow_up); leads.status only advances to 'engineering' for the
        // real post-ACC BOQ work, matching how Current Process already
        // derives "Survey" purely from the assignment's own active state
        // (Lead::currentPosition()), not from leads.status.
        if ($purpose === 'engineering') {
            $oldLeadStatus = $lead['status'];
            if ($oldLeadStatus !== 'engineering') {
                Lead::update((int) $lead['id'], ['status' => 'engineering', 'updated_by' => $actor['id'], 'updated_at' => $now]);
                LeadStatusHistory::record((int) $lead['id'], $oldLeadStatus, 'engineering', (int) $actor['id'], 'Menunggu analisa teknis engineer.');
            }
        }

        Notification::create(
            $engineerId,
            'engineer_assignment_new',
            'Assignment Baru',
            $purpose === 'survey'
                ? "Lead {$lead['lead_code']} ({$lead['customer_name']}) menunggu Survey (lengkapi Data Awal) bersama Anda."
                : ($type === 'engineer'
                    ? "Lead {$lead['lead_code']} ({$lead['customer_name']}) menunggu survey teknis/desain Anda."
                    : "Lead {$lead['lead_code']} ({$lead['customer_name']}) menunggu analisa teknis Anda."),
            '/engineer/' . $assignment['id']
        );

        Session::flash('success', "Assignment {$assignment['assignment_code']} berhasil dikirim ke " . ($type === 'engineer' ? 'engineer' : 'sales engineer') . '.');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    public function accept(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->isOwnerEngineer($assignment) && !Acl::can('engineer.manage')) {
            $this->abort(403);

            return;
        }

        if ($assignment['status'] !== 'pending') {
            Session::flash('error', 'Assignment ini sudah direspon sebelumnya.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $now = date('Y-m-d H:i:s');
        EngineerAssignment::update((int) $assignment['id'], [
            'status' => 'accepted',
            'responded_at' => $now,
            'accepted_at' => $now,
            'updated_at' => $now,
        ]);

        EngineerAssignmentStatusHistory::record((int) $assignment['id'], 'pending', 'accepted', Auth::id());
        AuditLogger::log((int) Auth::id(), 'engineer_assignment_accepted', 'engineer_assignment', (int) $assignment['id']);

        Session::flash('success', 'Assignment diterima. Selamat bekerja!');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    public function reject(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->isOwnerEngineer($assignment) && !Acl::can('engineer.manage')) {
            $this->abort(403);

            return;
        }

        if ($assignment['status'] !== 'pending') {
            Session::flash('error', 'Assignment ini sudah direspon sebelumnya.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $validator = new Validator($request->all(), ['rejection_reason' => 'required|max:1000']);
        if ($validator->fails()) {
            Session::flash('error', 'Alasan penolakan wajib diisi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $reason = trim((string) $request->input('rejection_reason'));
        $now = date('Y-m-d H:i:s');

        EngineerAssignment::update((int) $assignment['id'], [
            'status' => 'rejected',
            'responded_at' => $now,
            'rejection_reason' => $reason,
            'updated_at' => $now,
        ]);

        EngineerAssignmentStatusHistory::record((int) $assignment['id'], 'pending', 'rejected', Auth::id(), $reason);
        AuditLogger::log((int) Auth::id(), 'engineer_assignment_rejected', 'engineer_assignment', (int) $assignment['id'], null, ['reason' => $reason]);

        Session::flash('success', 'Assignment ditolak.');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    /** Self-service work-in-progress tracking: accepted <-> in_progress <-> waiting. */
    public function updateStatus(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->canOperate($assignment)) {
            $this->abort(403);

            return;
        }

        $allowedFrom = ['accepted', 'in_progress', 'waiting'];
        $allowedTo = ['in_progress', 'waiting'];
        $newStatus = (string) $request->input('status');

        if (!in_array($assignment['status'], $allowedFrom, true) || !in_array($newStatus, $allowedTo, true)) {
            Session::flash('error', 'Perubahan status tidak valid untuk kondisi assignment saat ini.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $oldStatus = $assignment['status'];
        $notes = trim((string) $request->input('notes', '')) ?: null;
        $now = date('Y-m-d H:i:s');

        if ($newStatus !== $oldStatus) {
            EngineerAssignment::update((int) $assignment['id'], ['status' => $newStatus, 'updated_at' => $now]);
            EngineerAssignmentStatusHistory::record((int) $assignment['id'], $oldStatus, $newStatus, Auth::id(), $notes);
        }

        Session::flash('success', 'Status assignment diperbarui.');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    public function addNote(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->canOperate($assignment)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), ['note' => 'required|max:2000']);
        if ($validator->fails()) {
            Session::flash('error', 'Catatan teknis wajib diisi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $note = trim((string) $request->input('note'));
        EngineerAssignmentNote::add((int) $assignment['id'], Auth::id(), $note);
        AuditLogger::log((int) Auth::id(), 'engineer_assignment_note_added', 'engineer_assignment', (int) $assignment['id'], null, ['note' => $note]);

        Session::flash('success', 'Catatan teknis ditambahkan.');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    public function uploadDocument(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->canOperate($assignment)) {
            $this->abort(403);

            return;
        }

        $file = $_FILES['document'] ?? null;

        if ($file === null || !is_uploaded_file($file['tmp_name'] ?? '')) {
            Session::flash('error', 'Pilih file yang akan diunggah.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Gagal mengunggah file (kode error: ' . $file['error'] . ').');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if ($file['size'] > self::UPLOAD_MAX_BYTES) {
            Session::flash('error', 'Ukuran file maksimal 10MB.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $originalName = (string) $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!isset(self::UPLOAD_ALLOWED[$ext])) {
            Session::flash('error', 'Jenis file tidak diizinkan. Format yang didukung: ' . implode(', ', array_keys(self::UPLOAD_ALLOWED)) . '.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $detectedMime = @mime_content_type($file['tmp_name']) ?: 'application/octet-stream';

        $dir = $this->storageDir((int) $assignment['id']);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Session::flash('error', 'Gagal menyiapkan folder penyimpanan.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $dir . '/' . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Session::flash('error', 'Gagal menyimpan file.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        EngineerDocument::add([
            'assignment_id' => (int) $assignment['id'],
            'uploaded_by' => Auth::id(),
            'file_name' => mb_substr($originalName, 0, 255),
            'stored_filename' => $storedFilename,
            'file_path' => 'engineer/' . $assignment['id'] . '/' . $storedFilename,
            'file_size' => (int) $file['size'],
            'mime_type' => mb_substr($detectedMime, 0, 100),
        ]);

        AuditLogger::log((int) Auth::id(), 'engineer_document_uploaded', 'engineer_assignment', (int) $assignment['id'], null, ['file_name' => $originalName]);

        Session::flash('success', 'File pendukung berhasil diunggah.');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    public function downloadDocument(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);
        $document = EngineerDocument::find((int) $params['docId']);

        if ($document === null || (int) $document['assignment_id'] !== (int) $assignment['id']) {
            $this->abort(404);

            return;
        }

        $fullPath = dirname(__DIR__, 2) . '/storage/uploads/' . $document['file_path'];

        if (!is_file($fullPath)) {
            $this->abort(404, 'File tidak ditemukan.');

            return;
        }

        header('Content-Type: ' . ($document['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $document['file_name']) . '"');
        header('Content-Length: ' . (string) filesize($fullPath));
        header('X-Content-Type-Options: nosniff');
        readfile($fullPath);
        exit;
    }

    public function deleteDocument(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);
        $document = EngineerDocument::find((int) $params['docId']);

        if ($document === null || (int) $document['assignment_id'] !== (int) $assignment['id']) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->canOperate($assignment)) {
            $this->abort(403);

            return;
        }

        $fullPath = dirname(__DIR__, 2) . '/storage/uploads/' . $document['file_path'];
        EngineerDocument::delete((int) $document['id']);

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }

        AuditLogger::log((int) Auth::id(), 'engineer_document_deleted', 'engineer_assignment', (int) $assignment['id'], ['file_name' => $document['file_name']], null);

        Session::flash('success', 'File dihapus.');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    /** "Add analysis/result" — also the moment Sales gets notified (see class docblock in Notification). */
    public function submitResult(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->canOperate($assignment)) {
            $this->abort(403);

            return;
        }

        if (!in_array($assignment['status'], ['accepted', 'in_progress', 'waiting', 'completed'], true)) {
            Session::flash('error', 'Assignment harus diterima terlebih dahulu sebelum mengisi hasil analisa.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $validator = new Validator($request->all(), ['result_notes' => 'required|max:5000']);
        if ($validator->fails()) {
            Session::flash('error', 'Hasil analisa teknis wajib diisi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $oldStatus = $assignment['status'];
        $resultNotes = trim((string) $request->input('result_notes'));
        $now = date('Y-m-d H:i:s');
        $wasAlreadyCompleted = $oldStatus === 'completed';

        EngineerAssignment::update((int) $assignment['id'], [
            'status' => 'completed',
            'result_notes' => $resultNotes,
            'completed_at' => $wasAlreadyCompleted ? $assignment['completed_at'] : $now,
            'updated_at' => $now,
        ]);

        if (!$wasAlreadyCompleted) {
            EngineerAssignmentStatusHistory::record((int) $assignment['id'], $oldStatus, 'completed', Auth::id(), 'Hasil analisa teknis disimpan.');
        }

        AuditLogger::log((int) Auth::id(), 'engineer_assignment_result_added', 'engineer_assignment', (int) $assignment['id']);

        $recipientId = (int) ($assignment['lead_sales_id'] ?: $assignment['assigned_by']);
        if ($recipientId > 0) {
            Notification::create(
                $recipientId,
                'engineer_assignment_result',
                'Hasil Analisa Teknis Siap',
                "{$assignment['engineer_name']} telah mengisi hasil analisa untuk lead {$assignment['lead_code']} ({$assignment['customer_name']}).",
                '/engineer/' . $assignment['id']
            );
        }

        Session::flash('success', 'Hasil analisa teknis tersimpan.');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    /** "Return to Sales" — the closing step of the flow; archives the assignment and hands the lead back to Sales for follow-up. */
    public function returnToSales(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->canOperate($assignment)) {
            $this->abort(403);

            return;
        }

        if ($assignment['status'] !== 'completed') {
            Session::flash('error', 'Isi hasil analisa terlebih dahulu sebelum mengembalikan ke sales.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $now = date('Y-m-d H:i:s');
        EngineerAssignment::update((int) $assignment['id'], ['status' => 'returned', 'updated_at' => $now]);
        EngineerAssignmentStatusHistory::record((int) $assignment['id'], 'completed', 'returned', Auth::id(), 'Dikembalikan ke sales.');
        AuditLogger::log((int) Auth::id(), 'engineer_assignment_returned', 'engineer_assignment', (int) $assignment['id']);

        // Revisi Sub-Fase 2 — hasil tipe 'engineer' belum menyelesaikan
        // tahap teknis lead (masih perlu Sales Engineer setelahnya), jadi
        // leads.status TIDAK dilompat ke follow_up di sini seperti alur
        // sales_engineer; Sales tinggal klik "Minta Sales Engineer".
        if ($assignment['assignment_type'] === 'sales_engineer') {
            $lead = Lead::find((int) $assignment['lead_id']);
            if ($lead !== null && $lead['status'] === 'engineering') {
                Lead::update((int) $lead['id'], ['status' => 'follow_up', 'updated_by' => Auth::id(), 'updated_at' => $now]);
                LeadStatusHistory::record((int) $lead['id'], 'engineering', 'follow_up', Auth::id(), 'Hasil analisa teknis diterima dari sales engineer.');
            }
        }

        Session::flash('success', 'Assignment dikembalikan ke sales.');
        $this->redirect('/engineer/' . $assignment['id']);
    }

    /**
     * "Technical Completed -> Procurement": alternative to returnToSales()
     * for jobs that need vendor sourcing/pricing before going back to Sales.
     * Purely additive — returnToSales() above is untouched and still used
     * for jobs that don't need procurement at all.
     */
    public function sendToProcurement(Request $request, array $params): void
    {
        $assignment = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (!$this->canOperate($assignment)) {
            $this->abort(403);

            return;
        }

        if ($assignment['status'] !== 'completed') {
            Session::flash('error', 'Isi hasil analisa terlebih dahulu sebelum mengirim ke procurement.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        // Revisi Sub-Fase 2 — hasil Engineer (survey lapangan/desain) tidak
        // boleh lompat langsung ke Procurement, harus lewat Sales Engineer
        // dulu sesuai flow dokumen.
        if ($assignment['assignment_type'] === 'engineer') {
            Session::flash('error', 'Hasil Engineer harus diteruskan ke Sales Engineer terlebih dahulu, belum bisa langsung ke Procurement.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        // Revisi Alur Bisnis (Prelim) — a 'survey' assignment exists only to
        // complete Data Awal before Prelim; it can never legitimately reach
        // Procurement (BOQ pricing) — that must come from an 'engineering'
        // (post-ACC) assignment, and only once Prelim has been ACC'd.
        if ($assignment['purpose'] !== 'engineering' || !Prelim::hasApprovedForLead((int) $assignment['lead_id'])) {
            Session::flash('error', 'Belum bisa mengirim ke Procurement karena Prelim belum di-ACC oleh Client.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        if (ProcurementRequest::activeForLead((int) $assignment['lead_id']) !== null) {
            Session::flash('error', 'Lead ini sudah memiliki request procurement yang masih berjalan.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $validator = new Validator($request->all(), ['procurement_id' => 'required']);
        if ($validator->fails()) {
            Session::flash('error', 'Pilih staff procurement yang akan menangani lead ini.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $procurementRole = Role::findBySlug('procurement');
        $procurementUserId = (int) $request->input('procurement_id');
        $procurementUser = User::find($procurementUserId);

        if ($procurementUser === null || (int) $procurementUser['is_active'] !== 1 || (int) $procurementUser['role_id'] !== (int) ($procurementRole['id'] ?? 0)) {
            Session::flash('error', 'Staff procurement tidak valid atau tidak aktif.');
            $this->redirect('/engineer/' . $assignment['id']);

            return;
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');

        $pr = ProcurementRequest::createForLead([
            'lead_id' => (int) $assignment['lead_id'],
            'engineer_assignment_id' => (int) $assignment['id'],
            'assigned_to' => $procurementUserId,
            'requested_by' => (int) $actor['id'],
            'status' => 'waiting',
            'priority' => $assignment['priority'],
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ProcurementStatusHistory::record($pr['id'], null, 'waiting', (int) $actor['id'], 'Request dibuat dari hasil Engineer.');
        AuditLogger::log((int) $actor['id'], 'procurement_requested', 'procurement_request', $pr['id'], null, [
            'request_code' => $pr['request_code'],
            'lead_code' => $assignment['lead_code'],
            'assigned_to' => $procurementUser['name'],
        ]);

        EngineerAssignment::update((int) $assignment['id'], ['status' => 'returned', 'updated_at' => $now]);
        EngineerAssignmentStatusHistory::record((int) $assignment['id'], 'completed', 'returned', (int) $actor['id'], 'Dikirim ke Procurement.');

        $lead = Lead::find((int) $assignment['lead_id']);
        if ($lead !== null && $lead['status'] === 'engineering') {
            Lead::update((int) $lead['id'], ['status' => 'procurement', 'updated_by' => $actor['id'], 'updated_at' => $now]);
            LeadStatusHistory::record((int) $lead['id'], 'engineering', 'procurement', (int) $actor['id'], 'Menunggu pencarian vendor & pricing dari procurement.');
        }

        Notification::create(
            $procurementUserId,
            'procurement_request_new',
            'Request Procurement Baru',
            "Lead {$assignment['lead_code']} ({$assignment['customer_name']}) menunggu pencarian vendor & pricing.",
            '/procurement/' . $pr['id']
        );

        Session::flash('success', "Request procurement {$pr['request_code']} berhasil dikirim.");
        $this->redirect('/procurement/' . $pr['id']);
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    public function findAuthorized(int $id): array
    {
        $assignment = EngineerAssignment::withRelations($id);

        if ($assignment === null) {
            $this->abort(404);
        }

        $isAssignee = $this->isOwnerEngineer($assignment);

        // Revisi Sub-Fase 2 — Engineer/Sales Engineer eligibility is a
        // capability flag now (is_engineer/is_sales_engineer), not the
        // `engineer-sales` role, so a flag-holder can keep role `sales`
        // (Fita/Rika). Gate on "is this a pooled worker viewing someone
        // else's item" via the flag instead of the role.
        $actor = Auth::user();
        $inEngineerPool = $actor && ((int) ($actor['is_engineer'] ?? 0) === 1 || (int) ($actor['is_sales_engineer'] ?? 0) === 1 || Acl::hasRole('engineer-sales'));

        if ($inEngineerPool && !$isAssignee && !Acl::can('engineer.manage')) {
            $this->abort(403, 'Anda hanya dapat mengakses assignment yang ditugaskan kepada Anda.');
        }

        if (Acl::hasRole('sales') && !$isAssignee && (int) $assignment['lead_sales_id'] !== Auth::id()) {
            $this->abort(403, 'Anda hanya dapat mengakses assignment dari lead Anda sendiri.');
        }

        return $assignment;
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

    private function scopeFilters(): array
    {
        $actor = Auth::user();
        $inEngineerPool = $actor && ((int) ($actor['is_engineer'] ?? 0) === 1 || (int) ($actor['is_sales_engineer'] ?? 0) === 1);

        if (Acl::hasRole('engineer-sales') || $inEngineerPool) {
            return ['scope_engineer_id' => Auth::id()];
        }

        if (Acl::hasRole('sales')) {
            return ['scope_sales_id' => Auth::id()];
        }

        return [];
    }

    /** Revisi Sub-Fase 2 — pure ownership check now (works for any role, since eligibility is a capability flag, not the `engineer-sales` role). */
    private function isOwnerEngineer(array $assignment): bool
    {
        return (int) $assignment['engineer_id'] === Auth::id();
    }

    /** Who may accept/reject/update status/add notes/upload files/fill result/return. */
    private function canOperate(array $assignment): bool
    {
        return Acl::can('engineer.manage') || $this->isOwnerEngineer($assignment);
    }

    /** @param array<int,array<string,mixed>> ...$lists */
    private function mergeUsersByName(array ...$lists): array
    {
        $merged = [];
        foreach ($lists as $list) {
            foreach ($list as $row) {
                $merged[(int) $row['id']] = $row;
            }
        }

        usort($merged, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return array_values($merged);
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

    private function storageDir(int $assignmentId): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/engineer/' . $assignmentId;
    }

    private function buildTimeline(int $assignmentId): array
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
        }, EngineerAssignmentStatusHistory::forAssignment($assignmentId));

        $noteEvents = array_map(function ($row) {
            return [
                'type' => 'note',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'note' => $row['note'],
            ];
        }, EngineerAssignmentNote::forAssignment($assignmentId));

        $documentEvents = array_map(function ($row) {
            return [
                'type' => 'document',
                'created_at' => $row['created_at'],
                'actor' => $row['uploaded_by_name'] ?? 'Sistem',
                'file_name' => $row['file_name'],
            ];
        }, EngineerDocument::forAssignment($assignmentId));

        // 'result_added' and 'returned' are intentionally excluded — both already
        // appear via the status-history entries above (…-> completed / -> returned).
        $auditLabels = [
            'engineer_assignment_priority_changed' => 'Prioritas diubah',
            'engineer_assignment_deadline_changed' => 'Deadline diubah',
            'engineer_assignment_reassigned' => 'Dialihkan ke engineer lain',
        ];

        $auditEvents = array_values(array_filter(array_map(function ($row) use ($auditLabels) {
            if (!isset($auditLabels[$row['action']])) {
                return null;
            }

            return [
                'type' => 'audit',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'label' => $auditLabels[$row['action']],
            ];
        }, AuditLog::forRecord('engineer_assignment', $assignmentId))));

        $merged = array_merge($statusEvents, $noteEvents, $documentEvents, $auditEvents);
        usort($merged, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $merged;
    }
}
