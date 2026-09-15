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

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', [], 'layouts/guest');
    }

    public function login(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/login');

            return;
        }

        $validator = new Validator($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            Session::flash('error', 'Username dan password wajib diisi.');
            Session::flashOld($request->only(['username']));
            $this->redirect('/login');

            return;
        }

        $remember = (bool) $request->input('remember');
        $result = Auth::attempt((string) $request->input('username'), (string) $request->input('password'), $remember);

        if (!$result['success']) {
            Session::flash('error', $result['message']);
            Session::flashOld($request->only(['username']));
            $this->redirect('/login');

            return;
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        if (!Csrf::verifyRequest()) {
            $this->redirect('/dashboard');

            return;
        }

        Auth::logout();
        $this->redirect('/login');
    }

    public function showChangePassword(): void
    {
        $this->view('auth/change_password', [
            'forced' => !empty(Auth::user()['must_change_password']),
        ]);
    }

    public function changePassword(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/change-password');

            return;
        }

        $user = Auth::user();

        $validator = new Validator($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails() || !password_verify((string) $request->input('current_password'), $user['password'])) {
            Session::flash('error', $validator->fails() ? $validator->firstError('new_password') ?? 'Data tidak valid.' : 'Password lama tidak sesuai.');
            $this->redirect('/change-password');

            return;
        }

        User::update((int) $user['id'], [
            'password' => password_hash((string) $request->input('new_password'), PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);

        AuditLogger::log((int) $user['id'], 'change_password', 'auth', (int) $user['id']);

        Session::flash('success', 'Password berhasil diubah.');
        $this->redirect('/dashboard');
    }
}
