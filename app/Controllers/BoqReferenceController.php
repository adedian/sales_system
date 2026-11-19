<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\AuditLogger;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Models\BoqRefItem;

/**
 * Data Referensi BoQ: katalog dari Excel BoQ (modul, inverter, breaker, kabel, skun, tarif jasa, dst).
 * Melihat: boq.view. Mengubah/menambah/menghapus: boq.manage.
 */
class BoqReferenceController extends Controller
{
    /** @return array<string,array> */
    private function categories(): array
    {
        return require __DIR__ . '/../Config/boq_reference.php';
    }

    private function category(string $type): array
    {
        $all = $this->categories();
        if (!isset($all[$type])) {
            $this->abort(404);
        }

        return $all[$type];
    }

    public function index(): void
    {
        $this->view('boq/reference-index', [
            'pageTitle' => 'Data Referensi BoQ',
            'categories' => $this->categories(),
            'counts' => BoqRefItem::counts(),
            'canManage' => Acl::can('boq.manage'),
        ]);
    }

    public function list(Request $request, array $params): void
    {
        $type = (string) $params['type'];
        $def = $this->category($type);
        $filters = ['q' => trim((string) $request->input('q', '')), 'page' => (int) $request->input('page', 1)];
        $result = BoqRefItem::search($type, $filters);

        $this->view('boq/reference-list', [
            'pageTitle' => $def['label'],
            'type' => $type,
            'def' => $def,
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'canManage' => Acl::can('boq.manage'),
        ]);
    }

    public function create(Request $request, array $params): void
    {
        $type = (string) $params['type'];
        $this->view('boq/reference-form', [
            'pageTitle' => 'Tambah ' . $this->category($type)['label'],
            'type' => $type,
            'def' => $this->category($type),
            'item' => null,
        ]);
    }

    public function store(Request $request, array $params): void
    {
        $type = (string) $params['type'];
        $def = $this->category($type);
        $back = '/boq/referensi/' . $type;

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($back . '/create');

            return;
        }

        $code = trim((string) $request->input('code', ''));
        $label = trim((string) $request->input('label', ''));
        if ($code === '' || mb_strlen($code) > 150) {
            Session::flash('error', 'Kode wajib diisi (maksimal 150 karakter).');
            $this->redirect($back . '/create');

            return;
        }
        if (BoqRefItem::codeExists($type, $code)) {
            Session::flash('error', 'Kode "' . $code . '" sudah ada di kategori ini.');
            $this->redirect($back . '/create');

            return;
        }

        $attrs = BoqRefItem::derive($type, $this->buildAttrs($def, (array) $request->input('attrs', [])));
        $id = BoqRefItem::insert([
            'category' => $type, 'code' => $code, 'label' => $label !== '' ? $label : $code,
            'attrs' => json_encode($attrs, JSON_UNESCAPED_UNICODE), 'sort_order' => BoqRefItem::nextSortOrder($type), 'is_active' => 1,
        ]);
        AuditLogger::log((int) Auth::id(), 'boq_reference_created', 'boq_reference', $id, null, ['category' => $type, 'code' => $code]);

        Session::flash('success', 'Data "' . $code . '" ditambahkan.');
        $this->redirect($back);
    }

    public function edit(Request $request, array $params): void
    {
        $type = (string) $params['type'];
        $item = BoqRefItem::findIn($type, (int) $params['id']);
        if ($item === null) {
            $this->abort(404);

            return;
        }
        $this->view('boq/reference-form', [
            'pageTitle' => 'Ubah ' . $this->category($type)['label'],
            'type' => $type,
            'def' => $this->category($type),
            'item' => $item,
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $type = (string) $params['type'];
        $def = $this->category($type);
        $item = BoqRefItem::findIn($type, (int) $params['id']);
        if ($item === null) {
            $this->abort(404);

            return;
        }
        $back = '/boq/referensi/' . $type;

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($back . '/' . $item['id'] . '/edit');

            return;
        }

        $label = trim((string) $request->input('label', ''));
        $attrs = BoqRefItem::derive($type, $this->buildAttrs($def, (array) $request->input('attrs', []), $item['attrs']));
        BoqRefItem::update((int) $item['id'], [
            'label' => $label !== '' ? $label : $item['code'],
            'attrs' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
        ]);
        AuditLogger::log((int) Auth::id(), 'boq_reference_updated', 'boq_reference', (int) $item['id'], ['attrs' => $item['attrs']], ['attrs' => $attrs]);

        Session::flash('success', 'Data "' . $item['code'] . '" diperbarui.');
        $this->redirect($back);
    }

    public function toggle(Request $request, array $params): void
    {
        $type = (string) $params['type'];
        $this->category($type);
        $item = BoqRefItem::findIn($type, (int) $params['id']);
        if ($item === null) {
            $this->abort(404);

            return;
        }
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/boq/referensi/' . $type);

            return;
        }
        $new = (int) $item['is_active'] === 1 ? 0 : 1;
        BoqRefItem::update((int) $item['id'], ['is_active' => $new]);
        AuditLogger::log((int) Auth::id(), $new ? 'boq_reference_activated' : 'boq_reference_deactivated', 'boq_reference', (int) $item['id'], null, ['code' => $item['code']]);

        Session::flash('success', 'Status "' . $item['code'] . '" diubah. Data nonaktif tidak dipakai kalkulator.');
        $this->back();
    }

    public function destroy(Request $request, array $params): void
    {
        $type = (string) $params['type'];
        $this->category($type);
        $item = BoqRefItem::findIn($type, (int) $params['id']);
        if ($item === null) {
            $this->abort(404);

            return;
        }
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/boq/referensi/' . $type);

            return;
        }
        BoqRefItem::delete((int) $item['id']);
        AuditLogger::log((int) Auth::id(), 'boq_reference_deleted', 'boq_reference', (int) $item['id'], ['category' => $type, 'code' => $item['code']], null);

        Session::flash('success', 'Data "' . $item['code'] . '" dihapus.');
        $this->back();
    }

    /**
     * Susun attrs dari isian form menurut definisi kolom. Kolom di luar definisi yang sudah ada tetap dipertahankan.
     *
     * @param array<string,mixed> $posted
     * @param array<string,mixed> $existing
     */
    private function buildAttrs(array $def, array $posted, array $existing = []): array
    {
        $attrs = $existing;
        foreach ($def['fields'] as $key => $field) {
            $type = $field[1];
            $raw = $posted[$key] ?? null;
            if ($type === 'number') {
                $s = trim((string) $raw);
                if ($s === '') {
                    unset($attrs[$key]);
                    continue;
                }
                $s = str_contains($s, ',') && !str_contains($s, '.') ? str_replace(',', '.', $s) : str_replace(',', '', $s);
                if (is_numeric($s)) {
                    $f = (float) $s;
                    $attrs[$key] = floor($f) == $f && abs($f) < 1e12 ? (int) $f : $f;
                }
            } elseif ($type === 'lines') {
                $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $raw) ?: []), fn ($l) => $l !== ''));
                $lines ? $attrs[$key] = $lines : $attrs[$key] = [];
            } elseif ($type === 'select') {
                $options = $field[2] ?? [];
                foreach (array_keys($options) as $optKey) {
                    if ((string) $optKey === (string) $raw) {
                        $attrs[$key] = $optKey;
                        break;
                    }
                }
            } else {
                $s = trim((string) $raw);
                $s === '' ? $attrs = array_diff_key($attrs, [$key => 1]) : $attrs[$key] = $s;
            }
        }

        return $attrs;
    }
}
