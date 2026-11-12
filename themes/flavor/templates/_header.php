<?php
/**
 * Apertura comune di ogni pagina: <head>, aurora, header di vetro con menu.
 *
 * Il template chiamante imposta $flavor prima dell'include:
 *   title       titolo della pagina (senza nome del sito); null = solo nome del sito
 *   fullTitle   titolo completo già pronto (sostituisce title + nome del sito)
 *   description meta description
 *   seo         array per flavor_seo_meta() oppure false per non emettere Open Graph
 *   robots      es. 'noindex,nofollow'
 *   bodyClass   classi aggiuntive su <body>
 *   bare        true = niente header del sito (schermate a tutto schermo)
 *   minimal     true = header con il solo nome del sito e il tema (es. durante un quiz)
 *   progress    true = barra di avanzamento della lettura
 *   styles      URL assoluti di fogli di stile in più (per le mini-app che usano il tema)
 *   scripts     URL assoluti di script in più, caricati con defer a fine pagina
 */
require_once __DIR__ . '/_bootstrap.php';

$flavor = (isset($flavor) && is_array($flavor)) ? $flavor : [];
$cfg      = $app->config ?? ocms_config();
$siteName = (string) ($cfg['site_name'] ?? 'O-CMS');
$lang     = (string) ($cfg['language'] ?? 'it');
$pageTitle = !empty($flavor['fullTitle']) ? (string) $flavor['fullTitle']
    : (isset($flavor['title']) && $flavor['title'] !== '' ? $flavor['title'] . ' — ' . $siteName : $siteName);
$metaDesc  = trim((string) ($flavor['description'] ?? ($cfg['site_description'] ?? '')));
$base      = ocms_base_url();
$seoGlobal = is_array($cfg['seo_global'] ?? null) ? $cfg['seo_global'] : [];

// Voce di menu attiva: confronto sul percorso richiesto senza query string
$flavorReqPath = rtrim((string) strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?'), '/') ?: '/';
$flavorIsCurrent = static function (?string $url) use ($flavorReqPath): bool {
    if ($url === null || preg_match('#^(https?:|mailto:|tel:|\#)#i', $url)) return false;
    $p = rtrim((string) strtok($url, '?'), '/') ?: '/';
    return $p === $flavorReqPath && !str_contains($url, '?');
};
?><!DOCTYPE html>
<html lang="<?= ocms_escape($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ocms_escape($pageTitle) ?></title>
<?php if ($metaDesc !== ''): ?>
    <meta name="description" content="<?= ocms_escape(ocms_truncate(flavor_plain($metaDesc), 300)) ?>">
<?php endif; ?>
<?php if (!empty($flavor['robots'])): ?>
    <meta name="robots" content="<?= ocms_escape($flavor['robots']) ?>">
<?php endif; ?>
    <meta name="theme-color" content="#0b1b3a" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#eef4ff" media="(prefers-color-scheme: light)">
<?php if (($flavor['seo'] ?? false) !== false) { flavor_seo_meta(is_array($flavor['seo']) ? $flavor['seo'] : []); } ?>
    <script>document.documentElement.classList.add('js');</script>
    <script src="<?= ocms_escape(flavor_ocms_asset('ocms-theme.js')) ?>"></script>
    <link rel="preload" href="<?= ocms_escape($base) ?>/assets/ocms/fonts/atkinson-next-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= ocms_escape($base) ?>/assets/ocms/fonts/bricolage-grotesque-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= ocms_escape($base) ?>/assets/ocms/tokens.css">
    <link rel="stylesheet" href="<?= ocms_escape(flavor_asset('css/style.css')) ?>">
<?php foreach ((array) ($flavor['styles'] ?? []) as $flavorStyle): if (is_string($flavorStyle) && $flavorStyle !== ''): ?>
    <link rel="stylesheet" href="<?= ocms_escape($flavorStyle) ?>">
<?php endif; endforeach; ?>
<?php if (!empty($seoGlobal['head_scripts']) && is_string($seoGlobal['head_scripts'])): ?>
    <?= $seoGlobal['head_scripts'] . "\n" ?>
<?php endif; ?>
</head>
<body class="<?= ocms_escape(trim('flavor ' . ($flavor['bodyClass'] ?? ''))) ?>">
<div class="ocms-aurora" aria-hidden="true"></div>
<a class="skip-link" href="#main">Vai al contenuto</a>
<?php if (!empty($flavor['progress'])): ?>
<div class="read-progress" aria-hidden="true"></div>
<?php endif; ?>
<?php if (empty($flavor['bare'])): ?>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?= ocms_escape($base) ?>/"><?= ocms_escape($siteName) ?></a>

<?php if (empty($flavor['minimal'])): ?>
        <div class="nav-panel" id="site-nav" data-nav-panel>
            <nav class="site-nav" aria-label="Menu principale">
                <ul class="nav-list">
<?php
$flavorMenu = $app->storage->find('menus', 'main');
$flavorItems = is_array($flavorMenu['items'] ?? null) ? ocms_filter_menu_items($flavorMenu['items']) : [];
if (!$flavorItems) {
    // Nessun menu configurato: voci essenziali
    $flavorItems = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Blog', 'url' => '/blog'],
        ['label' => 'Lezioni', 'url' => '/lezioni'],
        ['label' => 'Quiz', 'url' => '/quiz'],
    ];
}
foreach ($flavorItems as $i => $item):
    $label = (string) ($item['label'] ?? '');
    if ($label === '') continue;
    $url = flavor_menu_url((string) ($item['url'] ?? ''));
    $blank = ($item['target'] ?? '_self') === '_blank';
    $children = is_array($item['children'] ?? null) ? $item['children'] : [];
    $subId = 'sub-' . $i;
    if ($children):
        $childCurrent = false;
        foreach ($children as $c) { if ($flavorIsCurrent(flavor_menu_url((string) ($c['url'] ?? '')))) $childCurrent = true; }
?>
                    <li class="nav-item has-sub<?= $childCurrent ? ' is-current' : '' ?>">
<?php if ($url !== null): ?>
                        <a class="nav-link" href="<?= ocms_escape($url) ?>"<?= $blank ? ' target="_blank" rel="noopener"' : '' ?><?= $flavorIsCurrent($url) ? ' aria-current="page"' : '' ?>><?= ocms_escape($label) ?></a>
                        <button class="nav-sub-toggle" type="button" aria-expanded="false" aria-controls="<?= $subId ?>" aria-label="Sottomenu <?= ocms_escape($label) ?>"><?= flavor_icon('chevron', 16) ?></button>
<?php else: ?>
                        <button class="nav-link nav-sub-toggle is-label" type="button" aria-expanded="false" aria-controls="<?= $subId ?>"><?= ocms_escape($label) ?><?= flavor_icon('chevron', 16) ?></button>
<?php endif; ?>
                        <ul class="nav-sub" id="<?= $subId ?>">
<?php foreach ($children as $c):
        $cl = (string) ($c['label'] ?? '');
        $cu = flavor_menu_url((string) ($c['url'] ?? ''));
        if ($cl === '' || $cu === null) continue;
        $cb = ($c['target'] ?? '_self') === '_blank'; ?>
                            <li><a href="<?= ocms_escape($cu) ?>"<?= $cb ? ' target="_blank" rel="noopener"' : '' ?><?= $flavorIsCurrent($cu) ? ' aria-current="page"' : '' ?>><?= ocms_escape($cl) ?><?= $cb ? '<span class="sr-only"> (si apre in una nuova scheda)</span>' . flavor_icon('external', 14) : '' ?></a></li>
<?php endforeach; ?>
                        </ul>
                    </li>
<?php elseif ($url !== null): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= ocms_escape($url) ?>"<?= $blank ? ' target="_blank" rel="noopener"' : '' ?><?= $flavorIsCurrent($url) ? ' aria-current="page"' : '' ?>><?= ocms_escape($label) ?></a></li>
<?php endif; endforeach; ?>
                </ul>
            </nav>
            <form class="header-search" action="<?= ocms_escape($base) ?>/search" method="get" role="search">
                <label class="sr-only" for="site-search-q">Cerca nel sito</label>
                <input id="site-search-q" type="search" name="q" placeholder="Cerca" autocomplete="off" maxlength="200">
                <button type="submit" aria-label="Avvia la ricerca"><?= flavor_icon('search', 17) ?></button>
            </form>
        </div>
<?php endif; ?>

        <button class="icon-btn theme-toggle" type="button" data-ocms-theme-toggle aria-label="Cambia tema">
            <span class="when-dark"><?= flavor_icon('sun', 18) ?></span><span class="when-light"><?= flavor_icon('moon', 18) ?></span>
        </button>
<?php if (empty($flavor['minimal'])): ?>
        <button class="icon-btn nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
            <span class="when-closed"><?= flavor_icon('menu', 20) ?></span><span class="when-open"><?= flavor_icon('close', 20) ?></span>
            <span class="sr-only">Menu</span>
        </button>
<?php endif; ?>
    </div>
</header>
<?php endif; ?>
<main id="main" class="site-main" tabindex="-1">
