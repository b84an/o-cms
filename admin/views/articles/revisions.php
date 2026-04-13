<?php
$pageTitle = 'Revisioni — ' . ($article['title'] ?? '');
$activeMenu = 'articles';

ob_start();
?>

<div class="page-header">
    <h1>Revisioni</h1>
    <a href="<?= ocms_base_url() ?>/admin/articles/edit/<?= ocms_escape($article['slug']) ?>" class="btn btn-secondary">Torna all'articolo</a>
</div>

<div class="card" style="margin-bottom:24px;padding:16px 24px;">
    <div style="display:flex;align-items:center;gap:16px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-light)" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        <div>
            <strong><?= ocms_escape($article['title']) ?></strong>
            <span style="color:var(--text-muted);font-size:0.8rem;margin-left:12px;">Versione corrente: <?= ocms_format_date($article['updated_at']) ?></span>
        </div>
    </div>
</div>

<?php if (empty($revisions)): ?>
    <div class="empty-state card">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h3>Nessuna revisione</h3>
        <p>Le revisioni vengono create automaticamente ad ogni salvataggio.</p>
    </div>
<?php else: ?>
    <div style="position:relative;padding-left:24px;">
        <!-- Timeline line -->
        <div style="position:absolute;left:9px;top:8px;bottom:8px;width:2px;background:var(--border);"></div>

        <?php foreach ($revisions as $i => $rev): ?>
        <div class="card" style="margin-bottom:16px;position:relative;">
            <!-- Timeline dot -->
            <div style="position:absolute;left:-20px;top:24px;width:12px;height:12px;border-radius:50%;background:var(--primary);border:2px solid var(--bg);"></div>

            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <div style="font-weight:600;margin-bottom:4px;">
                        <?= ocms_escape($rev['title']) ?>
                    </div>
                    <div style="font-size:0.8rem;color:var(--text-muted);display:flex;gap:16px;flex-wrap:wrap;">
                        <span>Salvato: <?= ocms_format_date($rev['updated_at']) ?></span>
                        <span>Autore: <?= ocms_escape($rev['author'] ?? '—') ?></span>
                        <span>Stato: <?= $rev['status'] === 'published' ? 'Pubblicato' : 'Bozza' ?></span>
                    </div>
                    <?php
                    // Mostra differenze brevi
                    $changes = [];
                    if ($rev['title'] !== $article['title']) $changes[] = 'titolo';
                    if (($rev['excerpt'] ?? '') !== ($article['excerpt'] ?? '')) $changes[] = 'estratto';
                    if (($rev['content'] ?? '') !== ($article['content'] ?? '')) $changes[] = 'contenuto';
                    if (($rev['cover_image'] ?? '') !== ($article['cover_image'] ?? '')) $changes[] = 'cover';
                    if (($rev['category'] ?? '') !== ($article['category'] ?? '')) $changes[] = 'categoria';
                    if ($changes): ?>
                    <div style="margin-top:6px;font-size:0.75rem;color:var(--primary-light);">
                        Differenze dalla versione corrente: <?= implode(', ', $changes) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:8px;flex-shrink:0;">
                    <button class="btn btn-secondary btn-sm" onclick="togglePreview('rev-<?= $i ?>')">Anteprima</button>
                    <form method="POST" action="<?= ocms_base_url() ?>/admin/articles/revisions/restore/<?= ocms_escape($article['slug']) ?>/<?= ocms_escape($rev['_rev_file']) ?>"
                          onsubmit="return confirm('Ripristinare questa versione? La versione corrente verrà salvata come revisione.')">
                        <?= ocms_csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-sm">Ripristina</button>
                    </form>
                </div>
            </div>

            <!-- Preview contenuto (nascosta) -->
            <div id="rev-<?= $i ?>" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <div style="background:var(--bg);border-radius:8px;padding:16px;font-size:0.85rem;max-height:300px;overflow-y:auto;line-height:1.7;">
                    <?= $rev['content'] ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
function togglePreview(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
