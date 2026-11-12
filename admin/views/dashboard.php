<?php
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

// Conteggi
$mediaCount = $app->storage->count('media');
$formsCount = $app->storage->count('forms');
$extCount = count($app->extensions->getAll());
$extActive = count(array_filter($app->extensions->getAll(), fn($e) => $e['enabled'] ?? false));
$pendingComments = $app->storage->count('comments', fn($c) => ($c['status'] ?? '') === 'pending');

// Articoli: una sola lettura per recenti, bozze e programmati
$allArticles = $app->storage->findAll('articles', null, 'updated_at', 'desc');
$recentArticles = array_slice($allArticles, 0, 6);
$draftArticles = count(array_filter($allArticles, fn($a) => ($a['status'] ?? '') !== 'published'));
$scheduledArticles = count(array_filter($allArticles, fn($a) => ($a['status'] ?? '') === 'published' && ocms_is_future($a['publish_at'] ?? null)));
$recentPages = array_slice($app->storage->findAll('pages', null, 'updated_at', 'desc'), 0, 5);

// Analytics mini (ultimi 7 giorni)
$analytics7 = ocms_analytics_range(7);

$_b = ocms_base_url();
$_name = $app->auth->user()['display_name'] ?? 'Admin';
$_h = (int)date('G');
$_greet = $_h < 5 ? 'Buonanotte' : ($_h < 13 ? 'Buongiorno' : ($_h < 18 ? 'Buon pomeriggio' : 'Buonasera'));
$_days = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
$_months = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
$_daysLong = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
$_today = $_daysLong[(int)date('w')] . ' ' . date('j') . ' ' . $_months[(int)date('n') - 1] . ' ' . date('Y');

// Stato di un contenuto come badge
$_statusBadge = function (array $item, bool $isArticle): string {
    $pub = ($item['status'] ?? '') === 'published';
    if ($isArticle && $pub && ocms_is_future($item['publish_at'] ?? null)) {
        return '<span class="badge badge-scheduled">Programmato</span>';
    }
    return $pub ? '<span class="badge badge-published">' . ($isArticle ? 'Pubblicato' : 'Pubblicata') . '</span>'
                : '<span class="badge badge-draft">Bozza</span>';
};
$_date = function ($d): string {
    if (!is_string($d) || $d === '') return '';
    try { return ocms_format_date($d, 'd/m/Y'); } catch (\Throwable $e) { return ''; }
};
$_ico = fn(string $p) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';

ob_start();
?>

<div class="dash-hero">
    <div>
        <h1><?= $_greet ?>, <?= ocms_escape($_name) ?></h1>
        <p><?= ocms_escape(ucfirst($_today)) ?></p>
    </div>
    <div class="btn-row">
        <a href="<?= $_b ?>/admin/pages/new" class="btn btn-secondary">Nuova pagina</a>
        <a href="<?= $_b ?>/admin/articles/new" class="btn btn-primary">
            <?= $_ico('<path d="M12 5v14M5 12h14"/>') ?>
            Nuovo articolo
        </a>
    </div>
</div>

<!-- Contatori: un solo pannello diviso, non quattro card uguali -->
<section class="panel dash-counters" aria-label="Contatori">
    <a class="dash-counter" href="<?= $_b ?>/admin/articles">
        <span class="dash-counter-label"><?= $_ico('<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>') ?>Articoli</span>
        <span class="dash-counter-value"><?= (int)$articles_count ?></span>
        <span class="dash-counter-sub"><?= $draftArticles ?> in bozza<?= $scheduledArticles ? ' · ' . $scheduledArticles . ' programmati' : '' ?></span>
    </a>
    <a class="dash-counter" href="<?= $_b ?>/admin/pages">
        <span class="dash-counter-label"><?= $_ico('<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/>') ?>Pagine</span>
        <span class="dash-counter-value"><?= (int)$pages_count ?></span>
        <span class="dash-counter-sub">statiche del sito</span>
    </a>
    <a class="dash-counter" href="<?= $_b ?>/admin/media">
        <span class="dash-counter-label"><?= $_ico('<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>') ?>Media</span>
        <span class="dash-counter-value"><?= (int)$mediaCount ?></span>
        <span class="dash-counter-sub">file caricati</span>
    </a>
    <a class="dash-counter" href="<?= $_b ?>/admin/users">
        <span class="dash-counter-label"><?= $_ico('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>') ?>Utenti</span>
        <span class="dash-counter-value"><?= (int)$users_count ?></span>
        <span class="dash-counter-sub">con accesso all'admin o al sito</span>
    </a>
</section>

<div class="dash-grid">
    <div class="dash-col">
        <section class="card" aria-labelledby="dash-visits">
            <div class="card-head">
                <h2 id="dash-visits">Visite negli ultimi 7 giorni</h2>
                <a href="<?= $_b ?>/admin/analytics">Statistiche</a>
            </div>
            <?php $maxV = max(array_column($analytics7['days'], 'views') ?: [0]) ?: 1; ?>
            <div class="visits-chart" role="img" aria-label="<?= ocms_escape(implode(', ', array_map(fn($d) => $_days[(int)date('w', strtotime($d['date']))] . ' ' . $d['views'], $analytics7['days']))) ?>">
                <?php foreach ($analytics7['days'] as $d):
                    $h = max($d['views'] / $maxV * 100, 3);
                    $isToday = $d['date'] === date('Y-m-d');
                ?>
                <div class="visits-bar<?= $isToday ? ' is-today' : '' ?>" title="<?= ocms_escape(date('d/m', strtotime($d['date']))) ?>: <?= (int)$d['views'] ?> visite">
                    <span class="visits-bar-value"><?= (int)$d['views'] ?></span>
                    <div class="visits-bar-fill" style="height:<?= round($h, 1) ?>%"></div>
                    <span class="visits-bar-day"><?= $isToday ? 'Oggi' : $_days[(int)date('w', strtotime($d['date']))] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="visits-total"><strong><?= number_format((int)$analytics7['total'], 0, ',', '.') ?></strong> visite in totale</p>
        </section>

        <section class="card" aria-labelledby="dash-recent">
            <div class="card-head">
                <h2 id="dash-recent">Articoli recenti</h2>
                <a href="<?= $_b ?>/admin/articles">Tutti gli articoli</a>
            </div>
            <?php if (empty($recentArticles)): ?>
                <p class="text-muted text-sm">Nessun articolo ancora. <a href="<?= $_b ?>/admin/articles/new">Scrivi il primo</a>.</p>
            <?php else: ?>
            <ul class="recent-list">
                <?php foreach ($recentArticles as $a): ?>
                <li>
                    <a class="recent-item" href="<?= $_b ?>/admin/articles/edit/<?= ocms_escape($a['slug']) ?>">
                        <span class="recent-item-title"><?= ocms_escape($a['title'] ?? $a['slug']) ?></span>
                        <?= $_statusBadge($a, true) ?>
                        <span class="recent-item-date"><?= ocms_escape($_date($a['updated_at'] ?? '')) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <?php if (!empty($recentPages)): ?>
        <section class="card" aria-labelledby="dash-pages">
            <div class="card-head">
                <h2 id="dash-pages">Pagine modificate di recente</h2>
                <a href="<?= $_b ?>/admin/pages">Tutte le pagine</a>
            </div>
            <ul class="recent-list">
                <?php foreach ($recentPages as $p): ?>
                <li>
                    <a class="recent-item" href="<?= $_b ?>/admin/pages/edit/<?= ocms_escape($p['slug']) ?>">
                        <span class="recent-item-title"><?= ocms_escape($p['title'] ?? $p['slug']) ?></span>
                        <?= $_statusBadge($p, false) ?>
                        <span class="recent-item-date"><?= ocms_escape($_date($p['updated_at'] ?? '')) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>

    <div class="dash-col">
        <section class="card" aria-labelledby="dash-todo">
            <div class="card-head"><h2 id="dash-todo">Da guardare</h2></div>
            <a class="todo-item" href="<?= $_b ?>/admin/comments">
                <span class="todo-num <?= $pendingComments > 0 ? 'warn' : 'ok' ?>"><?= $pendingComments ?></span>
                <span class="todo-label">Commenti in attesa<small><?= $pendingComments > 0 ? 'da approvare o rifiutare' : 'tutto moderato' ?></small></span>
            </a>
            <a class="todo-item" href="<?= $_b ?>/admin/articles">
                <span class="todo-num info"><?= $draftArticles ?></span>
                <span class="todo-label">Articoli in bozza<small>non visibili sul sito</small></span>
            </a>
            <?php if ($scheduledArticles > 0): ?>
            <a class="todo-item" href="<?= $_b ?>/admin/articles">
                <span class="todo-num dusk"><?= $scheduledArticles ?></span>
                <span class="todo-label">Articoli programmati<small>usciranno da soli alla data scelta</small></span>
            </a>
            <?php endif; ?>
        </section>

        <section class="card" aria-labelledby="dash-quick">
            <div class="card-head"><h2 id="dash-quick">Scorciatoie</h2></div>
            <div class="quick-actions cols-2 mb-0">
                <a href="<?= $_b ?>/admin/lessons/new" class="quick-action"><span class="icon"><?= $_ico('<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>') ?></span>Nuova lezione</a>
                <a href="<?= $_b ?>/admin/media" class="quick-action"><span class="icon"><?= $_ico('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>') ?></span>Carica media</a>
                <a href="<?= $_b ?>/admin/forms/new" class="quick-action"><span class="icon"><?= $_ico('<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>') ?></span>Nuovo form</a>
                <a href="<?= $_b ?>/admin/backup" class="quick-action"><span class="icon"><?= $_ico('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>') ?></span>Backup</a>
                <a href="<?= $_b ?>/admin/extensions/wizard" class="quick-action"><span class="icon"><?= $_ico('<path d="M13.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6.5-7z"/><path d="M12 11v6M9 14h6"/>') ?></span>Crea estensione</a>
                <a href="<?= $_b ?>/" class="quick-action" target="_blank" rel="noopener"><span class="icon"><?= $_ico('<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>') ?></span>Vai al sito</a>
            </div>
        </section>

        <section class="card" aria-labelledby="dash-sys">
            <div class="card-head"><h2 id="dash-sys">Sistema</h2></div>
            <dl class="sys-grid">
                <div><dt>Estensioni</dt><dd><?= $extActive ?>/<?= $extCount ?> attive</dd></div>
                <div><dt>Form</dt><dd><?= $formsCount ?> moduli</dd></div>
                <div><dt>PHP</dt><dd><?= ocms_escape(phpversion()) ?></dd></div>
                <div><dt>Tema</dt><dd><?= ocms_escape(ucfirst($app->config['theme'] ?? 'flavor')) ?></dd></div>
            </dl>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
