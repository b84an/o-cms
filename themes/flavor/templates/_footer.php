<?php
/**
 * Chiusura comune: footer del sito, script del tema, </body></html>.
 * Il template chiamante può impostare $flavorScripts = ['js/quiz.js', ...] per script extra.
 */
$cfg      = $app->config ?? ocms_config();
$siteName = (string) ($cfg['site_name'] ?? 'O-CMS');
$base     = ocms_base_url();
$seoGlobal = is_array($cfg['seo_global'] ?? null) ? $cfg['seo_global'] : [];
?>
</main>
<?php if (empty($flavor['bare'])): ?>
<footer class="site-footer">
    <div class="wrap footer-inner">
        <div class="footer-brand">
            <a class="brand" href="<?= ocms_escape($base) ?>/"><?= ocms_escape($siteName) ?></a>
<?php if (!empty($cfg['site_description'])): ?>
            <p><?= ocms_escape($cfg['site_description']) ?></p>
<?php endif; ?>
        </div>
        <nav class="footer-nav" aria-label="Collegamenti di servizio">
            <ul>
<?php
$flavorFooterLinks = [];
$flavorFooterMenu = $app->storage->find('menus', 'footer');
if (is_array($flavorFooterMenu['items'] ?? null)) {
    foreach (ocms_filter_menu_items($flavorFooterMenu['items']) as $it) {
        $u = flavor_menu_url((string) ($it['url'] ?? ''));
        if ($u !== null && !empty($it['label'])) $flavorFooterLinks[] = [$it['label'], $u, ($it['target'] ?? '') === '_blank'];
    }
}
if (!$flavorFooterLinks) {
    $flavorFooterLinks[] = ['Blog', $base . '/blog', false];
    foreach (['contatto' => 'Contatti', 'privacy' => 'Privacy'] as $slug => $lbl) {
        $pg = $app->storage->find('pages', $slug);
        if ($pg && ($pg['status'] ?? '') === 'published') $flavorFooterLinks[] = [$lbl, $base . '/' . $slug, false];
    }
    $flavorFooterLinks[] = ['Cerca', $base . '/search', false];
}
foreach ($flavorFooterLinks as [$lbl, $u, $blank]): ?>
                <li><a href="<?= ocms_escape($u) ?>"<?= $blank ? ' target="_blank" rel="noopener"' : '' ?>><?= ocms_escape($lbl) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
        <p class="footer-copy">&copy; <?= date('Y') ?> <?= ocms_escape($siteName) ?></p>
    </div>
</footer>
<?php endif; ?>
<script src="<?= ocms_escape(flavor_asset('js/theme.js')) ?>" defer></script>
<?php foreach (($flavorScripts ?? []) as $flavorScript): ?>
<script src="<?= ocms_escape(flavor_asset($flavorScript)) ?>" defer></script>
<?php endforeach; ?>
<?php foreach ((array) ($flavor['scripts'] ?? []) as $flavorExtra): if (is_string($flavorExtra) && $flavorExtra !== ''): ?>
<script src="<?= ocms_escape($flavorExtra) ?>" defer></script>
<?php endif; endforeach; ?>
<?php if (!empty($seoGlobal['body_scripts']) && is_string($seoGlobal['body_scripts'])): ?>
<?= $seoGlobal['body_scripts'] . "\n" ?>
<?php endif; ?>
</body>
</html>
