<?php
require_once __DIR__ . '/_bootstrap.php';
$all        = array_values(is_array($articles ?? null) ? $articles : []);
$cats       = is_array($categories ?? null) ? $categories : [];
$currentSort = ($sort ?? 'date') === 'alpha' ? 'alpha' : 'date';
$currentCat  = is_string($filterCat ?? null) ? $filterCat : '';
$currentTag  = isset($_GET['tag']) && is_string($_GET['tag']) ? ocms_slug($_GET['tag']) : '';

// Filtro per tag (la rotta filtra già per categoria)
if ($currentTag !== '') {
    $all = array_values(array_filter($all, static function ($a) use ($currentTag) {
        foreach ((array) ($a['tags'] ?? []) as $t) {
            if (is_string($t) && ocms_slug($t) === $currentTag) return true;
        }
        return false;
    }));
}

$catNames = [];
foreach ($cats as $c) { $catNames[$c['slug'] ?? ''] = $c['name'] ?? ''; }

// Paginazione
$perPage    = max(1, (int) ($app->config['posts_per_page'] ?? 10) ?: 10);
$total      = count($all);
$totalPages = max(1, (int) ceil($total / $perPage));
$pageNum    = isset($_GET['page']) && is_scalar($_GET['page']) ? (int) $_GET['page'] : 1;
$pageNum    = min(max(1, $pageNum), $totalPages);
$items      = array_slice($all, ($pageNum - 1) * $perPage, $perPage);

$blogUrl = static function (array $over = []) use ($currentSort, $currentCat, $currentTag): string {
    $q = array_merge(['cat' => $currentCat, 'tag' => $currentTag, 'sort' => $currentSort, 'page' => 1], $over);
    if ($q['sort'] === 'date') unset($q['sort']);
    if ((int) $q['page'] <= 1) unset($q['page']);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return ocms_base_url() . '/blog' . ($q ? '?' . http_build_query($q) : '');
};

$heading = 'Blog';
if ($currentCat !== '') $heading = $catNames[$currentCat] ?? ucfirst(str_replace('-', ' ', $currentCat));
$titleParts = [$heading];
if ($currentTag !== '') $titleParts[] = '#' . flavor_tag_label($currentTag);
if ($pageNum > 1) $titleParts[] = 'pagina ' . $pageNum;

$flavor = [
    'title'       => implode(' — ', $titleParts),
    'description' => $app->config['site_description'] ?? '',
    'seo'         => ['title' => implode(' — ', $titleParts), 'description' => $app->config['site_description'] ?? ''],
    'bodyClass'   => 'page-blog',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap">
    <header class="page-head">
        <h1 class="display"><?= ocms_escape($heading) ?></h1>
        <p class="page-head-count"><?= $total === 1 ? '1 articolo' : number_format($total, 0, ',', '.') . ' articoli' ?><?php if ($currentTag !== ''): ?> con il tag <strong><?= ocms_escape(flavor_tag_label($currentTag)) ?></strong><?php endif; ?></p>
    </header>

    <div class="filter-bar">
<?php if ($cats): ?>
        <nav class="chips" aria-label="Categorie">
            <a class="chip" href="<?= ocms_escape($blogUrl(['cat' => ''])) ?>"<?= $currentCat === '' ? ' aria-current="true"' : '' ?>>Tutti</a>
<?php foreach ($cats as $cat): if (empty($cat['slug'])) continue; ?>
            <a class="chip" href="<?= ocms_escape($blogUrl(['cat' => $cat['slug']])) ?>"<?= $currentCat === $cat['slug'] ? ' aria-current="true"' : '' ?>><?= ocms_escape($cat['name'] ?? $cat['slug']) ?></a>
<?php endforeach; ?>
        </nav>
<?php endif; ?>
<?php if ($currentTag !== ''): ?>
        <a class="chip chip-active-tag" href="<?= ocms_escape($blogUrl(['tag' => ''])) ?>"><?= flavor_icon('tag', 14) ?><span><?= ocms_escape(flavor_tag_label($currentTag)) ?></span><?= flavor_icon('close', 14) ?><span class="sr-only">rimuovi il filtro</span></a>
<?php endif; ?>
        <div class="segmented" role="group" aria-label="Ordinamento">
            <a href="<?= ocms_escape($blogUrl(['sort' => 'date'])) ?>"<?= $currentSort === 'date' ? ' aria-current="true"' : '' ?>>Recenti</a>
            <a href="<?= ocms_escape($blogUrl(['sort' => 'alpha'])) ?>"<?= $currentSort === 'alpha' ? ' aria-current="true"' : '' ?>>A-Z</a>
        </div>
    </div>

<?php if (!$items): ?>
    <div class="empty-state ocms-glass">
        <p>Nessun articolo da mostrare con questi filtri.</p>
        <a class="btn btn-ghost" href="<?= ocms_base_url() ?>/blog">Mostra tutti gli articoli</a>
    </div>
<?php else: ?>
    <div class="post-grid post-grid-blog">
<?php foreach ($items as $i => $a):
    $lead = ($i === 0 && $pageNum === 1 && $currentSort === 'date');
    echo flavor_article_card($a, ['variant' => $lead ? 'lead' : 'standard', 'eager' => $i === 0, 'catNames' => $catNames]);
endforeach; ?>
    </div>

<?php if ($totalPages > 1):
    // Finestra di pagine: prima, ultima e due attorno a quella corrente
    $show = [];
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === 1 || $p === $totalPages || abs($p - $pageNum) <= 1) $show[] = $p;
    }
?>
    <nav class="pager" aria-label="Paginazione">
<?php if ($pageNum > 1): ?>
        <a class="pager-step" href="<?= ocms_escape($blogUrl(['page' => $pageNum - 1])) ?>" rel="prev"><?= flavor_icon('left', 16) ?><span>Precedenti</span></a>
<?php endif; ?>
        <ol class="pager-pages">
<?php $prev = 0; foreach ($show as $p): if ($p - $prev > 1): ?>
            <li class="pager-gap" aria-hidden="true">…</li>
<?php endif; $prev = $p; ?>
            <li><?php if ($p === $pageNum): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= ocms_escape($blogUrl(['page' => $p])) ?>" aria-label="Pagina <?= $p ?>"><?= $p ?></a><?php endif; ?></li>
<?php endforeach; ?>
        </ol>
<?php if ($pageNum < $totalPages): ?>
        <a class="pager-step" href="<?= ocms_escape($blogUrl(['page' => $pageNum + 1])) ?>" rel="next"><span>Successivi</span><?= flavor_icon('right', 16) ?></a>
<?php endif; ?>
    </nav>
<?php endif; ?>
<?php endif; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
