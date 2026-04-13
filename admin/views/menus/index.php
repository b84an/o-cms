<?php
$pageTitle = 'Menu';
$activeMenu = 'menus';

ob_start();
?>

<div class="page-header">
    <h1>Menu</h1>
    <a href="<?= ocms_base_url() ?>/admin/menus/new" class="btn btn-primary">+ Nuovo Menu</a>
</div>

<?php if (empty($menus)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="icon">&#9776;</div>
            <h3>Nessun menu</h3>
            <p>Crea il tuo primo menu di navigazione.</p>
            <a href="<?= ocms_base_url() ?>/admin/menus/new" class="btn btn-primary">Crea Menu</a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Etichetta</th>
                        <th>Voci</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menus as $m):
                        $itemCount = count($m['items'] ?? []);
                    ?>
                    <tr>
                        <td>
                            <a href="<?= ocms_base_url() ?>/admin/menus/edit/<?= ocms_escape($m['name']) ?>" style="font-weight:600;color:var(--text);">
                                <?= ocms_escape($m['name']) ?>
                            </a>
                        </td>
                        <td style="color:var(--text-muted);"><?= ocms_escape($m['label'] ?? '') ?></td>
                        <td>
                            <span class="badge badge-published"><?= $itemCount ?> <?= $itemCount === 1 ? 'voce' : 'voci' ?></span>
                        </td>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <a href="<?= ocms_base_url() ?>/admin/menus/edit/<?= ocms_escape($m['name']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                <?php if ($m['name'] !== 'main'): ?>
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/menus/delete/<?= ocms_escape($m['name']) ?>"
                                      onsubmit="return confirm('Eliminare questo menu?');" style="display:inline;">
                                    <?= ocms_csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                                </form>
                                <?php endif; ?>
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
