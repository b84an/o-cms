<?php
require_once __DIR__ . '/_bootstrap.php';
$page = (isset($page) && is_array($page)) ? $page : null;
$featuredList = array_values(is_array($featured ?? null) ? $featured : []);
$siteName = (string) ($app->config['site_name'] ?? 'O-CMS');
$siteDesc = (string) ($app->config['site_description'] ?? '');

// Senza articoli in evidenza la home mostra gli ultimi pubblicati
$homeLatest = false;
if (!$featuredList) {
    $featuredList = array_slice($app->storage->findAll('articles', function ($a) {
        return ($a['status'] ?? '') === 'published' && !ocms_is_future($a['publish_at'] ?? '');
    }, 'created_at', 'desc'), 0, 7);
    $homeLatest = true;
}
$hasLessons = (bool) $app->storage->findAll('lessons', fn($l) => ($l['status'] ?? '') === 'published');
$heroImg = is_string($page['hero_image'] ?? null) ? trim($page['hero_image']) : '';
$heroTitle = $page ? (string) ($page['title'] ?? $siteName) : $siteName;

$catNames = [];
foreach ($app->storage->findAll('categories') as $c) { $catNames[$c['slug'] ?? ''] = $c['name'] ?? ''; }

$flavor = [
    'fullTitle'   => ($page['meta']['title'] ?? '') ?: $siteName,
    'description' => ($page['meta']['description'] ?? '') ?: $siteDesc,
    'seo'         => [
        'title'       => ($page['meta']['title'] ?? '') ?: $siteName,
        'description' => ($page['meta']['description'] ?? '') ?: $siteDesc,
        'image'       => ($page['meta']['og_image'] ?? '') ?: $heroImg,
    ],
    'bodyClass'   => 'page-home',
];
include __DIR__ . '/_header.php';
?>
<section class="home-hero wrap<?= $heroImg !== '' ? ' has-figure' : '' ?>">
    <div class="home-hero-text">
        <h1 class="display"><?= ocms_escape($heroTitle) ?></h1>
<?php if ($siteDesc !== ''): ?>
        <p class="dek"><?= ocms_escape($siteDesc) ?></p>
<?php endif; ?>
        <div class="actions">
            <a class="btn btn-primary" href="<?= ocms_base_url() ?>/blog">Leggi il blog</a>
<?php if ($hasLessons): ?>
            <a class="btn btn-ghost" href="<?= ocms_base_url() ?>/lezioni">Sfoglia le lezioni</a>
<?php endif; ?>
        </div>
    </div>
<?php if ($heroImg !== ''):
    [$hw, $hh] = flavor_img_size($heroImg); ?>
    <figure class="home-hero-figure">
        <img src="<?= ocms_escape(flavor_media($heroImg)) ?>" alt="" <?= $hw > 0 ? 'width="' . $hw . '" height="' . $hh . '"' : '' ?> fetchpriority="high" decoding="async">
    </figure>
<?php endif; ?>
</section>

<?php if ($page && trim((string) ($page['content'] ?? '')) !== ''): ?>
<section class="wrap home-content prose prose-wide">
    <?= flavor_content((string) $page['content']) ?>
</section>
<?php elseif (!$page): ?>
<section class="wrap home-content">
    <p class="muted">Il sito è pronto. Crea una pagina con slug <code>home</code> dal pannello per personalizzare questa introduzione.</p>
</section>
<?php endif; ?>

<?php if (!empty($page['sections']) && is_array($page['sections'])): ?>
<div class="wrap home-sections">
<?php foreach ($page['sections'] as $section): if (!is_array($section)) continue; ?>
    <section class="home-section">
<?php if (!empty($section['title'])): ?>
        <h2 class="home-section-title"><?= ocms_escape($section['title']) ?></h2>
<?php endif; ?>
        <div class="prose"><?= flavor_content((string) ($section['content'] ?? '')) ?></div>
    </section>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($featuredList): ?>
<section class="wrap home-featured" aria-labelledby="home-featured-title">
    <div class="section-head">
        <h2 id="home-featured-title"><?= $homeLatest ? 'Ultimi articoli' : 'In evidenza' ?></h2>
        <a class="link-quiet" href="<?= ocms_base_url() ?>/blog">Tutti gli articoli</a>
    </div>
    <div class="post-grid">
<?php foreach ($featuredList as $i => $a):
    echo flavor_article_card($a, ['variant' => $i === 0 ? 'lead' : 'standard', 'heading' => 'h3', 'hideFeatured' => true, 'catNames' => $catNames]);
endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
