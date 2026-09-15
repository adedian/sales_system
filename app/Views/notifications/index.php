<?php
$pageTitle = 'Notifikasi';
$iconFor = function (string $type): string {
    if (str_starts_with($type, 'lead_')) return 'bi-person-lines-fill';
    if (str_starts_with($type, 'proposal_')) return 'bi-file-earmark-text';
    if (str_starts_with($type, 'engineer_')) return 'bi-tools';
    if (str_starts_with($type, 'procurement_')) return 'bi-truck';
    return 'bi-bell';
};
?>
<div class="page-header page-header-row">
    <div>
        <h2>Notifikasi</h2>
        <p class="text-muted"><?= (int) $total ?> notifikasi<?= $unreadCount > 0 ? ', ' . (int) $unreadCount . ' belum dibaca' : '' ?>.</p>
    </div>
    <?php if ($unreadCount > 0): ?>
    <button type="button" class="btn btn-light" id="notifPageMarkAllRead"><i class="bi bi-check2-all me-1"></i>Tandai Semua Dibaca</button>
    <?php endif; ?>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <div class="filter-form">
            <div class="filter-field">
                <a href="<?= url('/notifications') ?>" class="btn btn-sm <?= $filters['status'] === '' ? 'btn-primary' : 'btn-light' ?>">Semua</a>
                <a href="<?= url('/notifications') ?>?status=unread" class="btn btn-sm <?= $filters['status'] === 'unread' ? 'btn-primary' : 'btn-light' ?>">Belum Dibaca</a>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="empty-state"><i class="bi bi-bell-slash"></i><p><?= $filters['status'] === 'unread' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' ?></p></div>
        <?php else: ?>
        <div class="notif-page-list" id="notifPageList">
            <?php foreach ($notifications as $n): ?>
            <a href="<?= $n['link'] ? url($n['link']) : '#' ?>"
               class="notif-page-item <?= !$n['is_read'] ? 'notif-page-item-unread' : '' ?>"
               data-notif-id="<?= (int) $n['id'] ?>">
                <span class="notif-page-icon"><i class="bi <?= e($iconFor($n['type'])) ?>"></i></span>
                <span class="notif-page-body">
                    <span class="notif-page-title"><?= e($n['title']) ?><?php if (!$n['is_read']): ?><span class="notif-dot"></span><?php endif; ?></span>
                    <?php if ($n['message']): ?><span class="notif-page-message"><?= e($n['message']) ?></span><?php endif; ?>
                    <span class="notif-page-time text-muted"><?= e(format_datetime($n['created_at'])) ?></span>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-body pagination-bar">
        <?php $qs = fn ($p) => http_build_query(array_merge($filters, ['page' => $p])); ?>
        <div class="text-muted small">Halaman <?= (int) $page ?> dari <?= (int) $totalPages ?></div>
        <div class="pagination-controls">
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/notifications') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/notifications') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
    var list = document.getElementById('notifPageList');
    var markAllBtn = document.getElementById('notifPageMarkAllRead');

    if (list) {
        list.addEventListener('click', function (event) {
            var item = event.target.closest('[data-notif-id]');
            if (!item || item.classList.contains('notif-page-item-unread') === false) return;
            Api.post(base + '/api/notifications/' + item.getAttribute('data-notif-id') + '/read', {}).catch(function () {});
        });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            Api.post(base + '/api/notifications/read-all', {}).then(function () {
                document.querySelectorAll('.notif-page-item-unread').forEach(function (el) {
                    el.classList.remove('notif-page-item-unread');
                    var dot = el.querySelector('.notif-dot');
                    if (dot) dot.remove();
                });
                markAllBtn.remove();
                var badge = document.getElementById('notifBadge');
                if (badge) badge.classList.add('d-none');
            }).catch(function () {});
        });
    }
})();
</script>
