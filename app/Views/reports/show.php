<?php
$pageTitle = $title;
$exportQs = $filters;
$exportQs['format'] = 'csv';
?>
<div class="page-header page-header-row">
    <div>
        <h2><?= e($title) ?></h2>
        <p class="text-muted"><?= count($rows) ?> baris data.</p>
    </div>
    <div class="row-actions">
        <?php if ($canExport): ?>
        <a href="<?= url($path) ?>?<?= http_build_query($exportQs) ?>" class="btn btn-light"><i class="bi bi-download me-1"></i>Export CSV</a>
        <?php endif; ?>
        <a href="<?= url('/reports') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<?php if (!empty($summary)): ?>
<div class="stat-grid">
    <?php foreach ($summary as $card): ?>
    <div class="stat-card stat-card-compact">
        <div class="stat-body">
            <span class="stat-label"><?= e($card['label']) ?></span>
            <span class="stat-value" style="font-size:18px"><?= e($card['value']) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url($path) ?>" class="filter-form">
            <?php foreach ($filterDefs as $key => $def): ?>
            <div class="filter-field<?= $def['type'] === 'text' ? ' filter-field-grow' : '' ?>">
                <label class="form-label" for="f_<?= e($key) ?>"><?= e($def['label']) ?></label>
                <?php if ($def['type'] === 'select'): ?>
                <select id="f_<?= e($key) ?>" name="<?= e($key) ?>" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($def['options'] as $code => $label): ?>
                        <option value="<?= e((string) $code) ?>" <?= (string) ($filters[$key] ?? '') === (string) $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="<?= e($def['type']) ?>" id="f_<?= e($key) ?>" name="<?= e($key) ?>" class="form-control" value="<?= e($filters[$key] ?? '') ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="filter-field filter-field-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= url($path) ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><i class="bi bi-clipboard-data"></i><p>Tidak ada data yang cocok dengan filter.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr><?php foreach ($columns as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach (array_keys($columns) as $key): ?>
                        <td><?= e((string) ($row[$key] ?? '-')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
