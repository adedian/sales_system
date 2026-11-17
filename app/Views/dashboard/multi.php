<?php
$firstName = explode(' ', auth_user()['name'] ?? '')[0] ?? '';
?>
<div class="page-header">
    <h2>Selamat datang, <?= e($firstName) ?></h2>
    <p class="text-muted mb-0">Ringkasan pekerjaan dari semua role Anda, dikelompokkan per role.</p>
</div>

<nav class="dash-role-tabs" aria-label="Lompat ke role">
    <?php foreach ($groups as $g): ?>
        <a href="#<?= e($g['id']) ?>" class="dash-role-chip"><?= e($g['label']) ?></a>
    <?php endforeach; ?>
</nav>

<?php foreach ($groups as $g): ?>
<section class="dash-role-block" id="<?= e($g['id']) ?>">
    <h3 class="dash-role-title"><?= e($g['label']) ?></h3>
    <?= $g['html'] ?>
</section>
<?php endforeach; ?>
