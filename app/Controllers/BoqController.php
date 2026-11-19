<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\Request;
use App\Core\Session;
use App\Models\BoqCalculation;
use App\Services\Boq\BoqCalculator;
use App\Services\Boq\BoqReference;

/**
 * Kalkulator BoQ On-Grid pada tugas teknis Engineer / Sales Engineer.
 * Akses ke tugas mengikuti aturan EngineerController (hanya penerima tugas, pengelola, atau Sales pemilik lead).
 */
class BoqController extends Controller
{
    /** Status tugas saat BoQ masih boleh dihitung ulang (setelah dikirim ke Procurement = 'returned', terkunci). */
    private const EDITABLE_STATUSES = ['accepted', 'in_progress', 'waiting', 'completed'];

    public function show(Request $request, array $params): void
    {
        $assignment = (new EngineerController())->findAuthorized((int) $params['id']);
        $saved = BoqCalculation::forAssignment((int) $assignment['id']);
        $ref = BoqReference::fromDatabase();

        $inputs = $saved ? BoqCalculator::normalize($saved['inputs']) : BoqCalculator::defaults();
        // Merek awal: dari inverter pertama bila ada; kalau belum, pilihan merek pertama di katalog.
        if ($inputs['inverter_brand'] === '') {
            $inputs['inverter_brand'] = $ref->inverterBrands()[0] ?? '';
        }

        $this->view('boq/calculator', [
            'pageTitle' => 'Kalkulator BoQ — ' . $assignment['assignment_code'],
            'assignment' => $assignment,
            'saved' => $saved,
            'inputs' => $inputs,
            'result' => $saved['result'] ?? null,
            'editable' => $this->editable($assignment),
            'ref' => $ref,
            'notCovered' => BoqCalculator::notCovered(),
        ]);
    }

    public function calculate(Request $request, array $params): void
    {
        $assignment = (new EngineerController())->findAuthorized((int) $params['id']);
        $back = '/engineer/' . $assignment['id'] . '/boq';

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($back);

            return;
        }
        if (!$this->editable($assignment)) {
            Session::flash('error', 'BoQ tugas ini tidak bisa diubah (bukan penerima tugas, status tugas tidak aktif, atau sudah dikirim ke Procurement).');
            $this->redirect($back);

            return;
        }

        $raw = (array) $request->input('boq', []);
        $inputs = BoqCalculator::normalize($raw);
        $result = (new BoqCalculator(BoqReference::fromDatabase()))->calculate($raw);

        BoqCalculation::saveFor((int) $assignment['id'], (int) $assignment['lead_id'], $inputs, $result, (int) Auth::id());
        AuditLogger::log((int) Auth::id(), 'boq_calculated', 'boq_calculation', (int) $assignment['id'], null, [
            'kwp' => round(($result['summary']['system_kwp'] ?? 0), 2),
            'dc_ac' => $result['summary']['dc_ac_ratio'] !== null ? round($result['summary']['dc_ac_ratio'], 2) : null,
            'lines' => count($result['lines']),
        ]);

        $errors = count(array_filter($result['checks'], fn ($c) => $c['level'] === 'error'));
        Session::flash($errors ? 'error' : 'success', $errors
            ? "BoQ dihitung dan disimpan, tetapi ada {$errors} masalah yang perlu diperbaiki (lihat bagian Pemeriksaan)."
            : 'BoQ dihitung dan disimpan.');
        $this->redirect($back . '#hasil');
    }

    public function export(Request $request, array $params): void
    {
        $assignment = (new EngineerController())->findAuthorized((int) $params['id']);
        $saved = BoqCalculation::forAssignment((int) $assignment['id']);
        if ($saved === null || empty($saved['result']['lines'])) {
            Session::flash('error', 'Belum ada hasil BoQ untuk diunduh.');
            $this->redirect('/engineer/' . $assignment['id'] . '/boq');

            return;
        }

        $rows = array_map(fn ($l) => [
            'no' => $l['no'], 'section' => $l['section'], 'item' => $l['item'], 'spec' => $l['spec'], 'brand' => $l['brand'],
            'qty' => $l['qty'], 'unit' => $l['unit'], 'note' => $l['note'],
        ], $saved['result']['lines']);

        AuditLogger::log((int) Auth::id(), 'boq_downloaded', 'boq_calculation', (int) $assignment['id']);
        Csv::download('BoQ-' . $assignment['assignment_code'] . '.csv', [
            'no' => 'No', 'section' => 'Bagian', 'item' => 'Item', 'spec' => 'Spesifikasi', 'brand' => 'Merek', 'qty' => 'Jumlah', 'unit' => 'Satuan', 'note' => 'Catatan',
        ], $rows);
    }

    private function editable(array $assignment): bool
    {
        if (!Acl::can('boq.calculate')) {
            return false;
        }
        $isOwner = (int) $assignment['engineer_id'] === Auth::id();
        if (!Acl::can('engineer.manage') && !$isOwner) {
            return false;
        }
        if (in_array($assignment['lead_status'] ?? '', ['won', 'lost'], true)) {
            return false;
        }

        return in_array($assignment['status'], self::EDITABLE_STATUSES, true);
    }
}
