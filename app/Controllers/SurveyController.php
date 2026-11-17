<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Jobs;
use App\Core\Request;
use App\Core\Session;
use App\Models\Lead;
use App\Models\LeadSales;
use App\Models\Notification;
use App\Models\Survey;
use App\Models\SurveyPhoto;
use App\Models\SurveyReport;
use App\Models\User;
use App\Services\SurveyReportPdf;
use App\Services\SurveyReportSchema;

/**
 * Survey lapangan wajib: pengajuan (Sales), penerimaan/penjadwalan/pelaksanaan
 * (Surveyor), Survey Report dan pemeriksaannya. Setiap perpindahan status
 * dicatat ke riwayat + audit log dan menghasilkan notifikasi ke pihak
 * berikutnya. Akses divalidasi di sini (job + kepemilikan), bukan hanya menu.
 */
class SurveyController extends Controller
{
    // ------------------------------------------------------------------
    // Daftar & detail
    // ------------------------------------------------------------------

    public function index(Request $request): void
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', ''),
            'report_status' => (string) $request->input('report_status', ''),
            'page' => (int) $request->input('page', 1),
        ];

        // Cakupan data: pengelola melihat semua; Surveyor hanya penugasannya;
        // lainnya hanya Survey yang berkaitan dengan dirinya.
        if (!Jobs::has('super_admin', 'admin_sales', 'manager')) {
            if (Jobs::has('surveyor') && !Jobs::has('sales', 'sales_engineer', 'engineer')) {
                $filters['scope_surveyor_id'] = (int) Auth::id();
            } else {
                $filters['scope_user_id'] = (int) Auth::id();
            }
        }

        $this->view('surveys/index', [
            'pageTitle' => 'Survey Lapangan',
            'result' => Survey::search($filters),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $survey = $this->findAuthorized((int) $params['id']);
        $user = Auth::user();
        $report = SurveyReport::forSurvey((int) $survey['id']);

        $this->view('surveys/show', [
            'pageTitle' => $survey['survey_code'],
            'survey' => $survey,
            'participants' => Survey::participants((int) $survey['id']),
            'history' => Survey::history((int) $survey['id']),
            'report' => $report,
            'photoCount' => SurveyPhoto::countForSurvey((int) $survey['id']),
            'versions' => $report === null ? [] : \App\Core\Database::fetchAll(
                'SELECT v.revision_no, v.created_at, u.name AS created_by_name FROM survey_report_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.report_id = ? ORDER BY v.id DESC',
                [$report['id']]
            ),
            'canWork' => $this->isAssignedSurveyor($survey),
            'canManage' => $this->canManageSurvey($survey),
            'canReview' => $this->canReviewReport($survey),
            'today' => date('Y-m-d'),
            'viewer' => $user,
        ]);
    }

    // ------------------------------------------------------------------
    // Pengajuan (Sales / pengelola)
    // ------------------------------------------------------------------

    public function request(Request $request, array $params): void
    {
        $lead = Lead::withRelations((int) $params['id']);
        if ($lead === null || $lead['deleted_at']) {
            $this->abort(404);

            return;
        }
        $this->ensureLeadAccess($lead);
        $this->checkCsrf('/leads/' . $lead['id']);

        $back = '/leads/' . $lead['id'] . '#survey-section';
        if (in_array($lead['status'], ['won', 'lost'], true)) {
            $this->fail($back, 'Lead yang sudah ditutup tidak dapat diajukan Survey.');

            return;
        }
        if (Survey::currentForLead((int) $lead['id']) !== null) {
            $this->fail($back, 'Lead ini sudah memiliki Survey yang berjalan atau selesai.');

            return;
        }

        $surveyorId = (int) $request->input('surveyor_id', 0);
        $surveyor = $surveyorId > 0 ? User::find($surveyorId) : null;
        if ($surveyor === null || (int) $surveyor['is_surveyor'] !== 1 || (int) $surveyor['is_active'] !== 1) {
            $this->fail($back, 'Pilih Surveyor yang aktif.');

            return;
        }

        $date = (string) $request->input('scheduled_date', '');
        if (!$this->isFutureOrToday($date)) {
            $this->fail($back, 'Tanggal rencana survey wajib diisi dan tidak boleh sebelum hari ini.');

            return;
        }

        $participants = $this->resolveParticipants((array) $request->input('participants', []), (int) $lead['id']);
        $actor = Auth::user();
        $now = date('Y-m-d H:i:s');

        $created = Survey::createForLead([
            'lead_id' => (int) $lead['id'],
            'surveyor_id' => $surveyorId,
            'requested_by' => (int) $actor['id'],
            'status' => 'requested',
            'scheduled_date' => $date,
            'request_notes' => trim((string) $request->input('request_notes', '')) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Survey::replaceParticipants($created['id'], $participants);
        Survey::recordHistory($created['id'], 'survey', null, 'requested', (int) $actor['id'], 'Survey diajukan.');
        AuditLogger::log((int) $actor['id'], 'survey_requested', 'survey', $created['id'], null, [
            'survey_code' => $created['survey_code'], 'lead_code' => $lead['lead_code'], 'surveyor_id' => $surveyorId, 'scheduled_date' => $date,
        ]);

        $survey = Survey::withRelations($created['id']);
        $this->notify([$surveyorId], 'survey_requested', 'Penugasan Survey baru', $survey, 'Anda ditugaskan melakukan Survey lapangan. Mohon terima atau tolak penugasan.');
        $this->notify(array_keys($participants), 'survey_participant', 'Anda dilibatkan dalam Survey', $survey, 'Anda dilibatkan untuk mendukung kebutuhan teknis Survey.', (int) $actor['id']);

        Session::flash('success', "Survey {$created['survey_code']} berhasil diajukan.");
        $this->redirect('/surveys/' . $created['id']);
    }

    // ------------------------------------------------------------------
    // Pelaksanaan (Surveyor)
    // ------------------------------------------------------------------

    public function accept(Request $request, array $params): void
    {
        $survey = $this->workable((int) $params['id'], ['requested']);
        $this->transition($survey, 'scheduled', 'Surveyor menerima penugasan.', function (array $s) {
            $this->notify($this->salesRecipients($s), 'survey_accepted', 'Survey diterima Surveyor', $s, 'Surveyor menerima penugasan dan jadwal survey sudah ditetapkan.');
        });
    }

    public function reject(Request $request, array $params): void
    {
        $survey = $this->workable((int) $params['id'], ['requested', 'scheduled']);
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            $this->fail('/surveys/' . $survey['id'], 'Alasan penolakan wajib diisi.');

            return;
        }
        $this->transition($survey, 'rejected', 'Ditolak Surveyor: ' . $reason, function (array $s) use ($reason) {
            $this->notify($this->salesRecipients($s), 'survey_rejected', 'Survey ditolak Surveyor', $s, 'Alasan: ' . $reason . '. Silakan ajukan ulang ke Surveyor lain.');
        });
    }

    public function start(Request $request, array $params): void
    {
        $survey = $this->workable((int) $params['id'], ['scheduled']);
        $this->transition($survey, 'in_progress', 'Survey lapangan dimulai.', function (array $s) {
            $this->notify($this->salesRecipients($s), 'survey_started', 'Survey lapangan dimulai', $s, 'Surveyor sedang melakukan survey di lokasi.');
        }, ['started_at' => date('Y-m-d H:i:s')]);
    }

    public function complete(Request $request, array $params): void
    {
        $survey = $this->workable((int) $params['id'], ['in_progress']);
        $this->transition($survey, 'completed', 'Survey lapangan selesai.', function (array $s) {
            SurveyReport::ensureDraft((int) $s['id'], (int) Auth::id());
            $this->notify($this->salesRecipients($s), 'survey_completed', 'Survey lapangan selesai', $s, 'Surveyor akan menyusun Survey Report. Prelim baru dapat dibuat setelah Survey Report selesai.');
        }, ['completed_at' => date('Y-m-d H:i:s')]);
    }

    public function reschedule(Request $request, array $params): void
    {
        $survey = $this->findAuthorized((int) $params['id']);
        $back = '/surveys/' . $survey['id'];
        if (!($this->isAssignedSurveyor($survey) || $this->canManageSurvey($survey))) {
            $this->abort(403);

            return;
        }
        $this->checkCsrf($back);
        if (!in_array($survey['status'], ['requested', 'scheduled'], true)) {
            $this->fail($back, 'Jadwal hanya dapat diubah sebelum Survey dimulai.');

            return;
        }

        $date = (string) $request->input('scheduled_date', '');
        $reason = trim((string) $request->input('reason', ''));
        if (!$this->isFutureOrToday($date) || $reason === '') {
            $this->fail($back, 'Tanggal baru dan alasan penjadwalan ulang wajib diisi.');

            return;
        }

        $old = $survey['scheduled_date'];
        \App\Core\Database::execute('UPDATE surveys SET scheduled_date = ?, updated_at = ? WHERE id = ?', [$date, date('Y-m-d H:i:s'), $survey['id']]);
        Survey::recordHistory((int) $survey['id'], 'survey', $survey['status'], $survey['status'], (int) Auth::id(), 'Jadwal ulang ' . format_date($old) . ' → ' . format_date($date) . '. Alasan: ' . $reason);
        AuditLogger::log((int) Auth::id(), 'survey_rescheduled', 'survey', (int) $survey['id'], ['scheduled_date' => $old], ['scheduled_date' => $date, 'reason' => $reason]);

        $updated = Survey::withRelations((int) $survey['id']);
        $recipients = array_unique(array_filter(array_merge($this->salesRecipients($updated), [(int) $updated['surveyor_id']]), fn ($id) => $id && $id !== (int) Auth::id()));
        $this->notify($recipients, 'survey_rescheduled', 'Jadwal Survey diubah', $updated, 'Jadwal baru ' . format_date($date) . '. Alasan: ' . $reason);

        Session::flash('success', 'Jadwal Survey diperbarui.');
        $this->redirect($back);
    }

    public function cancel(Request $request, array $params): void
    {
        $survey = $this->findAuthorized((int) $params['id']);
        $back = '/surveys/' . $survey['id'];
        if (!$this->canManageSurvey($survey)) {
            $this->abort(403);

            return;
        }
        $this->checkCsrf($back);
        if (!in_array($survey['status'], ['requested', 'scheduled'], true)) {
            $this->fail($back, 'Survey yang sudah berjalan tidak dapat dibatalkan.');

            return;
        }
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            $this->fail($back, 'Alasan pembatalan wajib diisi.');

            return;
        }

        $this->transition($survey, 'cancelled', 'Dibatalkan: ' . $reason, function (array $s) use ($reason) {
            $this->notify([(int) $s['surveyor_id']], 'survey_cancelled', 'Survey dibatalkan', $s, 'Alasan: ' . $reason);
        }, [], false);
    }

    // ------------------------------------------------------------------
    // Survey Report
    // ------------------------------------------------------------------

    /** Halaman form Survey Report terstruktur (Surveyor yang ditugaskan, saat draft/revisi). */
    public function editReport(Request $request, array $params): void
    {
        $survey = $this->findAuthorized((int) $params['id']);
        if (!$this->isAssignedSurveyor($survey)) {
            $this->abort(403, 'Hanya Surveyor yang ditugaskan yang dapat mengisi Survey Report.');
        }

        $report = SurveyReport::forSurvey((int) $survey['id']);
        if ($survey['status'] !== 'completed' || $report === null || !in_array($report['status'], ['draft', 'revision'], true)) {
            $this->fail('/surveys/' . $survey['id'], 'Survey Report hanya dapat diisi setelah Survey selesai dan selama berstatus draft atau revisi.');

            return;
        }

        $lead = Lead::withRelations((int) $survey['lead_id']);
        $saved = json_decode((string) ($report['data'] ?? ''), true);

        $this->view('surveys/report_form', [
            'pageTitle' => 'Survey Report ' . $survey['survey_code'],
            'survey' => $survey,
            'report' => $report,
            'sections' => SurveyReportSchema::sections(),
            'data' => SurveyReportSchema::merged($survey, $lead ?? [], is_array($saved) ? $saved : null),
            'photos' => SurveyPhoto::forSurvey((int) $survey['id']),
            'maxPhotos' => SurveyPhoto::MAX_PER_SURVEY,
            'uploadLimit' => ini_get('upload_max_filesize'),
            'postLimit' => ini_get('post_max_size'),
        ]);
    }

    /** Menyimpan isi laporan, ringkasan, dan perubahan foto (keterangan, kategori, hapus, unggah baru) dalam satu form. */
    public function saveReport(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $edit = '/surveys/' . $id . '/report/edit';

        // Total unggahan melebihi post_max_size: PHP mengosongkan $_POST (token CSRF ikut hilang).
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Session::flash('error', 'Ukuran total unggahan melebihi batas server (' . ini_get('post_max_size') . '). Unggah lebih sedikit foto per penyimpanan.');
            $this->redirect($edit);
        }

        $survey = $this->workable($id, ['completed'], false);
        $report = SurveyReport::forSurvey((int) $survey['id']);
        if ($report === null || !in_array($report['status'], ['draft', 'revision'], true)) {
            $this->fail('/surveys/' . $id . '#report-section', 'Survey Report tidak dapat diubah pada status ini.');

            return;
        }

        $messages = [];

        // 1) Hapus foto yang ditandai, 2) simpan keterangan/kategori.
        foreach ((array) $request->input('photo_delete', []) as $pid) {
            $photo = SurveyPhoto::find((int) $pid);
            if ($photo !== null && (int) $photo['survey_id'] === $id) {
                SurveyPhoto::remove($photo);
            }
        }
        foreach ((array) $request->input('photo', []) as $pid => $meta) {
            $photo = SurveyPhoto::find((int) $pid);
            if ($photo === null || (int) $photo['survey_id'] !== $id || !is_array($meta)) {
                continue;
            }
            $cat = isset(SurveyReportSchema::PHOTO_CATEGORIES[$meta['category'] ?? '']) ? $meta['category'] : $photo['category'];
            SurveyPhoto::updateMeta((int) $photo['id'], mb_substr(trim((string) ($meta['caption'] ?? '')), 0, 255) ?: null, $cat);
        }

        // 3) Unggah foto baru (banyak sekaligus).
        $category = isset(SurveyReportSchema::PHOTO_CATEGORIES[$request->input('new_photo_category', '')]) ? (string) $request->input('new_photo_category') : 'notes';
        $uploaded = 0;
        foreach ($this->normalizedFiles('photos') as $file) {
            if (SurveyPhoto::countForSurvey($id) >= SurveyPhoto::MAX_PER_SURVEY) {
                $messages[] = 'Batas ' . SurveyPhoto::MAX_PER_SURVEY . ' foto per Survey tercapai.';
                break;
            }
            $res = SurveyPhoto::addFromUpload($id, $file, $category, (int) Auth::id());
            $res['ok'] ? $uploaded++ : $messages[] = $res['message'];
        }

        // 4) Isi terstruktur + ringkasan.
        $data = SurveyReportSchema::sanitize((array) $request->input('d', []));
        \App\Core\Database::execute(
            'UPDATE survey_reports SET data = ?, summary = ?, updated_at = ? WHERE id = ?',
            [json_encode($data, JSON_UNESCAPED_UNICODE), mb_substr(trim((string) $request->input('summary', '')), 0, 4000) ?: null, date('Y-m-d H:i:s'), $report['id']]
        );
        AuditLogger::log((int) Auth::id(), 'survey_report_saved', 'survey', $id, null, ['photos_uploaded' => $uploaded]);

        Session::flash($messages === [] ? 'success' : 'error', 'Survey Report disimpan' . ($uploaded > 0 ? " ({$uploaded} foto diunggah)" : '') . '.' . ($messages !== [] ? "\n" . implode("\n", $messages) : ''));
        $this->redirect($edit);
    }

    public function submitReport(Request $request, array $params): void
    {
        $survey = $this->workable((int) $params['id'], ['completed'], false);
        $report = SurveyReport::forSurvey((int) $survey['id']);
        $back = '/surveys/' . $survey['id'] . '#report-section';
        if ($report === null || !in_array($report['status'], ['draft', 'revision'], true)) {
            $this->fail($back, 'Survey Report tidak dapat diajukan pada status ini.');

            return;
        }

        $saved = json_decode((string) ($report['data'] ?? ''), true);
        $lead = Lead::withRelations((int) $survey['lead_id']);
        $merged = SurveyReportSchema::merged($survey, $lead ?? [], is_array($saved) ? $saved : null);
        $problems = SurveyReportSchema::missingRequired($merged);
        if (trim((string) ($report['summary'] ?? '')) === '') {
            $problems[] = 'Kesimpulan & rekomendasi';
        }
        if (SurveyPhoto::countForSurvey((int) $survey['id']) < 1) {
            $problems[] = 'Minimal 1 foto dokumentasi';
        }
        if ($problems !== []) {
            $this->fail('/surveys/' . $survey['id'] . '/report/edit', "Survey Report belum lengkap. Lengkapi dan simpan terlebih dahulu:\n- " . implode("\n- ", $problems));

            return;
        }

        // Pengajuan ulang setelah revisi menaikkan nomor revisi; isi disalin ke riwayat versi.
        $revision = (int) $report['revision_no'] + ($report['status'] === 'revision' ? 1 : 0);
        \App\Core\Database::execute(
            'INSERT INTO survey_report_versions (report_id, revision_no, summary, data, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$report['id'], $revision, $report['summary'], $report['data'], (int) Auth::id(), date('Y-m-d H:i:s')]
        );

        $now = date('Y-m-d H:i:s');
        \App\Core\Database::execute(
            "UPDATE survey_reports SET status = 'pending_review', revision_no = ?, submitted_at = ?, updated_at = ? WHERE id = ?",
            [$revision, $now, $now, $report['id']]
        );
        Survey::recordHistory((int) $survey['id'], 'report', $report['status'], 'pending_review', (int) Auth::id(), 'Survey Report diajukan untuk diperiksa (Rev' . str_pad((string) $revision, 2, '0', STR_PAD_LEFT) . ').');
        AuditLogger::log((int) Auth::id(), 'survey_report_submitted', 'survey', (int) $survey['id'], ['status' => $report['status']], ['status' => 'pending_review']);

        $this->notify($this->reviewerRecipients($survey), 'survey_report_submitted', 'Survey Report menunggu pemeriksaan', $survey, 'Mohon periksa dan setujui, atau minta revisi.');
        Session::flash('success', 'Survey Report diajukan untuk diperiksa.');
        $this->redirect($back);
    }

    public function reviewReport(Request $request, array $params): void
    {
        $survey = $this->findAuthorized((int) $params['id']);
        $back = '/surveys/' . $survey['id'] . '#report-section';
        if (!$this->canReviewReport($survey)) {
            $this->abort(403);

            return;
        }
        $this->checkCsrf($back);

        $report = SurveyReport::forSurvey((int) $survey['id']);
        if ($report === null || $report['status'] !== 'pending_review') {
            $this->fail($back, 'Tidak ada Survey Report yang menunggu pemeriksaan.');

            return;
        }

        $decision = (string) $request->input('decision', '');
        $notes = trim((string) $request->input('notes', ''));
        if (!in_array($decision, ['approve', 'revision'], true)) {
            $this->fail($back, 'Keputusan tidak valid.');

            return;
        }
        if ($decision === 'revision' && $notes === '') {
            $this->fail($back, 'Catatan revisi wajib diisi.');

            return;
        }

        $to = $decision === 'approve' ? 'completed' : 'revision';
        $now = date('Y-m-d H:i:s');
        \App\Core\Database::execute(
            'UPDATE survey_reports SET status = ?, reviewed_by = ?, reviewed_at = ?, review_notes = ?, updated_at = ? WHERE id = ?',
            [$to, (int) Auth::id(), $now, $notes ?: null, $now, $report['id']]
        );
        Survey::recordHistory((int) $survey['id'], 'report', 'pending_review', $to, (int) Auth::id(), $notes ?: ($decision === 'approve' ? 'Survey Report disetujui.' : null));
        AuditLogger::log((int) Auth::id(), 'survey_report_' . $to, 'survey', (int) $survey['id'], ['status' => 'pending_review'], ['status' => $to, 'notes' => $notes]);

        if ($decision === 'approve') {
            $this->notify($this->salesRecipients($survey), 'survey_report_completed', 'Survey Report selesai', $survey, 'Survey dan Survey Report sudah selesai. Prelim sekarang dapat dibuat.');
            Session::flash('success', 'Survey Report disetujui. Lead dapat melanjutkan ke Prelim.');
        } else {
            $this->notify([(int) $survey['surveyor_id']], 'survey_report_revision', 'Survey Report perlu revisi', $survey, 'Catatan: ' . $notes);
            Session::flash('success', 'Permintaan revisi dikirim ke Surveyor.');
        }
        $this->redirect($back);
    }

    /** PDF Survey Report (pratinjau di browser; ?download=1 untuk mengunduh). */
    public function reportPdf(Request $request, array $params): void
    {
        $survey = $this->findAuthorized((int) $params['id']);
        $report = SurveyReport::forSurvey((int) $survey['id']);
        if ($report === null) {
            $this->abort(404, 'Survey Report belum dibuat.');
        }

        $lead = Lead::withRelations((int) $survey['lead_id']);
        $saved = json_decode((string) ($report['data'] ?? ''), true);
        $data = SurveyReportSchema::merged($survey, $lead ?? [], is_array($saved) ? $saved : null);
        $reporter = $report['created_by'] ? (User::find((int) $report['created_by'])['name'] ?? null) : null;

        $dompdf = SurveyReportPdf::render($survey, $report, $data, SurveyPhoto::forSurvey((int) $survey['id']), $reporter);

        $download = (bool) $request->input('download');
        $filename = 'SurveyReport_' . $survey['survey_code'] . '_Rev' . str_pad((string) ((int) $report['revision_no']), 2, '0', STR_PAD_LEFT) . '.pdf';
        AuditLogger::log((int) Auth::id(), 'survey_report_pdf', 'survey', (int) $survey['id'], null, ['download' => $download]);

        $dompdf->stream($filename, ['Attachment' => $download]);
        exit;
    }

    /** Menyajikan foto (versi web, atau ?v=orig untuk file asli) hanya kepada yang berhak mengakses Survey. */
    public function photo(Request $request, array $params): void
    {
        $survey = $this->findAuthorized((int) $params['id']);
        $photo = SurveyPhoto::find((int) $params['photoId']);
        if ($photo === null || (int) $photo['survey_id'] !== (int) $survey['id']) {
            $this->abort(404);
        }

        $original = $request->input('v') === 'orig';
        $relative = (string) ($original ? $photo['original_path'] : $photo['web_path']);
        $path = realpath(SurveyPhoto::absolute($relative));
        $base = realpath(SurveyPhoto::baseDir());
        if ($path === false || $base === false || !str_starts_with($path, $base) || !is_file($path)) {
            $this->abort(404);
        }

        header('Content-Type: ' . ($original ? $photo['mime_type'] : 'image/jpeg'));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $photo['original_name']) . '"');
        readfile($path);
        exit;
    }

    /** Menyusun ulang $_FILES['x'] (multiple) menjadi daftar file; entri kosong dilewati. @return array<int,array<string,mixed>> */
    private function normalizedFiles(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if ($f === null || !is_array($f['name'] ?? null)) {
            return [];
        }

        $out = [];
        foreach ($f['name'] as $i => $name) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || $name === '') {
                continue;
            }
            $out[] = ['name' => $name, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Override gerbang Survey (khusus Super Admin, alasan wajib)
    // ------------------------------------------------------------------

    public function override(Request $request, array $params): void
    {
        $lead = Lead::withRelations((int) $params['id']);
        if ($lead === null || $lead['deleted_at']) {
            $this->abort(404);

            return;
        }
        if (!Jobs::has('super_admin')) {
            $this->abort(403, 'Override hanya dapat dilakukan Super Admin.');

            return;
        }
        $this->checkCsrf('/leads/' . $lead['id']);

        $back = '/leads/' . $lead['id'] . '#survey-section';
        $reason = trim((string) $request->input('reason', ''));
        if (mb_strlen($reason) < 10) {
            $this->fail($back, 'Alasan override wajib diisi (minimal 10 karakter).');

            return;
        }

        $now = date('Y-m-d H:i:s');
        \App\Core\Database::execute(
            'UPDATE leads SET survey_override_by = ?, survey_override_reason = ?, survey_override_at = ?, updated_at = ? WHERE id = ?',
            [(int) Auth::id(), mb_substr($reason, 0, 500), $now, $now, $lead['id']]
        );
        AuditLogger::log((int) Auth::id(), 'survey_gate_override', 'lead', (int) $lead['id'], null, ['reason' => $reason]);

        Session::flash('success', 'Gerbang Survey di-override dan dicatat di audit log.');
        $this->redirect($back);
    }

    // ------------------------------------------------------------------
    // Helper internal
    // ------------------------------------------------------------------

    private function findAuthorized(int $id): array
    {
        $survey = Survey::withRelations($id);
        if ($survey === null) {
            $this->abort(404);
        }
        if (!Survey::canAccess(Auth::user(), $survey)) {
            $this->abort(403, 'Anda tidak memiliki akses ke Survey ini.');
        }

        return $survey;
    }

    /** Survey milik Surveyor yang login (atau Super Admin) pada salah satu status. */
    private function workable(int $id, array $allowedStatuses, bool $checkCsrf = true): array
    {
        $survey = $this->findAuthorized($id);
        $back = '/surveys/' . $survey['id'];
        if (!$this->isAssignedSurveyor($survey)) {
            $this->abort(403, 'Hanya Surveyor yang ditugaskan yang dapat mengerjakan Survey ini.');
        }
        if ($checkCsrf) {
            $this->checkCsrf($back);
        } elseif (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($back);
            exit;
        }
        if (!in_array($survey['status'], $allowedStatuses, true)) {
            $this->fail($back, 'Aksi ini tidak tersedia pada status Survey saat ini.');
            exit;
        }

        return $survey;
    }

    private function isAssignedSurveyor(array $survey): bool
    {
        return Jobs::has('super_admin') || (Jobs::has('surveyor') && (int) $survey['surveyor_id'] === (int) Auth::id());
    }

    private function canManageSurvey(array $survey): bool
    {
        if (Jobs::has('super_admin', 'admin_sales') || (Acl::can('survey.manage') && Jobs::has('manager'))) {
            return true;
        }
        $uid = (int) Auth::id();

        return Acl::can('survey.request') && ((int) $survey['lead_sales_id'] === $uid || LeadSales::isAssigned((int) $survey['lead_id'], $uid) || (int) $survey['requested_by'] === $uid);
    }

    /**
     * Pemeriksa Survey Report: pengelola (izin survey.review) atau Sales
     * Engineer/Engineer yang DILIBATKAN pada Survey ini. Penentuan lewat job,
     * karena Sales Engineer/Engineer sering berlogin dengan role "sales".
     */
    private function canReviewReport(array $survey): bool
    {
        if (Acl::can('survey.review') && Jobs::has('super_admin', 'admin_sales', 'manager')) {
            return true;
        }

        $isTechnical = Jobs::has('sales_engineer', 'engineer') || Acl::can('survey.review');

        return $isTechnical && Survey::isParticipant((int) $survey['id'], (int) Auth::id());
    }

    private function ensureLeadAccess(array $lead): void
    {
        if (Jobs::has('super_admin', 'admin_sales', 'manager')) {
            return;
        }
        $uid = (int) Auth::id();
        if ((int) $lead['sales_id'] !== $uid && !LeadSales::isAssigned((int) $lead['id'], $uid)) {
            $this->abort(403, 'Anda hanya dapat mengajukan Survey untuk lead yang menjadi tanggung jawab Anda.');
        }
    }

    /** @return array<int,string> user_id => role */
    private function resolveParticipants(array $ids, int $leadId): array
    {
        $out = [];
        foreach ($ids as $id) {
            $user = User::find((int) $id);
            if ($user === null || (int) $user['is_active'] !== 1) {
                continue;
            }
            if ((int) $user['is_sales_engineer'] === 1) {
                $out[(int) $user['id']] = 'sales_engineer';
            } elseif ((int) $user['is_engineer'] === 1) {
                $out[(int) $user['id']] = 'engineer';
            }
        }

        return $out;
    }

    private function isFutureOrToday(string $date): bool
    {
        $ts = strtotime($date);

        return $ts !== false && date('Y-m-d', $ts) === $date && $date >= date('Y-m-d');
    }

    /**
     * Pindah status + riwayat + audit + callback notifikasi, lalu kembali ke
     * halaman Survey.
     *
     * @param array<string,mixed> $extraColumns
     */
    private function transition(array $survey, string $to, string $note, callable $afterNotify, array $extraColumns = [], bool $csrfAlreadyChecked = true): void
    {
        $from = $survey['status'];
        $data = ['status' => $to, 'updated_at' => date('Y-m-d H:i:s')] + $extraColumns;
        Survey::update((int) $survey['id'], $data);
        Survey::recordHistory((int) $survey['id'], 'survey', $from, $to, (int) Auth::id(), $note);
        AuditLogger::log((int) Auth::id(), 'survey_' . $to, 'survey', (int) $survey['id'], ['status' => $from], ['status' => $to]);

        $afterNotify(Survey::withRelations((int) $survey['id']));

        Session::flash('success', 'Status Survey: ' . Survey::STATUS_META[$to]['name'] . '.');
        $this->redirect('/surveys/' . $survey['id']);
    }

    /** Sales pemilik lead + pengaju Survey (tanpa duplikat, tanpa pelaku). */
    private function salesRecipients(array $survey): array
    {
        $ids = array_filter([(int) ($survey['lead_sales_id'] ?? 0), (int) ($survey['requested_by'] ?? 0)]);
        foreach (LeadSales::forLead((int) $survey['lead_id']) as $row) {
            $ids[] = (int) ($row['user_id'] ?? $row['id'] ?? 0);
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0 && $id !== (int) Auth::id())));
    }

    /** Pemeriksa Survey Report: Sales Engineer/Engineer yang dilibatkan; bila tidak ada, pengelola. */
    private function reviewerRecipients(array $survey): array
    {
        $ids = [];
        foreach (Survey::participants((int) $survey['id']) as $p) {
            if (in_array($p['role'], ['sales_engineer', 'engineer'], true)) {
                $ids[] = (int) $p['user_id'];
            }
        }
        if ($ids === []) {
            foreach (\App\Core\Database::fetchAll(
                "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.slug IN ('manager','admin-sales') AND u.is_active = 1 AND u.deleted_at IS NULL"
            ) as $row) {
                $ids[] = (int) $row['id'];
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id !== (int) Auth::id())));
    }

    /**
     * Notifikasi dengan konteks lengkap: lead, pekerjaan, jadwal/deadline,
     * penanggung jawab, status, dan tautan ke halaman Survey.
     *
     * @param int[] $userIds
     */
    private function notify(array $userIds, string $type, string $title, array $survey, string $extra, ?int $exceptUserId = null): void
    {
        $context = sprintf(
            'Lead %s (%s) · %s · Jadwal: %s · Surveyor: %s · Status: %s. %s',
            $survey['lead_code'],
            $survey['customer_name'],
            $survey['survey_code'],
            format_date($survey['scheduled_date']),
            $survey['surveyor_name'] ?? '-',
            Survey::STATUS_META[$survey['status']]['name'] ?? $survey['status'],
            $extra
        );

        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0 && $uid !== (int) Auth::id() && $uid !== $exceptUserId) {
                Notification::create($uid, $type, $title, $context, '/surveys/' . $survey['id']);
            }
        }
    }

    private function checkCsrf(string $redirectTo): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($redirectTo);
            exit;
        }
    }

    private function fail(string $path, string $message): void
    {
        Session::flash('error', $message);
        $this->redirect($path);
    }
}
