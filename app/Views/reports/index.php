<?php
$pageTitle = 'Laporan';
$reports = [
    ['url' => '/reports/leads', 'icon' => 'bi-person-lines-fill', 'color' => 'primary', 'title' => 'Laporan Lead', 'desc' => 'Seluruh lead beserta status, sumber, dan estimasi nilai.'],
    ['url' => '/reports/lead-monitoring', 'icon' => 'bi-diagram-3-fill', 'color' => 'indigo', 'title' => 'Lead Monitoring', 'desc' => 'Posisi dan PIC saat ini per lead, lintas Antrian.'],
    ['url' => '/reports/queue', 'icon' => 'bi-list-ol', 'color' => 'indigo', 'title' => 'Laporan Antrian', 'desc' => 'Antrian sales, status, dan keterlambatan.'],
    ['url' => '/reports/engineer', 'icon' => 'bi-tools', 'color' => 'amber', 'title' => 'Laporan Engineer', 'desc' => 'Assignment analisa teknis per engineer.'],
    ['url' => '/reports/procurement', 'icon' => 'bi-truck', 'color' => 'indigo', 'title' => 'Laporan Procurement', 'desc' => 'Request procurement dan nilai pembelian.'],
    ['url' => '/reports/proposals', 'icon' => 'bi-file-earmark-text', 'color' => 'primary', 'title' => 'Laporan Proposal', 'desc' => 'Proposal, nilai penawaran, dan win rate.'],
    ['url' => '/reports/follow-ups', 'icon' => 'bi-telephone-outbound', 'color' => 'emerald', 'title' => 'Laporan Follow Up', 'desc' => 'Riwayat kontak dan respon customer.'],
    ['url' => '/reports/deals', 'icon' => 'bi-trophy-fill', 'color' => 'emerald', 'title' => 'Laporan Deal', 'desc' => 'Deal Won/Lost, nilai, dan alasan kalah.'],
    ['url' => '/reports/performance', 'icon' => 'bi-graph-up-arrow', 'color' => 'danger', 'title' => 'Laporan Performance', 'desc' => 'Scorecard performa per sales.'],
];
?>
<div class="page-header">
    <h2>Laporan</h2>
    <p class="text-muted">Pilih jenis laporan — semua data dihitung langsung dari database, dengan filter dan export CSV.</p>
</div>

<div class="report-menu-grid">
    <?php foreach ($reports as $r): ?>
    <a href="<?= url($r['url']) ?>" class="report-menu-card">
        <div class="stat-icon stat-icon-<?= e($r['color']) ?>"><i class="bi <?= e($r['icon']) ?>"></i></div>
        <div class="report-menu-body">
            <h3><?= e($r['title']) ?></h3>
            <p><?= e($r['desc']) ?></p>
        </div>
        <i class="bi bi-chevron-right report-menu-arrow"></i>
    </a>
    <?php endforeach; ?>
</div>
