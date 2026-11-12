<?php
require_once __DIR__ . '/_bootstrap.php';
$metaTitle = ($page['meta']['title'] ?? '') ?: (string) ($page['title'] ?? '');
$metaDesc  = (string) ($page['meta']['description'] ?? '');
$flashes   = ocms_flash_get();
$flavor = [
    'title'       => $metaTitle,
    'description' => $metaDesc,
    'seo'         => [
        'title'       => $metaTitle,
        'description' => $metaDesc,
        'image'       => ($page['meta']['og_image'] ?? '') ?: ($page['cover_image'] ?? ''),
    ],
    'bodyClass'   => 'page-static',
];
include __DIR__ . '/_header.php';
?>
<?php
// Template "Larghezza Piena" (scelto nell'admin): il contenuto porta il proprio titolo
// e la propria impaginazione, quindi niente testata e niente limite di larghezza
$fullWidth = ($page['template'] ?? '') === 'full-width';
?>
<article class="static-page<?= $fullWidth ? ' static-page-full' : '' ?>">
<?php if (!$fullWidth): ?>
    <header class="wrap page-head page-head-narrow">
        <h1 class="display"><?= ocms_escape($page['title'] ?? '') ?></h1>
    </header>
<?php endif; ?>
    <div class="wrap">
        <?= flavor_flashes($flashes) ?>
        <div class="<?= $fullWidth ? 'page-full' : 'prose prose-page' ?>">
            <?= flavor_content((string) ($page['content'] ?? '')) ?>
        </div>
<?php if (($page['views'] ?? 0) > 0 || !empty($page['updated_at'])): ?>
        <p class="page-foot muted">
<?php if (!empty($page['updated_at'])): ?>
            <span>Aggiornata il <time datetime="<?= ocms_escape(flavor_datetime($page['updated_at'])) ?>"><?= ocms_escape(flavor_date($page['updated_at'])) ?></time></span>
<?php endif; ?>
<?php if (($page['views'] ?? 0) > 0): ?>
            <span><?= flavor_icon('eye', 15) ?><?= number_format((int) $page['views'], 0, ',', '.') ?> visite</span>
<?php endif; ?>
        </p>
<?php endif; ?>
    </div>
</article>
<?php include __DIR__ . '/_footer.php'; ?>
