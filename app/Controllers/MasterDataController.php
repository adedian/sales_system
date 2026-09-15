<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\MasterData;

class MasterDataController extends Controller
{
    public function landing(): void
    {
        $this->view('master-data/index', [
            'pageTitle' => 'Master Data',
            'types' => config('master_data'),
        ]);
    }

    public function index(Request $request, array $params): void
    {
        $type = $this->resolveType($params['type']);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', ''),
            'page' => (int) $request->input('page', 1),
        ];

        $result = MasterData::search($type['table'], $filters);

        $this->view('master-data/type', [
            'pageTitle' => $type['label'],
            'breadcrumb' => ['Master Data' => url('/master-data'), $type['label']],
            'typeSlug' => $params['type'],
            'type' => $type,
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
            'openModal' => (string) $request->input('open', ''),
        ]);
    }

    public function store(Request $request, array $params): void
    {
        $type = $this->resolveType($params['type']);
        $redirectBack = '/master-data/' . $params['type'];

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($redirectBack);

            return;
        }

        $code = $this->normalizeCode((string) $request->input('code'));

        $validator = new Validator(['code' => $code, 'name' => $request->input('name')], [
            'code' => 'required|max:50',
            'name' => 'required|max:150',
        ]);

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            Session::flashOld($request->only(['code', 'name', 'description', 'color', 'sort_order']));
            $this->redirect($redirectBack . '?open=create');

            return;
        }

        if (MasterData::codeExists($type['table'], $code)) {
            Session::flash('error', "Kode \"{$code}\" sudah dipakai di {$type['label']}.");
            Session::flashOld($request->only(['code', 'name', 'description', 'color', 'sort_order']));
            $this->redirect($redirectBack . '?open=create');

            return;
        }

        $actor = Auth::user();

        $id = MasterData::insert($type['table'], [
            'code' => $code,
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'color' => $type['has_color'] ? ($request->input('color') ?: null) : null,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => 1,
            'is_system' => 0,
            'created_by' => $actor['id'],
            'updated_by' => $actor['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log((int) $actor['id'], 'master_data_created', 'master_data:' . $params['type'], $id, null, [
            'code' => $code,
            'name' => $request->input('name'),
        ]);

        Session::flash('success', $type['singular'] . ' berhasil ditambahkan.');
        $this->redirect($redirectBack);
    }

    public function update(Request $request, array $params): void
    {
        $type = $this->resolveType($params['type']);
        $id = (int) $params['id'];
        $redirectBack = '/master-data/' . $params['type'];

        $row = MasterData::find($type['table'], $id);
        if ($row === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($redirectBack);

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:150',
        ]);

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            $this->redirect($redirectBack . '?open=edit-' . $id);

            return;
        }

        $isSystem = (int) $row['is_system'] === 1;
        $newData = [
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
            'updated_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($type['has_color']) {
            $newData['color'] = $request->input('color') ?: null;
        }

        // Locked (is_system) rows keep their code — other modules rely on it
        // matching a fixed ENUM value, so renaming it here would silently
        // break status/priority matching elsewhere.
        if (!$isSystem) {
            $code = $this->normalizeCode((string) $request->input('code'));

            if ($code === '') {
                Session::flash('error', 'Kode wajib diisi.');
                $this->redirect($redirectBack . '?open=edit-' . $id);

                return;
            }

            if (MasterData::codeExists($type['table'], $code, $id)) {
                Session::flash('error', "Kode \"{$code}\" sudah dipakai di {$type['label']}.");
                $this->redirect($redirectBack . '?open=edit-' . $id);

                return;
            }

            $newData['code'] = $code;
        }

        MasterData::update($type['table'], $id, $newData);

        AuditLogger::log((int) Auth::id(), 'master_data_updated', 'master_data:' . $params['type'], $id, [
            'code' => $row['code'],
            'name' => $row['name'],
        ], $newData);

        Session::flash('success', $type['singular'] . ' berhasil diperbarui.');
        $this->redirect($redirectBack);
    }

    public function toggleStatus(Request $request, array $params): void
    {
        $type = $this->resolveType($params['type']);
        $id = (int) $params['id'];
        $redirectBack = '/master-data/' . $params['type'];

        $row = MasterData::find($type['table'], $id);
        if ($row === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($redirectBack);

            return;
        }

        $newStatus = (int) $row['is_active'] === 1 ? 0 : 1;
        MasterData::update($type['table'], $id, ['is_active' => $newStatus, 'updated_by' => Auth::id(), 'updated_at' => date('Y-m-d H:i:s')]);

        AuditLogger::log(
            (int) Auth::id(),
            $newStatus ? 'master_data_activated' : 'master_data_deactivated',
            'master_data:' . $params['type'],
            $id,
            ['is_active' => (int) $row['is_active']],
            ['is_active' => $newStatus]
        );

        Session::flash('success', $type['singular'] . ' berhasil diubah statusnya.');
        $this->redirect($redirectBack);
    }

    public function destroy(Request $request, array $params): void
    {
        $type = $this->resolveType($params['type']);
        $id = (int) $params['id'];
        $redirectBack = '/master-data/' . $params['type'];

        $row = MasterData::find($type['table'], $id);
        if ($row === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect($redirectBack);

            return;
        }

        if ((int) $row['is_system'] === 1) {
            Session::flash('error', 'Baris bawaan sistem tidak dapat dihapus, hanya bisa diubah label/deskripsinya.');
            $this->redirect($redirectBack);

            return;
        }

        MasterData::delete($type['table'], $id);

        AuditLogger::log((int) Auth::id(), 'master_data_deleted', 'master_data:' . $params['type'], $id, [
            'code' => $row['code'],
            'name' => $row['name'],
        ], null);

        Session::flash('success', $type['singular'] . ' berhasil dihapus.');
        $this->redirect($redirectBack);
    }

    private function resolveType(string $slug): array
    {
        $registry = config('master_data');

        if (!isset($registry[$slug])) {
            $this->abort(404);
        }

        return $registry[$slug];
    }

    private function normalizeCode(string $value): string
    {
        $code = strtolower(trim($value));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?? '';

        return trim($code, '_');
    }
}
