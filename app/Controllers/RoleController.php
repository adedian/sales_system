<?php

namespace App\Controllers;

use App\Core\Acl;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Role;

class RoleController extends Controller
{
    public function index(): void
    {
        $this->view('roles/index', [
            'pageTitle' => 'Role',
            'breadcrumb' => ['Pengguna & Role' => url('/users'), 'Role'],
            'roles' => Role::withUserCount(),
        ]);
    }

    public function create(): void
    {
        $this->view('roles/form', [
            'pageTitle' => 'Tambah Role',
            'breadcrumb' => ['Pengguna & Role' => url('/users'), 'Role' => url('/roles'), 'Tambah'],
            'role' => null,
            'assignedSlugs' => [],
            'permissionGroups' => config('permissions'),
        ]);
    }

    public function store(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/roles/create');

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:100',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Nama role wajib diisi.');
            $this->redirect('/roles/create');

            return;
        }

        $actor = Auth::user();
        $slug = Role::uniqueSlug((string) $request->input('name'));

        $roleId = Role::insert([
            'name' => trim((string) $request->input('name')),
            'slug' => $slug,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'is_system' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $permissionIds = $this->resolvePermissionIds($this->allowedSlugs((array) $request->input('permissions', []), (int) $actor['role_id']));
        Role::syncPermissions($roleId, $permissionIds);

        AuditLogger::log((int) $actor['id'], 'role_created', 'role', $roleId, null, [
            'name' => $request->input('name'),
            'permissions' => count($permissionIds),
        ]);

        Session::flash('success', 'Role berhasil dibuat.');
        $this->redirect('/roles');
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $role = Role::find($id);

        if ($role === null) {
            $this->abort(404);

            return;
        }

        $this->view('roles/form', [
            'pageTitle' => 'Ubah Role',
            'breadcrumb' => ['Pengguna & Role' => url('/users'), 'Role' => url('/roles'), 'Ubah'],
            'role' => $role,
            'assignedSlugs' => Role::permissionSlugs($id),
            'permissionGroups' => config('permissions'),
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $role = Role::find($id);

        if ($role === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect("/roles/{$id}/edit");

            return;
        }

        $validator = new Validator($request->all(), [
            'name' => 'required|max:100',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Nama role wajib diisi.');
            $this->redirect("/roles/{$id}/edit");

            return;
        }

        // The Super Admin role is the top of the privilege chain — letting anyone
        // else edit it (even down to their own permission subset) would let them
        // cripple or repurpose it. Only a Super Admin may touch it.
        if ($role['slug'] === 'super-admin' && !Acl::hasRole('super-admin')) {
            Session::flash('error', 'Role Super Admin hanya dapat diubah oleh Super Admin.');
            $this->redirect('/roles');

            return;
        }

        $actor = Auth::user();
        $before = Role::permissionSlugs($id);

        Role::update($id, [
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $permissionIds = $this->resolvePermissionIds($this->allowedSlugs((array) $request->input('permissions', []), (int) $actor['role_id']));
        Role::syncPermissions($id, $permissionIds);
        $after = Role::permissionSlugs($id);

        AuditLogger::log((int) $actor['id'], 'role_updated', 'role', $id, [
            'name' => $role['name'],
            'permissions' => $before,
        ], [
            'name' => $request->input('name'),
            'permissions' => $after,
        ]);

        Session::flash('success', 'Role berhasil diperbarui.');
        $this->redirect('/roles');
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $role = Role::find($id);

        if ($role === null) {
            $this->abort(404);

            return;
        }

        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/roles');

            return;
        }

        if ((int) $role['is_system'] === 1) {
            Session::flash('error', 'Role bawaan sistem tidak dapat dihapus.');
            $this->redirect('/roles');

            return;
        }

        if (Role::userCount($id) > 0) {
            Session::flash('error', 'Role masih dipakai oleh pengguna aktif, tidak dapat dihapus.');
            $this->redirect('/roles');

            return;
        }

        Role::delete($id);

        AuditLogger::log((int) Auth::id(), 'role_deleted', 'role', $id, ['name' => $role['name']], null);

        Session::flash('success', 'Role berhasil dihapus.');
        $this->redirect('/roles');
    }

    /**
     * A role editor can only hand out permissions they themselves hold —
     * otherwise a role with user.manage (e.g. Admin Sales) could grant its
     * own role permissions it was never meant to have. Super Admin already
     * holds every permission, so this is a no-op for them.
     *
     * @param string[] $requestedSlugs
     * @return string[]
     */
    private function allowedSlugs(array $requestedSlugs, int $actorRoleId): array
    {
        if (Acl::hasRole('super-admin')) {
            return $requestedSlugs;
        }

        return array_values(array_intersect($requestedSlugs, Acl::permissions($actorRoleId)));
    }

    /**
     * @param string[] $slugs
     * @return int[]
     */
    private function resolvePermissionIds(array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $rows = Database::fetchAll("SELECT id FROM permissions WHERE slug IN ({$placeholders})", $slugs);

        return array_map(fn ($row) => (int) $row['id'], $rows);
    }
}
