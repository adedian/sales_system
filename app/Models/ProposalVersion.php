<?php

namespace App\Models;

use App\Core\Database;

/**
 * Riwayat versi proposal: salinan isi (header + item) pada saat diajukan review
 * dan saat dikirim ke customer. Nomor versi aktif ada di proposals.version_no.
 */
class ProposalVersion
{
    public const EVENTS = ['submitted' => 'Diajukan review', 'sent' => 'Dikirim ke customer'];

    /** Kolom header proposal yang disalin ke snapshot. */
    private const HEADER_FIELDS = [
        'proposal_code', 'customer_pic', 'project_name', 'scope_description', 'subtotal',
        'discount_percent', 'discount_amount', 'tax_percent', 'tax_amount', 'total',
        'timeline_text', 'payment_terms', 'warranty', 'terms_conditions', 'valid_until', 'notes',
    ];

    private const ITEM_FIELDS = ['item_name', 'specification', 'quantity', 'unit', 'unit_cost', 'unit_price', 'subtotal'];

    /** Menyimpan salinan kondisi proposal saat ini sebagai bagian dari versi aktif. */
    public static function capture(int $proposalId, string $event, ?int $userId): void
    {
        $proposal = Proposal::find($proposalId);
        if ($proposal === null) {
            return;
        }

        $header = [];
        foreach (self::HEADER_FIELDS as $f) {
            $header[$f] = $proposal[$f] ?? null;
        }
        $items = [];
        foreach (ProposalItem::forProposal($proposalId) as $row) {
            $item = [];
            foreach (self::ITEM_FIELDS as $f) {
                $item[$f] = $row[$f] ?? null;
            }
            $items[] = $item;
        }

        Database::execute(
            'INSERT INTO proposal_versions (proposal_id, version_no, event, total, snapshot, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $proposalId,
                max(1, (int) ($proposal['version_no'] ?? 1)),
                $event,
                (float) ($proposal['total'] ?? 0),
                json_encode(['proposal' => $header, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $userId,
                date('Y-m-d H:i:s'),
            ]
        );
    }

    /** Menaikkan nomor versi aktif — dipanggil saat proposal dibuka kembali untuk revisi. */
    public static function bump(int $proposalId): void
    {
        Database::execute('UPDATE proposals SET version_no = version_no + 1 WHERE id = ?', [$proposalId]);
    }

    /** @return array<int,array<string,mixed>> terbaru dulu */
    public static function forProposal(int $proposalId): array
    {
        return Database::fetchAll(
            'SELECT v.id, v.proposal_id, v.version_no, v.event, v.total, v.created_at, u.name AS created_by_name
             FROM proposal_versions v LEFT JOIN users u ON u.id = v.created_by
             WHERE v.proposal_id = ? ORDER BY v.id DESC',
            [$proposalId]
        );
    }

    /** @return array<string,mixed>|null baris + snapshot ter-decode (kunci "data": proposal, items) */
    public static function find(int $proposalId, int $id): ?array
    {
        $row = Database::fetch(
            'SELECT v.*, u.name AS created_by_name FROM proposal_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.id = ? AND v.proposal_id = ?',
            [$id, $proposalId]
        );
        if ($row === null) {
            return null;
        }
        $row['data'] = json_decode((string) $row['snapshot'], true) ?: ['proposal' => [], 'items' => []];

        return $row;
    }
}
