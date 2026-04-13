<?php
$pageTitle = 'Form Builder';
$activeMenu = 'forms';
ob_start();
?>

<div class="page-header">
    <h1>Form Builder</h1>
    <a href="<?= ocms_base_url() ?>/admin/forms/new" class="btn btn-primary">+ Nuovo Form</a>
</div>

<?php if (empty($forms)): ?>
    <div class="card"><div class="empty-state"><div class="icon">&#128221;</div><h3>Nessun form</h3><p>Crea il tuo primo modulo di contatto.</p>
    <a href="<?= ocms_base_url() ?>/admin/forms/new" class="btn btn-primary">Crea Form</a></div></div>
<?php else: ?>
    <div class="card"><div class="table-wrapper"><table class="data-table">
        <thead><tr><th>Nome</th><th>Campi</th><th>Risposte</th><th>Azioni</th></tr></thead>
        <tbody>
        <?php foreach ($forms as $f): ?>
        <tr>
            <td style="font-weight:600;"><?= ocms_escape($f['name']) ?></td>
            <td><span class="badge badge-published"><?= count($f['fields'] ?? []) ?></span></td>
            <td><span class="badge badge-draft"><?= count($f['submissions'] ?? []) ?></span></td>
            <td><div style="display:flex;gap:8px;">
                <a href="<?= ocms_base_url() ?>/admin/forms/edit/<?= ocms_escape($f['slug']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                <a href="<?= ocms_base_url() ?>/admin/forms/submissions/<?= ocms_escape($f['slug']) ?>" class="btn btn-secondary btn-sm">Risposte</a>
                <form method="POST" action="<?= ocms_base_url() ?>/admin/forms/delete/<?= ocms_escape($f['slug']) ?>" onsubmit="return confirm('Eliminare?');" style="display:inline;">
                    <?= ocms_csrf_field() ?><button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                </form>
            </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
