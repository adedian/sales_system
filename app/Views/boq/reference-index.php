<?php $pageTitle = 'Data Referensi BoQ'; ?>
<div class="page-header">
    <h2>Data Referensi BoQ</h2>
    <p class="text-muted">
        Katalog dari Excel &ldquo;Detailed Calculation BoQ Copper Residensial Industrial&rdquo; yang dipakai Kalkulator BoQ
        (modul, inverter, breaker, kabel, skun, tarif jasa, dan parameter hitung).
        <?= $canManage ? 'Perubahan di sini langsung dipakai kalkulator.' : 'Anda hanya dapat melihat; perubahan data dilakukan oleh pengelola.' ?>
    </p>
</div>

<div class="master-data-grid">
    <?php foreach ($categories as $slug => $cat): $c = $counts[$slug] ?? ['total' => 0, 'active' => 0]; ?>
    <a href="<?= url('/boq/referensi/' . $slug) ?>" class="master-data-card">
        <span class="master-data-card-icon"><i class="bi <?= e($cat['icon']) ?>"></i></span>
        <div>
            <h4><?= e($cat['label']) ?> <span class="badge-pill badge-pill-muted"><?= (int) $c['total'] ?></span></h4>
            <p><?= e($cat['description']) ?></p>
            <p class="small text-muted mb-0">Asal Excel: <?= e($cat['source']) ?><?= $c['active'] < $c['total'] ? ' &middot; ' . ($c['total'] - $c['active']) . ' nonaktif' : '' ?></p>
        </div>
        <i class="bi bi-chevron-right master-data-card-arrow"></i>
    </a>
    <?php endforeach; ?>
</div>
