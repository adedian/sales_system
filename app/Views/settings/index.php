<?php $pageTitle = 'Pengaturan Sistem'; ?>
<div class="page-header">
    <h2>Pengaturan Sistem</h2>
    <p class="text-muted">Profil perusahaan, format penomoran dokumen, ambang SLA, dan notifikasi otomatis.</p>
</div>

<form method="POST" action="<?= url('/settings') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="card card-elevated mb-3">
        <div class="card-header"><h3>Profil Perusahaan</h3></div>
        <div class="card-body">
            <p class="text-muted small mb-3">Ditampilkan pada header dokumen PDF Proposal.</p>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="company_name">Nama Perusahaan</label>
                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?= e($values['company_name']) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="company_phone">Telepon</label>
                    <input type="text" class="form-control" id="company_phone" name="company_phone" value="<?= e($values['company_phone']) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="company_email">Email</label>
                    <input type="email" class="form-control" id="company_email" name="company_email" value="<?= e($values['company_email']) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="company_address">Alamat</label>
                    <input type="text" class="form-control" id="company_address" name="company_address" value="<?= e($values['company_address']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-elevated mb-3">
        <div class="card-header"><h3>Penomoran Dokumen</h3></div>
        <div class="card-body">
            <p class="text-muted small mb-3">Prefix kode otomatis — mis. prefix <code>LD</code> menghasilkan <code>LD-000001</code>. Nomor urut tetap otomatis, hanya prefix yang bisa diubah.</p>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="numbering_lead">Lead</label>
                    <input type="text" class="form-control" id="numbering_lead" name="numbering_lead_prefix" value="<?= e($values['numbering_lead_prefix']) ?>" maxlength="10">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="numbering_prelim">Prelim</label>
                    <input type="text" class="form-control" id="numbering_prelim" name="numbering_prelim_prefix" value="<?= e($values['numbering_prelim_prefix']) ?>" maxlength="10">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="numbering_engineer">Engineer Assignment</label>
                    <input type="text" class="form-control" id="numbering_engineer" name="numbering_engineer_prefix" value="<?= e($values['numbering_engineer_prefix']) ?>" maxlength="10">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="numbering_procurement">Procurement</label>
                    <input type="text" class="form-control" id="numbering_procurement" name="numbering_procurement_prefix" value="<?= e($values['numbering_procurement_prefix']) ?>" maxlength="10">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="numbering_proposal">Proposal</label>
                    <input type="text" class="form-control" id="numbering_proposal" name="numbering_proposal_prefix" value="<?= e($values['numbering_proposal_prefix']) ?>" maxlength="10">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-elevated mb-3">
        <div class="card-header"><h3>SLA</h3></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="sla_days">Ambang Usia Lead Menumpuk (hari)</label>
                    <input type="number" min="1" class="form-control" id="sla_days" name="sla_lead_aging_days" value="<?= e($values['sla_lead_aging_days']) ?>">
                    <p class="text-muted small mt-1 mb-0">Dipakai tabel "Aging &amp; Bottleneck" pada Dashboard untuk menandai lead yang lewat SLA.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-elevated mb-3">
        <div class="card-header"><h3>Notifikasi Otomatis</h3></div>
        <div class="card-body">
            <p class="text-muted small mb-3">Nonaktifkan kategori notifikasi tertentu jika tidak diperlukan — tidak memengaruhi notifikasi yang sudah terkirim.</p>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="notify_lead" name="notify_lead" value="1" <?= $values['notify_lead'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_lead">Lead (assignment, Won, Lost)</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="notify_prelim" name="notify_prelim" value="1" <?= $values['notify_prelim'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_prelim">Prelim (terkirim, ACC/revisi client)</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="notify_proposal" name="notify_proposal" value="1" <?= $values['notify_proposal'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_proposal">Proposal (review, approve, revisi)</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="notify_engineer" name="notify_engineer" value="1" <?= $values['notify_engineer'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_engineer">Engineer (assignment baru, hasil analisa)</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="notify_procurement" name="notify_procurement" value="1" <?= $values['notify_procurement'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_procurement">Procurement (request baru, revisi, harga selesai)</label>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </div>
</form>
