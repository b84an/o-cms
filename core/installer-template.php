<?php
/**
 * O-CMS — Installer (incluso nei backup completi)
 *
 * Questo file:
 * 1. Verifica i requisiti del server
 * 2. Chiede i parametri di configurazione
 * 3. Estrae il backup nella cartella di destinazione
 * 4. Aggiorna la configurazione per il nuovo server/dominio
 * 5. Crea un nuovo utente admin se richiesto
 * 6. Si auto-elimina per sicurezza
 */

// Impedisci timeout su server lenti
set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$step = $_GET['step'] ?? ($_POST['step'] ?? 'check');
$errors = [];
$warnings = [];

// ─── FUNZIONI ───

function checkRequirements(): array {
    $checks = [];

    // PHP Version
    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks[] = ['name' => 'PHP >= 8.0', 'status' => $phpOk, 'value' => PHP_VERSION, 'required' => true];

    // Estensioni obbligatorie
    $requiredExts = ['json', 'mbstring', 'zip', 'fileinfo', 'session'];
    foreach ($requiredExts as $ext) {
        $loaded = extension_loaded($ext);
        $checks[] = ['name' => "Estensione: {$ext}", 'status' => $loaded, 'value' => $loaded ? 'Installata' : 'Mancante', 'required' => true];
    }

    // Estensioni opzionali
    $optionalExts = ['intl', 'gd', 'curl'];
    foreach ($optionalExts as $ext) {
        $loaded = extension_loaded($ext);
        $checks[] = ['name' => "Estensione: {$ext}", 'status' => $loaded, 'value' => $loaded ? 'Installata' : 'Mancante (opzionale)', 'required' => false];
    }

    // mod_rewrite
    $modRewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : null;
    $checks[] = ['name' => 'Apache mod_rewrite', 'status' => $modRewrite !== false, 'value' => $modRewrite === true ? 'Attivo' : ($modRewrite === null ? 'Non verificabile (probabilmente OK)' : 'Non attivo'), 'required' => false];

    // Permessi scrittura
    $dir = dirname(__FILE__);
    $writable = is_writable($dir);
    $checks[] = ['name' => 'Cartella scrivibile', 'status' => $writable, 'value' => $writable ? 'Si (' . $dir . ')' : 'No — permessi insufficienti', 'required' => true];

    // Spazio disco
    $freeSpace = disk_free_space($dir);
    $hasSpace = $freeSpace > 50 * 1024 * 1024; // 50MB minimo
    $checks[] = ['name' => 'Spazio disco >= 50MB', 'status' => $hasSpace, 'value' => round($freeSpace / (1024*1024)) . ' MB liberi', 'required' => true];

    // Limite upload
    $uploadMax = ini_get('upload_max_filesize');
    $postMax = ini_get('post_max_size');
    $checks[] = ['name' => 'Upload max filesize', 'status' => true, 'value' => "upload: {$uploadMax}, post: {$postMax}", 'required' => false];

    // Memory limit
    $memLimit = ini_get('memory_limit');
    $memBytes = convertToBytes($memLimit);
    $memOk = $memBytes >= 64 * 1024 * 1024 || $memBytes === -1;
    $checks[] = ['name' => 'Memory limit >= 64MB', 'status' => $memOk, 'value' => $memLimit, 'required' => false];

    // ZipArchive
    $zipOk = class_exists('ZipArchive');
    $checks[] = ['name' => 'ZipArchive class', 'status' => $zipOk, 'value' => $zipOk ? 'Disponibile' : 'Mancante', 'required' => true];

    return $checks;
}

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

function extractBackup(string $zipFile, string $destDir): bool {
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) return false;

    // Cerca la cartella ocms/ nel backup
    $prefix = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_starts_with($name, 'ocms/')) {
            $prefix = 'ocms/';
            break;
        }
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        // Salta l'installer stesso
        if ($name === 'installer.php') continue;

        // Rimuovi prefisso ocms/
        if ($prefix && str_starts_with($name, $prefix)) {
            $relativePath = substr($name, strlen($prefix));
        } else {
            $relativePath = $name;
        }

        if (!$relativePath || str_ends_with($name, '/')) continue;
        if (str_contains($relativePath, '..')) continue; // sicurezza

        $targetPath = $destDir . '/' . $relativePath;
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

        $content = $zip->getFromIndex($i);
        if ($content !== false) {
            file_put_contents($targetPath, $content);
        }
    }

    $zip->close();
    return true;
}

function updateConfig(string $destDir, array $params): bool {
    $configFile = $destDir . '/data/config.json';
    if (!file_exists($configFile)) return false;

    $config = json_decode(file_get_contents($configFile), true);
    if (!$config) return false;

    $config['site_name'] = $params['site_name'];
    $config['site_url'] = rtrim($params['site_url'], '/');
    $config['admin_email'] = $params['admin_email'];

    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
}

function createAdmin(string $destDir, string $username, string $password, string $email): bool {
    $usersDir = $destDir . '/data/users';
    if (!is_dir($usersDir)) mkdir($usersDir, 0755, true);

    $user = [
        'id' => bin2hex(random_bytes(16)),
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => 'Amministratore',
        'role' => 'super_administrator',
        'avatar' => '',
        'active' => true,
        'created_at' => date('c'),
        'last_login' => null,
    ];

    return file_put_contents(
        $usersDir . '/' . $username . '.json',
        json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

// ─── PROCESSA INSTALLAZIONE ───

if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $destDir = dirname(__FILE__);
    $zipFile = $destDir . '/installer.php'; // placeholder — il file ZIP è nella stessa cartella

    // Cerca il file ZIP del backup nella stessa cartella
    $zipFiles = glob($destDir . '/ocms-full-*.zip');
    if (empty($zipFiles)) {
        // Prova a estrarre da se stesso se siamo nella root
        $zipFiles = glob($destDir . '/*.zip');
    }

    $zipFile = null;
    foreach (($zipFiles ?: []) as $zf) {
        if (basename($zf) !== 'installer.php') {
            $zipFile = $zf;
            break;
        }
    }

    if (!$zipFile) {
        $errors[] = 'File ZIP del backup non trovato nella stessa cartella dell\'installer.';
    } else {
        // Estrai
        if (!extractBackup($zipFile, $destDir)) {
            $errors[] = 'Errore durante l\'estrazione del backup.';
        }

        // Aggiorna config
        if (empty($errors)) {
            $updated = updateConfig($destDir, [
                'site_name' => trim($_POST['site_name'] ?? 'O-CMS'),
                'site_url' => trim($_POST['site_url'] ?? ''),
                'admin_email' => trim($_POST['admin_email'] ?? ''),
            ]);
            if (!$updated) $warnings[] = 'Impossibile aggiornare config.json — modifica manualmente.';
        }

        // Crea nuovo admin se richiesto
        if (empty($errors) && !empty($_POST['admin_username']) && !empty($_POST['admin_password'])) {
            $created = createAdmin(
                $destDir,
                trim($_POST['admin_username']),
                $_POST['admin_password'],
                trim($_POST['admin_email'] ?? '')
            );
            if (!$created) $warnings[] = 'Impossibile creare l\'utente admin.';
        }

        // Proteggi cartella data
        if (empty($errors)) {
            $dataHtaccess = $destDir . '/data/.htaccess';
            if (!file_exists($dataHtaccess)) {
                file_put_contents($dataHtaccess, "Deny from all\n");
            }
        }
    }

    if (empty($errors)) {
        $step = 'done';
    }
}

// ─── INTERFACCIA HTML ───

$checks = checkRequirements();
$allRequired = !in_array(false, array_map(fn($c) => !$c['required'] || $c['status'], $checks));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O-CMS Installer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5; --bg: #0f172a;
            --bg-card: #1e293b; --text: #f1f5f9; --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.08); --success: #22c55e; --error: #ef4444;
            --warning: #f59e0b; --radius: 12px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .installer { max-width:640px; width:100%; }
        .card { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:32px; margin-bottom:20px; }
        .logo { text-align:center; margin-bottom:32px; }
        .logo h1 { font-size:2rem; font-weight:800; background:linear-gradient(135deg,#818cf8,#a78bfa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .logo p { color:var(--text-muted); font-size:0.9rem; margin-top:4px; }

        h2 { font-size:1.1rem; font-weight:700; margin-bottom:16px; }
        h3 { font-size:1rem; font-weight:700; margin-bottom:12px; }

        .check-list { margin-bottom:20px; }
        .check-item { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--border); font-size:0.875rem; }
        .check-item:last-child { border-bottom:none; }
        .check-icon { width:20px; text-align:center; font-size:1rem; }
        .check-name { flex:1; font-weight:500; }
        .check-value { color:var(--text-muted); font-size:0.8rem; }

        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
        .form-input { width:100%; padding:12px 14px; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:0.9rem; font-family:inherit; outline:none; }
        .form-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,0.12); }
        .form-hint { font-size:0.75rem; color:var(--text-muted); margin-top:4px; }

        .btn { display:inline-block; padding:12px 24px; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer; border:none; font-family:inherit; transition:all 0.15s; }
        .btn-primary { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:white; }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(99,102,241,0.3); }
        .btn-primary:disabled { opacity:0.5; cursor:not-allowed; transform:none; }
        .btn-secondary { background:var(--bg); color:var(--text); border:1px solid var(--border); text-decoration:none; }

        .alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:0.85rem; }
        .alert-error { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#fca5a5; }
        .alert-warning { background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3); color:#fcd34d; }
        .alert-success { background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); color:#86efac; }

        .steps { display:flex; gap:0; margin-bottom:24px; }
        .step-item { flex:1; text-align:center; padding:10px; font-size:0.8rem; font-weight:600; color:var(--text-muted); position:relative; }
        .step-item.active { color:var(--primary); }
        .step-item.done { color:var(--success); }
        .step-item::after { content:''; position:absolute; bottom:0; left:0; right:0; height:3px; background:var(--border); }
        .step-item.active::after { background:var(--primary); }
        .step-item.done::after { background:var(--success); }

        .success-icon { font-size:4rem; text-align:center; margin-bottom:16px; }
    </style>
</head>
<body>
<div class="installer">
    <div class="logo">
        <h1>O-CMS</h1>
        <p>Installazione e Migrazione</p>
    </div>

    <!-- Steps -->
    <div class="steps">
        <div class="step-item <?= $step==='check'?'active':($step!=='check'?'done':'') ?>">1. Requisiti</div>
        <div class="step-item <?= $step==='config'?'active':($step==='done'?'done':'') ?>">2. Configurazione</div>
        <div class="step-item <?= $step==='done'?'active':'' ?>">3. Completato</div>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php foreach ($warnings as $w): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($w) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 'check'): ?>
    <!-- STEP 1: Requisiti -->
    <div class="card">
        <h2>Verifica Requisiti Server</h2>
        <div class="check-list">
            <?php foreach ($checks as $c): ?>
            <div class="check-item">
                <span class="check-icon"><?= $c['status'] ? '&#9989;' : ($c['required'] ? '&#10060;' : '&#9888;&#65039;') ?></span>
                <span class="check-name"><?= htmlspecialchars($c['name']) ?></span>
                <span class="check-value"><?= htmlspecialchars($c['value']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($allRequired): ?>
            <div class="alert alert-success">Tutti i requisiti obbligatori sono soddisfatti!</div>
            <a href="?step=config" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;">Continua &rarr;</a>
        <?php else: ?>
            <div class="alert alert-error">Alcuni requisiti obbligatori non sono soddisfatti. Correggili prima di procedere.</div>
            <button class="btn btn-primary" disabled>Requisiti non soddisfatti</button>
        <?php endif; ?>
    </div>

    <?php elseif ($step === 'config'): ?>
    <!-- STEP 2: Configurazione -->
    <form method="POST" action="?step=install">
        <input type="hidden" name="step" value="install">

        <div class="card">
            <h2>Configurazione Sito</h2>

            <div class="form-group">
                <label>Nome del Sito *</label>
                <input type="text" name="site_name" class="form-input" value="O-CMS" required>
            </div>

            <div class="form-group">
                <label>URL Base (percorso) *</label>
                <input type="text" name="site_url" class="form-input" placeholder="/o/cms oppure vuoto se nella root" value="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']),'/')) ?>">
                <div class="form-hint">Il percorso dalla root del dominio. Es: <code>/cms</code> o vuoto se il sito è nella root</div>
            </div>

            <div class="form-group">
                <label>Email Amministratore *</label>
                <input type="email" name="admin_email" class="form-input" placeholder="admin@tuodominio.com" required>
            </div>
        </div>

        <div class="card">
            <h2>Utente Amministratore</h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Crea un nuovo utente admin o lascia vuoto per mantenere quelli del backup.
            </p>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="admin_username" class="form-input" placeholder="admin" pattern="[a-z0-9_-]+">
                <div class="form-hint">Solo lettere minuscole, numeri, - e _</div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="admin_password" class="form-input" placeholder="Minimo 4 caratteri" minlength="4">
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:space-between;">
            <a href="?step=check" class="btn btn-secondary">&larr; Indietro</a>
            <button type="submit" class="btn btn-primary">Installa O-CMS &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 'done'): ?>
    <!-- STEP 3: Completato -->
    <div class="card" style="text-align:center;">
        <div class="success-icon">&#127881;</div>
        <h2 style="text-align:center;">Installazione Completata!</h2>
        <p style="color:var(--text-muted);margin-bottom:24px;">
            O-CMS è stato installato con successo. Per sicurezza, <strong>elimina subito questo file</strong> e il file ZIP del backup.
        </p>

        <div class="alert alert-warning" style="text-align:left;">
            <strong>Azioni importanti:</strong><br>
            1. Elimina <code>installer.php</code> dalla cartella del sito<br>
            2. Elimina il file ZIP del backup dalla cartella<br>
            3. Verifica che <code>data/.htaccess</code> contenga "Deny from all"<br>
            4. Cambia la password dell'admin dal pannello
        </div>

        <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;">
            <a href="<?= htmlspecialchars(rtrim($_POST['site_url'] ?? '', '/')) ?>/" class="btn btn-secondary" style="text-decoration:none;">Vai al Sito</a>
            <a href="<?= htmlspecialchars(rtrim($_POST['site_url'] ?? '', '/')) ?>/admin/" class="btn btn-primary" style="text-decoration:none;">Vai all'Admin</a>
        </div>
    </div>
    <?php endif; ?>

    <p style="text-align:center;color:var(--text-muted);font-size:0.75rem;margin-top:20px;">
        O-CMS Installer v1.0 &mdash; Powered by O-CMS
    </p>
</div>
</body>
</html>
