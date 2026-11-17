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
            'phone' => 'max:30',
        ]);

        $roleIds = $this->requestedRoleIds($request);
        if (!$validator->fails() && $roleIds === []) {
            Session::flash('error', 'Pilih minimal satu role yang valid.');
            Session::flashOld($request->only(['name', 'username', 'email', 'phone']) + ['role_ids' => (array) $request->input('role_ids', [])]);
            $this->redirect('/users/create');

            return;
        }

        // Only an existing Super Admin may mint another Super Admin account —
        // otherwise any role holding user.manage (e.g. Admin Sales) could
        // self-escalate by creating a new top-tier account.
        if (!$validator->fails() && $roleIds !== [] && $this->grantsSuperAdmin($roleIds) && !Acl::hasRole('super-admin')) {
            Session::flash('error', 'Anda tidak memiliki izin untuk menetapkan role Super Admin.');
            Session::flashOld($request->only(['name', 'username', 'email', 'phone']) + ['role_ids' => $roleIds]);
            $this->redirect('/users/create');

            return;
        }

        if ($validator->fails()) {
            $firstError = collect_first_error($validator->errors());
            Session::flash('error', $firstError);
            Session::flashOld($request->only(['name', 'username', 'email', 'phone']) + ['role_ids' => $roleIds]);
            $this->redirect('/users/create');

            return;
        }

        $actor = Auth::user();

        $newUserId = User::insert(array_merge([
            'name' => trim((string) $request->input('name')),
            'username' => trim((string) $request->input('username')),
            'email' => trim((string) $request->input('email')),
            'password' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            'role_id' => $roleIds[0],
            'phone' => trim((string) $request->input('phone')) ?: null,
            'is_active' => 1,
            'must_change_password' => 1,
            'created_by' => $actor['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], []));
        User::syncRoles($newUserId, $roleIds);

        AuditLogger::log((int) $actor['id'], 'user_created', 'user', $newUserId, null, [
            'username' => $request->input('username'),
            'role_ids' => $roleIds,
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
        // own roles or active flag here — prevents an accidental self-lockout.
        $newRoleIds = null;
        if (!$isSelf) {
            $newRoleIds = $this->requestedRoleIds($request);
            if ($newRoleIds === []) {
                Session::flash('error', 'Pilih minimal satu role yang valid.');
                $this->redirect("/users/{$id}/edit");

                return;
            }
            // Eskalasi: hanya Super Admin yang boleh memberi atau mencabut role Super Admin.
            $touchesSuperAdmin = $this->grantsSuperAdmin($newRoleIds) || in_array('super-admin', (array) $targetUser['role_slugs'], true);
            if ($touchesSuperAdmin && !Acl::hasRole('super-admin')) {
                Session::flash('error', 'Anda tidak memiliki izin untuk mengubah role Super Admin.');
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
        if ($newRoleIds !== null) {
            User::syncRoles($id, $newRoleIds);
            $newData['role_ids'] = $newRoleIds;
        }

        AuditLogger::log((int) $actor['id'], 'user_updated', 'user', $id, [
            'name' => $targetUser['name'],
            'email' => $targetUser['email'],
            'role_ids' => $targetUser['role_ids'],
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

    /** Urutan prioritas role utama (users.role_id); role kustom menyusul. */
    private const ROLE_PRIORITY = ['super-admin', 'admin-sales', 'manager', 'sales', 'sales-engineer', 'surveyor', 'engineer', 'procurement', 'direktur'];

    /**
     * Role yang dipilih pada form (role_ids[]), difilter ke role yang benar-benar ada
     * dan diurutkan menurut prioritas (elemen pertama menjadi role utama).
     *
     * @return int[]
     */
    private function requestedRoleIds(Request $request): array
    {
        $submitted = array_unique(array_map('intval', (array) $request->input('role_ids', [])));
        $valid = [];
        foreach (Role::all('id ASC') as $role) {
            if (in_array((int) $role['id'], $submitted, true)) {
                $valid[] = $role;
            }
        }
        usort($valid, static function (array $x, array $y): int {
            $px = array_search($x['slug'], self::ROLE_PRIORITY, true);
            $py = array_search($y['slug'], self::ROLE_PRIORITY, true);

            return ($px === false ? 99 : $px) <=> ($py === false ? 99 : $py);
        });

        return array_map(static fn (array $r): int => (int) $r['id'], $valid);
    }

    /** @param int[] $roleIds */
    private function grantsSuperAdmin(array $roleIds): bool
    {
        $super = Role::findBySlug('super-admin');

        return $super !== null && in_array((int) $super['id'], $roleIds, true);
    }
}
