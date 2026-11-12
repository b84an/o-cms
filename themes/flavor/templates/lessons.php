<?php
require_once __DIR__ . '/_bootstrap.php';
$list = array_values(is_array($lessons ?? null) ? $lessons : []);

// Tag presenti (etichetta leggibile, senza duplicati per maiuscole/minuscole)
$allTags = [];
foreach ($list as $l) {
    foreach ((array) ($l['tags'] ?? []) as $t) {
        if (!is_string($t) || trim($t) === '') continue;
        $label = flavor_tag_label($t);
        $key = mb_strtolower($label);
        $allTags[$key] = ['label' => $label, 'n' => ($allTags[$key]['n'] ?? 0) + 1];
    }
}
uasort($allTags, fn($a, $b) => strcasecmp($a['label'], $b['label']));

$flavor = [
    'title'       => 'Lezioni',
    'description' => 'Tutte le lezioni e le risorse didattiche di ' . ($app->config['site_name'] ?? 'questo sito'),
    'seo'         => ['title' => 'Lezioni', 'description' => $app->config['site_description'] ?? ''],
    'bodyClass'   => 'page-lessons',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap">
    <header class="page-head">
        <h1 class="display">Lezioni</h1>
        <p class="page-head-count"><?= count($list) === 1 ? '1 lezione' : count($list) . ' lezioni' ?><?= $allTags ? ', ' . count($allTags) . ' argomenti' : '' ?></p>
    </header>

<?php if (!$list): ?>
    <div class="empty-state ocms-glass"><p>Non ci sono ancora lezioni pubblicate.</p></div>
<?php else: ?>
    <div class="finder" data-finder>
        <div class="finder-controls">
            <div class="search-field">
                <?= flavor_icon('search', 18) ?>
                <label class="sr-only" for="lesson-q">Cerca tra le lezioni</label>
                <input id="lesson-q" type="search" placeholder="Cerca per titolo, descrizione o argomento" autocomplete="off" data-finder-q>
            </div>
            <div class="segmented" role="group" aria-label="Ordinamento">
                <button type="button" data-finder-sort="date" aria-pressed="true">Recenti</button>
                <button type="button" data-finder-sort="alpha" aria-pressed="false">A-Z</button>
            </div>
        </div>
<?php if ($allTags): ?>
        <div class="chips chips-scroll" role="group" aria-label="Filtra per argomento">
<?php foreach ($allTags as $key => $t): ?>
            <button type="button" class="chip" data-finder-tag="<?= ocms_escape($key) ?>" aria-pressed="false"><?= ocms_escape($t['label']) ?> <span class="chip-n"><?= (int) $t['n'] ?></span></button>
<?php endforeach; ?>
        </div>
<?php endif; ?>
        <p class="finder-status" aria-live="polite" data-finder-status></p>

        <ul class="lesson-grid" data-finder-list>
<?php foreach ($list as $i => $l):
    $tags = array_values(array_filter((array) ($l['tags'] ?? []), fn($t) => is_string($t) && trim($t) !== ''));
    $labels = array_map('flavor_tag_label', $tags);
    $hay = mb_strtolower(($l['title'] ?? '') . ' ' . ($l['description'] ?? '') . ' ' . implode(' ', $labels));
    $hasCover = is_string($l['cover_image'] ?? null) && trim($l['cover_image']) !== '';
?>
            <li class="lesson-card<?= $hasCover ? ' has-cover' : '' ?>" data-title="<?= ocms_escape(mb_strtolower((string) ($l['title'] ?? ''))) ?>" data-date="<?= ocms_escape((string) ($l['updated_at'] ?? '')) ?>" data-hay="<?= ocms_escape($hay) ?>" data-tags="<?= ocms_escape('|' . implode('|', array_map('mb_strtolower', $labels)) . '|') ?>" data-order="<?= $i ?>">
<?php if ($hasCover): ?>
                <div class="lesson-card-media"><?= flavor_cover($l, ['class' => 'cover', 'sizes' => '(min-width: 900px) 33vw, 100vw']) ?></div>
<?php endif; ?>
                <div class="lesson-card-body">
                    <h2 class="card-title"><a href="<?= ocms_base_url() ?>/lezione/<?= rawurlencode((string) $l['slug']) ?>"><?= ocms_escape($l['title'] ?? '') ?></a></h2>
<?php if (!empty($l['description'])): ?>
                    <p class="card-excerpt"><?= ocms_escape(ocms_truncate(flavor_plain($l['description']), 160)) ?></p>
<?php endif; ?>
<?php if ($labels): ?>
                    <ul class="tag-list tag-list-small">
<?php foreach (array_slice($labels, 0, 4) as $lab): ?>
                        <li><span class="tag"><?= ocms_escape($lab) ?></span></li>
<?php endforeach; ?>
<?php if (count($labels) > 4): ?>
                        <li><span class="tag tag-more">+<?= count($labels) - 4 ?></span></li>
<?php endif; ?>
                    </ul>
<?php endif; ?>
                    <div class="card-meta">
<?php if (!empty($l['updated_at'])): ?>
                        <time datetime="<?= ocms_escape(flavor_datetime($l['updated_at'])) ?>"><?= ocms_escape(flavor_date($l['updated_at'], true)) ?></time>
<?php endif; ?>
<?php if (($l['views'] ?? 0) > 0): ?>
                        <span><?= flavor_icon('eye', 14) ?><?= number_format((int) $l['views'], 0, ',', '.') ?></span>
<?php endif; ?>
                    </div>
                </div>
            </li>
<?php endforeach; ?>
        </ul>
        <div class="empty-state ocms-glass" data-finder-empty hidden>
            <p>Nessuna lezione corrisponde alla ricerca.</p>
            <button type="button" class="btn btn-ghost" data-finder-reset>Azzera i filtri</button>
        </div>
    </div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
