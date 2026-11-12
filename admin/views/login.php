<?php
$_base = ocms_base_url();
$_assetVer = function (string $rel): string {
    $t = @filemtime(ocms_base_path() . $rel);
    return $t ? '?v=' . $t : '';
};
$_cfg = ocms_config();
$_siteName = trim((string)($_cfg['site_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="it" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Accedi — O-CMS</title>
    <script>document.documentElement.classList.remove('no-js');document.documentElement.classList.add('js');</script>
    <script src="<?= $_base ?>/assets/ocms/ocms-theme.js<?= $_assetVer('/assets/ocms/ocms-theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= $_base ?>/assets/ocms/tokens.css<?= $_assetVer('/assets/ocms/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $_base ?>/admin/assets/css/admin.css<?= $_assetVer('/admin/assets/css/admin.css') ?>">
    <script src="<?= $_base ?>/admin/assets/js/admin.js<?= $_assetVer('/admin/assets/js/admin.js') ?>" defer></script>
</head>
<body class="login-body" data-base-url="<?= ocms_escape($_base) ?>">
    <div class="ocms-aurora" aria-hidden="true"></div>

    <button type="button" class="icon-btn login-theme" data-ocms-theme-toggle aria-label="Cambia tema">
        <svg class="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <svg class="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
    </button>

    <main class="login-container">
        <div class="login-card ocms-glass">
            <div class="login-logo">
                <span class="brand-mark" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="8"/><path d="M4 15c3 2 6.5 2 8 0s5-2 8 0"/></svg>
                </span>
                <h1>O-CMS</h1>
                <p><?= $_siteName !== '' ? 'Pannello di ' . ocms_escape($_siteName) : 'Pannello di amministrazione' ?></p>
            </div>

            <?php foreach (ocms_flash_get() as $flash):
                $_ft = in_array($flash['type'] ?? '', ['success', 'error', 'warning'], true) ? $flash['type'] : '';
            ?>
                <div class="flash-message <?= $_ft ?>" role="<?= $_ft === 'error' ? 'alert' : 'status' ?>">
                    <?= ocms_escape($flash['message'] ?? '') ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" action="">
                <?= ocms_csrf_field() ?>

                <div class="form-group">
                    <label for="username">Nome utente</label>
                    <input type="text" id="username" name="username" class="form-input" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" class="form-input" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Mostra password" hidden>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="caps-hint" data-caps-hint="password" aria-live="polite">Maiuscole attive</div>
                </div>

                <button type="submit" class="btn btn-primary btn-login">Accedi</button>
            </form>

            <?php if (!empty($_cfg['registration_enabled'])): ?>
            <div class="login-foot">
                <a href="<?= $_base ?>/register">Non hai un account? Registrati</a>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
