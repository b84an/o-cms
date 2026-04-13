<?php
/**
 * O-CMS — Extension Manager
 *
 * Manages the full lifecycle of extensions:
 * - Loading and booting active extensions
 * - Installing from ZIP packages
 * - Uninstalling and cleanup
 * - Creating distributable ZIP packages
 * - Manifest validation
 * - Scaffolding new extensions via wizard
 *
 * Each extension lives in extensions/{ext-name}/ and has an extension.json manifest.
 *
 * @package O-CMS
 * @version 1.0.0
 */
class ExtensionManager {
    private App $app;
    private string $extensionsPath;
    /** @var array<string, array> Already booted extensions keyed by ID */
    private array $loaded = [];

    /**
     * @param App $app The main application instance
     */
    public function __construct(App $app) {
        $this->app = $app;
        $this->extensionsPath = ocms_base_path() . '/extensions';
        if (!is_dir($this->extensionsPath)) {
            mkdir($this->extensionsPath, 0755, true);
        }
    }

    /**
     * Load and boot all enabled extensions.
     *
     * @return void
     */
    public function bootAll(): void {
        $extensions = $this->getAll();
        foreach ($extensions as $ext) {
            if (($ext['enabled'] ?? false) === true) {
                $this->boot($ext['id']);
            }
        }
    }

    /**
     * Boot a single extension by ID.
     *
     * @param string $id Extension identifier (directory name)
     * @return bool True if booted successfully
     */
    public function boot(string $id): bool {
        if (isset($this->loaded[$id])) {
            return true;
        }

        $manifest = $this->getManifest($id);
        if (!$manifest) return false;

        $entryPoint = $this->extensionsPath . '/' . $id . '/' . ($manifest['entry_point'] ?? 'boot.php');
        if (!file_exists($entryPoint)) {
            return false;
        }

        // Make variables available to the extension
        $app = $this->app;
        $extension = $manifest;

        try {
            require_once $entryPoint;
            $this->loaded[$id] = $manifest;
            Hooks::trigger('extension.booted', ['id' => $id, 'manifest' => $manifest]);
            return true;
        } catch (\Throwable $e) {
            $this->log($id, 'error', 'Boot failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return all installed extensions (enabled and disabled).
     *
     * @return array List of manifest arrays sorted by name
     */
    public function getAll(): array {
        $extensions = [];
        if (!is_dir($this->extensionsPath)) return [];

        foreach (scandir($this->extensionsPath) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            $manifest = $this->getManifest($dir);
            if ($manifest) {
                $extensions[] = $manifest;
            }
        }

        usort($extensions, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
        return $extensions;
    }

    /**
     * Read an extension's manifest (extension.json).
     *
     * @param string $id Extension identifier
     * @return array|null The manifest data or null if not found
     */
    public function getManifest(string $id): ?array {
        $file = $this->extensionsPath . '/' . basename($id) . '/extension.json';
        if (!file_exists($file)) return null;

        $manifest = json_decode(file_get_contents($file), true);
        if (!$manifest) return null;

        $manifest['id'] = basename($id);
        return $manifest;
    }

    /**
     * Enable an extension.
     *
     * @param string $id Extension identifier
     * @return bool True on success
     */
    public function enable(string $id): bool {
        return $this->setEnabled($id, true);
    }

    /**
     * Disable an extension.
     *
     * @param string $id Extension identifier
     * @return bool True on success
     */
    public function disable(string $id): bool {
        return $this->setEnabled($id, false);
    }

    /**
     * Toggle the enabled state of an extension in its manifest.
     *
     * @param string $id      Extension identifier
     * @param bool   $enabled Whether to enable or disable
     * @return bool True on success
     */
    private function setEnabled(string $id, bool $enabled): bool {
        $manifest = $this->getManifest($id);
        if (!$manifest) return false;

        $manifest['enabled'] = $enabled;
        $file = $this->extensionsPath . '/' . basename($id) . '/extension.json';
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return file_put_contents($file, $json) !== false;
    }

    /**
     * Install an extension from a ZIP file.
     *
     * @param string $zipPath Absolute path to the ZIP file
     * @return array Result with 'success' (bool), optionally 'error', 'id', and 'manifest'
     */
    public function installFromZip(string $zipPath): array {
        if (!file_exists($zipPath)) {
            return ['success' => false, 'error' => 'File ZIP non trovato'];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['success' => false, 'error' => 'Impossibile aprire il file ZIP'];
        }

        // Look for extension.json in the ZIP
        $manifestContent = null;
        $rootDir = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (basename($name) === 'extension.json') {
                $manifestContent = $zip->getFromIndex($i);
                $rootDir = dirname($name);
                if ($rootDir === '.') $rootDir = '';
                break;
            }
        }

        if (!$manifestContent) {
            $zip->close();
            return ['success' => false, 'error' => 'File extension.json non trovato nel pacchetto'];
        }

        $manifest = json_decode($manifestContent, true);
        if (!$manifest) {
            $zip->close();
            return ['success' => false, 'error' => 'extension.json non valido'];
        }

        // Validate manifest
        $validation = $this->validateManifest($manifest);
        if (!$validation['valid']) {
            $zip->close();
            return ['success' => false, 'error' => 'Manifest non valido: ' . implode(', ', $validation['errors'])];
        }

        $extId = ocms_slug($manifest['id'] ?? $manifest['name']);
        $destDir = $this->extensionsPath . '/' . $extId;

        // If it already exists, remove it (upgrade)
        if (is_dir($destDir)) {
            $this->removeDirectory($destDir);
        }

        mkdir($destDir, 0755, true);

        // Extract files
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $relativePath = $rootDir ? substr($name, strlen($rootDir) + 1) : $name;
            if (!$relativePath || str_ends_with($name, '/')) continue;

            // Security: prevent path traversal
            if (str_contains($relativePath, '..')) continue;

            $targetPath = $destDir . '/' . $relativePath;
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            file_put_contents($targetPath, $zip->getFromIndex($i));
        }

        $zip->close();

        // Ensure the extension is disabled by default
        $manifest['id'] = $extId;
        $manifest['enabled'] = false;
        $manifest['installed_at'] = ocms_now();
        file_put_contents(
            $destDir . '/extension.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // Run install script if present
        $installScript = $destDir . '/install.php';
        if (file_exists($installScript)) {
            $app = $this->app;
            $extension = $manifest;
            try {
                require $installScript;
            } catch (\Throwable $e) {
                $this->log($extId, 'warning', 'Install script failed: ' . $e->getMessage());
            }
        }

        $this->log($extId, 'info', 'Extension installed');
        return ['success' => true, 'id' => $extId, 'manifest' => $manifest];
    }

    /**
     * Uninstall an extension (runs uninstall script and removes directory).
     *
     * @param string $id Extension identifier
     * @return bool True if successfully removed
     */
    public function uninstall(string $id): bool {
        $id = basename($id);
        $dir = $this->extensionsPath . '/' . $id;
        if (!is_dir($dir)) return false;

        // Run uninstall script if present
        $uninstallScript = $dir . '/uninstall.php';
        if (file_exists($uninstallScript)) {
            $app = $this->app;
            $extension = $this->getManifest($id);
            try {
                require $uninstallScript;
            } catch (\Throwable $e) {
                $this->log($id, 'warning', 'Uninstall script failed: ' . $e->getMessage());
            }
        }

        $this->removeDirectory($dir);
        $this->log($id, 'info', 'Extension uninstalled');
        return true;
    }

    /**
     * Create a distributable ZIP package of an extension.
     *
     * @param string $id Extension identifier
     * @return string|null Absolute path to the created ZIP, or null on failure
     */
    public function createPackage(string $id): ?string {
        $id = basename($id);
        $dir = $this->extensionsPath . '/' . $id;
        if (!is_dir($dir)) return null;

        $zipName = $id . '.zip';
        $zipPath = ocms_data_path('backups') . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $this->addDirectoryToZip($zip, $dir, $id);
        $zip->close();

        return $zipPath;
    }

    /**
     * Scaffold a new extension from wizard data.
     *
     * @param array $data Wizard form data (name, description, author, etc.)
     * @return array Result with 'success' (bool), optionally 'error', 'id', and 'manifest'
     */
    public function createFromWizard(array $data): array {
        $id = ocms_slug($data['name'] ?? 'my-extension');
        $dir = $this->extensionsPath . '/' . $id;

        if (is_dir($dir)) {
            return ['success' => false, 'error' => "L'estensione '{$id}' esiste già"];
        }

        mkdir($dir, 0755, true);

        // Build manifest
        $manifest = [
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'version' => '1.0.0',
            'author' => $data['author'] ?? '',
            'author_url' => $data['author_url'] ?? '',
            'license' => $data['license'] ?? 'MIT',
            'min_cms_version' => '1.0.0',
            'entry_point' => 'boot.php',
            'enabled' => false,
            'permissions' => $data['permissions'] ?? [],
            'has_admin' => (bool)($data['has_admin'] ?? true),
            'has_frontend' => (bool)($data['has_frontend'] ?? false),
            'admin_menu' => ($data['has_admin'] ?? true) ? [
                'label' => $data['name'],
                'icon' => $data['icon'] ?? 'puzzle',
                'position' => 'extensions',
            ] : null,
            'hooks' => [],
            'created_at' => ocms_now(),
            'installed_at' => ocms_now(),
        ];

        file_put_contents(
            $dir . '/extension.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // boot.php -- entry point
        $bootContent = $this->generateBootFile($id, $data);
        file_put_contents($dir . '/boot.php', $bootContent);

        // Create subdirectories
        if ($data['has_admin'] ?? true) {
            mkdir($dir . '/views', 0755, true);
            $viewContent = $this->generateAdminView($id, $data);
            file_put_contents($dir . '/views/index.php', $viewContent);
        }

        if ($data['has_frontend'] ?? false) {
            mkdir($dir . '/templates', 0755, true);
        }

        if ($data['has_assets'] ?? false) {
            mkdir($dir . '/assets/css', 0755, true);
            mkdir($dir . '/assets/js', 0755, true);
            file_put_contents($dir . '/assets/css/style.css', "/* {$data['name']} — Styles */\n");
            file_put_contents($dir . '/assets/js/app.js', "// {$data['name']} — Script\n");
        }

        if ($data['has_data'] ?? false) {
            mkdir($dir . '/data', 0755, true);
        }

        // install.php and uninstall.php
        file_put_contents($dir . '/install.php', "<?php\n// Install script for {$data['name']}\n// \$app and \$extension are available\n");
        file_put_contents($dir . '/uninstall.php', "<?php\n// Uninstall script for {$data['name']}\n// \$app and \$extension are available\n");

        $this->log($id, 'info', 'Extension created from wizard');
        return ['success' => true, 'id' => $id, 'manifest' => $manifest];
    }

    /**
     * Generate the boot.php entry point for a new extension.
     *
     * @param string $id   Extension identifier
     * @param array  $data Wizard form data
     * @return string PHP source code for boot.php
     */
    private function generateBootFile(string $id, array $data): string {
        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $id)));
        $hasAdmin = $data['has_admin'] ?? true;
        $hasFrontend = $data['has_frontend'] ?? false;

        $code = "<?php\n";
        $code .= "/**\n * {$data['name']} — O-CMS Extension\n *\n";
        $code .= " * @version 1.0.0\n";
        if (!empty($data['author'])) $code .= " * @author {$data['author']}\n";
        $code .= " */\n\n";
        $code .= "// \$app (App) and \$extension (manifest) are available\n\n";

        if ($hasAdmin) {
            $code .= "// ─── ADMIN ROUTES ───\n";
            $code .= "// Base route: /admin/ext/{$id}\n";
            $code .= "\$app->router->get('/ext/{$id}', function () use (\$app, \$extension) {\n";
            $code .= "    \$app->auth->requireRole('administrator');\n";
            $code .= "    \$extPath = ocms_base_path() . '/extensions/{$id}/views/index.php';\n";
            $code .= "    \$pageTitle = '{$data['name']}';\n";
            $code .= "    \$activeMenu = 'ext-{$id}';\n";
            $code .= "    \$data = [];\n\n";
            $code .= "    // Load the view\n";
            $code .= "    ob_start();\n";
            $code .= "    include \$extPath;\n";
            $code .= "    \$content = ob_get_clean();\n";
            $code .= "    include ocms_base_path() . '/admin/views/layout.php';\n";
            $code .= "});\n\n";
        }

        if ($hasFrontend) {
            $code .= "// ─── FRONTEND ROUTES ───\n";
            $code .= "// Add your frontend routes here\n";
            $code .= "// \$app->router->get('/my-route', function () use (\$app) { ... });\n\n";
        }

        $code .= "// ─── HOOKS ───\n";
        $code .= "// Hooks::on('app.before_dispatch', function (\$app) {\n";
        $code .= "//     // Code executed before route dispatch\n";
        $code .= "// });\n";

        return $code;
    }

    /**
     * Generate the default admin view template for a new extension.
     *
     * @param string $id   Extension identifier
     * @param array  $data Wizard form data
     * @return string PHP/HTML template source
     */
    private function generateAdminView(string $id, array $data): string {
        return <<<'HTML'
<div class="page-header">
    <h1><?= ocms_escape($extension['name'] ?? '') ?></h1>
</div>

<div class="card">
    <p style="color:var(--text-muted);">
        Extension is active and running. Edit this file to build your interface.
    </p>
    <p style="color:var(--text-muted);margin-top:8px;font-size:0.85rem;">
        File: <code>extensions/<?= ocms_escape($extension['id'] ?? '') ?>/views/index.php</code>
    </p>
</div>
HTML;
    }

    /**
     * Validate an extension manifest for required fields and security.
     *
     * @param array $manifest The manifest data to validate
     * @return array Associative array with 'valid' (bool) and 'errors' (string[])
     */
    public function validateManifest(array $manifest): array {
        $errors = [];
        if (empty($manifest['name'])) $errors[] = 'Campo "name" obbligatorio';
        if (empty($manifest['version'])) $errors[] = 'Campo "version" obbligatorio';
        if (isset($manifest['entry_point'])) {
            if (str_contains($manifest['entry_point'], '..')) {
                $errors[] = 'entry_point non può contenere ".."';
            }
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Return admin sidebar menu items declared by enabled extensions.
     *
     * @return array List of menu item arrays with 'id', 'label', 'icon', 'url'
     */
    public function getAdminMenuItems(): array {
        $items = [];
        foreach ($this->getAll() as $ext) {
            if (($ext['enabled'] ?? false) && !empty($ext['admin_menu'])) {
                $items[] = [
                    'id' => $ext['id'],
                    'label' => $ext['admin_menu']['label'] ?? $ext['name'],
                    'icon' => $ext['admin_menu']['icon'] ?? 'puzzle',
                    'url' => ocms_base_url() . '/admin/ext/' . $ext['id'],
                ];
            }
        }
        return $items;
    }

    /**
     * Write a log entry for extension events.
     *
     * @param string $extId   Extension identifier
     * @param string $level   Log level (info, warning, error)
     * @param string $message Log message
     * @return void
     */
    private function log(string $extId, string $level, string $message): void {
        $logDir = ocms_data_path('logs');
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $entry = [
            'timestamp' => ocms_now(),
            'extension' => $extId,
            'level' => $level,
            'message' => $message,
        ];

        $logFile = $logDir . '/extensions.log';
        file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Recursively remove a directory and all its contents.
     *
     * @param string $dir Absolute directory path
     * @return void
     */
    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($dir);
    }

    /**
     * Recursively add a directory's files to a ZipArchive.
     *
     * @param ZipArchive $zip    The ZIP archive to add files to
     * @param string     $dir    Source directory path
     * @param string     $prefix ZIP path prefix for entries
     * @return void
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $prefix): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = $prefix . '/' . substr($file->getRealPath(), strlen($dir) + 1);
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }
    }
}
