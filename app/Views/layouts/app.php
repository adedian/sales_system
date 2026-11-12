<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> &middot; <?= e(config('app.name')) ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="poll-interval" content="<?= (int) config('app.dashboard.poll_interval_ms', 30000) ?>">
    <script>window.APP_BASE_URL = <?= json_encode(url(''), JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body>
    <div class="app-shell">
        <div class="app-main">
            <?php include __DIR__ . '/../partials/topbar.php'; ?>

            <main class="app-content">
                <?php if (!empty($breadcrumb ?? [])): ?>
                <nav class="page-crumbs" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= url('/dashboard') ?>"><i class="bi bi-house-door"></i></a></li>
                        <?php foreach ($breadcrumb as $label => $link): ?>
                            <?php if (is_string($label)): ?>
                                <li class="breadcrumb-item"><a href="<?= e($link) ?>"><?= e($label) ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" aria-current="page"><?= e($link) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
                <?php endif; ?>
                <?php include __DIR__ . '/../partials/flash.php'; ?>
                <?= $content ?>
            </main>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/toast-container.php'; ?>
    <?php include __DIR__ . '/../partials/confirm-modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
