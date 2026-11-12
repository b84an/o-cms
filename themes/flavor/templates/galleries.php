<?php
require_once __DIR__ . '/_bootstrap.php';
$list   = array_values(is_array($galleries ?? null) ? $galleries : []);
$tagsAll = is_array($allTags ?? null) ? $allTags : [];
$active = is_string($filterTag ?? null) ? $filterTag : '';

$coverOf = static function (array $g): string {
    $slug = rawurlencode((string) ($g['slug'] ?? ''));
    $base = ocms_base_url() . '/uploads/gallery/' . $slug . '/';
    if (!empty($g['cover_image']) && is_string($g['cover_image'])) {
        $c = $g['cover_image'];
        return (str_contains($c, '/') || preg_match('#^https?://#', $c)) ? flavor_media($c) : $base . rawurlencode($c);
    }
    $first = $g['images'][0] ?? null;
    if (is_array($first) && !empty($first['filename'])) return $base . 'thumb_' . rawurlencode((string) $first['filename']);
    return '';
};

$flavor = [
    'title'       => 'Gallerie',
    'description' => 'Le gallerie fotografiche di ' . ($app->config['site_name'] ?? 'questo sito'),
    'seo'         => ['title' => 'Gallerie', 'description' => $app->config['site_description'] ?? ''],
    'bodyClass'   => 'page-galleries',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap">
    <header class="page-head">
        <h1 class="display">Gallerie</h1>
        <p class="page-head-count"><?= count($list) === 1 ? '1 galleria' : count($list) . ' gallerie' ?></p>
    </header>
<?php if ($tagsAll): ?>
    <nav class="chips" aria-label="Filtra per tag">
        <a class="chip" href="<?= ocms_base_url() ?>/gallerie"<?= $active === '' ? ' aria-current="true"' : '' ?>>Tutte</a>
<?php foreach ($tagsAll as $tag): if (!is_string($tag)) continue; $key = mb_strtolower($tag); ?>
        <a class="chip" href="<?= ocms_base_url() ?>/gallerie?tag=<?= rawurlencode($key) ?>"<?= mb_strtolower($active) === $key ? ' aria-current="true"' : '' ?>><?= ocms_escape(flavor_tag_label($tag)) ?></a>
<?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if (!$list): ?>
    <div class="empty-state ocms-glass"><p>Nessuna galleria da mostrare.</p></div>
<?php else: ?>
    <ul class="gallery-grid">
<?php foreach ($list as $g):
    $n = count(is_array($g['images'] ?? null) ? $g['images'] : []);
    $cover = $coverOf($g); ?>
        <li class="gallery-card">
            <div class="gallery-card-media">
<?php if ($cover !== ''): ?>
                <img src="<?= ocms_escape($cover) ?>" alt="" loading="lazy" decoding="async">
<?php else: ?>
                <?= flavor_cover(['title' => $g['title'] ?? '', 'slug' => $g['slug'] ?? ''], ['class' => 'cover']) ?>
<?php endif; ?>
            </div>
            <div class="gallery-card-body">
                <h2 class="card-title"><a href="<?= ocms_base_url() ?>/galleria/<?= rawurlencode((string) $g['slug']) ?>"><?= ocms_escape($g['title'] ?? '') ?></a></h2>
<?php if (!empty($g['description'])): ?>
                <p class="card-excerpt"><?= ocms_escape(ocms_truncate(flavor_plain($g['description']), 120)) ?></p>
<?php endif; ?>
                <div class="card-meta"><span><?= flavor_icon('image', 14) ?><?= $n === 1 ? '1 immagine' : $n . ' immagini' ?></span></div>
            </div>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
