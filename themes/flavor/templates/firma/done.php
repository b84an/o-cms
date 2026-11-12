<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
$dup = !empty($dup);
$flashes = ocms_flash_get(); // il messaggio del duplicato è già detto dalla pagina
$flavor = [
    'title'     => ($dup ? 'Hai già firmato' : 'Firma registrata') . ' — ' . ($sheet['name'] ?? ''),
    'robots'    => 'noindex,nofollow',
    'seo'       => false,
    'bodyClass' => 'page-firma',
    'minimal'   => true,
];
include dirname(__DIR__) . '/_header.php';
?>
<div class="wrap narrow-center">
    <section class="sign-card sign-result ocms-glass<?= $dup ? ' is-dup' : ' is-ok' ?>" aria-labelledby="sign-title">
        <span class="result-mark" aria-hidden="true"><?= flavor_icon($dup ? 'alert' : 'check', 40) ?></span>
<?php if ($dup): ?>
        <h1 id="sign-title" class="gate-title">Hai già firmato</h1>
        <p>Per <strong><?= ocms_escape($sheet['name'] ?? '') ?></strong> risulta già una firma con questa email. Non serve firmare di nuovo.</p>
<?php else: ?>
        <h1 id="sign-title" class="gate-title">Firma registrata</h1>
        <p>Grazie! La tua presenza a <strong><?= ocms_escape($sheet['name'] ?? '') ?></strong> è stata registrata<?= !empty($sheet['send_email']) ? '. Ti abbiamo inviato una email di conferma' : '' ?>.</p>
<?php endif; ?>
        <p class="muted">Puoi chiudere questa pagina.</p>
    </section>
</div>
<?php include dirname(__DIR__) . '/_footer.php'; ?>
