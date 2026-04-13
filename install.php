<?php
/**
 * O-CMS — Procedura guidata di installazione
 *
 * Guida passo-passo alla configurazione iniziale di O-CMS.
 * Questo file va eliminato dopo l'installazione per motivi di sicurezza.
 *
 * Passaggi:
 *   1. Verifica requisiti server
 *   2. Configurazione sito (nome, URL, fuso orario)
 *   3. Creazione account amministratore
 *   4. Configurazione email / SMTP (opzionale)
 *   5. Notifica installazione (informativa)
 *   6. Riepilogo e conferma
 *   7. Installazione completata
 *
 * @package O-CMS
 * @version 1.0.0
 */

set_time_limit(120);
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Già installato? Blocca l'accesso.
if (file_exists(__DIR__ . '/data/.installed')) {
    // Gestisci richiesta di auto-eliminazione
    if (isset($_GET['self-delete']) && $_GET['self-delete'] === 'confirm') {
        $siteUrl = '';
        $configFile = __DIR__ . '/data/config.json';
        if (file_exists($configFile)) {
            $cfg = json_decode(file_get_contents($configFile), true);
            $siteUrl = $cfg['site_url'] ?? '';
        }
        @unlink(__FILE__);
        $redirect = $siteUrl ? $siteUrl . '/admin/' : '/admin/';
        header('Location: ' . $redirect);
        exit;
    }
    die('O-CMS è già installato. Per sicurezza, elimina install.php dal server.');
}

session_start();

// ─── PROTEZIONE CSRF ───

function install_csrf_token(): string {
    if (empty($_SESSION['_install_csrf'])) {
        $_SESSION['_install_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_install_csrf'];
}

function install_csrf_verify(): bool {
    $token = $_POST['_csrf'] ?? '';
    return !empty($token) && hash_equals($_SESSION['_install_csrf'] ?? '', $token);
}

function install_csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . install_csrf_token() . '">';
}

// ─── FUNZIONI HELPER ───

function convertToBytes(string $val): int {
    $val = trim($val);
    if ($val === '-1') return -1;
    $unit = strtolower(substr($val, -1));
    $num = (int)$val;
    return match($unit) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}

function checkRequirements(): array {
    $checks = [];

    // Versione PHP
    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks[] = ['name' => 'PHP >= 8.0', 'ok' => $phpOk, 'value' => PHP_VERSION, 'required' => true];

    // Estensioni obbligatorie
    foreach (['json', 'mbstring', 'session', 'fileinfo'] as $ext) {
        $loaded = extension_loaded($ext);
        $checks[] = ['name' => "Estensione: {$ext}", 'ok' => $loaded, 'value' => $loaded ? 'Installata' : 'Mancante', 'required' => true];
    }

    // Estensioni opzionali
    $optDescriptions = [
        'zip'  => 'Necessaria per backup e installazione estensioni',
        'intl' => 'Supporto internazionalizzazione avanzato',
        'gd'   => 'Ridimensionamento immagini e miniature',
        'curl' => 'Integrazione AI e chiamate API esterne',
    ];
    foreach ($optDescriptions as $ext => $desc) {
        $loaded = extension_loaded($ext);
        $checks[] = ['name' => "Estensione: {$ext}", 'ok' => $loaded, 'value' => $loaded ? 'Installata' : "Mancante — {$desc}", 'required' => false];
    }

    // mod_rewrite
    $modRewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : null;
    $checks[] = [
        'name' => 'Apache mod_rewrite',
        'ok' => $modRewrite !== false,
        'value' => $modRewrite === true ? 'Attivo' : ($modRewrite === null ? 'Non verificabile (probabilmente OK)' : 'Disattivo'),
        'required' => false,
    ];

    // Directory scrivibile
    $writable = is_writable(__DIR__);
    $checks[] = ['name' => 'Directory scrivibile', 'ok' => $writable, 'value' => $writable ? 'Sì' : 'No — permessi insufficienti', 'required' => true];

    // Spazio disco
    $freeSpace = @disk_free_space(__DIR__) ?: 0;
    $hasSpace = $freeSpace > 20 * 1024 * 1024;
    $checks[] = ['name' => 'Spazio disco >= 20 MB', 'ok' => $hasSpace, 'value' => round($freeSpace / (1024 * 1024)) . ' MB liberi', 'required' => true];

    // Limite memoria
    $memLimit = ini_get('memory_limit');
    $memBytes = convertToBytes($memLimit);
    $memOk = $memBytes >= 64 * 1024 * 1024 || $memBytes === -1;
    $checks[] = ['name' => 'Limite memoria >= 64 MB', 'ok' => $memOk, 'value' => $memLimit, 'required' => false];

    return $checks;
}

function detectBaseUrl(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(dirname($scriptName), '/\\');
    return $dir === '.' ? '' : $dir;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ─── GESTIONE PASSAGGI ───

$step = (int)($_POST['_step'] ?? $_GET['step'] ?? 1);
$errors = [];
$data = $_SESSION['_install_data'] ?? [];

// Elaborazione invio POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && install_csrf_verify()) {
    switch ($step) {
        case 2: // Configurazione sito inviata
            $data['site_name']   = trim($_POST['site_name'] ?? 'Il mio sito');
            $data['site_url']    = rtrim(trim($_POST['site_url'] ?? ''), '/');
            $data['language']    = 'it';
            $data['timezone']    = $_POST['timezone'] ?? 'Europe/Rome';
            $data['date_format'] = $_POST['date_format'] ?? 'd/m/Y';
            if (empty($data['site_name'])) $errors[] = 'Il nome del sito è obbligatorio.';
            if (empty($errors)) { $_SESSION['_install_data'] = $data; $step = 3; }
            break;

        case 3: // Account admin inviato
            $data['admin_user']    = trim($_POST['admin_user'] ?? '');
            $data['admin_email']   = trim($_POST['admin_email'] ?? '');
            $data['admin_pass']    = $_POST['admin_pass'] ?? '';
            $data['admin_pass2']   = $_POST['admin_pass2'] ?? '';
            $data['admin_display'] = trim($_POST['admin_display'] ?? '') ?: $data['admin_user'];
            if (empty($data['admin_user']) || !preg_match('/^[a-z0-9_-]{3,30}$/', $data['admin_user'])) {
                $errors[] = 'Lo username deve essere di 3-30 caratteri: solo lettere minuscole, numeri, - e _.';
            }
            if (empty($data['admin_email']) || !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Inserisci un indirizzo email valido.';
            }
            if (strlen($data['admin_pass']) < 8) {
                $errors[] = 'La password deve essere di almeno 8 caratteri.';
            }
            if ($data['admin_pass'] !== $data['admin_pass2']) {
                $errors[] = 'Le password non coincidono.';
            }
            if (empty($errors)) { $_SESSION['_install_data'] = $data; $step = 4; }
            break;

        case 4: // SMTP inviato
            $data['smtp_method'] = $_POST['smtp_method'] ?? 'php_mail';
            if ($data['smtp_method'] === 'smtp') {
                $data['smtp_host']       = trim($_POST['smtp_host'] ?? '');
                $data['smtp_port']       = (int)($_POST['smtp_port'] ?? 587);
                $data['smtp_encryption'] = $_POST['smtp_encryption'] ?? 'tls';
                $data['smtp_username']   = trim($_POST['smtp_username'] ?? '');
                $data['smtp_password']   = $_POST['smtp_password'] ?? '';
                $data['smtp_from_email'] = trim($_POST['smtp_from_email'] ?? '') ?: $data['admin_email'];
                $data['smtp_from_name']  = trim($_POST['smtp_from_name'] ?? '') ?: $data['site_name'];
            }
            $_SESSION['_install_data'] = $data;
            $step = 5;
            break;

        case 5: // Phone home informativa
            $_SESSION['_install_data'] = $data;
            $step = 6;
            break;

        case 6: // Riepilogo confermato → avvia installazione
            $step = 7;
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !install_csrf_verify()) {
    $errors[] = 'Token di sicurezza non valido. Riprova.';
}

// Permetti navigazione indietro via GET (solo quando non si elabora un POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['step'])) {
    $s = (int)$_GET['step'];
    if ($s >= 1 && $s <= 6) $step = $s;
}

// ─── LOGICA DI INSTALLAZIONE (Passaggio 7) ───

$installSuccess = false;
$installWarnings = [];

if ($step === 7) {
    $baseDir = __DIR__;

    // Crea tutte le directory dati
    $dirs = [
        'data', 'data/articles', 'data/analytics', 'data/backups', 'data/cache',
        'data/categories', 'data/comments', 'data/forms', 'data/galleries',
        'data/layouts', 'data/lessons', 'data/logs', 'data/media', 'data/menus',
        'data/pages', 'data/quiz-results', 'data/quizzes', 'data/revisions',
        'data/snippets', 'data/tags', 'data/translations', 'data/users', 'data/widgets',
        'uploads', 'uploads/images', 'uploads/documents', 'uploads/media',
        'extensions',
    ];
    foreach ($dirs as $d) {
        $path = $baseDir . '/' . $d;
        if (!is_dir($path) && !@mkdir($path, 0755, true)) {
            $installWarnings[] = "Impossibile creare la directory: {$d}";
        }
    }

    // Proteggi la directory dati
    $htaccess = $baseDir . '/data/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "# Proteggi directory dati\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }

    $now = date('c');
    $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    // 1. Configurazione
    $config = [
        'site_name'            => $data['site_name'] ?? 'Il mio sito',
        'site_description'     => '',
        'site_url'             => $data['site_url'] ?? '',
        'theme'                => 'flavor',
        'language'             => $data['language'] ?? 'it',
        'timezone'             => $data['timezone'] ?? 'Europe/Rome',
        'date_format'          => $data['date_format'] ?? 'd/m/Y',
        'posts_per_page'       => 10,
        'admin_email'          => $data['admin_email'] ?? '',
        'maintenance_mode'     => false,
        'registration_enabled' => false,
        'site_width_value'     => '90',
        'site_width_unit'      => '%',
        'smtp' => [
            'host'       => $data['smtp_host'] ?? '',
            'port'       => $data['smtp_port'] ?? 587,
            'encryption' => $data['smtp_encryption'] ?? 'tls',
            'username'   => $data['smtp_username'] ?? '',
            'password'   => $data['smtp_password'] ?? '',
            'from_email' => $data['smtp_from_email'] ?? ($data['admin_email'] ?? ''),
            'from_name'  => $data['smtp_from_name'] ?? ($data['site_name'] ?? 'O-CMS'),
        ],
        'seo_global' => [
            'robots'       => "User-agent: *\nAllow: /",
            'head_scripts' => '',
            'body_scripts' => '',
        ],
        'ai' => [
            'provider'     => 'none',
            'api_key'      => '',
            'model'        => '',
            'instructions' => '',
        ],
        'installation_notified' => false,
        'ocms_version'          => '1.0.0',
    ];
    if (file_put_contents($baseDir . '/data/config.json', json_encode($config, $jsonFlags)) === false) {
        $installWarnings[] = 'Impossibile scrivere config.json';
    }

    // 2. Utente admin
    $adminUser = $data['admin_user'] ?? 'admin';
    $user = [
        'id'           => bin2hex(random_bytes(16)),
        'username'     => $adminUser,
        'email'        => $data['admin_email'] ?? '',
        'password'     => password_hash($data['admin_pass'] ?? 'changeme', PASSWORD_DEFAULT),
        'display_name' => $data['admin_display'] ?? 'Amministratore',
        'role'         => 'super_administrator',
        'avatar'       => '',
        'active'       => true,
        'api_tokens'   => [],
        'created_at'   => $now,
        'last_login'   => null,
    ];
    if (file_put_contents($baseDir . '/data/users/' . $adminUser . '.json', json_encode($user, $jsonFlags)) === false) {
        $installWarnings[] = 'Impossibile scrivere il file utente admin';
    }

    // 3. Pagina Home
    $homePage = [
        'id'         => bin2hex(random_bytes(16)),
        'title'      => 'Benvenuto',
        'slug'       => 'home',
        'content'    => '<h2>Benvenuto su ' . e($data['site_name'] ?? 'O-CMS') . '</h2>'
                      . '<p>Il tuo nuovo sito è pronto! Vai al <a href="' . e($data['site_url'] ?? '') . '/admin/">pannello di amministrazione</a> per iniziare a creare contenuti.</p>'
                      . '<p>O-CMS è un CMS flat-file: niente database, tutto è salvato in semplici file JSON.</p>',
        'template'   => 'home',
        'layout'     => 'none',
        'status'     => 'published',
        'meta'       => ['title' => '', 'description' => 'Benvenuto su ' . ($data['site_name'] ?? 'O-CMS'), 'og_image' => ''],
        'order'      => 0,
        'parent'     => null,
        'author'     => $adminUser,
        'created_at' => $now,
        'updated_at' => $now,
        'views'      => 0,
    ];
    if (file_put_contents($baseDir . '/data/pages/home.json', json_encode($homePage, $jsonFlags)) === false) {
        $installWarnings[] = 'Impossibile scrivere la pagina home';
    }

    // 4. Pagina Chi siamo
    $aboutPage = [
        'id'         => bin2hex(random_bytes(16)),
        'title'      => 'Chi siamo',
        'slug'       => 'chi-siamo',
        'content'    => '<p>Questo sito è realizzato con <strong>O-CMS</strong>, un sistema di gestione contenuti leggero basato su file.</p>'
                      . '<p>Modifica questa pagina dal pannello admin per raccontare ai visitatori chi sei o di cosa tratta il tuo progetto.</p>',
        'template'   => 'page',
        'layout'     => 'none',
        'status'     => 'published',
        'meta'       => ['title' => 'Chi siamo', 'description' => 'Informazioni su questo sito', 'og_image' => ''],
        'order'      => 1,
        'parent'     => null,
        'author'     => $adminUser,
        'created_at' => $now,
        'updated_at' => $now,
        'views'      => 0,
    ];
    if (file_put_contents($baseDir . '/data/pages/chi-siamo.json', json_encode($aboutPage, $jsonFlags)) === false) {
        $installWarnings[] = 'Impossibile scrivere la pagina Chi siamo';
    }

    // 5. Articolo di esempio
    $article = [
        'id'          => bin2hex(random_bytes(16)),
        'title'       => 'Primi passi con O-CMS',
        'slug'        => 'primi-passi-con-ocms',
        'excerpt'     => 'Una guida rapida per configurare il tuo nuovo sito O-CMS.',
        'content'     => '<p>Benvenuto su O-CMS! Ecco qualche indicazione per iniziare.</p>'
                       . '<h3>Personalizza le impostazioni</h3>'
                       . '<p>Vai su <strong>Impostazioni</strong> nel pannello admin per configurare nome del sito, descrizione, tema e altro.</p>'
                       . '<h3>Crea contenuti</h3>'
                       . '<p>Usa le <strong>Pagine</strong> per i contenuti statici (come una pagina Chi siamo) e gli <strong>Articoli</strong> per il blog.</p>'
                       . '<h3>Gestisci il menu</h3>'
                       . '<p>Il <strong>Menu Builder</strong> ti permette di organizzare la navigazione con il drag-and-drop.</p>'
                       . '<h3>Scegli un tema</h3>'
                       . '<p>O-CMS include il tema Flavor. Puoi crearne uno tuo con il Theme Wizard integrato oppure installarne uno da file ZIP.</p>',
        'cover_image' => '',
        'category'    => 'generale',
        'tags'        => ['primi-passi'],
        'status'      => 'published',
        'meta'        => ['title' => 'Primi passi con O-CMS', 'description' => 'Una guida rapida per configurare il tuo nuovo sito O-CMS.', 'og_image' => ''],
        'author'      => $adminUser,
        'created_at'  => $now,
        'updated_at'  => $now,
        'views'       => 0,
    ];
    if (file_put_contents($baseDir . '/data/articles/primi-passi-con-ocms.json', json_encode($article, $jsonFlags)) === false) {
        $installWarnings[] = 'Impossibile scrivere l\'articolo di esempio';
    }

    // 6. Categoria predefinita
    $category = [
        'id'          => bin2hex(random_bytes(16)),
        'name'        => 'Generale',
        'slug'        => 'generale',
        'description' => 'Articoli e aggiornamenti generali',
        'parent'      => null,
        'order'       => 0,
        'created_at'  => $now,
    ];
    if (file_put_contents($baseDir . '/data/categories/generale.json', json_encode($category, $jsonFlags)) === false) {
        $installWarnings[] = 'Impossibile scrivere la categoria predefinita';
    }

    // 7. Tag predefinito
    $tag = [
        'id'         => bin2hex(random_bytes(16)),
        'name'       => 'Primi passi',
        'slug'       => 'primi-passi',
        'created_at' => $now,
    ];
    if (file_put_contents($baseDir . '/data/tags/primi-passi.json', json_encode($tag, $jsonFlags)) === false) {
        $installWarnings[] = 'Impossibile scrivere il tag predefinito';
    }

    // 8. Menu principale
    $menu = [
        'name'  => 'main',
        'label' => 'Menu Principale',
        'items' => [
            ['id' => bin2hex(random_bytes(8)), 'label' => 'Home',      'url' => '/',          'target' => '_self', 'published' => true, 'children' => []],
            ['id' => bin2hex(random_bytes(8)), 'label' => 'Blog',      'url' => '/blog',      'target' => '_self', 'published' => true, 'children' => []],
            ['id' => bin2hex(random_bytes(8)), 'label' => 'Chi siamo', 'url' => '/chi-siamo', 'target' => '_self', 'published' => true, 'children' => []],
        ],
    ];
    if (file_put_contents($baseDir . '/data/menus/main.json', json_encode($menu, $jsonFlags)) === false) {
        $installWarnings[] = 'Impossibile scrivere il menu principale';
    }

    // 9. File di blocco
    if (file_put_contents($baseDir . '/data/.installed', date('c')) === false) {
        $installWarnings[] = 'Impossibile scrivere il file di blocco';
    }

    // Segna come riuscito solo se nessun file critico ha fallito
    $criticalFiles = ['config.json', 'utente admin', 'file di blocco'];
    $hasCriticalFailure = false;
    foreach ($installWarnings as $w) {
        foreach ($criticalFiles as $cf) {
            if (stripos($w, $cf) !== false) { $hasCriticalFailure = true; break 2; }
        }
    }
    $installSuccess = !$hasCriticalFailure;

    // Pulizia sessione
    unset($_SESSION['_install_data'], $_SESSION['_install_csrf']);
}

// Requisiti (necessari per la visualizzazione del passaggio 1)
$checks = checkRequirements();
$allRequiredOk = !in_array(false, array_map(fn($c) => !$c['required'] || $c['ok'], $checks));

// Etichette passaggi per la barra di avanzamento
$stepLabels = [1 => 'Requisiti', 2 => 'Sito', 3 => 'Admin', 4 => 'Email', 5 => 'Privacy', 6 => 'Riepilogo', 7 => 'Fine'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O-CMS — Installazione</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5; --bg: #0f172a;
            --bg-card: #1e293b; --text: #f1f5f9; --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.08); --success: #22c55e; --error: #ef4444;
            --warning: #f59e0b; --radius: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .installer { max-width: 640px; width: 100%; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; margin-bottom: 20px; }
        .logo { text-align: center; margin-bottom: 28px; }
        .logo h1 {
            font-size: 2rem; font-weight: 800;
            background: linear-gradient(135deg, #818cf8, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .logo p { color: var(--text-muted); font-size: 0.9rem; margin-top: 4px; }

        h2 { font-size: 1.15rem; font-weight: 700; margin-bottom: 16px; }

        /* Barra passaggi */
        .steps { display: flex; gap: 0; margin-bottom: 24px; }
        .step-item {
            flex: 1; text-align: center; padding: 8px 2px; font-size: 0.7rem; font-weight: 600;
            color: var(--text-muted); position: relative;
        }
        .step-item.active { color: var(--primary); }
        .step-item.done { color: var(--success); }
        .step-item::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: var(--border); border-radius: 2px; }
        .step-item.active::after { background: var(--primary); }
        .step-item.done::after { background: var(--success); }

        /* Lista controlli */
        .check-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 0.875rem; }
        .check-item:last-child { border-bottom: none; }
        .check-icon { width: 20px; text-align: center; font-size: 1rem; flex-shrink: 0; }
        .check-name { flex: 1; font-weight: 500; }
        .check-value { color: var(--text-muted); font-size: 0.8rem; text-align: right; }

        /* Form */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .form-input, .form-select {
            width: 100%; padding: 12px 14px; background: var(--bg); border: 1px solid var(--border);
            border-radius: 8px; color: var(--text); font-size: 0.9rem; font-family: inherit; outline: none;
        }
        .form-select { appearance: none; cursor: pointer; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
        .form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* Pulsanti */
        .btn { display: inline-block; padding: 12px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; border: none; font-family: inherit; transition: all 0.15s; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.3); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-secondary { background: var(--bg); color: var(--text); border: 1px solid var(--border); }
        .btn-row { display: flex; gap: 12px; justify-content: space-between; }

        /* Avvisi */
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem; line-height: 1.5; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
        .alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); color: #fcd34d; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #86efac; }
        .alert-info { background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.3); color: #a5b4fc; }

        /* Tabella riepilogo */
        .summary-table { width: 100%; font-size: 0.875rem; }
        .summary-table td { padding: 8px 0; border-bottom: 1px solid var(--border); vertical-align: top; }
        .summary-table td:first-child { font-weight: 600; width: 40%; color: var(--text-muted); }
        .summary-table tr:last-child td { border-bottom: none; }

        /* Checkbox */
        .checkbox-row { display: flex; align-items: flex-start; gap: 10px; padding: 12px 0; }
        .checkbox-row input[type="checkbox"] { margin-top: 3px; width: 18px; height: 18px; accent-color: var(--primary); flex-shrink: 0; }

        .success-icon { font-size: 4rem; text-align: center; margin-bottom: 16px; }

        /* SMTP toggle */
        .smtp-fields { display: none; margin-top: 16px; }
        .smtp-fields.visible { display: block; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .btn-row { flex-direction: column; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="installer">
    <div class="logo">
        <h1>O-CMS</h1>
        <p>Installazione guidata</p>
    </div>

    <!-- Barra passaggi -->
    <div class="steps">
        <?php foreach ($stepLabels as $num => $label): ?>
        <div class="step-item <?= $num === $step ? 'active' : ($num < $step ? 'done' : '') ?>">
            <?= $num ?>. <?= $label ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Errori -->
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 1): ?>
    <!-- ═══ PASSAGGIO 1: REQUISITI ═══ -->
    <div class="card">
        <h2>Requisiti del server</h2>
        <div class="check-list">
            <?php foreach ($checks as $c): ?>
            <div class="check-item">
                <span class="check-icon"><?= $c['ok'] ? '&#9989;' : ($c['required'] ? '&#10060;' : '&#9888;&#65039;') ?></span>
                <span class="check-name"><?= e($c['name']) ?></span>
                <span class="check-value"><?= e($c['value']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($allRequiredOk): ?>
            <div class="alert alert-success">Tutti i requisiti obbligatori sono soddisfatti!</div>
            <a href="?step=2" class="btn btn-primary" style="display:block;text-align:center;">Continua &rarr;</a>
        <?php else: ?>
            <div class="alert alert-error">Alcuni requisiti obbligatori non sono soddisfatti. Risolvili prima di continuare.</div>
            <button class="btn btn-primary" disabled>Requisiti non soddisfatti</button>
        <?php endif; ?>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- ═══ PASSAGGIO 2: CONFIGURAZIONE SITO ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="2">

        <div class="card">
            <h2>Configurazione del sito</h2>

            <div class="form-group">
                <label>Nome del sito *</label>
                <input type="text" name="site_name" class="form-input" value="<?= e($data['site_name'] ?? 'Il mio sito') ?>" required>
            </div>

            <div class="form-group">
                <label>Percorso URL base</label>
                <input type="text" name="site_url" class="form-input" value="<?= e($data['site_url'] ?? detectBaseUrl()) ?>" placeholder="Lascia vuoto se installato nella root del dominio">
                <div class="form-hint">Il percorso dalla root del dominio. Esempio: <code>/cms</code> o <code>/blog</code>. Lascia vuoto se O-CMS è nella root.</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Fuso orario</label>
                    <select name="timezone" class="form-select form-input">
                        <?php
                        $zones = ['Europe/Rome', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Madrid',
                                  'UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
                                  'America/Sao_Paulo', 'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Kolkata', 'Australia/Sydney'];
                        $selTz = $data['timezone'] ?? 'Europe/Rome';
                        foreach ($zones as $tz):
                        ?>
                        <option value="<?= $tz ?>" <?= $selTz === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Formato data</label>
                    <select name="date_format" class="form-select form-input">
                        <?php
                        $formats = ['d/m/Y' => 'GG/MM/AAAA (13/04/2026)', 'Y-m-d' => 'AAAA-MM-GG (2026-04-13)', 'm/d/Y' => 'MM/GG/AAAA (04/13/2026)', 'd.m.Y' => 'GG.MM.AAAA (13.04.2026)'];
                        $selFmt = $data['date_format'] ?? 'd/m/Y';
                        foreach ($formats as $fmt => $label):
                        ?>
                        <option value="<?= $fmt ?>" <?= $selFmt === $fmt ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="btn-row">
            <a href="?step=1" class="btn btn-secondary">&larr; Indietro</a>
            <button type="submit" class="btn btn-primary">Continua &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 3): ?>
    <!-- ═══ PASSAGGIO 3: ACCOUNT ADMIN ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="3">

        <div class="card">
            <h2>Account amministratore</h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Crea il primo account amministratore del sito.
            </p>

            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="admin_user" class="form-input" value="<?= e($data['admin_user'] ?? '') ?>" pattern="[a-z0-9_-]{3,30}" required placeholder="admin">
                <div class="form-hint">Solo lettere minuscole, numeri, trattini e underscore (3-30 caratteri)</div>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="admin_email" class="form-input" value="<?= e($data['admin_email'] ?? '') ?>" required placeholder="tu@esempio.com">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="admin_pass" class="form-input" required minlength="8" placeholder="Minimo 8 caratteri">
                </div>
                <div class="form-group">
                    <label>Conferma password *</label>
                    <input type="password" name="admin_pass2" class="form-input" required minlength="8" placeholder="Ripeti la password">
                </div>
            </div>

            <div class="form-group">
                <label>Nome visualizzato</label>
                <input type="text" name="admin_display" class="form-input" value="<?= e($data['admin_display'] ?? '') ?>" placeholder="Se vuoto, usa lo username">
            </div>
        </div>

        <div class="btn-row">
            <a href="?step=2" class="btn btn-secondary">&larr; Indietro</a>
            <button type="submit" class="btn btn-primary">Continua &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 4): ?>
    <!-- ═══ PASSAGGIO 4: EMAIL / SMTP ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="4">

        <div class="card">
            <h2>Configurazione email</h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Configura come O-CMS invia le email (form di contatto, notifiche, reset password).
                Puoi modificare queste impostazioni in seguito.
            </p>

            <div class="form-group">
                <label>Metodo di invio</label>
                <select name="smtp_method" id="smtpMethod" class="form-select form-input" onchange="toggleSmtp()">
                    <option value="php_mail" <?= ($data['smtp_method'] ?? 'php_mail') === 'php_mail' ? 'selected' : '' ?>>PHP mail() — Predefinito</option>
                    <option value="smtp" <?= ($data['smtp_method'] ?? '') === 'smtp' ? 'selected' : '' ?>>Server SMTP</option>
                </select>
                <div class="form-hint">PHP mail() funziona sulla maggior parte degli hosting condivisi. Usa SMTP per una consegna più affidabile.</div>
            </div>

            <div id="smtpFields" class="smtp-fields">
                <div class="form-row">
                    <div class="form-group">
                        <label>Host SMTP</label>
                        <input type="text" name="smtp_host" class="form-input" value="<?= e($data['smtp_host'] ?? '') ?>" placeholder="mail.esempio.com">
                    </div>
                    <div class="form-group">
                        <label>Porta SMTP</label>
                        <input type="number" name="smtp_port" class="form-input" value="<?= e($data['smtp_port'] ?? '587') ?>" placeholder="587">
                    </div>
                </div>

                <div class="form-group">
                    <label>Crittografia</label>
                    <select name="smtp_encryption" class="form-select form-input">
                        <option value="tls" <?= ($data['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (consigliato)</option>
                        <option value="ssl" <?= ($data['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($data['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Nessuna</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="smtp_username" class="form-input" value="<?= e($data['smtp_username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="smtp_password" class="form-input" value="<?= e($data['smtp_password'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email mittente</label>
                        <input type="email" name="smtp_from_email" class="form-input" value="<?= e($data['smtp_from_email'] ?? '') ?>" placeholder="noreply@esempio.com">
                    </div>
                    <div class="form-group">
                        <label>Nome mittente</label>
                        <input type="text" name="smtp_from_name" class="form-input" value="<?= e($data['smtp_from_name'] ?? '') ?>" placeholder="Il mio sito">
                    </div>
                </div>
            </div>
        </div>

        <div class="btn-row">
            <a href="?step=3" class="btn btn-secondary">&larr; Indietro</a>
            <button type="submit" class="btn btn-primary">Continua &rarr;</button>
        </div>
    </form>

    <script>
    function toggleSmtp() {
        const fields = document.getElementById('smtpFields');
        const method = document.getElementById('smtpMethod').value;
        fields.classList.toggle('visible', method === 'smtp');
    }
    toggleSmtp();
    </script>

    <?php elseif ($step === 5): ?>
    <!-- ═══ PASSAGGIO 5: NOTIFICA INSTALLAZIONE ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="5">

        <div class="card">
            <h2>Notifica di installazione</h2>

            <div class="alert alert-info">
                <strong>Avviso di trasparenza</strong>
            </div>

            <p style="color:var(--text-muted);font-size:0.9rem;line-height:1.7;margin-bottom:16px;">
                O-CMS invia una <strong>notifica anonima una tantum</strong>
                allo sviluppatore del progetto al primo accesso all'area admin. Questo aiuta a monitorare
                l'adozione e a dare priorità allo sviluppo.
            </p>

            <div style="background:var(--bg);border-radius:8px;padding:16px;margin-bottom:16px;">
                <p style="font-size:0.85rem;font-weight:600;margin-bottom:8px;">Dati inviati (una sola volta):</p>
                <ul style="font-size:0.85rem;color:var(--text-muted);padding-left:20px;line-height:1.8;">
                    <li>Il nome del tuo dominio (es. <code>esempio.com</code>)</li>
                    <li>Versione PHP (es. <code><?= PHP_VERSION ?></code>)</li>
                    <li>Versione O-CMS (es. <code>1.0.0</code>)</li>
                </ul>
            </div>

            <p style="color:var(--text-muted);font-size:0.85rem;line-height:1.6;margin-bottom:20px;">
                Nessun dato personale, nessun tracciamento IP, nessun cookie, nessuna chiamata ricorrente.
                L'unica informazione trasmessa è il nome del dominio su cui O-CMS viene installato.
                Questa notifica supporta lo sviluppo del progetto e non può essere disattivata.
            </p>
        </div>

        <div class="btn-row">
            <a href="?step=4" class="btn btn-secondary">&larr; Indietro</a>
            <button type="submit" class="btn btn-primary">Continua &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 6): ?>
    <!-- ═══ PASSAGGIO 6: RIEPILOGO ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="6">

        <div class="card">
            <h2>Riepilogo installazione</h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Controlla le impostazioni prima di installare. Clicca su un titolo di sezione per tornare indietro e modificarlo.
            </p>

            <table class="summary-table">
                <tr><td><a href="?step=2" style="color:var(--primary);text-decoration:none;">Nome sito</a></td><td><?= e($data['site_name'] ?? '') ?></td></tr>
                <tr><td>URL base</td><td><code><?= e($data['site_url'] ?? '/') ?: '/' ?></code></td></tr>
                <tr><td>Fuso orario</td><td><?= e($data['timezone'] ?? 'Europe/Rome') ?></td></tr>
                <tr><td>Formato data</td><td><code><?= e($data['date_format'] ?? 'd/m/Y') ?></code></td></tr>
                <tr><td colspan="2" style="height:8px;border:none;"></td></tr>
                <tr><td><a href="?step=3" style="color:var(--primary);text-decoration:none;">Utente admin</a></td><td><?= e($data['admin_user'] ?? '') ?></td></tr>
                <tr><td>Email admin</td><td><?= e($data['admin_email'] ?? '') ?></td></tr>
                <tr><td colspan="2" style="height:8px;border:none;"></td></tr>
                <tr><td><a href="?step=4" style="color:var(--primary);text-decoration:none;">Metodo email</a></td><td><?= ($data['smtp_method'] ?? 'php_mail') === 'smtp' ? 'SMTP (' . e($data['smtp_host'] ?? '') . ')' : 'PHP mail()' ?></td></tr>
                <tr><td colspan="2" style="height:8px;border:none;"></td></tr>
                <tr><td><a href="?step=5" style="color:var(--primary);text-decoration:none;">Notifica</a></td><td>&#9989; Attiva (solo nome dominio, una tantum)</td></tr>
            </table>
        </div>

        <div class="btn-row">
            <a href="?step=5" class="btn btn-secondary">&larr; Indietro</a>
            <button type="submit" class="btn btn-primary">Installa O-CMS &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 7): ?>
    <!-- ═══ PASSAGGIO 7: COMPLETAMENTO ═══ -->
    <div class="card" style="text-align:center;">
        <?php if ($installSuccess): ?>
            <div class="success-icon">&#127881;</div>
            <h2 style="text-align:center;">Installazione completata!</h2>
            <p style="color:var(--text-muted);margin-bottom:24px;">
                O-CMS è stato installato con successo. Il tuo sito è pronto.
            </p>

            <?php if (!empty($installWarnings)): ?>
            <div class="alert alert-warning" style="text-align:left;">
                <strong>Alcuni file non critici non sono stati creati:</strong><br>
                <?php foreach ($installWarnings as $w): ?>
                    &bull; <?= e($w) ?><br>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="alert alert-info" style="text-align:left;">
                Il tuo login admin: <strong><?= e($data['admin_user'] ?? 'admin') ?></strong> con la password che hai scelto.
            </div>

            <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;flex-wrap:wrap;">
                <a href="<?= e($data['site_url'] ?? '') ?>/" class="btn btn-secondary">Vai al sito</a>
                <a href="<?= e($data['site_url'] ?? '') ?>/admin/" class="btn btn-secondary">Pannello admin</a>
                <button onclick="deleteInstaller()" id="btnDelete" class="btn btn-primary">Elimina install.php e vai al pannello &rarr;</button>
            </div>
            <p id="deleteStatus" style="color:var(--text-muted);font-size:0.8rem;margin-top:12px;display:none;"></p>

            <script>
            function deleteInstaller() {
                if (!confirm('Eliminare install.php dal server? Questa azione è irreversibile.')) return;
                const btn = document.getElementById('btnDelete');
                const status = document.getElementById('deleteStatus');
                btn.disabled = true;
                btn.textContent = 'Eliminazione in corso...';
                status.style.display = 'block';
                status.textContent = '';
                fetch('?self-delete=confirm')
                    .then(r => {
                        if (r.redirected || r.ok) {
                            status.textContent = 'File eliminato! Reindirizzamento...';
                            status.style.color = 'var(--success)';
                            setTimeout(() => { window.location.href = '<?= e($data['site_url'] ?? '') ?>/admin/'; }, 1000);
                        } else {
                            throw new Error('Risposta inattesa');
                        }
                    })
                    .catch(() => {
                        status.textContent = 'Impossibile eliminare il file. Rimuovilo manualmente via FTP.';
                        status.style.color = 'var(--error)';
                        btn.disabled = false;
                        btn.textContent = 'Riprova';
                    });
            }
            </script>
        <?php else: ?>
            <div class="success-icon">&#9888;&#65039;</div>
            <h2 style="text-align:center;">Installazione fallita</h2>
            <p style="color:var(--text-muted);">Qualcosa è andato storto. Controlla i permessi dei file e riprova.</p>
            <a href="?step=1" class="btn btn-primary" style="margin-top:16px;">Ricomincia</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p style="text-align:center;color:var(--text-muted);font-size:0.7rem;margin-top:20px;">
        O-CMS v1.0.0 &mdash; Un CMS flat-file di <a href="https://github.com/b84an/o-cms" style="color:var(--primary);text-decoration:none;">Ivan Bertotto</a>
    </p>
</div>
</body>
</html>
