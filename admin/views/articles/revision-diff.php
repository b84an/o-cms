<?php
/** @var array $article @var array $rev @var string $revName @var array $fields @var array $diff @var bool $htmlChanged */
$pageTitle = 'Confronto revisione — ' . ($article['title'] ?? '');
$activeMenu = 'articles';
$added = count(array_filter($diff, fn($d) => $d['op'] === '+'));
$removed = count(array_filter($diff, fn($d) => $d['op'] === '-'));

ob_start();
?>
<style>
.rev-diff-legend { display:flex; gap:18px; flex-wrap:wrap; font-size:.85rem; color:var(--text-muted); margin:0 0 14px; }
.rev-diff-legend b { font-weight:700; }
.rev-diff-legend .is-del b { color:var(--ocms-danger, #dc2626); }
.rev-diff-legend .is-add b { color:var(--ocms-ok, #16a34a); }
.rev-fields { width:100%; border-collapse:collapse; font-size:.9rem; }
.rev-fields th, .rev-fields td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--border); vertical-align:top; }
.rev-fields th { width:120px; color:var(--text-muted); font-weight:600; }
.rev-fields del { background:color-mix(in srgb, #dc2626 16%, transparent); text-decoration:line-through; padding:1px 4px; border-radius:4px; }
.rev-fields ins { background:color-mix(in srgb, #16a34a 18%, transparent); text-decoration:none; padding:1px 4px; border-radius:4px; }
.rev-diff { list-style:none; margin:0; padding:0; font-size:.92rem; line-height:1.55; max-height:70vh; overflow:auto; border-radius:var(--ocms-radius-s, 8px); }
.rev-diff li { padding:6px 12px 6px 34px; position:relative; border-left:3px solid transparent; white-space:pre-wrap; word-break:break-word; }
.rev-diff li::before { position:absolute; left:12px; top:6px; font-weight:700; font-family:var(--ocms-font-code, monospace); }
.rev-diff .op-del { background:color-mix(in srgb, #dc2626 12%, transparent); border-left-color:#dc2626; }
.rev-diff .op-del::before { content:'−'; color:#dc2626; }
.rev-diff .op-add { background:color-mix(in srgb, #16a34a 12%, transparent); border-left-color:#16a34a; }
.rev-diff .op-add::before { content:'+'; color:#16a34a; }
.rev-diff .op-eq { color:var(--text-muted); }
.rev-diff .op-gap { color:var(--text-muted); font-style:italic; text-align:center; padding-left:12px; }
</style>

<div class="page-header">
    <h1>Confronto con la versione attuale</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= ocms_base_url() ?>/admin/articles/revisions/<?= ocms_escape($article['slug']) ?>" class="btn btn-secondary">Tutte le revisioni</a>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/articles/revisions/restore/<?= ocms_escape($article['slug']) ?>/<?= ocms_escape($revName) ?>"
              onsubmit="return confirm('Ripristinare questa versione? Quella attuale verrà salvata come revisione.')">
            <?= ocms_csrf_field() ?>
            <button type="submit" class="btn btn-primary">Ripristina questa revisione</button>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <p style="margin:0 0 6px;"><strong><?= ocms_escape($article['title'] ?? '') ?></strong></p>
    <p style="margin:0;color:var(--text-muted);font-size:.9rem;">
        Revisione salvata il <?= ocms_escape(ocms_format_date((string)($rev['updated_at'] ?? ''))) ?>,
        versione attuale del <?= ocms_escape(ocms_format_date((string)($article['updated_at'] ?? ''))) ?>.
    </p>
</div>

<?php if ($fields): ?>
<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-top:0;">Campi cambiati</h3>
    <table class="rev-fields">
        <?php foreach ($fields as $f): ?>
        <tr>
            <th scope="row"><?= ocms_escape($f['label']) ?></th>
            <td><?php if ($f['old'] !== ''): ?><del><?= ocms_escape($f['old']) ?></del><?php endif; ?>
                <?php if ($f['new'] !== ''): ?> <ins><?= ocms_escape($f['new']) ?></ins><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;">Testo</h3>
    <?php if (!$htmlChanged): ?>
        <p style="color:var(--text-muted);margin:0;">Il contenuto è identico a quello attuale.</p>
    <?php elseif ($added === 0 && $removed === 0): ?>
        <p style="color:var(--text-muted);margin:0;">Il testo è lo stesso: cambia solo il codice HTML (formattazione, attributi, immagini o stili).</p>
    <?php else: ?>
        <div class="rev-diff-legend">
            <span class="is-del"><b><?= $removed ?></b> righe presenti solo nella revisione</span>
            <span class="is-add"><b><?= $added ?></b> righe presenti solo nella versione attuale</span>
        </div>
        <ul class="rev-diff">
            <?php
            // Le righe uguali lontane dai cambiamenti vengono compresse
            $n = count($diff);
            $near = array_fill(0, $n, false);
            foreach ($diff as $i => $d) {
                if ($d['op'] !== '=') for ($k = max(0, $i - 2); $k <= min($n - 1, $i + 2); $k++) $near[$k] = true;
            }
            $skipped = 0;
            foreach ($diff as $i => $d):
                if ($d['op'] === '=' && !$near[$i]) { $skipped++; continue; }
                if ($skipped): ?>
                    <li class="op-gap"><?= $skipped ?> righe uguali</li>
                <?php $skipped = 0; endif;
                $cls = $d['op'] === '+' ? 'op-add' : ($d['op'] === '-' ? 'op-del' : 'op-eq'); ?>
                <li class="<?= $cls ?>"><?= ocms_escape($d['text']) ?></li>
            <?php endforeach;
            if ($skipped): ?>
                <li class="op-gap"><?= $skipped ?> righe uguali</li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
