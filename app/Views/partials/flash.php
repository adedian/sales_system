<?php
/**
 * Single, app-wide place that reads flash('success')/flash('error').
 * Controllers should only ever WRITE via Session::flash() and let this
 * partial (included by both layouts) render it — reading it a second time
 * anywhere else would pop it before this ever runs.
 */
$successMessage = flash('success');
$errorMessage = flash('error');
?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= e($successMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($errorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($successMessage || $errorMessage): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        <?php if ($successMessage): ?>Toast.show(<?= json_encode($successMessage, JSON_UNESCAPED_UNICODE) ?>, 'success');<?php endif; ?>
        <?php if ($errorMessage): ?>Toast.show(<?= json_encode($errorMessage, JSON_UNESCAPED_UNICODE) ?>, 'danger');<?php endif; ?>
    });
</script>
<?php endif; ?>
