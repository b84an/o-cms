<?php
require_once dirname(__DIR__) . '/_bootstrap.php';
$needCode = !empty($needCode);
$codeOk   = !empty($codeOk);
$old      = is_array($old ?? null) ? $old : [];
$action   = ocms_base_url() . '/firma/' . rawurlencode((string) $sheet['slug']);
$flashes  = ocms_flash_get();
$flavor = [
    'title'       => 'Firma: ' . ($sheet['name'] ?? ''),
    'description' => 'Registra la tua presenza a ' . ($sheet['name'] ?? ''),
    'robots'      => 'noindex,nofollow',
    'seo'         => false,
    'bodyClass'   => 'page-firma',
    'minimal'     => true,
];
include dirname(__DIR__) . '/_header.php';
?>
<div class="wrap narrow-center">
    <section class="sign-card ocms-glass" aria-labelledby="sign-title">
        <span class="gate-ico"><?= flavor_icon('pen', 26) ?></span>
        <p class="sign-event"><?= ocms_escape($sheet['name'] ?? '') ?></p>
        <h1 id="sign-title" class="gate-title">Firma la tua presenza</h1>
<?php if (!empty($sheet['event_date'])): ?>
        <p class="sign-date"><?= flavor_icon('calendar', 16) ?><time datetime="<?= ocms_escape(flavor_datetime($sheet['event_date'])) ?>"><?= ocms_escape(flavor_date($sheet['event_date']) ?: $sheet['event_date']) ?></time></p>
<?php endif; ?>
<?php if (!empty($sheet['description'])): ?>
        <p class="sign-desc"><?= nl2br(ocms_escape($sheet['description'])) ?></p>
<?php endif; ?>
        <?= flavor_flashes($flashes) ?>

        <form method="post" action="<?= ocms_escape($action) ?>" class="sign-form">
            <?= ocms_csrf_field() ?>
            <input type="hidden" name="_ts" value="<?= time() ?>">
<?php if ($needCode && $codeOk): ?>
            <input type="hidden" name="access_code" value="<?= ocms_escape(is_string($_GET['code'] ?? null) ? $_GET['code'] : '') ?>">
<?php elseif ($needCode): ?>
            <div class="field">
                <label for="access_code">Codice di accesso</label>
                <input type="text" id="access_code" name="access_code" class="code-input" required autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="64">
                <p class="field-hint">Lo trovi sullo schermo proiettato o te lo comunica chi organizza.</p>
            </div>
<?php endif; ?>
            <div class="form-row">
                <div class="field">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" name="nome" value="<?= ocms_escape($old['nome'] ?? '') ?>" required maxlength="100" autocomplete="given-name">
                </div>
                <div class="field">
                    <label for="cognome">Cognome</label>
                    <input type="text" id="cognome" name="cognome" value="<?= ocms_escape($old['cognome'] ?? '') ?>" required maxlength="100" autocomplete="family-name">
                </div>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= ocms_escape($old['email'] ?? '') ?>" required maxlength="200" autocomplete="email" inputmode="email">
            </div>
            <div class="hp" aria-hidden="true">
                <label>Sito web <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Firma</button>
            <p class="form-note">Una firma per indirizzo email. I dati servono solo a registrare la presenza<?= !empty($sheet['send_email']) ? ' e a inviarti la conferma' : '' ?>.</p>
        </form>
    </section>
</div>
<?php include dirname(__DIR__) . '/_footer.php'; ?>
