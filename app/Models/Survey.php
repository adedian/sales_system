<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Jobs;
use App\Core\Model;

/**
 * Survey lapangan (wajib untuk lead baru, lihat leads.survey_required).
 *
 * Alur status: requested -> scheduled -> in_progress -> completed.
 * Cabang: cancelled (dibatalkan Sales/pengelola), rejected (ditolak Surveyor;
 * Sales mengajukan ulang). Survey dianggap "selesai untuk keperluan Prelim"
 * hanya bila Survey completed DAN Survey Report completed (lihat prelimBlocker).
 */
class Survey extends Model
{
    protected static string $table = 'surveys';
    protected static bool $softDeletes = true;

    /** Status yang masih menempel pada lead (belum dibatalkan/ditolak). */
    public const ACTIVE_STATUSES = ['requested', 'scheduled', 'in_progress', 'completed'];

    public const STATUS_META = [
        'requested' => ['name' => 'Diajukan', 'color' => 'muted'],
        'scheduled' => ['name' => 'Dijadwalkan', 'color' => 'primary'],
        'in_progress' => ['name' => 'Sedang Berlangsung', 'color' => 'indigo'],
        'completed' => ['name' => 'Survey Selesai', 'color' => 'emerald'],
        'cancelled' => ['name' => 'Dibatalkan', 'color' => 'muted'],
        'rejected' => ['name' => 'Ditolak Surveyor', 'color' => 'danger'],
    ];

    public const REPORT_META = [
        'draft' => ['name' => 'Draft', 'color' => 'muted'],
        'pending_review' => ['name' => 'Menunggu Pemeriksaan', 'color' => 'amber'],
        'revision' => ['name' => 'Revisi', 'color' => 'danger'],
        'completed' => ['name' => 'Selesai', 'color' => 'emerald'],
    ];

    public const PARTICIPANT_ROLES = [
        'sales' => 'Sales',
        'sales_engineer' => 'Sales Engineer',
        'engineer' => 'Engineer',
    ];

    private static function baseSelect(): string
    {
        return "SELECT surveys.*,
                       leads.lead_code, leads.customer_name, leads.company_name, leads.phone AS lead_phone,
                       leads.site_location, leads.address AS lead_address, leads.maps_url,
                       leads.sales_id AS lead_sales_id, leads.deleted_at AS lead_deleted_at,
                       sales.name AS sales_name,
                       surveyor.name AS surveyor_name,
                       requester.name AS requested_by_name,
                       sr.status AS report_status, sr.id AS report_id
                FROM surveys
                INNER JOIN leads ON leads.id = surveys.lead_id
                LEFT JOIN users sales ON sales.id = leads.sales_id
                LEFT JOIN users surveyor ON surveyor.id = surveys.surveyor_id
                LEFT JOIN users requester ON requester.id = surveys.requested_by
                LEFT JOIN survey_reports sr ON sr.survey_id = surveys.id";
    }

    public static function withRelations(int $id): ?array
    {
        return Database::fetch(self::baseSelect() . ' WHERE surveys.id = ? AND surveys.deleted_at IS NULL', [$id]);
    }

    /** Race-safe: insert lalu cap survey_code (pola sama dengan Prelim/Proposal). */
    public static function createForLead(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $id = self::insert(['survey_code' => 'TMP-' . bin2hex(random_bytes(8))] + $data);
            $code = sprintf('%s-%06d', Setting::get('numbering_survey_prefix', 'SV'), $id);
            self::update($id, ['survey_code' => $code]);

            return ['id' => $id, 'survey_code' => $code];
        });
    }

    /** Semua Survey sebuah lead, terbaru dulu. */
    public static function forLead(int $leadId): array
    {
        return Database::fetchAll(
            self::baseSelect() . ' WHERE surveys.lead_id = ? AND surveys.deleted_at IS NULL ORDER BY surveys.id DESC',
            [$leadId]
        );
    }

    /** Survey yang berlaku untuk lead (bukan dibatalkan/ditolak), terbaru. */
    public static function currentForLead(int $leadId): ?array
    {
        return Database::fetch(
            self::baseSelect() . " WHERE surveys.lead_id = ? AND surveys.deleted_at IS NULL
             AND surveys.status IN ('requested','scheduled','in_progress','completed')
             ORDER BY surveys.id DESC LIMIT 1",
            [$leadId]
        );
    }

    // ------------------------------------------------------------------
    // Gerbang: Survey + Survey Report wajib sebelum Prelim
    // ------------------------------------------------------------------

    /**
     * Pesan alasan Prelim belum boleh dibuat, atau null bila boleh.
     * Lead lama (survey_required = 0) memakai aturan lama (hanya Data Awal).
     * Override Super Admin (kolom survey_override_*) melewati gerbang ini.
     */
    public static function prelimBlocker(array $lead): ?string
    {
        if ((int) ($lead['survey_required'] ?? 0) !== 1 || !empty($lead['survey_override_at'])) {
            return null;
        }

        $survey = self::currentForLead((int) $lead['id']);
        if ($survey === null) {
            return 'Survey lapangan wajib dilakukan lebih dulu. Ajukan penugasan Survey ke Surveyor.';
        }
        if ($survey['status'] !== 'completed') {
            return 'Survey ' . $survey['survey_code'] . ' belum selesai (status: ' . self::STATUS_META[$survey['status']]['name'] . ').';
        }
        if (($survey['report_status'] ?? null) !== 'completed') {
            $label = self::REPORT_META[$survey['report_status'] ?? 'draft']['name'] ?? 'Belum dibuat';

            return 'Survey Report belum selesai (status: ' . $label . ').';
        }

        return null;
    }

    /**
     * Tahap Survey untuk Current Position lead: null bila tidak relevan.
     * Dipakai Lead::currentPosition() hanya untuk lead survey_required yang
     * belum punya Prelim aktif.
     */
    public static function openStageForLead(array $lead): ?array
    {
        if ((int) ($lead['survey_required'] ?? 0) !== 1 || !empty($lead['survey_override_at'])) {
            return null;
        }

        $survey = self::currentForLead((int) $lead['id']);
        if ($survey === null) {
            return ['survey' => null, 'pic' => null];
        }
        if ($survey['status'] === 'completed' && ($survey['report_status'] ?? null) === 'completed') {
            return null;
        }

        return ['survey' => $survey, 'pic' => $survey['surveyor_name'] ?? null];
    }

    // ------------------------------------------------------------------
    // Akses
    // ------------------------------------------------------------------

    public static function canAccess(array $user, array $survey): bool
    {
        if (Jobs::has('super_admin', 'admin_sales', 'manager')) {
            return true;
        }

        $uid = (int) $user['id'];
        if ((int) ($survey['surveyor_id'] ?? 0) === $uid || (int) ($survey['requested_by'] ?? 0) === $uid) {
            return true;
        }
        if ((int) ($survey['lead_sales_id'] ?? 0) === $uid || LeadSales::isAssigned((int) $survey['lead_id'], $uid)) {
            return true;
        }

        return self::isParticipant((int) $survey['id'], $uid);
    }

    public static function isParticipant(int $surveyId, int $userId): bool
    {
        return Database::fetch(
            'SELECT id FROM survey_participants WHERE survey_id = ? AND user_id = ? LIMIT 1',
            [$surveyId, $userId]
        ) !== null;
    }

    // ------------------------------------------------------------------
    // Partisipan, riwayat
    // ------------------------------------------------------------------

    public static function participants(int $surveyId): array
    {
        return Database::fetchAll(
            'SELECT sp.*, u.name AS user_name FROM survey_participants sp
             INNER JOIN users u ON u.id = sp.user_id
             WHERE sp.survey_id = ? ORDER BY sp.role, u.name',
            [$surveyId]
        );
    }

    /** @param array<int,string> $userRoles user_id => role */
    public static function replaceParticipants(int $surveyId, array $userRoles): void
    {
        Database::execute('DELETE FROM survey_participants WHERE survey_id = ?', [$surveyId]);
        foreach ($userRoles as $userId => $role) {
            Database::execute(
                'INSERT IGNORE INTO survey_participants (survey_id, user_id, role) VALUES (?, ?, ?)',
                [$surveyId, (int) $userId, $role]
            );
        }
    }

    public static function recordHistory(int $surveyId, string $entity, ?string $from, string $to, ?int $by, ?string $notes = null): void
    {
        Database::execute(
            'INSERT INTO survey_status_history (survey_id, entity, from_status, to_status, changed_by, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$surveyId, $entity, $from, $to, $by, $notes, date('Y-m-d H:i:s')]
        );
    }

    public static function history(int $surveyId): array
    {
        return Database::fetchAll(
            'SELECT h.*, u.name AS changed_by_name FROM survey_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.survey_id = ? ORDER BY h.created_at DESC, h.id DESC',
            [$surveyId]
        );
    }

    // ------------------------------------------------------------------
    // Daftar
    // ------------------------------------------------------------------

    /**
     * @param array{q?:string,status?:string,report_status?:string,scope_surveyor_id?:int,scope_user_id?:int,page?:int,per_page?:int} $f
     * @return array{rows:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public static function search(array $f): array
    {
        $where = ['surveys.deleted_at IS NULL', 'leads.deleted_at IS NULL'];
        $params = [];

        if (!empty($f['scope_surveyor_id'])) {
            $where[] = 'surveys.surveyor_id = ?';
            $params[] = (int) $f['scope_surveyor_id'];
        }
        if (!empty($f['scope_user_id'])) {
            // Sales pemilik lead, pengaju, atau pihak yang dilibatkan.
            $where[] = '(leads.sales_id = ? OR surveys.requested_by = ?
                         OR EXISTS (SELECT 1 FROM lead_sales ls WHERE ls.lead_id = leads.id AND ls.user_id = ?)
                         OR EXISTS (SELECT 1 FROM survey_participants sp WHERE sp.survey_id = surveys.id AND sp.user_id = ?))';
            array_push($params, (int) $f['scope_user_id'], (int) $f['scope_user_id'], (int) $f['scope_user_id'], (int) $f['scope_user_id']);
        }
        if (!empty($f['q'])) {
            $where[] = '(surveys.survey_code LIKE ? OR leads.lead_code LIKE ? OR leads.customer_name LIKE ? OR leads.company_name LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($f['status']) && isset(self::STATUS_META[$f['status']])) {
            $where[] = 'surveys.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['report_status']) && isset(self::REPORT_META[$f['report_status']])) {
            $where[] = 'sr.status = ?';
            $params[] = $f['report_status'];
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM surveys INNER JOIN leads ON leads.id = surveys.lead_id
             LEFT JOIN survey_reports sr ON sr.survey_id = surveys.id' . $whereSql,
            $params
        )['c'] ?? 0);

        $perPage = max(5, min(50, (int) ($f['per_page'] ?? 15)));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($totalPages, (int) ($f['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            self::baseSelect() . $whereSql . " ORDER BY FIELD(surveys.status,'requested','scheduled','in_progress','completed','rejected','cancelled'),
             surveys.scheduled_date IS NULL, surveys.scheduled_date ASC, surveys.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
    }

    /** Hitungan untuk dashboard Surveyor. */
    public static function countsForSurveyor(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT surveys.status, COUNT(*) AS c FROM surveys
             INNER JOIN leads ON leads.id = surveys.lead_id AND leads.deleted_at IS NULL
             WHERE surveys.deleted_at IS NULL AND surveys.surveyor_id = ? GROUP BY surveys.status",
            [$userId]
        );
        $out = array_fill_keys(array_keys(self::STATUS_META), 0);
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }

        $out['report_open'] = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM surveys
             INNER JOIN survey_reports sr ON sr.survey_id = surveys.id AND sr.status IN ('draft','revision')
             WHERE surveys.deleted_at IS NULL AND surveys.surveyor_id = ?",
            [$userId]
        )['c'] ?? 0);
        $out['overdue'] = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM surveys WHERE deleted_at IS NULL AND surveyor_id = ?
             AND status IN ('requested','scheduled') AND scheduled_date IS NOT NULL AND scheduled_date < CURDATE()",
            [$userId]
        )['c'] ?? 0);

        return $out;
    }

    /** Survey terbuka milik Surveyor (untuk daftar di dashboard). */
    public static function openForSurveyor(int $userId, int $limit = 8): array
    {
        $limit = max(1, $limit);

        return Database::fetchAll(
            self::baseSelect() . " WHERE surveys.deleted_at IS NULL AND surveys.surveyor_id = ?
             AND (surveys.status IN ('requested','scheduled','in_progress') OR sr.status IN ('draft','revision'))
             ORDER BY surveys.scheduled_date IS NULL, surveys.scheduled_date ASC, surveys.id DESC LIMIT {$limit}",
            [$userId]
        );
    }
}
