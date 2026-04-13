<?php
$pageTitle = 'Pagine';
$activeMenu = 'pages';

ob_start();
?>

<div class="page-header">
    <h1>Pagine</h1>
    <a href="<?= ocms_base_url() ?>/admin/pages/new" class="btn btn-primary">+ Nuova Pagina</a>
</div>

<?php if (empty($pages)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="icon">&#128196;</div>
            <h3>Nessuna pagina</h3>
            <p>Crea la tua prima pagina per iniziare.</p>
            <a href="<?= ocms_base_url() ?>/admin/pages/new" class="btn btn-primary">Crea Pagina</a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Titolo</th>
                        <th>Slug</th>
                        <th>Stato</th>
                        <th>Aggiornata</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $p): ?>
                    <tr>
                        <td>
                            <a href="<?= ocms_base_url() ?>/admin/pages/edit/<?= ocms_escape($p['slug']) ?>" style="font-weight:600;color:var(--text);">
                                <?= ocms_escape($p['title']) ?>
                            </a>
                        </td>
                        <td style="color:var(--text-muted);">/<?= ocms_escape($p['slug']) ?></td>
                        <td>
                            <span class="badge <?= $p['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>">
                                <?= $p['status'] === 'published' ? 'Pubblicata' : 'Bozza' ?>
                            </span>
                        </td>
                        <td style="color:var(--text-muted); font-size:0.85rem;">
                            <?= ocms_format_date($p['updated_at']) ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <a href="<?= ocms_base_url() ?>/admin/pages/edit/<?= ocms_escape($p['slug']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                <a href="<?= ocms_base_url() ?>/<?= ocms_escape($p['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm">Vedi</a>
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/pages/delete/<?= ocms_escape($p['slug']) ?>"
                                      onsubmit="return confirm('Eliminare questa pagina?');" style="display:inline;">
                                    <?= ocms_csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
