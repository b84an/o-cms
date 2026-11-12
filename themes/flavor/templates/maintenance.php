<?php
/* Pagina 503 della modalità manutenzione: la rotta imposta già il codice 503 e Retry-After. */
require_once __DIR__ . '/_bootstrap.php';
$siteName = (string) ($app->config['site_name'] ?? 'Il sito');
$flavor = [
    'title'     => 'Manutenzione in corso',
    'robots'    => 'noindex',
    'seo'       => false,
    'bodyClass' => 'page-maintenance',
    'bare'      => true,
];
include __DIR__ . '/_header.php';
?>
<div class="wrap narrow-center maint">
    <section class="lost ocms-glass" aria-labelledby="maint-title">
        <svg class="maint-art" viewBox="0 0 240 80" aria-hidden="true" focusable="false">
            <path class="w1" d="M0 50 Q 30 38 60 50 T 120 50 T 180 50 T 240 50"/>
            <path class="w2" d="M0 62 Q 30 52 60 62 T 120 62 T 180 62 T 240 62"/>
            <circle class="moon" cx="190" cy="22" r="11"/>
        </svg>
        <p class="sign-event"><?= ocms_escape($siteName) ?></p>
        <h1 id="maint-title" class="gate-title">Torniamo tra poco</h1>
        <p class="muted">Stiamo facendo un po' di manutenzione. Il sito sarà di nuovo raggiungibile a breve: riprova tra qualche minuto.</p>
    </section>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
