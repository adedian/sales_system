<?php
$isEdit = $item !== null;
$pageTitle = ($isEdit ? 'Ubah ' : 'Tambah ') . $def['label'];
$attrs = $item['attrs'] ?? [];
?>
<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
    <p class="text-muted"><?= $isEdit ? 'Perbarui data ' . e($item['code']) . '. Kode tidak dapat diubah karena dipakai sebagai kunci pencarian kalkulator.' : 'Tambahkan baris baru ke Data Referensi.' ?></p>
</div>

<div class="card card-elevated" style="max-width:760px;">
    <div class="card-body">
        <form method="POST" action="<?= $isEdit ? url('/boq/referensi/' . $type . '/' . $item['id']) : url('/boq/referensi/' . $type) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="code">Kode</label>
                    <input type="text" class="form-control" id="code" name="code" value="<?= e($item['code'] ?? '') ?>" <?= $isEdit ? 'readonly' : 'required' ?> maxlength="150">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="label">Nama tampilan</label>
                    <input type="text" class="form-control" id="label" name="label" value="<?= e($item['label'] ?? '') ?>" maxlength="255" placeholder="Kosongkan untuk memakai kode">
                </div>

                <?php foreach ($def['fields'] as $key => $field): [$label, $kind] = $field; $v = $attrs[$key] ?? ''; ?>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f_<?= e($key) ?>"><?= e($label) ?></label>
                    <?php if ($kind === 'select'): ?>
                        <select class="form-select" id="f_<?= e($key) ?>" name="attrs[<?= e($key) ?>]">
                            <option value="">-</option>
                            <?php foreach ($field[2] as $optKey => $optLabel): ?>
                                <option value="<?= e((string) $optKey) ?>" <?= (string) $v === (string) $optKey && $v !== '' ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($kind === 'lines'): ?>
                        <textarea class="form-control" id="f_<?= e($key) ?>" name="attrs[<?= e($key) ?>]" rows="6"><?= e(is_array($v) ? implode("\n", $v) : $v) ?></textarea>
                    <?php elseif ($kind === 'number'): ?>
                        <input type="text" inputmode="decimal" class="form-control" id="f_<?= e($key) ?>" name="attrs[<?= e($key) ?>]" value="<?= e($v) ?>">
                    <?php else: ?>
                        <input type="text" class="form-control" id="f_<?= e($key) ?>" name="attrs[<?= e($key) ?>]" value="<?= e($v) ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Tambah' ?></button>
                <a href="<?= url('/boq/referensi/' . $type) ?>" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
