<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Revisi Sub-Fase 3 — audit trail for the Direktur price-validation gate
 * between Procurement finishing pricing and the result reaching Sales.
 * Mirrors ProcurementStatusHistory's shape/pattern.
 */
class ProcurementPriceValidation extends Model
{
    protected static string $table = 'procurement_price_validations';

    public static function submit(int $procurementRequestId, ?int $submittedBy, float $totalPrice): int
    {
        return static::insert([
            'procurement_request_id' => $procurementRequestId,
            'submitted_by' => $submittedBy,
            'submitted_at' => date('Y-m-d H:i:s'),
            'total_price' => $totalPrice,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** The one still-pending validation row for a request, if any. */
    public static function pendingForRequest(int $procurementRequestId): ?array
    {
        return Database::fetch(
            "SELECT v.*, submitter.name AS submitted_by_name
             FROM procurement_price_validations v
             LEFT JOIN users submitter ON submitter.id = v.submitted_by
             WHERE v.procurement_request_id = ? AND v.status = 'pending'
             ORDER BY v.id DESC LIMIT 1",
            [$procurementRequestId]
        );
    }

    public static function forRequest(int $procurementRequestId): array
    {
        return Database::fetchAll(
            "SELECT v.*, submitter.name AS submitted_by_name, validator.name AS validated_by_name
             FROM procurement_price_validations v
             LEFT JOIN users submitter ON submitter.id = v.submitted_by
             LEFT JOIN users validator ON validator.id = v.validated_by
             WHERE v.procurement_request_id = ?
             ORDER BY v.id DESC",
            [$procurementRequestId]
        );
    }

    /** All requests currently awaiting Direktur validation — feeds the /procurement/validation queue page. */
    public static function pendingQueue(): array
    {
        return Database::fetchAll(
            "SELECT v.*,
                    pr.request_code, pr.lead_id, pr.priority AS request_priority,
                    leads.lead_code, leads.customer_name, leads.company_name,
                    sales.name AS sales_name,
                    submitter.name AS submitted_by_name
             FROM procurement_price_validations v
             INNER JOIN procurement_requests pr ON pr.id = v.procurement_request_id
             INNER JOIN leads ON leads.id = pr.lead_id
             LEFT JOIN users sales ON sales.id = leads.sales_id
             LEFT JOIN users submitter ON submitter.id = v.submitted_by
             WHERE v.status = 'pending'
             ORDER BY v.submitted_at ASC"
        );
    }

    public static function approve(int $id, int $validatedBy, ?string $notes): void
    {
        static::update($id, [
            'status' => 'approved',
            'validated_by' => $validatedBy,
            'validated_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
        ]);
    }

    public static function requestRevision(int $id, int $validatedBy, string $notes): void
    {
        static::update($id, [
            'status' => 'revision_required',
            'validated_by' => $validatedBy,
            'validated_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
        ]);
    }
}
