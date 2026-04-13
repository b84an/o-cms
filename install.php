<?php
/**
 * O-CMS Installation Wizard
 *
 * A multi-step wizard that guides you through the initial setup of O-CMS.
 * This file should be deleted after installation for security.
 *
 * Steps:
 *   1. Server requirements check
 *   2. Site configuration (name, URL, language, timezone)
 *   3. Admin account creation
 *   4. SMTP / Email setup (optional)
 *   5. Phone home disclosure (transparent opt-in)
 *   6. Summary and confirmation
 *   7. Installation complete
 *
 * @package O-CMS
 * @version 1.0.0
 */

set_time_limit(120);
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Already installed? Block access.
if (file_exists(__DIR__ . '/data/.installed')) {
    die('O-CMS is already installed. For security, delete install.php from your server.');
}

session_start();

// ─── CSRF PROTECTION ───

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

// ─── HELPER FUNCTIONS ───

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

    // PHP version
    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks[] = ['name' => 'PHP >= 8.0', 'ok' => $phpOk, 'value' => PHP_VERSION, 'required' => true];

    // Required extensions
    foreach (['json', 'mbstring', 'session', 'fileinfo'] as $ext) {
        $loaded = extension_loaded($ext);
        $checks[] = ['name' => "Extension: {$ext}", 'ok' => $loaded, 'value' => $loaded ? 'Installed' : 'Missing', 'required' => true];
    }

    // Optional extensions
    $optDescriptions = [
        'zip'  => 'Needed for backups and extension installation',
        'intl' => 'Better internationalization support',
        'gd'   => 'Image resizing and thumbnail generation',
        'curl' => 'AI integration and external API calls',
    ];
    foreach ($optDescriptions as $ext => $desc) {
        $loaded = extension_loaded($ext);
        $checks[] = ['name' => "Extension: {$ext}", 'ok' => $loaded, 'value' => $loaded ? 'Installed' : "Missing — {$desc}", 'required' => false];
    }

    // mod_rewrite
    $modRewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : null;
    $checks[] = [
        'name' => 'Apache mod_rewrite',
        'ok' => $modRewrite !== false,
        'value' => $modRewrite === true ? 'Enabled' : ($modRewrite === null ? 'Cannot verify (likely OK)' : 'Disabled'),
        'required' => false,
    ];

    // Writable directory
    $writable = is_writable(__DIR__);
    $checks[] = ['name' => 'Directory writable', 'ok' => $writable, 'value' => $writable ? 'Yes' : 'No — insufficient permissions', 'required' => true];

    // Disk space
    $freeSpace = @disk_free_space(__DIR__) ?: 0;
    $hasSpace = $freeSpace > 20 * 1024 * 1024;
    $checks[] = ['name' => 'Disk space >= 20 MB', 'ok' => $hasSpace, 'value' => round($freeSpace / (1024 * 1024)) . ' MB free', 'required' => true];

    // Memory limit
    $memLimit = ini_get('memory_limit');
    $memBytes = convertToBytes($memLimit);
    $memOk = $memBytes >= 64 * 1024 * 1024 || $memBytes === -1;
    $checks[] = ['name' => 'Memory limit >= 64 MB', 'ok' => $memOk, 'value' => $memLimit, 'required' => false];

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

// ─── STEP MANAGEMENT ───

$step = (int)($_POST['_step'] ?? $_GET['step'] ?? 1);
$errors = [];
$data = $_SESSION['_install_data'] ?? [];

// Process POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && install_csrf_verify()) {
    switch ($step) {
        case 2: // Site config submitted
            $data['site_name']   = trim($_POST['site_name'] ?? 'My Website');
            $data['site_url']    = rtrim(trim($_POST['site_url'] ?? ''), '/');
            $data['language']    = $_POST['language'] ?? 'en';
            $data['timezone']    = $_POST['timezone'] ?? 'UTC';
            $data['date_format'] = $_POST['date_format'] ?? 'Y-m-d';
            if (empty($data['site_name'])) $errors[] = 'Site name is required.';
            if (empty($errors)) { $_SESSION['_install_data'] = $data; $step = 3; }
            break;

        case 3: // Admin account submitted
            $data['admin_user']    = trim($_POST['admin_user'] ?? '');
            $data['admin_email']   = trim($_POST['admin_email'] ?? '');
            $data['admin_pass']    = $_POST['admin_pass'] ?? '';
            $data['admin_pass2']   = $_POST['admin_pass2'] ?? '';
            $data['admin_display'] = trim($_POST['admin_display'] ?? '') ?: $data['admin_user'];
            if (empty($data['admin_user']) || !preg_match('/^[a-z0-9_-]{3,30}$/', $data['admin_user'])) {
                $errors[] = 'Username must be 3-30 characters, lowercase letters, numbers, - and _ only.';
            }
            if (empty($data['admin_email']) || !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid email address is required.';
            }
            if (strlen($data['admin_pass']) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if ($data['admin_pass'] !== $data['admin_pass2']) {
                $errors[] = 'Passwords do not match.';
            }
            if (empty($errors)) { $_SESSION['_install_data'] = $data; $step = 4; }
            break;

        case 4: // SMTP submitted
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

        case 5: // Phone home submitted
            $data['phone_home'] = !empty($_POST['phone_home']);
            $_SESSION['_install_data'] = $data;
            $step = 6;
            break;

        case 6: // Summary confirmed → run installation
            $step = 7;
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !install_csrf_verify()) {
    $errors[] = 'Invalid security token. Please try again.';
}

// Allow navigating back via GET (only when not processing a POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['step'])) {
    $s = (int)$_GET['step'];
    if ($s >= 1 && $s <= 6) $step = $s;
}

// ─── INSTALLATION LOGIC (Step 7) ───

$installSuccess = false;
$installWarnings = [];

if ($step === 7) {
    $baseDir = __DIR__;

    // Create all data directories
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
            $installWarnings[] = "Failed to create directory: {$d}";
        }
    }

    // Protect data directory
    $htaccess = $baseDir . '/data/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "# Protect data directory\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }

    $now = date('c');
    $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    // 1. Config
    $config = [
        'site_name'            => $data['site_name'] ?? 'My Website',
        'site_description'     => '',
        'site_url'             => $data['site_url'] ?? '',
        'theme'                => 'flavor',
        'language'             => $data['language'] ?? 'en',
        'timezone'             => $data['timezone'] ?? 'UTC',
        'date_format'          => $data['date_format'] ?? 'Y-m-d',
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
        'phone_home_allowed'    => !empty($data['phone_home']),
        'installation_notified' => false,
        'ocms_version'          => '1.0.0',
    ];
    if (file_put_contents($baseDir . '/data/config.json', json_encode($config, $jsonFlags)) === false) {
        $installWarnings[] = 'Failed to write config.json';
    }

    // 2. Admin user
    $adminUser = $data['admin_user'] ?? 'admin';
    $user = [
        'id'           => bin2hex(random_bytes(16)),
        'username'     => $adminUser,
        'email'        => $data['admin_email'] ?? '',
        'password'     => password_hash($data['admin_pass'] ?? 'changeme', PASSWORD_DEFAULT),
        'display_name' => $data['admin_display'] ?? 'Administrator',
        'role'         => 'super_administrator',
        'avatar'       => '',
        'active'       => true,
        'api_tokens'   => [],
        'created_at'   => $now,
        'last_login'   => null,
    ];
    if (file_put_contents($baseDir . '/data/users/' . $adminUser . '.json', json_encode($user, $jsonFlags)) === false) {
        $installWarnings[] = 'Failed to write admin user file';
    }

    // 3. Home page
    $homePage = [
        'id'         => bin2hex(random_bytes(16)),
        'title'      => 'Welcome',
        'slug'       => 'home',
        'content'    => '<h2>Welcome to ' . e($data['site_name'] ?? 'O-CMS') . '</h2>'
                      . '<p>Your new website is ready! Head over to the <a href="' . e($data['site_url'] ?? '') . '/admin/">admin panel</a> to start creating content.</p>'
                      . '<p>O-CMS is a flat-file CMS — no database needed. Everything is stored in simple JSON files.</p>',
        'template'   => 'home',
        'layout'     => 'none',
        'status'     => 'published',
        'meta'       => ['title' => '', 'description' => 'Welcome to ' . ($data['site_name'] ?? 'O-CMS'), 'og_image' => ''],
        'order'      => 0,
        'parent'     => null,
        'author'     => $adminUser,
        'created_at' => $now,
        'updated_at' => $now,
        'views'      => 0,
    ];
    if (file_put_contents($baseDir . '/data/pages/home.json', json_encode($homePage, $jsonFlags)) === false) {
        $installWarnings[] = 'Failed to write home page';
    }

    // 4. About page
    $aboutPage = [
        'id'         => bin2hex(random_bytes(16)),
        'title'      => 'About',
        'slug'       => 'about',
        'content'    => '<p>This website is powered by <strong>O-CMS</strong>, a lightweight flat-file content management system.</p>'
                      . '<p>Edit this page from the admin panel to tell visitors about yourself or your project.</p>',
        'template'   => 'page',
        'layout'     => 'none',
        'status'     => 'published',
        'meta'       => ['title' => 'About', 'description' => 'About this website', 'og_image' => ''],
        'order'      => 1,
        'parent'     => null,
        'author'     => $adminUser,
        'created_at' => $now,
        'updated_at' => $now,
        'views'      => 0,
    ];
    if (file_put_contents($baseDir . '/data/pages/about.json', json_encode($aboutPage, $jsonFlags)) === false) {
        $installWarnings[] = 'Failed to write about page';
    }

    // 5. Sample article
    $article = [
        'id'          => bin2hex(random_bytes(16)),
        'title'       => 'Getting Started with O-CMS',
        'slug'        => 'getting-started-with-ocms',
        'excerpt'     => 'A quick guide to setting up your new O-CMS website.',
        'content'     => '<p>Welcome to O-CMS! Here are a few things to get you started.</p>'
                       . '<h3>Customize Your Settings</h3>'
                       . '<p>Head to <strong>Settings</strong> in the admin panel to configure your site name, description, theme, and more.</p>'
                       . '<h3>Create Content</h3>'
                       . '<p>Use <strong>Pages</strong> for static content (like an About page) and <strong>Articles</strong> for your blog posts.</p>'
                       . '<h3>Manage Your Menu</h3>'
                       . '<p>The <strong>Menu Builder</strong> lets you organize your navigation with drag-and-drop simplicity.</p>'
                       . '<h3>Choose a Theme</h3>'
                       . '<p>O-CMS ships with the Flavor theme. You can create your own using the built-in Theme Wizard, or install one from a ZIP file.</p>',
        'cover_image' => '',
        'category'    => 'general',
        'tags'        => ['getting-started'],
        'status'      => 'published',
        'meta'        => ['title' => 'Getting Started with O-CMS', 'description' => 'A quick guide to setting up your new O-CMS website.', 'og_image' => ''],
        'author'      => $adminUser,
        'created_at'  => $now,
        'updated_at'  => $now,
        'views'       => 0,
    ];
    if (file_put_contents($baseDir . '/data/articles/getting-started-with-ocms.json', json_encode($article, $jsonFlags)) === false) {
        $installWarnings[] = 'Failed to write sample article';
    }

    // 6. Default category
    $category = [
        'id'          => bin2hex(random_bytes(16)),
        'name'        => 'General',
        'slug'        => 'general',
        'description' => 'General posts and updates',
        'parent'      => null,
        'order'       => 0,
        'created_at'  => $now,
    ];
    if (file_put_contents($baseDir . '/data/categories/general.json', json_encode($category, $jsonFlags)) === false) {
        $installWarnings[] = 'Failed to write default category';
    }

    // 7. Default tag
    $tag = [
        'id'         => bin2hex(random_bytes(16)),
        'name'       => 'Getting Started',
        'slug'       => 'getting-started',
        'created_at' => $now,
    ];
    if (file_put_contents($baseDir . '/data/tags/getting-started.json', json_encode($tag, $jsonFlags)) === false) {
        $installWarnings[] = 'Failed to write default tag';
    }

    // 8. Main menu
    $menu = [
        'name'  => 'main',
        'label' => 'Main Menu',
        'items' => [
            ['id' => bin2hex(random_bytes(8)), 'label' => 'Home',  'url' => '/',      'target' => '_self', 'published' => true, 'children' => []],
            ['id' => bin2hex(random_bytes(8)), 'label' => 'Blog',  'url' => '/blog',  'target' => '_self', 'published' => true, 'children' => []],
            ['id' => bin2hex(random_bytes(8)), 'label' => 'About', 'url' => '/about', 'target' => '_self', 'published' => true, 'children' => []],
        ],
    ];
    if (file_put_contents($baseDir . '/data/menus/main.json', json_encode($menu, $jsonFlags)) === false) {
        $installWarnings[] = 'Failed to write main menu';
    }

    // 9. Lock file
    if (file_put_contents($baseDir . '/data/.installed', date('c')) === false) {
        $installWarnings[] = 'Failed to write lock file';
    }

    // Only mark as success if no critical files failed
    $criticalFiles = ['config.json', 'admin user file', 'lock file'];
    $hasCriticalFailure = false;
    foreach ($installWarnings as $w) {
        foreach ($criticalFiles as $cf) {
            if (stripos($w, $cf) !== false) { $hasCriticalFailure = true; break 2; }
        }
    }
    $installSuccess = !$hasCriticalFailure;

    // Clean up session
    unset($_SESSION['_install_data'], $_SESSION['_install_csrf']);
}

// Requirements (needed for step 1 display)
$checks = checkRequirements();
$allRequiredOk = !in_array(false, array_map(fn($c) => !$c['required'] || $c['ok'], $checks));

// Step labels for the progress bar
$stepLabels = [1 => 'Requirements', 2 => 'Site', 3 => 'Admin', 4 => 'Email', 5 => 'Privacy', 6 => 'Summary', 7 => 'Done'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O-CMS — Installation Wizard</title>
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

        /* Steps bar */
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

        /* Check list */
        .check-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 0.875rem; }
        .check-item:last-child { border-bottom: none; }
        .check-icon { width: 20px; text-align: center; font-size: 1rem; flex-shrink: 0; }
        .check-name { flex: 1; font-weight: 500; }
        .check-value { color: var(--text-muted); font-size: 0.8rem; text-align: right; }

        /* Forms */
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

        /* Buttons */
        .btn { display: inline-block; padding: 12px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; border: none; font-family: inherit; transition: all 0.15s; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.3); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-secondary { background: var(--bg); color: var(--text); border: 1px solid var(--border); }
        .btn-row { display: flex; gap: 12px; justify-content: space-between; }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem; line-height: 1.5; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
        .alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); color: #fcd34d; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #86efac; }
        .alert-info { background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.3); color: #a5b4fc; }

        /* Summary table */
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
        <p>Installation Wizard</p>
    </div>

    <!-- Steps bar -->
    <div class="steps">
        <?php foreach ($stepLabels as $num => $label): ?>
        <div class="step-item <?= $num === $step ? 'active' : ($num < $step ? 'done' : '') ?>">
            <?= $num ?>. <?= $label ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Errors -->
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 1): ?>
    <!-- ═══ STEP 1: REQUIREMENTS ═══ -->
    <div class="card">
        <h2>Server Requirements</h2>
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
            <div class="alert alert-success">All required checks passed!</div>
            <a href="?step=2" class="btn btn-primary" style="display:block;text-align:center;">Continue &rarr;</a>
        <?php else: ?>
            <div class="alert alert-error">Some required checks failed. Fix them before continuing.</div>
            <button class="btn btn-primary" disabled>Requirements not met</button>
        <?php endif; ?>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- ═══ STEP 2: SITE CONFIGURATION ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="2">

        <div class="card">
            <h2>Site Configuration</h2>

            <div class="form-group">
                <label>Site Name *</label>
                <input type="text" name="site_name" class="form-input" value="<?= e($data['site_name'] ?? 'My Website') ?>" required>
            </div>

            <div class="form-group">
                <label>Base URL Path *</label>
                <input type="text" name="site_url" class="form-input" value="<?= e($data['site_url'] ?? detectBaseUrl()) ?>" placeholder="Leave empty if installed at domain root">
                <div class="form-hint">The path from your domain root. Example: <code>/cms</code> or <code>/blog</code>. Leave empty if O-CMS is at the root.</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Language</label>
                    <select name="language" class="form-select form-input">
                        <?php
                        $langs = ['en' => 'English', 'it' => 'Italiano', 'es' => 'Espa&ntilde;ol', 'fr' => 'Fran&ccedil;ais', 'de' => 'Deutsch', 'pt' => 'Portugu&ecirc;s'];
                        $sel = $data['language'] ?? 'en';
                        foreach ($langs as $code => $name):
                        ?>
                        <option value="<?= $code ?>" <?= $sel === $code ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Timezone</label>
                    <select name="timezone" class="form-select form-input">
                        <?php
                        $zones = ['UTC', 'Europe/London', 'Europe/Rome', 'Europe/Paris', 'Europe/Berlin', 'Europe/Madrid',
                                  'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
                                  'America/Sao_Paulo', 'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Kolkata', 'Australia/Sydney'];
                        $selTz = $data['timezone'] ?? 'UTC';
                        foreach ($zones as $tz):
                        ?>
                        <option value="<?= $tz ?>" <?= $selTz === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Date Format</label>
                <select name="date_format" class="form-select form-input">
                    <?php
                    $formats = ['Y-m-d' => 'YYYY-MM-DD (2026-04-13)', 'd/m/Y' => 'DD/MM/YYYY (13/04/2026)', 'm/d/Y' => 'MM/DD/YYYY (04/13/2026)', 'd.m.Y' => 'DD.MM.YYYY (13.04.2026)'];
                    $selFmt = $data['date_format'] ?? 'Y-m-d';
                    foreach ($formats as $fmt => $label):
                    ?>
                    <option value="<?= $fmt ?>" <?= $selFmt === $fmt ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="btn-row">
            <a href="?step=1" class="btn btn-secondary">&larr; Back</a>
            <button type="submit" class="btn btn-primary">Continue &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 3): ?>
    <!-- ═══ STEP 3: ADMIN ACCOUNT ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="3">

        <div class="card">
            <h2>Admin Account</h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Create the first administrator account for your site.
            </p>

            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="admin_user" class="form-input" value="<?= e($data['admin_user'] ?? '') ?>" pattern="[a-z0-9_-]{3,30}" required placeholder="admin">
                <div class="form-hint">Lowercase letters, numbers, hyphens, and underscores only (3-30 chars)</div>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="admin_email" class="form-input" value="<?= e($data['admin_email'] ?? '') ?>" required placeholder="you@example.com">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="admin_pass" class="form-input" required minlength="8" placeholder="Min. 8 characters">
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="admin_pass2" class="form-input" required minlength="8" placeholder="Repeat password">
                </div>
            </div>

            <div class="form-group">
                <label>Display Name</label>
                <input type="text" name="admin_display" class="form-input" value="<?= e($data['admin_display'] ?? '') ?>" placeholder="Defaults to username">
            </div>
        </div>

        <div class="btn-row">
            <a href="?step=2" class="btn btn-secondary">&larr; Back</a>
            <button type="submit" class="btn btn-primary">Continue &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 4): ?>
    <!-- ═══ STEP 4: EMAIL / SMTP ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="4">

        <div class="card">
            <h2>Email Configuration</h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Configure how O-CMS sends emails (contact forms, notifications, password resets).
                You can change this later in Settings.
            </p>

            <div class="form-group">
                <label>Email Method</label>
                <select name="smtp_method" id="smtpMethod" class="form-select form-input" onchange="toggleSmtp()">
                    <option value="php_mail" <?= ($data['smtp_method'] ?? 'php_mail') === 'php_mail' ? 'selected' : '' ?>>PHP mail() — Default</option>
                    <option value="smtp" <?= ($data['smtp_method'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP Server</option>
                </select>
                <div class="form-hint">PHP mail() works on most shared hosting. Use SMTP for better deliverability.</div>
            </div>

            <div id="smtpFields" class="smtp-fields">
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-input" value="<?= e($data['smtp_host'] ?? '') ?>" placeholder="mail.example.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-input" value="<?= e($data['smtp_port'] ?? '587') ?>" placeholder="587">
                    </div>
                </div>

                <div class="form-group">
                    <label>Encryption</label>
                    <select name="smtp_encryption" class="form-select form-input">
                        <option value="tls" <?= ($data['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (recommended)</option>
                        <option value="ssl" <?= ($data['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($data['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
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
                        <label>From Email</label>
                        <input type="email" name="smtp_from_email" class="form-input" value="<?= e($data['smtp_from_email'] ?? '') ?>" placeholder="noreply@example.com">
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" class="form-input" value="<?= e($data['smtp_from_name'] ?? '') ?>" placeholder="My Website">
                    </div>
                </div>
            </div>
        </div>

        <div class="btn-row">
            <a href="?step=3" class="btn btn-secondary">&larr; Back</a>
            <button type="submit" class="btn btn-primary">Continue &rarr;</button>
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
    <!-- ═══ STEP 5: PHONE HOME DISCLOSURE ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="5">

        <div class="card">
            <h2>Installation Notification</h2>

            <div class="alert alert-info">
                <strong>Transparency notice</strong>
            </div>

            <p style="color:var(--text-muted);font-size:0.9rem;line-height:1.7;margin-bottom:16px;">
                O-CMS includes an optional feature that sends a <strong>one-time anonymous notification</strong>
                to the project maintainer when a new instance is installed. This helps track adoption and
                prioritize development efforts.
            </p>

            <div style="background:var(--bg);border-radius:8px;padding:16px;margin-bottom:16px;">
                <p style="font-size:0.85rem;font-weight:600;margin-bottom:8px;">Data sent (one time only):</p>
                <ul style="font-size:0.85rem;color:var(--text-muted);padding-left:20px;line-height:1.8;">
                    <li>Your domain name (e.g. <code>example.com</code>)</li>
                    <li>PHP version (e.g. <code><?= PHP_VERSION ?></code>)</li>
                    <li>O-CMS version (e.g. <code>1.0.0</code>)</li>
                </ul>
            </div>

            <p style="color:var(--text-muted);font-size:0.85rem;line-height:1.6;margin-bottom:20px;">
                No personal information, no IP tracking, no cookies, no recurring calls.
                You can disable this at any time in <strong>Settings</strong> by setting
                <code>phone_home_allowed</code> to <code>false</code> in your config.
            </p>

            <div class="checkbox-row">
                <input type="checkbox" name="phone_home" id="phoneHome" value="1" checked>
                <label for="phoneHome" style="font-size:0.9rem;cursor:pointer;">
                    <strong>Allow one-time installation notification</strong><br>
                    <span style="color:var(--text-muted);font-size:0.8rem;">Help the O-CMS project by letting the developer know you're using it.</span>
                </label>
            </div>
        </div>

        <div class="btn-row">
            <a href="?step=4" class="btn btn-secondary">&larr; Back</a>
            <button type="submit" class="btn btn-primary">Continue &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 6): ?>
    <!-- ═══ STEP 6: SUMMARY ═══ -->
    <form method="POST">
        <?= install_csrf_field() ?>
        <input type="hidden" name="_step" value="6">

        <div class="card">
            <h2>Installation Summary</h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Review your settings before installing. Click a section title to go back and edit.
            </p>

            <table class="summary-table">
                <tr><td><a href="?step=2" style="color:var(--primary);text-decoration:none;">Site Name</a></td><td><?= e($data['site_name'] ?? '') ?></td></tr>
                <tr><td>Base URL</td><td><code><?= e($data['site_url'] ?? '/') ?: '/' ?></code></td></tr>
                <tr><td>Language</td><td><?= e($data['language'] ?? 'en') ?></td></tr>
                <tr><td>Timezone</td><td><?= e($data['timezone'] ?? 'UTC') ?></td></tr>
                <tr><td>Date Format</td><td><code><?= e($data['date_format'] ?? 'Y-m-d') ?></code></td></tr>
                <tr><td colspan="2" style="height:8px;border:none;"></td></tr>
                <tr><td><a href="?step=3" style="color:var(--primary);text-decoration:none;">Admin User</a></td><td><?= e($data['admin_user'] ?? '') ?></td></tr>
                <tr><td>Admin Email</td><td><?= e($data['admin_email'] ?? '') ?></td></tr>
                <tr><td colspan="2" style="height:8px;border:none;"></td></tr>
                <tr><td><a href="?step=4" style="color:var(--primary);text-decoration:none;">Email Method</a></td><td><?= ($data['smtp_method'] ?? 'php_mail') === 'smtp' ? 'SMTP (' . e($data['smtp_host'] ?? '') . ')' : 'PHP mail()' ?></td></tr>
                <tr><td colspan="2" style="height:8px;border:none;"></td></tr>
                <tr><td><a href="?step=5" style="color:var(--primary);text-decoration:none;">Phone Home</a></td><td><?= !empty($data['phone_home']) ? '&#9989; Enabled' : '&#10060; Disabled' ?></td></tr>
            </table>
        </div>

        <div class="btn-row">
            <a href="?step=5" class="btn btn-secondary">&larr; Back</a>
            <button type="submit" class="btn btn-primary">Install O-CMS &rarr;</button>
        </div>
    </form>

    <?php elseif ($step === 7): ?>
    <!-- ═══ STEP 7: COMPLETION ═══ -->
    <div class="card" style="text-align:center;">
        <?php if ($installSuccess): ?>
            <div class="success-icon">&#127881;</div>
            <h2 style="text-align:center;">Installation Complete!</h2>
            <p style="color:var(--text-muted);margin-bottom:24px;">
                O-CMS has been installed successfully. Your site is ready to use.
            </p>

            <?php if (!empty($installWarnings)): ?>
            <div class="alert alert-warning" style="text-align:left;">
                <strong>Some non-critical files could not be created:</strong><br>
                <?php foreach ($installWarnings as $w): ?>
                    &bull; <?= e($w) ?><br>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="alert alert-warning" style="text-align:left;">
                <strong>Important — do this now:</strong><br>
                1. <strong>Delete <code>install.php</code></strong> from your server for security<br>
                2. Your admin login: <strong><?= e($data['admin_user'] ?? 'admin') ?></strong> with the password you chose
            </div>

            <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;">
                <a href="<?= e($data['site_url'] ?? '') ?>/" class="btn btn-secondary">Visit Site</a>
                <a href="<?= e($data['site_url'] ?? '') ?>/admin/" class="btn btn-primary">Go to Admin Panel &rarr;</a>
            </div>
        <?php else: ?>
            <div class="success-icon">&#9888;&#65039;</div>
            <h2 style="text-align:center;">Installation Failed</h2>
            <p style="color:var(--text-muted);">Something went wrong. Check file permissions and try again.</p>
            <a href="?step=1" class="btn btn-primary" style="margin-top:16px;">Start Over</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p style="text-align:center;color:var(--text-muted);font-size:0.7rem;margin-top:20px;">
        O-CMS v1.0.0 &mdash; A flat-file CMS by <a href="https://github.com/b84an/o-cms" style="color:var(--primary);text-decoration:none;">Ivan Bertotto</a>
    </p>
</div>
</body>
</html>
