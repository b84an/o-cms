<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ocms_escape($pageTitle ?? 'Admin') ?> — O-CMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ocms_base_url() ?>/admin/assets/css/admin.css">
    <?= $headExtra ?? '' ?>
</head>
<body>

<!-- SIDEBAR -->
<nav class="sidebar">
    <div class="sidebar-logo">
        <h2>O-CMS</h2>
    </div>

    <div class="sidebar-nav">
        <!-- Ricerca rapida -->
        <div style="padding:0 12px 16px;position:relative;">
            <input type="text" id="admin-search" class="form-input" placeholder="Cerca..." style="padding:8px 12px;font-size:0.85rem;background:var(--bg);border-radius:8px;">
            <div id="search-results" style="display:none;position:absolute;left:12px;right:12px;top:100%;background:var(--bg-sidebar);border:1px solid var(--border);border-radius:8px;max-height:300px;overflow-y:auto;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,0.3);"></div>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Generale</div>
            <a href="<?= ocms_base_url() ?>/admin" class="nav-item <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                Dashboard
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Contenuti</div>
            <a href="<?= ocms_base_url() ?>/admin/pages" class="nav-item <?= ($activeMenu ?? '') === 'pages' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                Pagine
            </a>
            <a href="<?= ocms_base_url() ?>/admin/articles" class="nav-item <?= ($activeMenu ?? '') === 'articles' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Articoli
            </a>
            <?php $pendingComments = $app->storage->count('comments', fn($c) => ($c['status'] ?? '') === 'pending'); ?>
            <a href="<?= ocms_base_url() ?>/admin/comments" class="nav-item <?= ($activeMenu ?? '') === 'comments' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Commenti
                <?php if ($pendingComments > 0): ?>
                <span style="background:var(--warning);color:#000;font-size:0.65rem;padding:1px 6px;border-radius:8px;font-weight:700;margin-left:auto;"><?= $pendingComments ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= ocms_base_url() ?>/admin/media" class="nav-item <?= ($activeMenu ?? '') === 'media' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                Media
            </a>
            <a href="<?= ocms_base_url() ?>/admin/menus" class="nav-item <?= ($activeMenu ?? '') === 'menus' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                Menu
            </a>
            <a href="<?= ocms_base_url() ?>/admin/layouts" class="nav-item <?= ($activeMenu ?? '') === 'layouts' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Layout Builder
            </a>
            <a href="<?= ocms_base_url() ?>/admin/forms" class="nav-item <?= ($activeMenu ?? '') === 'forms' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                Form
            </a>
            <a href="<?= ocms_base_url() ?>/admin/lessons" class="nav-item <?= ($activeMenu ?? '') === 'lessons' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Lezioni
            </a>
            <a href="<?= ocms_base_url() ?>/admin/quizzes" class="nav-item <?= ($activeMenu ?? '') === 'quizzes' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Quiz
            </a>
            <a href="<?= ocms_base_url() ?>/admin/galleries" class="nav-item <?= ($activeMenu ?? '') === 'galleries' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                Gallerie
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Sistema</div>
            <a href="<?= ocms_base_url() ?>/admin/analytics" class="nav-item <?= ($activeMenu ?? '') === 'analytics' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Analytics
            </a>
            <a href="<?= ocms_base_url() ?>/admin/users" class="nav-item <?= ($activeMenu ?? '') === 'users' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Utenti
            </a>
            <a href="<?= ocms_base_url() ?>/admin/themes" class="nav-item <?= ($activeMenu ?? '') === 'themes' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10 15 15 0 0 1-4-10 15 15 0 0 1 4-10z"/></svg>
                Temi
            </a>
            <a href="<?= ocms_base_url() ?>/admin/settings" class="nav-item <?= ($activeMenu ?? '') === 'settings' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Impostazioni
            </a>
            <a href="<?= ocms_base_url() ?>/admin/extensions" class="nav-item <?= ($activeMenu ?? '') === 'extensions' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6.5-7z"/><path d="M10 12l2 2 4-4"/></svg>
                Estensioni
            </a>
            <a href="<?= ocms_base_url() ?>/admin/api-docs" class="nav-item <?= ($activeMenu ?? '') === 'api' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17l6-6-6-6"/><path d="M12 19h8"/></svg>
                API
            </a>
            <a href="<?= ocms_base_url() ?>/admin/backup" class="nav-item <?= ($activeMenu ?? '') === 'backup' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Backup
            </a>
            <a href="<?= ocms_base_url() ?>/admin/guide" class="nav-item <?= ($activeMenu ?? '') === 'guide' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Guida
            </a>
        </div>

        <?php
        // Menu estensioni attive
        $extMenuItems = $app->extensions->getAdminMenuItems();
        if (!empty($extMenuItems)):
        ?>
        <div class="nav-section">
            <div class="nav-section-title">Estensioni</div>
            <?php foreach ($extMenuItems as $extItem): ?>
            <a href="<?= ocms_escape($extItem['url']) ?>" class="nav-item <?= ($activeMenu ?? '') === 'ext-' . $extItem['id'] ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6.5-7z"/><path d="M10 12l2 2 4-4"/></svg>
                <?= ocms_escape($extItem['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(mb_substr($app->auth->user()['display_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-details">
                <div class="name"><?= ocms_escape($app->auth->user()['display_name'] ?? '') ?></div>
                <?php $roleLabels = Auth::getRoleLabels(); ?>
                <div class="role"><?= ocms_escape($roleLabels[$app->auth->user()['role'] ?? ''] ?? ucfirst(str_replace('_', ' ', $app->auth->user()['role'] ?? ''))) ?></div>
            </div>
        </div>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/logout" style="margin:0;">
            <?= ocms_csrf_field() ?>
            <button type="submit" class="nav-item logout-link" style="background:none;border:none;width:100%;text-align:left;font:inherit;cursor:pointer;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Esci
            </button>
        </form>
    </div>
</nav>

<!-- MAIN -->
<div class="main">
    <?php foreach (ocms_flash_get() as $flash): ?>
        <div class="flash-message <?= ocms_escape($flash['type']) ?>">
            <?= ocms_escape($flash['message']) ?>
        </div>
    <?php endforeach; ?>

    <?= $content ?? '' ?>
</div>

<script>
(function(){
    const input = document.getElementById('admin-search');
    const box = document.getElementById('search-results');
    const BASE = '<?= ocms_base_url() ?>';
    if (!input) return;
    let timer;

    // Enter → vai alla ricerca avanzata
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = this.value.trim();
            if (q) window.location.href = BASE + '/admin/search/full?q=' + encodeURIComponent(q);
        }
    });

    input.addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { box.style.display='none'; return; }
        timer = setTimeout(async () => {
            const res = await fetch(BASE + '/admin/search?q=' + encodeURIComponent(q));
            const data = await res.json();
            let html = '';
            if (data.results.length) {
                html = data.results.map(r =>
                    '<a href="'+r.url+'" style="display:flex;gap:8px;align-items:center;padding:8px 12px;color:var(--text);font-size:0.85rem;text-decoration:none;border-bottom:1px solid var(--border);">'
                    +'<span style="flex-shrink:0;opacity:0.6;">'+r.icon+'</span>'
                    +'<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+r.title+'</span>'
                    +'<span style="font-size:0.7rem;color:var(--text-muted);flex-shrink:0;">'+r.type_label+'</span>'
                    +'</a>'
                ).join('');
            } else {
                html = '<div style="padding:12px;color:var(--text-muted);font-size:0.85rem;">Nessun risultato</div>';
            }
            // Link ricerca avanzata
            html += '<a href="'+BASE+'/admin/search/full?q='+encodeURIComponent(q)+'" style="display:block;padding:8px 12px;font-size:0.8rem;font-weight:600;color:var(--primary-light);text-align:center;border-top:1px solid var(--border);text-decoration:none;">Ricerca avanzata →</a>';
            box.innerHTML = html;
            box.style.display = 'block';
        }, 200);
    });
    input.addEventListener('blur', () => setTimeout(()=>box.style.display='none', 200));
    input.addEventListener('focus', function() { if (this.value.trim().length >= 2) this.dispatchEvent(new Event('input')); });
})();
</script>
<?= $footerExtra ?? '' ?>
</body>
</html>
