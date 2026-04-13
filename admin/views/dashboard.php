<?php
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

// Conteggi
$mediaCount = $app->storage->count('media');
$formsCount = $app->storage->count('forms');
$extCount = count($app->extensions->getAll());
$extActive = count(array_filter($app->extensions->getAll(), fn($e) => $e['enabled'] ?? false));
$pendingComments = $app->storage->count('comments', fn($c) => ($c['status'] ?? '') === 'pending');

// Ultimi articoli
$recentArticles = array_slice($app->storage->findAll('articles', null, 'updated_at', 'desc'), 0, 5);
$recentPages = array_slice($app->storage->findAll('pages', null, 'updated_at', 'desc'), 0, 5);

// Analytics mini (ultimi 7 giorni)
$analytics7 = ocms_analytics_range(7);

ob_start();
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <span style="color:var(--text-muted);font-size:0.875rem;"><?= date('d/m/Y H:i') ?></span>
</div>

<div class="welcome-card">
    <h2>Ciao, <?= ocms_escape($app->auth->user()['display_name'] ?? 'Admin') ?>!</h2>
    <p>Benvenuto nel pannello di amministrazione di O-CMS.</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <div class="stat-value"><?= $pages_count ?></div>
        <div class="stat-label">Pagine</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></div>
        <div class="stat-value"><?= $articles_count ?></div>
        <div class="stat-label">Articoli</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div class="stat-value"><?= $users_count ?></div>
        <div class="stat-label">Utenti</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></div>
        <div class="stat-value"><?= $mediaCount ?></div>
        <div class="stat-label">Media</div>
    </div>
</div>

<!-- Visite 7 giorni + Commenti in attesa -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:32px;">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:1rem;font-weight:700;">Visite ultimi 7 giorni</h3>
            <a href="<?= ocms_base_url() ?>/admin/analytics" style="font-size:0.8rem;">Vedi tutto &rarr;</a>
        </div>
        <?php
        $maxV = max(array_column($analytics7['days'], 'views') ?: [0]) ?: 1;
        ?>
        <div style="display:flex;align-items:flex-end;gap:6px;height:80px;">
            <?php foreach ($analytics7['days'] as $d): ?>
            <?php $h = max($d['views'] / $maxV * 100, 4); ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
                <span style="font-size:0.7rem;color:var(--text-muted);"><?= $d['views'] ?></span>
                <div style="width:100%;height:<?= $h ?>%;background:linear-gradient(to top,var(--primary),var(--primary-light));border-radius:4px 4px 0 0;min-height:4px;"></div>
                <span style="font-size:0.65rem;color:var(--text-muted);"><?= date('D', strtotime($d['date'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:12px;font-size:0.85rem;color:var(--text-muted);">
            <strong style="color:var(--text);"><?= number_format($analytics7['total']) ?></strong> visite totali
        </div>
    </div>

    <div class="card">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Commenti</h3>
        <?php if ($pendingComments > 0): ?>
        <div style="text-align:center;padding:16px 0;">
            <div style="font-size:2.5rem;font-weight:800;color:var(--warning);margin-bottom:4px;"><?= $pendingComments ?></div>
            <div style="font-size:0.85rem;color:var(--text-muted);">in attesa di approvazione</div>
            <a href="<?= ocms_base_url() ?>/admin/comments" class="btn btn-sm" style="margin-top:12px;background:rgba(245,158,11,0.1);color:var(--warning);border:1px solid rgba(245,158,11,0.3);">Modera</a>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:16px 0;color:var(--text-muted);">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;opacity:.3;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Nessun commento in attesa
        </div>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
    <!-- Azioni rapide -->
    <div>
        <h3 class="section-title">Azioni Rapide</h3>
        <div class="quick-actions" style="grid-template-columns:1fr 1fr;">
            <a href="<?= ocms_base_url() ?>/admin/pages/new" class="quick-action"><span class="icon">+</span> Nuova Pagina</a>
            <a href="<?= ocms_base_url() ?>/admin/articles/new" class="quick-action"><span class="icon">+</span> Nuovo Articolo</a>
            <a href="<?= ocms_base_url() ?>/admin/media" class="quick-action"><span class="icon">+</span> Carica Media</a>
            <a href="<?= ocms_base_url() ?>/admin/forms/new" class="quick-action"><span class="icon">+</span> Nuovo Form</a>
            <a href="<?= ocms_base_url() ?>/admin/backup" class="quick-action"><span class="icon">&#128190;</span> Backup</a>
            <a href="<?= ocms_base_url() ?>/admin/extensions/wizard" class="quick-action"><span class="icon">&#129513;</span> Crea Estensione</a>
        </div>
    </div>

    <!-- Info sistema -->
    <div>
        <h3 class="section-title">Stato Sistema</h3>
        <div class="card">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:0.85rem;">
                <div><span style="color:var(--text-muted);">Estensioni</span><br><strong><?= $extActive ?>/<?= $extCount ?> attive</strong></div>
                <div><span style="color:var(--text-muted);">Form</span><br><strong><?= $formsCount ?> moduli</strong></div>
                <div><span style="color:var(--text-muted);">PHP</span><br><strong><?= phpversion() ?></strong></div>
                <div><span style="color:var(--text-muted);">Tema</span><br><strong><?= ucfirst($app->config['theme'] ?? 'flavor') ?></strong></div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($recentArticles) || !empty($recentPages)): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <?php if (!empty($recentPages)): ?>
    <div>
        <h3 class="section-title">Pagine Recenti</h3>
        <div class="card">
            <?php foreach ($recentPages as $p): ?>
            <a href="<?= ocms_base_url() ?>/admin/pages/edit/<?= ocms_escape($p['slug']) ?>"
               style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);color:var(--text);font-size:0.875rem;text-decoration:none;">
                <span style="font-weight:500;"><?= ocms_escape($p['title']) ?></span>
                <span class="badge <?= $p['status']==='published'?'badge-published':'badge-draft' ?>" style="font-size:0.7rem;"><?= $p['status']==='published'?'Pub':'Bozza' ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($recentArticles)): ?>
    <div>
        <h3 class="section-title">Articoli Recenti</h3>
        <div class="card">
            <?php foreach ($recentArticles as $a): ?>
            <a href="<?= ocms_base_url() ?>/admin/articles/edit/<?= ocms_escape($a['slug']) ?>"
               style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);color:var(--text);font-size:0.875rem;text-decoration:none;">
                <span style="font-weight:500;"><?= ocms_escape($a['title']) ?></span>
                <span class="badge <?= $a['status']==='published'?'badge-published':'badge-draft' ?>" style="font-size:0.7rem;"><?= $a['status']==='published'?'Pub':'Bozza' ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
