<?php

namespace App\Services\Boq;

use App\Models\BoqCalculation;
use App\Models\ProcurementItem;

/**
 * Menyalin baris BoQ hasil Kalkulator ke procurement_items saat tugas Sales Engineer dikirim ke
 * Procurement. Hanya item + spesifikasi + jumlah + satuan; harga beli diisi Procurement seperti biasa
 * (tarif jasa dari Excel hanya dicatat di kolom catatan sebagai acuan).
 */
class BoqProcurementSeeder
{
    /** @return int jumlah item yang disalin (0 bila assignment belum punya hasil BoQ). */
    public static function copy(int $assignmentId, int $procurementRequestId): int
    {
        $calc = BoqCalculation::forAssignment($assignmentId);
        if ($calc === null || empty($calc['result']['lines'])) {
            return 0;
        }

        $n = 0;
        foreach ($calc['result']['lines'] as $line) {
            if ((float) $line['qty'] <= 0) {
                continue;
            }
            $spec = trim($line['spec'] . ($line['brand'] !== '' ? ' — ' . $line['brand'] : ''));
            ProcurementItem::insert([
                'procurement_request_id' => $procurementRequestId,
                'item_name' => mb_substr($line['item'], 0, 200),
                'specification' => $spec !== '' ? $spec : null,
                'quantity' => (float) $line['qty'],
                'unit' => $line['unit'] ?: null,
                'notes' => trim('[BoQ] ' . $line['section'] . ($line['note'] !== '' ? ' — ' . $line['note'] : '')),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $n++;
        }

        return $n;
    }
}
