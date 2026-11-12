<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
$flavor = [
    'title'     => 'Firme chiuse — ' . ($sheet['name'] ?? ''),
    'robots'    => 'noindex,nofollow',
    'seo'       => false,
    'bodyClass' => 'page-firma',
    'minimal'   => true,
];
include dirname(__DIR__) . '/_header.php';
?>
<div class="wrap narrow-center">
    <section class="sign-card sign-result ocms-glass is-closed" aria-labelledby="sign-title">
        <span class="result-mark" aria-hidden="true"><?= flavor_icon('lock', 40) ?></span>
        <h1 id="sign-title" class="gate-title">Firme chiuse</h1>
        <p>L'evento <strong><?= ocms_escape($sheet['name'] ?? '') ?></strong> non accetta più firme.</p>
        <p class="muted">Se pensi che sia un errore, rivolgiti a chi ha organizzato l'evento.</p>
    </section>
</div>
<?php include dirname(__DIR__) . '/_footer.php'; ?>
