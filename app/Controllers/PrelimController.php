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
use App\Models\Lead;
use App\Models\MasterData;
use App\Models\Prelim;
use App\Models\PrelimDocument;
use App\Models\PrelimNote;
use App\Models\PrelimStatusHistory;
use App\Models\User;

/**
 * Revisi Alur Bisnis — Prelim: penawaran awal ke Client sebelum Proposal+BOQ.
 * Structurally mirrors ProposalController (header + status flow + notes +
 * documents + timeline) but deliberately has NO line-item/BOQ endpoints and
 * NO internal-review/approve step — the business spec's Prelim lifecycle is
 * only Draft -> (Ready to Send) -> Sent -> Client ACC/Revisi, decided solely
 * by Sales and the Client (no internal Manager gate, unlike Proposal).
 */
class PrelimController extends Controller
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
            'sales_id' => $request->input('sales_id', ''),
            'include_closed' => $request->input('include_closed') ? true : false,
            'sort' => $request->input('sort', 'created_at'),
            'dir' => $request->input('dir', 'desc'),
            'page' => (int) $request->input('page', 1),
        ];

        $result = Prelim::search(array_merge($filters, $scope));

        $this->view('prelims/index', [
            'pageTitle' => 'Prelim',
            'prelims' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'dashboardCounts' => Prelim::dashboardCounts($scope['scope_sales_id'] ?? null),
            'statusMap' => MasterData::allAsMap('prelim_statuses'),
            'salesUsers' => User::activeSales(),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);

        $this->view('prelims/show', [
            'pageTitle' => $prelim['prelim_code'],
            'prelim' => $prelim,
            'timeline' => $this->buildTimeline((int) $prelim['id']),
            'documents' => PrelimDocument::forPrelim((int) $prelim['id']),
            'statusMap' => MasterData::allAsMap('prelim_statuses'),
            'canOperate' => $this->canOperate($prelim),
        ]);
    }

    /**
     * "Lead -> Buat Prelim": gated server-side by Data Awal completeness
     * (ID PLN, Tagihan Listrik, Model System, Lokasi) regardless of what the
     * disabled-button UI state shows — per the business spec, validation
     * must never rely on JavaScript alone.
     */
    public function createFromLead(Request $request, array $params): void
    {
        $lead = $this->findAuthorizedLead((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        if (!Acl::can('prelim.create') || $lead['deleted_at']) {
            $this->abort(403);

            return;
        }

        $missing = Lead::missingPrelimFields($lead);
        if (!empty($missing)) {
            Session::flash('error', "Prelim belum dapat dibuat. Data yang belum lengkap:\n- " . implode("\n- ", $missing));
            $this->redirect('/leads/' . $lead['id']);

            return;
        }

        $existing = Prelim::latestForLead((int) $lead['id']);
        if ($existing !== null && in_array($existing['status'], Prelim::OPEN_STATUSES, true)) {
            $this->redirect('/prelims/' . $existing['id']);

            return;
        }

        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');
        $salesId = (int) ($lead['sales_id'] ?? $actor['id']);

        $prelim = Prelim::createForLead([
            'lead_id' => (int) $lead['id'],
            'sales_id' => $salesId,
            'version' => 1,
            'status' => 'draft',
            'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'created_by' => $actor['id'],
            'updated_by' => $actor['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        PrelimStatusHistory::record($prelim['id'], null, 'draft', (int) $actor['id'], 'Prelim dibuat.');
        AuditLogger::log((int) $actor['id'], 'prelim_created', 'prelim', $prelim['id'], null, [
            'prelim_code' => $prelim['prelim_code'],
            'lead_code' => $lead['lead_code'],
        ]);

        Session::flash('success', "Prelim {$prelim['prelim_code']} berhasil dibuat.");
        $this->redirect('/prelims/' . $prelim['id']);
    }

    /** Edits the Prelim header (content, estimated value, validity) — allowed anytime, same permissive convention as ProposalController::update(). */
    public function update(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (!$this->canOperate($prelim)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), [
            'estimated_value' => 'numeric',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Estimasi nilai harus berupa angka.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        Prelim::update((int) $prelim['id'], [
            'content' => trim((string) $request->input('content', '')) ?: null,
            'estimated_value' => $request->input('estimated_value') !== '' && $request->input('estimated_value') !== null ? (float) $request->input('estimated_value') : null,
            'valid_until' => $request->input('valid_until') ?: null,
            'updated_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log((int) Auth::id(), 'prelim_updated', 'prelim', (int) $prelim['id']);

        Session::flash('success', 'Detail prelim tersimpan.');
        $this->redirect('/prelims/' . $prelim['id']);
    }

    public function destroy(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (!$this->canOperate($prelim) || $prelim['status'] !== 'draft') {
            $this->abort(403);

            return;
        }

        Prelim::softDelete((int) $prelim['id']);
        AuditLogger::log((int) Auth::id(), 'prelim_deleted', 'prelim', (int) $prelim['id'], ['prelim_code' => $prelim['prelim_code']], null);

        Session::flash('success', "Prelim {$prelim['prelim_code']} dihapus.");
        $this->redirect('/prelims');
    }

    // ------------------------------------------------------------------
    // Status flow: draft -> ready_to_send (optional) -> sent -> approved | client_revision -> sent (loop)
    // ------------------------------------------------------------------

    /** Optional "siap dikirim" flag Sales can set once content is finalized — purely informational, not required before send(). */
    public function markReady(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (!$this->canOperate($prelim) || $prelim['status'] !== 'draft') {
            $this->abort(403);

            return;
        }

        Prelim::update((int) $prelim['id'], ['status' => 'ready_to_send', 'updated_at' => date('Y-m-d H:i:s')]);
        PrelimStatusHistory::record((int) $prelim['id'], 'draft', 'ready_to_send', Auth::id());
        AuditLogger::log((int) Auth::id(), 'prelim_marked_ready', 'prelim', (int) $prelim['id']);

        Session::flash('success', 'Prelim ditandai siap dikirim.');
        $this->redirect('/prelims/' . $prelim['id']);
    }

    public function send(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (!$this->canOperate($prelim) || !Acl::can('prelim.send')) {
            $this->abort(403);

            return;
        }

        if (!in_array($prelim['status'], ['draft', 'ready_to_send', 'client_revision'], true)) {
            Session::flash('error', 'Prelim ini tidak dalam status yang bisa dikirim.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (trim((string) $prelim['content']) === '') {
            Session::flash('error', 'Isi penawaran (content) wajib diisi sebelum mengirim Prelim.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        $oldStatus = $prelim['status'];
        $now = date('Y-m-d H:i:s');

        Prelim::update((int) $prelim['id'], ['status' => 'sent', 'sent_at' => $now, 'updated_at' => $now]);
        PrelimStatusHistory::record((int) $prelim['id'], $oldStatus, 'sent', Auth::id());
        AuditLogger::log((int) Auth::id(), 'prelim_sent', 'prelim', (int) $prelim['id']);

        Session::flash('success', 'Prelim ditandai sudah dikirim ke Client.');
        $this->redirect('/prelims/' . $prelim['id']);
    }

    /**
     * Self-reported client response, logged manually by Sales after
     * real-world contact — same "no customer portal" convention as
     * ProposalController::updateStatus(). ACC -> approved (unlocks Sales
     * Engineer/Engineer per EngineerController::requestAssignment()'s gate);
     * Revisi -> client_revision (reopens for editing, version++, loop).
     */
    public function clientResponse(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (!$this->canOperate($prelim) || $prelim['status'] !== 'sent') {
            $this->abort(403);

            return;
        }

        $response = (string) $request->input('response');
        if (!in_array($response, ['approved', 'client_revision'], true)) {
            Session::flash('error', 'Pilih respon Client (ACC atau Revisi) terlebih dahulu.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        $now = date('Y-m-d H:i:s');

        if ($response === 'approved') {
            Prelim::update((int) $prelim['id'], [
                'status' => 'approved',
                'responded_at' => $now,
                'approved_at' => $now,
                'updated_at' => $now,
            ]);
            PrelimStatusHistory::record((int) $prelim['id'], 'sent', 'approved', Auth::id(), 'Client ACC Prelim.');
            AuditLogger::log((int) Auth::id(), 'prelim_approved', 'prelim', (int) $prelim['id']);

            Session::flash('success', 'Prelim disetujui (ACC) oleh Client. Lead dapat dilanjutkan ke Sales Engineer/Engineer untuk Proposal+BOQ.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        $validator = new Validator($request->all(), ['client_revision_reason' => 'required|max:1000']);
        if ($validator->fails()) {
            Session::flash('error', 'Alasan revisi dari Client wajib diisi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        $reason = trim((string) $request->input('client_revision_reason'));

        Prelim::update((int) $prelim['id'], [
            'status' => 'client_revision',
            'responded_at' => $now,
            'client_revision_reason' => $reason,
            'version' => (int) $prelim['version'] + 1,
            'updated_at' => $now,
        ]);
        PrelimStatusHistory::record((int) $prelim['id'], 'sent', 'client_revision', Auth::id(), 'Client minta revisi: ' . $reason);
        AuditLogger::log((int) Auth::id(), 'prelim_client_revision', 'prelim', (int) $prelim['id'], null, ['reason' => $reason]);

        Session::flash('success', 'Prelim dibuka kembali untuk revisi (v' . ((int) $prelim['version'] + 1) . '). Perbarui isi lalu kirim ulang.');
        $this->redirect('/prelims/' . $prelim['id']);
    }

    public function addNote(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (!$this->canOperate($prelim)) {
            $this->abort(403);

            return;
        }

        $validator = new Validator($request->all(), ['note' => 'required|max:2000']);
        if ($validator->fails()) {
            Session::flash('error', 'Catatan wajib diisi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        $note = trim((string) $request->input('note'));
        PrelimNote::add((int) $prelim['id'], Auth::id(), $note);
        AuditLogger::log((int) Auth::id(), 'prelim_note_added', 'prelim', (int) $prelim['id'], null, ['note' => $note]);

        Session::flash('success', 'Catatan ditambahkan.');
        $this->redirect('/prelims/' . $prelim['id']);
    }

    // ------------------------------------------------------------------
    // Documents (dokumen penawaran, mis. PDF hasil olahan Sales) — copies
    // EngineerController's upload guards exactly.
    // ------------------------------------------------------------------

    public function uploadDocument(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (!$this->canOperate($prelim)) {
            $this->abort(403);

            return;
        }

        $file = $_FILES['document'] ?? null;

        if ($file === null || !is_uploaded_file($file['tmp_name'] ?? '')) {
            Session::flash('error', 'Pilih file yang akan diunggah.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Gagal mengunggah file (kode error: ' . $file['error'] . ').');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if ($file['size'] > self::UPLOAD_MAX_BYTES) {
            Session::flash('error', 'Ukuran file maksimal 10MB.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        $originalName = (string) $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!isset(self::UPLOAD_ALLOWED[$ext])) {
            Session::flash('error', 'Jenis file tidak diizinkan. Format yang didukung: ' . implode(', ', array_keys(self::UPLOAD_ALLOWED)) . '.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        $detectedMime = @mime_content_type($file['tmp_name']) ?: 'application/octet-stream';

        $dir = $this->storageDir((int) $prelim['id']);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Session::flash('error', 'Gagal menyiapkan folder penyimpanan.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $dir . '/' . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Session::flash('error', 'Gagal menyimpan file.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        PrelimDocument::add([
            'prelim_id' => (int) $prelim['id'],
            'uploaded_by' => Auth::id(),
            'file_name' => mb_substr($originalName, 0, 255),
            'stored_filename' => $storedFilename,
            'file_path' => 'prelim/' . $prelim['id'] . '/' . $storedFilename,
            'file_size' => (int) $file['size'],
            'mime_type' => mb_substr($detectedMime, 0, 100),
        ]);

        AuditLogger::log((int) Auth::id(), 'prelim_document_uploaded', 'prelim', (int) $prelim['id'], null, ['file_name' => $originalName]);

        Session::flash('success', 'Dokumen prelim berhasil diunggah.');
        $this->redirect('/prelims/' . $prelim['id']);
    }

    public function downloadDocument(Request $request, array $params): void
    {
        $prelim = $this->findAuthorized((int) $params['id']);
        $document = PrelimDocument::find((int) $params['docId']);

        if ($document === null || (int) $document['prelim_id'] !== (int) $prelim['id']) {
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
        $prelim = $this->findAuthorized((int) $params['id']);
        $document = PrelimDocument::find((int) $params['docId']);

        if ($document === null || (int) $document['prelim_id'] !== (int) $prelim['id']) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/prelims/' . $prelim['id']);

            return;
        }

        if (!$this->canOperate($prelim)) {
            $this->abort(403);

            return;
        }

        $fullPath = dirname(__DIR__, 2) . '/storage/uploads/' . $document['file_path'];
        PrelimDocument::delete((int) $document['id']);

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }

        AuditLogger::log((int) Auth::id(), 'prelim_document_deleted', 'prelim', (int) $prelim['id'], ['file_name' => $document['file_name']], null);

        Session::flash('success', 'Dokumen dihapus.');
        $this->redirect('/prelims/' . $prelim['id']);
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    public function findAuthorized(int $id): array
    {
        $prelim = Prelim::withRelations($id);

        if ($prelim === null) {
            $this->abort(404);
        }

        if (Acl::hasRole('sales') && (int) $prelim['sales_id'] !== Auth::id()) {
            $this->abort(403, 'Anda hanya dapat mengakses prelim milik Anda sendiri.');
        }

        return $prelim;
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
        if (Acl::hasRole('sales')) {
            return ['scope_sales_id' => Auth::id()];
        }

        return [];
    }

    /** Who may edit header, mark ready, send, and log client response — ownership-gated, same convention as ProposalController::canOperate(). */
    private function canOperate(array $prelim): bool
    {
        return Acl::can('prelim.edit') && (!Acl::hasRole('sales') || (int) $prelim['sales_id'] === Auth::id());
    }

    private function storageDir(int $prelimId): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/prelim/' . $prelimId;
    }

    private function buildTimeline(int $prelimId): array
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
        }, PrelimStatusHistory::forPrelim($prelimId));

        $noteEvents = array_map(function ($row) {
            return [
                'type' => 'note',
                'created_at' => $row['created_at'],
                'actor' => $row['user_name'] ?? 'Sistem',
                'note' => $row['note'],
            ];
        }, PrelimNote::forPrelim($prelimId));

        $documentEvents = array_map(function ($row) {
            return [
                'type' => 'document',
                'created_at' => $row['created_at'],
                'actor' => $row['uploaded_by_name'] ?? 'Sistem',
                'file_name' => $row['file_name'],
            ];
        }, PrelimDocument::forPrelim($prelimId));

        $merged = array_merge($statusEvents, $noteEvents, $documentEvents);
        usort($merged, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $merged;
    }
}
