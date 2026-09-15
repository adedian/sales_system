<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;

class ProfileController extends Controller
{
    public function show(): void
    {
        $this->view('profile/show', [
            'pageTitle' => 'Profil Saya',
            'user' => Auth::user(),
        ]);
    }

    public function update(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/profile');

            return;
        }

        $user = Auth::user();

        $validator = new Validator($request->all(), [
            'name' => 'required|max:150',
            'email' => 'required|email|max:150|unique:users,email,' . $user['id'],
            'phone' => 'max:30',
        ]);

        if ($validator->fails()) {
            Session::flash('error', $validator->firstError('email') ?? $validator->firstError('name') ?? 'Data tidak valid.');
            $this->redirect('/profile');

            return;
        }

        $newData = [
            'name' => trim((string) $request->input('name')),
            'email' => trim((string) $request->input('email')),
            'phone' => trim((string) $request->input('phone')) ?: null,
        ];

        User::update((int) $user['id'], $newData + ['updated_by' => $user['id']]);

        AuditLogger::log((int) $user['id'], 'profile_update', 'user', (int) $user['id'], [
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
        ], $newData);

        Session::flash('success', 'Profil berhasil diperbarui.');
        $this->redirect('/profile');
    }
}
