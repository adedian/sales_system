<?php $pageTitle = 'Master Data'; ?>
<div class="page-header">
    <h2>Master Data</h2>
    <p class="text-muted">Kelola nilai referensi yang dipakai di seluruh modul sistem.</p>
</div>

<div class="master-data-grid">
    <?php foreach ($types as $slug => $type): ?>
    <a href="<?= url('/master-data/' . $slug) ?>" class="master-data-card">
        <span class="master-data-card-icon"><i class="bi <?= e($type['icon']) ?>"></i></span>
        <div>
            <h4><?= e($type['label']) ?></h4>
            <p><?= e($type['description']) ?></p>
        </div>
        <i class="bi bi-chevron-right master-data-card-arrow"></i>
    </a>
    <?php endforeach; ?>
</div>
