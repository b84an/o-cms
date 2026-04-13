<?php
$pageTitle = 'Risposte: ' . ($form['name'] ?? '');
$activeMenu = 'forms';
$submissions = array_reverse($form['submissions'] ?? []);
ob_start();
?>

<div class="page-header">
    <h1>Risposte: <?= ocms_escape($form['name']) ?></h1>
    <div style="display:flex;gap:10px;align-items:center;">
        <span class="badge badge-published"><?= count($submissions) ?> risposte</span>
        <a href="<?= ocms_base_url() ?>/admin/forms/edit/<?= ocms_escape($form['slug']) ?>" class="btn btn-secondary">Modifica Form</a>
    </div>
</div>

<?php if (empty($submissions)): ?>
    <div class="card"><div class="empty-state" style="padding:40px 20px;"><h3>Nessuna risposta</h3><p>Le risposte appariranno qui quando qualcuno compila il form.</p></div></div>
<?php else: ?>
    <?php foreach ($submissions as $sub): ?>
    <div class="card" style="margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
            <span style="font-size:0.8rem;color:var(--text-muted);"><?= ocms_format_date($sub['submitted_at'] ?? '') ?></span>
            <span style="font-size:0.75rem;color:var(--text-muted);">ID: <?= ocms_escape(substr($sub['id'] ?? '', 0, 8)) ?></span>
        </div>
        <div style="display:grid;grid-template-columns:140px 1fr;gap:6px 16px;font-size:0.875rem;">
            <?php foreach ($sub['data'] ?? [] as $key => $val): ?>
                <span style="color:var(--text-muted);font-weight:600;"><?= ocms_escape($key) ?></span>
                <span><?= ocms_escape($val) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
