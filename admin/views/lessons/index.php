<?php
$pageTitle = 'Lezioni';
$activeMenu = 'lessons';
ob_start();
?>

<div class="page-header">
    <h1>Lezioni</h1>
    <a href="<?= ocms_base_url() ?>/admin/lessons/new" class="btn btn-primary">+ Nuova Lezione</a>
</div>

<?php if (empty($lessons)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <p style="font-size:2rem;margin-bottom:12px;">📚</p>
        <h3>Nessuna lezione</h3>
        <p style="color:var(--text-muted);margin-bottom:20px;">Crea la tua prima lezione con contenuti multimediali.</p>
        <a href="<?= ocms_base_url() ?>/admin/lessons/new" class="btn btn-primary">Crea Lezione</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Titolo</th>
                        <th>Tag</th>
                        <th>File</th>
                        <th>Stato</th>
                        <th>Visite</th>
                        <th>Aggiornata</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lessons as $l):
                    $dir = ocms_base_path() . '/uploads/lezioni/' . $l['slug'];
                    $fileCount = 0;
                    if (is_dir($dir)) {
                        $fileCount = count(array_filter(scandir($dir), function($f) use ($dir) {
                            return $f[0] !== '.' && !is_dir($dir.'/'.$f) && !in_array($f, ['info.txt','visite.txt']) && !preg_match('/^index(_backup_\d+)?\.php$/', $f);
                        }));
                    }
                ?>
                    <tr>
                        <td>
                            <a href="<?= ocms_base_url() ?>/admin/lessons/edit/<?= ocms_escape($l['slug']) ?>" style="font-weight:600;color:var(--text);">
                                <?= ocms_escape($l['title']) ?>
                            </a>
                            <div style="font-size:0.75rem;color:var(--text-muted);">/lezione/<?= ocms_escape($l['slug']) ?></div>
                        </td>
                        <td>
                            <?php foreach ($l['tags'] ?? [] as $tag): ?>
                                <span class="badge" style="background:rgba(99,102,241,0.15);color:var(--primary-light);font-size:0.7rem;padding:2px 8px;border-radius:10px;margin-right:4px;"><?= ocms_escape($tag) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td style="font-family:monospace;font-size:0.85rem;"><?= $fileCount ?></td>
                        <td>
                            <span class="badge badge-<?= ($l['status'] ?? 'draft') === 'published' ? 'published' : 'draft' ?>">
                                <?= ($l['status'] ?? 'draft') === 'published' ? 'Pubblicata' : 'Bozza' ?>
                            </span>
                        </td>
                        <td style="font-family:monospace;font-size:0.85rem;"><?= number_format($l['views'] ?? 0, 0, '', '.') ?></td>
                        <td style="font-size:0.85rem;color:var(--text-muted);"><?= ocms_format_date($l['updated_at'], 'd/m/Y H:i') ?></td>
                        <td>
                            <div style="display:flex;gap:8px;justify-content:flex-end;">
                                <?php if (($l['status'] ?? '') === 'published'): ?>
                                <a href="<?= ocms_base_url() ?>/lezione/<?= ocms_escape($l['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Visualizza">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <?php endif; ?>
                                <a href="<?= ocms_base_url() ?>/admin/lessons/edit/<?= ocms_escape($l['slug']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/lessons/delete/<?= ocms_escape($l['slug']) ?>" onsubmit="return confirm('Eliminare questa lezione e tutti i suoi file?');" style="margin:0;">
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
