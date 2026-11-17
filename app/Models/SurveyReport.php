<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Survey Report: satu laporan per Survey. Status: draft -> pending_review ->
 * (revision <-> pending_review) -> completed. Isi terstruktur (data) dan
 * pembuatan PDF menyusul di Tahap 4; sekarang ringkasan/kesimpulan.
 */
class SurveyReport extends Model
{
    protected static string $table = 'survey_reports';

    public static function forSurvey(int $surveyId): ?array
    {
        return Database::fetch(
            'SELECT sr.*, reviewer.name AS reviewed_by_name
             FROM survey_reports sr LEFT JOIN users reviewer ON reviewer.id = sr.reviewed_by
             WHERE sr.survey_id = ?',
            [$surveyId]
        );
    }

    /** Membuat draft bila belum ada (dipanggil saat Survey diselesaikan). */
    public static function ensureDraft(int $surveyId, ?int $userId): array
    {
        $existing = self::forSurvey($surveyId);
        if ($existing !== null) {
            return $existing;
        }

        $id = self::insert([
            'survey_id' => $surveyId,
            'status' => 'draft',
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Survey::recordHistory($surveyId, 'report', null, 'draft', $userId, 'Survey Report dibuat (draft).');

        return self::forSurvey($surveyId) ?? ['id' => $id];
    }
}
