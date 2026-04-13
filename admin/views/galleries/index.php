<?php
$pageTitle = 'Gallerie';
$activeMenu = 'galleries';
ob_start();
?>

<div class="page-header">
    <h1>Gallerie</h1>
    <a href="<?= ocms_base_url() ?>/admin/galleries/new" class="btn btn-primary">+ Nuova Galleria</a>
</div>

<?php if (empty($galleries)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <p style="font-size:2rem;margin-bottom:12px;">&#128247;</p>
        <h3>Nessuna galleria</h3>
        <p style="color:var(--text-muted);margin-bottom:20px;">Crea la tua prima galleria fotografica.</p>
        <a href="<?= ocms_base_url() ?>/admin/galleries/new" class="btn btn-primary">Crea Galleria</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;"></th>
                        <th>Titolo</th>
                        <th>Immagini</th>
                        <th>Tag</th>
                        <th>Stato</th>
                        <th>Data</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($galleries as $g):
                    $imgCount = count($g['images'] ?? []);
                    $coverUrl = '';
                    if (!empty($g['cover_image'])) {
                        $coverUrl = ocms_base_url() . $g['cover_image'];
                    } elseif ($imgCount > 0) {
                        $coverUrl = ocms_base_url() . '/uploads/gallery/' . $g['slug'] . '/thumb_' . $g['images'][0]['filename'];
                    }
                ?>
                    <tr>
                        <td>
                            <?php if ($coverUrl): ?>
                            <img src="<?= ocms_escape($coverUrl) ?>" alt="" style="width:50px;height:38px;object-fit:cover;border-radius:6px;">
                            <?php else: ?>
                            <div style="width:50px;height:38px;background:var(--bg);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:1.2rem;">&#128247;</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= ocms_base_url() ?>/admin/galleries/edit/<?= ocms_escape($g['slug']) ?>" style="font-weight:600;color:var(--text);">
                                <?= ocms_escape($g['title']) ?>
                            </a>
                            <div style="font-size:0.75rem;color:var(--text-muted);">/galleria/<?= ocms_escape($g['slug']) ?></div>
                        </td>
                        <td style="font-family:monospace;font-size:0.85rem;"><?= $imgCount ?></td>
                        <td>
                            <?php foreach ($g['tags'] ?? [] as $tag): ?>
                                <span class="badge" style="background:rgba(99,102,241,0.15);color:var(--primary-light);font-size:0.7rem;padding:2px 8px;border-radius:10px;margin-right:4px;"><?= ocms_escape($tag) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= ($g['status'] ?? 'draft') === 'published' ? 'published' : 'draft' ?>">
                                <?= ($g['status'] ?? 'draft') === 'published' ? 'Pubblicata' : 'Bozza' ?>
                            </span>
                        </td>
                        <td style="font-size:0.85rem;color:var(--text-muted);"><?= ocms_format_date($g['created_at'] ?? '', 'd/m/Y') ?></td>
                        <td>
                            <div style="display:flex;gap:8px;justify-content:flex-end;">
                                <?php if (($g['status'] ?? '') === 'published'): ?>
                                <a href="<?= ocms_base_url() ?>/galleria/<?= ocms_escape($g['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Visualizza">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <?php endif; ?>
                                <a href="<?= ocms_base_url() ?>/admin/galleries/edit/<?= ocms_escape($g['slug']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/galleries/delete/<?= ocms_escape($g['slug']) ?>" onsubmit="return confirm('Eliminare questa galleria e tutte le immagini?');" style="margin:0;">
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
