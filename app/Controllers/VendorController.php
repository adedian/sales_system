<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Vendor;

class VendorController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', ''),
            'page' => (int) $request->input('page', 1),
        ];

        $result = Vendor::search($filters);

        $this->view('vendors/index', [
            'pageTitle' => 'Vendor',
            'vendors' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('vendors/form', [
            'pageTitle' => 'Tambah Vendor',
            'breadcrumb' => ['Vendor' => url('/vendors'), 'Tambah'],
            'vendor' => null,
        ]);
    }

    public function store(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/vendors/create');

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:150',
            'contact_person' => 'max:150',
            'phone' => 'max:30',
            'email' => 'email|max:150',
        ]);

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            Session::flashOld($request->only(['name', 'contact_person', 'phone', 'email', 'address', 'category', 'notes']));
            $this->redirect('/vendors/create');

            return;
        }

        $actor = Auth::user();

        $vendor = Vendor::createWithCode([
            'name' => trim((string) $request->input('name')),
            'contact_person' => trim((string) $request->input('contact_person', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'address' => trim((string) $request->input('address', '')) ?: null,
            'category' => trim((string) $request->input('category', '')) ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'is_active' => 1,
            'created_by' => $actor['id'],
            'updated_by' => $actor['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log((int) $actor['id'], 'vendor_created', 'vendor', $vendor['id'], null, ['vendor_code' => $vendor['vendor_code'], 'name' => $request->input('name')]);

        Session::flash('success', "Vendor {$vendor['vendor_code']} berhasil dibuat.");
        $this->redirect('/vendors');
    }

    public function edit(Request $request, array $params): void
    {
        $vendor = Vendor::find((int) $params['id']);
        if ($vendor === null) {
            $this->abort(404);

            return;
        }

        $this->view('vendors/form', [
            'pageTitle' => 'Ubah Vendor',
            'breadcrumb' => ['Vendor' => url('/vendors'), 'Ubah'],
            'vendor' => $vendor,
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $vendor = Vendor::find((int) $params['id']);
        if ($vendor === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/vendors/' . $vendor['id'] . '/edit');

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:150',
            'contact_person' => 'max:150',
            'phone' => 'max:30',
            'email' => 'email|max:150',
        ]);

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            $this->redirect('/vendors/' . $vendor['id'] . '/edit');

            return;
        }

        $actor = Auth::user();

        $newData = [
            'name' => trim((string) $request->input('name')),
            'contact_person' => trim((string) $request->input('contact_person', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'address' => trim((string) $request->input('address', '')) ?: null,
            'category' => trim((string) $request->input('category', '')) ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'updated_by' => $actor['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Vendor::update((int) $vendor['id'], $newData);

        AuditLogger::log((int) $actor['id'], 'vendor_updated', 'vendor', (int) $vendor['id'], ['name' => $vendor['name']], ['name' => $newData['name']]);

        Session::flash('success', 'Vendor berhasil diperbarui.');
        $this->redirect('/vendors');
    }

    public function toggleStatus(Request $request, array $params): void
    {
        $vendor = Vendor::find((int) $params['id']);
        if ($vendor === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/vendors');

            return;
        }

        $actor = Auth::user();
        $newStatus = (int) $vendor['is_active'] === 1 ? 0 : 1;
        Vendor::update((int) $vendor['id'], ['is_active' => $newStatus, 'updated_by' => $actor['id']]);

        AuditLogger::log((int) $actor['id'], $newStatus ? 'vendor_activated' : 'vendor_deactivated', 'vendor', (int) $vendor['id'], ['is_active' => (int) $vendor['is_active']], ['is_active' => $newStatus]);

        Session::flash('success', 'Status vendor berhasil diubah.');
        $this->redirect('/vendors');
    }

    public function destroy(Request $request, array $params): void
    {
        $vendor = Vendor::find((int) $params['id']);
        if ($vendor === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/vendors');

            return;
        }

        Vendor::softDelete((int) $vendor['id']);

        AuditLogger::log((int) Auth::id(), 'vendor_deleted', 'vendor', (int) $vendor['id'], ['vendor_code' => $vendor['vendor_code']], null);

        Session::flash('success', "Vendor {$vendor['vendor_code']} dihapus.");
        $this->redirect('/vendors');
    }
}
