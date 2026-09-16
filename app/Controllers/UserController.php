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
use App\Models\Role;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'role_id' => $request->input('role_id', ''),
            'status' => $request->input('status', ''),
            'page' => (int) $request->input('page', 1),
        ];

        $result = User::search($filters);

        $this->view('users/index', [
            'pageTitle' => 'Pengguna',
            'breadcrumb' => ['Pengguna & Role' => url('/users'), 'Pengguna'],
            'users' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['totalPages'],
            'roles' => Role::all('name ASC'),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('users/form', [
            'pageTitle' => 'Tambah Pengguna',
            'breadcrumb' => ['Pengguna & Role' => url('/users'), 'Pengguna' => url('/users'), 'Tambah'],
            'roles' => Role::all('name ASC'),
            'targetUser' => null,
        ]);
    }

    public function store(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/users/create');

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:150',
            'username' => 'required|max:100|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|min:8',
            'role_id' => 'required',
            'phone' => 'max:30',
        ]);

        $role = Role::find((int) $request->input('role_id'));
        if (!$validator->fails() && $role === null) {
            Session::flash('error', 'Role tidak valid.');
            Session::flashOld($request->only(['name', 'username', 'email', 'phone', 'role_id']));
            $this->redirect('/users/create');

            return;
        }

        // Only an existing Super Admin may mint another Super Admin account —
        // otherwise any role holding user.manage (e.g. Admin Sales) could
        // self-escalate by creating a new top-tier account.
        if (!$validator->fails() && $role !== null && $role['slug'] === 'super-admin' && !Acl::hasRole('super-admin')) {
            Session::flash('error', 'Anda tidak memiliki izin untuk menetapkan role Super Admin.');
            Session::flashOld($request->only(['name', 'username', 'email', 'phone', 'role_id']));
            $this->redirect('/users/create');

            return;
        }

        if ($validator->fails()) {
            $firstError = collect_first_error($validator->errors());
            Session::flash('error', $firstError);
            Session::flashOld($request->only(['name', 'username', 'email', 'phone', 'role_id']));
            $this->redirect('/users/create');

            return;
        }

        $actor = Auth::user();

        $newUserId = User::insert([
            'name' => trim((string) $request->input('name')),
            'username' => trim((string) $request->input('username')),
            'email' => trim((string) $request->input('email')),
            'password' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            'role_id' => (int) $request->input('role_id'),
            'phone' => trim((string) $request->input('phone')) ?: null,
            'is_active' => 1,
            'must_change_password' => 1,
            'created_by' => $actor['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log((int) $actor['id'], 'user_created', 'user', $newUserId, null, [
            'username' => $request->input('username'),
            'role_id' => (int) $request->input('role_id'),
        ]);

        Session::flash('success', 'Pengguna berhasil dibuat.');
        $this->redirect('/users');
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $targetUser = User::withRole($id);

        if ($targetUser === null) {
            $this->abort(404);

            return;
        }

        $this->view('users/form', [
            'pageTitle' => 'Ubah Pengguna',
            'breadcrumb' => ['Pengguna & Role' => url('/users'), 'Pengguna' => url('/users'), 'Ubah'],
            'roles' => Role::all('name ASC'),
            'targetUser' => $targetUser,
            'isSelf' => (int) $targetUser['id'] === Auth::id(),
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $targetUser = User::withRole($id);

        if ($targetUser === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect("/users/{$id}/edit");

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:150',
            'username' => 'required|max:100|unique:users,username,' . $id,
            'email' => 'required|email|max:150|unique:users,email,' . $id,
            'phone' => 'max:30',
        ]);

        $newPassword = trim((string) $request->input('password', ''));
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            Session::flash('error', 'Password baru minimal 8 karakter.');
            $this->redirect("/users/{$id}/edit");

            return;
        }

        if ($validator->fails()) {
            Session::flash('error', collect_first_error($validator->errors()));
            $this->redirect("/users/{$id}/edit");

            return;
        }

        $actor = Auth::user();
        $isSelf = (int) $targetUser['id'] === (int) $actor['id'];

        $newData = [
            'name' => trim((string) $request->input('name')),
            'username' => trim((string) $request->input('username')),
            'email' => trim((string) $request->input('email')),
            'phone' => trim((string) $request->input('phone')) ?: null,
            'updated_by' => $actor['id'],
        ];

        // Safety guard: an admin editing their own account can't change their
        // own role or active flag here — prevents an accidental self-lockout.
        if (!$isSelf) {
            $role = Role::find((int) $request->input('role_id'));
            if ($role !== null && ($role['slug'] !== 'super-admin' || Acl::hasRole('super-admin'))) {
                // Same escalation guard as store(): only Super Admin can hand out the Super Admin role.
                $newData['role_id'] = (int) $request->input('role_id');
            } elseif ($role !== null && $role['slug'] === 'super-admin') {
                Session::flash('error', 'Anda tidak memiliki izin untuk menetapkan role Super Admin.');
                $this->redirect("/users/{$id}/edit");

                return;
            }
            $newData['is_active'] = $request->input('is_active') ? 1 : 0;
        }

        if ($newPassword !== '') {
            $newData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $newData['must_change_password'] = 1;
        }

        User::update($id, $newData);

        AuditLogger::log((int) $actor['id'], 'user_updated', 'user', $id, [
            'name' => $targetUser['name'],
            'email' => $targetUser['email'],
            'role_id' => (int) $targetUser['role_id'],
            'is_active' => (int) $targetUser['is_active'],
        ], $newData);

        Session::flash('success', 'Pengguna berhasil diperbarui.' . ($isSelf ? ' (role & status akun sendiri tidak diubah)' : ''));
        $this->redirect('/users');
    }

    public function toggleStatus(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $targetUser = User::find($id);

        if ($targetUser === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/users');

            return;
        }

        $actor = Auth::user();

        if ($id === (int) $actor['id']) {
            Session::flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
            $this->redirect('/users');

            return;
        }

        $newStatus = (int) $targetUser['is_active'] === 1 ? 0 : 1;
        User::update($id, ['is_active' => $newStatus, 'updated_by' => $actor['id']]);

        AuditLogger::log(
            (int) $actor['id'],
            $newStatus ? 'user_activated' : 'user_deactivated',
            'user',
            $id,
            ['is_active' => (int) $targetUser['is_active']],
            ['is_active' => $newStatus]
        );

        Session::flash('success', 'Status pengguna berhasil diubah.');
        $this->redirect('/users');
    }
}
