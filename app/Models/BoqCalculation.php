<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Hasil Kalkulator BoQ per tugas Engineer (satu baris per assignment). */
class BoqCalculation extends Model
{
    protected static string $table = 'boq_calculations';

    public static function forAssignment(int $assignmentId): ?array
    {
        $row = Database::fetch('SELECT * FROM boq_calculations WHERE engineer_assignment_id = ?', [$assignmentId]);
        if ($row === null) {
            return null;
        }
        $row['inputs'] = json_decode((string) $row['inputs'], true) ?: [];
        $row['result'] = json_decode((string) ($row['result'] ?? ''), true) ?: [];

        return $row;
    }

    /** Simpan (insert/update) input + hasil perhitungan terbaru untuk satu assignment. */
    public static function saveFor(int $assignmentId, int $leadId, array $inputs, array $result, int $userId): void
    {
        $s = $result['summary'] ?? [];
        $data = [
            'inputs' => json_encode($inputs, JSON_UNESCAPED_UNICODE),
            'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'system_wp' => $s['system_wp'] ?? null,
            'inverter_w' => $s['inverter_w'] ?? null,
            'dc_ac_ratio' => $s['dc_ac_ratio'] ?? null,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $existing = Database::fetch('SELECT id FROM boq_calculations WHERE engineer_assignment_id = ?', [$assignmentId]);
        if ($existing) {
            self::update((int) $existing['id'], $data);

            return;
        }
        self::insert($data + [
            'engineer_assignment_id' => $assignmentId,
            'lead_id' => $leadId,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
