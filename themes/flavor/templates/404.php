<?php
require_once __DIR__ . '/_bootstrap.php';
$flavor = [
    'title'     => 'Pagina non trovata',
    'robots'    => 'noindex',
    'seo'       => false,
    'bodyClass' => 'page-404',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap narrow-center">
    <section class="lost ocms-glass" aria-labelledby="lost-title">
        <p class="lost-code" aria-hidden="true">404</p>
        <h1 id="lost-title" class="gate-title">Qui non c'è niente</h1>
        <p class="muted">La pagina che cerchi è stata spostata, rinominata o non è mai esistita. Capita anche ai link migliori.</p>
        <form class="search-big search-compact" action="<?= ocms_base_url() ?>/search" method="get" role="search">
            <label class="sr-only" for="lost-q">Cerca nel sito</label>
            <?= flavor_icon('search', 20) ?>
            <input id="lost-q" type="search" name="q" placeholder="Prova a cercarla" maxlength="200">
            <button class="btn btn-primary" type="submit">Cerca</button>
        </form>
        <div class="actions actions-center">
            <a class="btn btn-ghost" href="<?= ocms_base_url() ?>/">Torna alla home</a>
            <a class="btn btn-ghost" href="<?= ocms_base_url() ?>/blog">Vai al blog</a>
        </div>
    </section>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
