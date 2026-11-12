<?php
require_once __DIR__ . '/_bootstrap.php';
$q = is_string($query ?? null) ? $query : '';
$res = is_array($result['results'] ?? null) ? $result['results'] : [];
$total = (int) ($result['total'] ?? count($res));
$limited = http_response_code() === 429;
$flavor = [
    'title'     => $q !== '' ? 'Ricerca: ' . $q : 'Ricerca',
    'robots'    => 'noindex,follow',
    'seo'       => false,
    'bodyClass' => 'page-search',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap">
    <header class="page-head page-head-narrow">
        <h1 class="display">Ricerca</h1>
        <form class="search-big" action="<?= ocms_base_url() ?>/search" method="get" role="search">
            <label class="sr-only" for="search-big-q">Cosa vuoi cercare?</label>
            <?= flavor_icon('search', 22) ?>
            <input id="search-big-q" type="search" name="q" value="<?= ocms_escape($q) ?>" placeholder="Articoli, lezioni, pagine" maxlength="200"<?= $q === '' ? ' autofocus' : '' ?>>
            <button class="btn btn-primary" type="submit">Cerca</button>
        </form>
    </header>

    <div class="search-results">
<?php if ($limited): ?>
        <div class="notice notice-error" role="alert"><?= flavor_icon('alert') ?><span>Troppe ricerche in poco tempo. Aspetta un minuto e riprova.</span></div>
<?php elseif ($q === ''): ?>
        <p class="muted">Scrivi una o più parole: la ricerca guarda titoli, riassunti e testo di articoli, lezioni e pagine.</p>
<?php elseif (!$res): ?>
        <div class="empty-state ocms-glass">
            <p>Nessun risultato per <strong><?= ocms_escape($q) ?></strong>.</p>
            <p class="muted">Prova con meno parole o con un sinonimo.</p>
        </div>
<?php else: ?>
        <p class="search-stats" role="status"><?= $total === 1 ? '1 risultato' : $total . ' risultati' ?> per <strong><?= ocms_escape($q) ?></strong><?= $total > count($res) ? ', ecco i primi ' . count($res) : '' ?></p>
        <ol class="result-list">
<?php
// Il motore di ricerca riceve testo con entità (&nbsp;) e lo ri-escapa: qui le entità
// doppie tornano entità singole, che il browser mostra come caratteri (mai come tag).
$fixEnt = static fn($h) => preg_replace('/&amp;(#\d+|#x[0-9a-f]+|[a-z][a-z0-9]{1,31});/i', '&$1;', (string) $h);
foreach ($res as $r):
    $type = (string) ($r['type'] ?? '');
    $icon = ['article' => 'pen', 'lesson' => 'file', 'page' => 'home'][$type] ?? 'file'; ?>
            <li class="result">
                <p class="result-type"><?= flavor_icon($icon, 15) ?><?= ocms_escape($r['type_label'] ?? $type) ?><?php if (!empty($r['date'])): ?><time datetime="<?= ocms_escape(flavor_datetime($r['date'])) ?>"><?= ocms_escape(flavor_date($r['date'], true)) ?></time><?php endif; ?></p>
                <h2 class="result-title"><a href="<?= ocms_escape($r['url'] ?? '#') ?>"><?= isset($r['highlights']['title']) ? $fixEnt($r['highlights']['title']) : ocms_escape(flavor_plain($r['title'] ?? '')) ?></a></h2>
<?php if (!empty($r['highlights']['excerpt'])): ?>
                <p class="result-snippet"><?= $fixEnt($r['highlights']['excerpt']) ?></p>
<?php elseif (!empty($r['highlights']['content'])): ?>
                <p class="result-snippet"><?= $fixEnt($r['highlights']['content']) ?></p>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
