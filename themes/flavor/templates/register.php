<?php
require_once __DIR__ . '/_bootstrap.php';
$flashes = ocms_flash_get();
$question = is_array($captcha ?? null) ? (string) ($captcha['question'] ?? '') : '';
$flavor = [
    'title'     => 'Registrazione',
    'robots'    => 'noindex,nofollow',
    'seo'       => false,
    'bodyClass' => 'page-register',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap narrow-center">
    <section class="gate ocms-glass" aria-labelledby="reg-title">
        <span class="gate-ico"><?= flavor_icon('user', 26) ?></span>
        <h1 id="reg-title" class="gate-title">Crea il tuo account</h1>
        <p class="muted">Dopo la registrazione riceverai un'email con il link per attivare l'account.</p>
        <?= flavor_flashes($flashes) ?>
        <form method="post" action="<?= ocms_base_url() ?>/register" class="gate-form gate-form-left">
            <?= ocms_csrf_field() ?>
            <div class="field">
                <label for="display_name">Nome e cognome</label>
                <input type="text" id="display_name" name="display_name" required maxlength="100" autocomplete="name">
            </div>
            <div class="field">
                <label for="username">Nome utente</label>
                <input type="text" id="username" name="username" required maxlength="40" pattern="[a-z0-9_\-]+" autocomplete="username" autocapitalize="off" spellcheck="false" aria-describedby="username-hint">
                <p class="field-hint" id="username-hint">Solo lettere minuscole, numeri, trattino e trattino basso.</p>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="200" autocomplete="email">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="password-hint">
                <p class="field-hint" id="password-hint">Almeno 8 caratteri.</p>
            </div>
            <div class="field field-captcha">
                <label for="captcha">Verifica: quanto fa <?= ocms_escape(str_replace(' = ?', '', $question)) ?>?</label>
                <input type="text" id="captcha" name="captcha" required inputmode="numeric" autocomplete="off" maxlength="4">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Registrati</button>
        </form>
        <p class="gate-foot"><a href="<?= ocms_base_url() ?>/admin/login">Hai già un account? Accedi</a></p>
    </section>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
