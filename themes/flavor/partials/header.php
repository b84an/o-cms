    <header class="site-header">
        <div class="container">
            <a href="<?= ocms_base_url() ?>/" class="site-logo">
                <?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?>
            </a>
            <div style="display:flex;align-items:center;gap:24px;">
                <nav class="site-nav">
                    <?php $menu = $app->storage->find('menus', 'main');
                    if ($menu && !empty($menu['items'])):
                        foreach (ocms_filter_menu_items($menu['items']) as $item): ?>
                        <a href="<?= ocms_base_url() . ocms_escape($item['url']) ?>" class="nav-link"
                           <?= ($item['target'] ?? '_self') === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>>
                            <?= ocms_escape($item['label']) ?>
                        </a>
                    <?php endforeach; endif; ?>
                </nav>
                <!-- Theme Toggle -->
                <button onclick="toggleTheme()" aria-label="Cambia tema" style="background:none;border:1px solid var(--border);border-radius:8px;padding:7px 9px;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;transition:border-color .2s;">
                    <svg id="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg id="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <!-- Ricerca -->
                <form action="<?= ocms_base_url() ?>/search" method="GET" class="site-search-form">
                    <input type="text" name="q" placeholder="Cerca..." class="site-search-input" value="<?= ocms_escape($_GET['q'] ?? '') ?>">
                    <button type="submit" class="site-search-btn" aria-label="Cerca">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>
