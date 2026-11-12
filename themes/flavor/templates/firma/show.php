<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
$publicUrl = ocms_absolute_url('/firma/' . rawurlencode((string) $sheet['slug']));
$count = count(is_array($sheet['firme'] ?? null) ? $sheet['firme'] : []);
$open = ($sheet['status'] ?? 'open') === 'open' && !ocms_fogliofirme_is_expired($sheet);
$flavor = [
    'title'     => 'QR — ' . ($sheet['name'] ?? ''),
    'robots'    => 'noindex,nofollow',
    'seo'       => false,
    'bodyClass' => 'page-firma-show',
    'bare'      => true,
];
$flavorScripts = ['js/firma-show.js'];
include dirname(__DIR__) . '/_header.php';
?>
<div class="show" data-firma-show data-count-url="<?= ocms_escape(ocms_base_url() . '/firma/' . rawurlencode((string) $sheet['slug']) . '/count' . (isset($_GET['k']) && is_string($_GET['k']) ? '?k=' . rawurlencode($_GET['k']) : '')) ?>">
    <header class="show-head">
        <h1 class="display"><?= ocms_escape($sheet['name'] ?? '') ?></h1>
<?php if (!empty($sheet['event_date'])): ?>
        <p class="show-date"><?= ocms_escape(flavor_date($sheet['event_date']) ?: $sheet['event_date']) ?></p>
<?php endif; ?>
    </header>

    <div class="show-grid">
        <figure class="show-qr">
            <img src="<?= ocms_escape(ocms_fogliofirme_qr_url($publicUrl, 600)) ?>" alt="Codice QR per aprire la pagina di firma" width="600" height="600">
            <figcaption class="show-url"><?= ocms_escape($publicUrl) ?></figcaption>
        </figure>

        <div class="show-side">
            <p class="show-step">Inquadra il codice con la fotocamera del telefono e compila nome, cognome ed email.</p>
<?php if (!empty($sheet['access_code'])): ?>
            <div class="show-code">
                <span>Codice di accesso</span>
                <strong><?= ocms_escape($sheet['access_code']) ?></strong>
            </div>
<?php endif; ?>
            <div class="show-counter ocms-glass">
                <span class="show-count" data-firma-count aria-live="polite"><?= $count ?></span>
                <span class="show-count-label" data-firma-label><?= $count === 1 ? 'firma raccolta' : 'firme raccolte' ?></span>
            </div>
            <p class="show-last" data-firma-last hidden></p>
            <p class="show-status<?= $open ? '' : ' is-closed' ?>" data-firma-status><?= $open ? 'Raccolta firme aperta' : 'Raccolta firme chiusa' ?></p>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/_footer.php'; ?>
