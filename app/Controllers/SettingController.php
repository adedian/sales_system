<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Models\Setting;

/**
 * Phase 14 — System Settings: company profile (used on the Proposal PDF
 * header), document numbering prefixes, the dashboard's SLA aging
 * threshold, and per-module notification toggles (checked inside
 * Notification::create()). All stored as plain key/value rows in
 * `settings` — see app/Models/Setting.php.
 */
class SettingController extends Controller
{
    private const KEYS = [
        'company_name', 'company_address', 'company_phone', 'company_email',
        'numbering_lead_prefix', 'numbering_engineer_prefix', 'numbering_procurement_prefix', 'numbering_prelim_prefix', 'numbering_proposal_prefix',
        'sla_lead_aging_days',
        'notify_lead', 'notify_prelim', 'notify_proposal', 'notify_engineer', 'notify_procurement',
    ];

    public function index(): void
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = Setting::get($key, '');
        }

        $this->view('settings/index', [
            'pageTitle' => 'Pengaturan Sistem',
            'values' => $values,
        ]);
    }

    public function update(Request $request): void
    {
        if (!Csrf::verifyRequest()) {
            Session::flash('error', 'Sesi telah kedaluwarsa, silakan coba lagi.');
            $this->redirect('/settings');

            return;
        }

        $actor = Auth::user();
        $newValues = [];

        foreach (self::KEYS as $key) {
            if (str_starts_with($key, 'notify_')) {
                // Checkboxes only submit when checked — an absent key means "off".
                $newValues[$key] = $request->input($key) ? '1' : '0';
                continue;
            }

            $value = trim((string) $request->input($key, ''));

            if ($key === 'sla_lead_aging_days') {
                $value = $value !== '' && is_numeric($value) ? (string) max(1, (int) $value) : '7';
            }

            $newValues[$key] = $value;
        }

        Setting::setMany($newValues, (int) $actor['id']);

        AuditLogger::log((int) $actor['id'], 'settings_updated', 'settings', null, null, null);

        Session::flash('success', 'Pengaturan berhasil disimpan.');
        $this->redirect('/settings');
    }
}
