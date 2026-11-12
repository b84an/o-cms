<?php
require_once __DIR__ . '/_bootstrap.php';
$images = array_values(array_filter(is_array($gallery['images'] ?? null) ? $gallery['images'] : [], fn($i) => is_array($i) && !empty($i['filename'])));
$n = count($images);
$relBase = '/uploads/gallery/' . rawurlencode((string) $gallery['slug']) . '/';
$urlBase = ocms_base_url() . $relBase;
$ogImage = '';
if (!empty($gallery['cover_image']) && is_string($gallery['cover_image'])) {
    $ogImage = str_contains($gallery['cover_image'], '/') ? $gallery['cover_image'] : $relBase . rawurlencode($gallery['cover_image']);
} elseif ($images) {
    $ogImage = $relBase . rawurlencode((string) $images[0]['filename']);
}
$tags = array_values(array_filter((array) ($gallery['tags'] ?? []), fn($t) => is_string($t) && trim($t) !== ''));

$flavor = [
    'title'       => $gallery['title'] ?? 'Galleria',
    'description' => $gallery['description'] ?? '',
    'seo'         => ['title' => $gallery['title'] ?? 'Galleria', 'description' => $gallery['description'] ?? '', 'image' => $ogImage],
    'bodyClass'   => 'page-gallery',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap">
    <header class="page-head">
        <nav class="crumbs" aria-label="Percorso"><a href="<?= ocms_base_url() ?>/gallerie">Gallerie</a></nav>
        <h1 class="display"><?= ocms_escape($gallery['title'] ?? '') ?></h1>
<?php if (!empty($gallery['description'])): ?>
        <p class="dek"><?= ocms_escape(flavor_plain($gallery['description'])) ?></p>
<?php endif; ?>
        <ul class="meta-row">
            <li><?= flavor_icon('image', 16) ?><span><?= $n === 1 ? '1 immagine' : $n . ' immagini' ?></span></li>
<?php if (!empty($gallery['created_at'])): ?>
            <li><?= flavor_icon('calendar', 16) ?><time datetime="<?= ocms_escape(flavor_datetime($gallery['created_at'])) ?>"><?= ocms_escape(flavor_date($gallery['created_at'])) ?></time></li>
<?php endif; ?>
        </ul>
<?php if ($tags): ?>
        <ul class="tag-list">
<?php foreach ($tags as $t): ?>
            <li><a class="tag" href="<?= ocms_base_url() ?>/gallerie?tag=<?= rawurlencode(mb_strtolower($t)) ?>"><?= ocms_escape(flavor_tag_label($t)) ?></a></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
    </header>

<?php if ($n === 0): ?>
    <div class="empty-state ocms-glass"><p>Questa galleria non contiene ancora immagini.</p></div>
<?php else: ?>
    <ul class="masonry">
<?php foreach ($images as $img):
    $fn = rawurlencode((string) $img['filename']);
    $title = (string) ($img['title'] ?? '');
    $desc = (string) ($img['description'] ?? '');
    $thumbFs = ocms_base_path() . '/uploads/gallery/' . basename((string) $gallery['slug']) . '/thumb_' . basename((string) $img['filename']);
    $thumb = is_file($thumbFs) ? $urlBase . 'thumb_' . $fn : $urlBase . $fn;
    [$w, $h] = flavor_img_size($relBase . 'thumb_' . $fn); ?>
        <li>
            <a href="<?= ocms_escape($urlBase . $fn) ?>" data-lightbox="gallery" data-caption="<?= ocms_escape(trim($title . ($desc !== '' ? ' — ' . $desc : ''))) ?>">
                <img src="<?= ocms_escape($thumb) ?>" alt="<?= ocms_escape($title !== '' ? $title : 'Immagine della galleria') ?>" loading="lazy" decoding="async"<?= $w > 0 ? ' width="' . $w . '" height="' . $h . '"' : '' ?>>
<?php if ($title !== ''): ?>
                <span class="masonry-cap"><?= ocms_escape($title) ?></span>
<?php endif; ?>
            </a>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
