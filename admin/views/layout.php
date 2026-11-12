<?php
// ─── Dati per il guscio dell'admin ───
$_base = ocms_base_url();
$_user = $app->auth->user() ?? [];
$_role = $_user['role'] ?? '';
$_active = $activeMenu ?? '';
$_assetVer = function (string $rel): string {
    $t = @filemtime(ocms_base_path() . $rel);
    return $t ? '?v=' . $t : '';
};
$roleLabels = Auth::getRoleLabels();
$_roleLabel = $roleLabels[$_role] ?? ucfirst(str_replace('_', ' ', $_role));
$_displayName = $_user['display_name'] ?? '';

$pendingComments = $app->storage->count('comments', fn($c) => ($c['status'] ?? '') === 'pending');

// Icone (stroke, 24x24) — stesso disegno della sidebar precedente
$_ico = [
    'dashboard'   => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
    'pages'       => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/>',
    'articles'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
    'comments'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'media'       => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
    'menus'       => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>',
    'layouts'     => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    'forms'       => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>',
    'lessons'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    'quizzes'     => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    'galleries'   => '<rect x="2" y="6" width="16" height="14" rx="2"/><path d="M6 6V4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-2"/><circle cx="7" cy="11" r="1.5"/><path d="m18 17-4-4-8 7"/>',
    'fogliofirme' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/><path d="M15 5 19 9"/>',
    'analytics'   => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
    'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'themes'      => '<circle cx="12" cy="12" r="10"/><path d="M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10 15 15 0 0 1-4-10 15 15 0 0 1 4-10z"/>',
    'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    'extensions'  => '<path d="M13.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6.5-7z"/><path d="M10 12l2 2 4-4"/>',
    'api'         => '<path d="M4 17l6-6-6-6"/><path d="M12 19h8"/>',
    'backup'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    'updates'     => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><polyline points="21 3 21 8 16 8"/>',
    'installations' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
    'guide'       => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
];
$_svg = function (string $paths): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};

// Gruppi della sidebar: [chiave => [titolo, [ [menu, url, etichetta, icona, extra] ... ]]]
$_groups = [
    'contenuti' => ['Contenuti', [
        ['pages', '/admin/pages', 'Pagine', 'pages'],
        ['articles', '/admin/articles', 'Articoli', 'articles'],
        ['comments', '/admin/comments', 'Commenti', 'comments', $pendingComments > 0 ? (string)$pendingComments : ''],
        ['media', '/admin/media', 'Media', 'media'],
        ['galleries', '/admin/galleries', 'Gallerie', 'galleries'],
    ]],
    'didattica' => ['Didattica', [
        ['lessons', '/admin/lessons', 'Lezioni', 'lessons'],
        ['quizzes', '/admin/quizzes', 'Quiz', 'quizzes'],
        ['fogliofirme', '/admin/fogliofirme', 'FoglioFirme', 'fogliofirme'],
    ]],
    'struttura' => ['Struttura', [
        ['menus', '/admin/menus', 'Menu', 'menus'],
        ['layouts', '/admin/layouts', 'Layout Builder', 'layouts'],
        ['forms', '/admin/forms', 'Form', 'forms'],
    ]],
];
$_system = [
    ['analytics', '/admin/analytics', 'Analytics', 'analytics'],
    ['users', '/admin/users', 'Utenti', 'users'],
    ['themes', '/admin/themes', 'Temi', 'themes'],
    ['settings', '/admin/settings', 'Impostazioni', 'settings'],
    ['extensions', '/admin/extensions', 'Estensioni', 'extensions'],
    ['api', '/admin/api-docs', 'API', 'api'],
    ['backup', '/admin/backup', 'Backup', 'backup'],
];
if ($_role === 'super_administrator') {
    $_system[] = ['updates', '/admin/updates', 'Aggiornamenti', 'updates'];
    $_system[] = ['installations', '/admin/installations', 'Installazioni', 'installations'];
}
$_system[] = ['guide', '/admin/guide', 'Guida', 'guide'];
$_groups['sistema'] = ['Sistema', $_system];

// Menu estensioni attive
$extMenuItems = $app->extensions->getAdminMenuItems();

$_navItem = function (array $it) use ($_base, $_active, $_ico, $_svg): string {
    [$key, $url, $label, $icon] = $it;
    $badge = $it[4] ?? '';
    $isActive = $_active === $key;
    $h = '<a href="' . $_base . $url . '" class="nav-item' . ($isActive ? ' active' : '') . '"'
       . ($isActive ? ' aria-current="page"' : '') . ' data-nav-label="' . ocms_escape($label) . '">'
       . $_svg($_ico[$icon] ?? $_ico['extensions'])
       . '<span class="nav-label">' . ocms_escape($label) . '</span>';
    if ($badge !== '') {
        $h .= '<span class="nav-count" aria-label="' . ocms_escape($badge) . ' in attesa">' . ocms_escape($badge) . '</span>';
    }
    return $h . '</a>';
};
$_chevron = '<svg class="nav-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>';
?>
<!DOCTYPE html>
<html lang="it" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= ocms_escape($pageTitle ?? 'Admin') ?> — O-CMS</title>
    <script>
    (function () {
        var r = document.documentElement;
        r.classList.remove('no-js'); r.classList.add('js');
        try { if (localStorage.getItem('ocms-admin-sidebar') === 'collapsed') r.setAttribute('data-sidebar', 'collapsed'); } catch (e) {}
    })();
    </script>
    <script src="<?= $_base ?>/assets/ocms/ocms-theme.js<?= $_assetVer('/assets/ocms/ocms-theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= $_base ?>/assets/ocms/tokens.css<?= $_assetVer('/assets/ocms/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $_base ?>/admin/assets/css/admin.css<?= $_assetVer('/admin/assets/css/admin.css') ?>">
    <script src="<?= $_base ?>/admin/assets/js/admin.js<?= $_assetVer('/admin/assets/js/admin.js') ?>" defer></script>
    <?= $headExtra ?? '' ?>
</head>
<body class="admin-body" data-base-url="<?= ocms_escape($_base) ?>" data-user="<?= ocms_escape(substr(hash('sha256', (string)($app->auth->user()['username'] ?? '')), 0, 12)) ?>">
<div class="ocms-aurora" aria-hidden="true"></div>
<a class="skip-link" href="#main-content">Vai al contenuto</a>

<!-- SIDEBAR -->
<aside class="sidebar ocms-glass" id="admin-sidebar" aria-label="Menu di amministrazione">
    <div class="sidebar-logo">
        <a href="<?= $_base ?>/admin" class="brand" aria-label="O-CMS, vai alla dashboard">
            <span class="brand-mark" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="8"/><path d="M4 15c3 2 6.5 2 8 0s5-2 8 0"/></svg>
            </span>
            <span class="brand-name">O-CMS</span>
        </a>
        <button type="button" class="sidebar-collapse" data-sidebar-toggle aria-pressed="false" aria-label="Riduci la barra laterale" title="Riduci la barra laterale">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/><path d="m15 10-2 2 2 2"/></svg>
        </button>
        <button type="button" class="icon-btn sidebar-close" data-menu-close aria-label="Chiudi il menu">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="Sezioni">
        <div class="nav-section">
            <?= $_navItem(['dashboard', '/admin', 'Dashboard', 'dashboard']) ?>
        </div>

        <?php foreach ($_groups as $gKey => [$gTitle, $gItems]):
            $gHasActive = in_array($_active, array_column($gItems, 0), true);
        ?>
        <details class="nav-section nav-group" data-group="<?= $gKey ?>" open<?= $gHasActive ? ' data-has-active' : '' ?>>
            <summary class="nav-section-title"><span class="nav-label"><?= ocms_escape($gTitle) ?></span><?= $_chevron ?></summary>
            <div class="nav-group-items">
                <?php foreach ($gItems as $it) echo $_navItem($it); ?>
            </div>
        </details>
        <?php endforeach; ?>

        <?php if (!empty($extMenuItems)):
            $gHasActive = false;
            foreach ($extMenuItems as $extItem) { if ($_active === 'ext-' . $extItem['id']) $gHasActive = true; }
        ?>
        <details class="nav-section nav-group" data-group="estensioni" open<?= $gHasActive ? ' data-has-active' : '' ?>>
            <summary class="nav-section-title"><span class="nav-label">Estensioni</span><?= $_chevron ?></summary>
            <div class="nav-group-items">
            <?php foreach ($extMenuItems as $extItem):
                $extActive = $_active === 'ext-' . $extItem['id']; ?>
                <a href="<?= ocms_escape($extItem['url']) ?>" class="nav-item<?= $extActive ? ' active' : '' ?>"<?= $extActive ? ' aria-current="page"' : '' ?> data-nav-label="<?= ocms_escape($extItem['label']) ?>">
                    <?= $_svg($_ico['extensions']) ?>
                    <span class="nav-label"><?= ocms_escape($extItem['label']) ?></span>
                </a>
            <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= $_base ?>/" class="nav-item" target="_blank" rel="noopener" data-nav-label="Vai al sito">
            <?= $_svg('<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>') ?>
            <span class="nav-label">Vai al sito<?= defined('OCMS_VERSION') ? ' <small class="text-muted">· v' . ocms_escape(OCMS_VERSION) . '</small>' : '' ?></span>
        </a>
    </div>
</aside>
<script>
// Stato dei gruppi applicato subito (prima del disegno), il resto in admin.js
(function () {
    var st = {};
    try { st = JSON.parse(localStorage.getItem('ocms-admin-nav') || '{}') || {}; } catch (e) {}
    document.querySelectorAll('#admin-sidebar details[data-group]').forEach(function (d) {
        var k = d.getAttribute('data-group');
        if (st[k] === false && !d.hasAttribute('data-has-active')) d.open = false;
    });
})();
</script>
<div class="sidebar-backdrop" data-menu-close aria-hidden="true"></div>

<!-- MAIN -->
<div class="main" id="admin-main">
    <header class="topbar ocms-glass">
        <button type="button" class="icon-btn topbar-menu" data-menu-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Apri il menu">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
        </button>

        <form class="search-form" role="search" method="get" action="<?= $_base ?>/admin/search/full">
            <label class="visually-hidden" for="admin-search">Cerca nei contenuti</label>
            <input type="search" id="admin-search" name="q" class="form-input" placeholder="Cerca nei contenuti…">
        </form>
        <button type="button" class="search-trigger" data-cmdk-open aria-haspopup="dialog" aria-controls="cmdk">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="label">Cerca o vai a…</span>
            <kbd data-shortcut-label>Ctrl K</kbd>
        </button>

        <div class="topbar-actions">
            <a class="icon-btn" href="<?= $_base ?>/" target="_blank" rel="noopener" aria-label="Apri il sito in una nuova scheda" title="Vai al sito">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </a>
            <button type="button" class="icon-btn" data-ocms-theme-toggle aria-label="Cambia tema">
                <svg class="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg class="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
            <div class="user-chip" title="<?= ocms_escape($_displayName) ?> — <?= ocms_escape($_roleLabel) ?>">
                <div class="user-avatar" aria-hidden="true"><?= ocms_escape(mb_strtoupper(mb_substr($_displayName !== '' ? $_displayName : 'A', 0, 1))) ?></div>
                <div class="user-details">
                    <div class="name"><?= ocms_escape($_displayName) ?></div>
                    <div class="role"><?= ocms_escape($_roleLabel) ?></div>
                </div>
            </div>
            <form method="POST" action="<?= $_base ?>/admin/logout" data-no-loading data-ocms-logout>
                <?= ocms_csrf_field() ?>
                <button type="submit" class="icon-btn danger" aria-label="Esci" title="Esci">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </button>
            </form>
        </div>
    </header>

    <main class="main-content" id="main-content" tabindex="-1">
        <?= $content ?? '' ?>
    </main>
</div>

<!-- Notifiche (messaggi flash) -->
<div class="toast-region" id="toast-region" aria-live="polite">
    <?php foreach (ocms_flash_get() as $flash):
        $_ft = in_array($flash['type'] ?? '', ['success', 'error', 'warning', 'info'], true) ? $flash['type'] : 'info';
        $_isErr = $_ft === 'error';
    ?>
    <div class="toast toast-<?= $_ft ?>" role="<?= $_isErr ? 'alert' : 'status' ?>" data-toast data-type="<?= $_ft ?>"<?= $_isErr ? '' : ' data-autodismiss="6000"' ?>>
        <span class="toast-icon" aria-hidden="true">
            <?php if ($_ft === 'success'): ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <?php elseif ($_isErr): ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg>
            <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 8h.01M12 11v6"/></svg>
            <?php endif; ?>
        </span>
        <p class="toast-msg"><?= ocms_escape($flash['message'] ?? '') ?></p>
        <button type="button" class="toast-close" data-toast-close aria-label="Chiudi notifica">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
        <?php if (!$_isErr): ?><span class="toast-timer" aria-hidden="true"></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Palette comandi (Ctrl/Cmd+K): popolata da admin.js -->
<dialog class="cmdk ocms-glass" id="cmdk" aria-label="Cerca e vai a">
    <div class="cmdk-head">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="text" class="cmdk-input" id="cmdk-input" role="combobox" aria-expanded="true" aria-controls="cmdk-list" aria-autocomplete="list" autocomplete="off" spellcheck="false" placeholder="Cerca sezioni, azioni, contenuti…" aria-label="Cerca sezioni, azioni e contenuti">
        <kbd>Esc</kbd>
    </div>
    <ul class="cmdk-list" id="cmdk-list" role="listbox" aria-label="Risultati"></ul>
    <div class="cmdk-foot" aria-hidden="true">
        <span><kbd>↑</kbd><kbd>↓</kbd> scegli</span>
        <span><kbd>Invio</kbd> apri</span>
        <span><kbd>Esc</kbd> chiudi</span>
    </div>
</dialog>

<?= $footerExtra ?? '' ?>
</body>
</html>
