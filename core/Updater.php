<?php
/**
 * O-CMS — Updater
 *
 * Checks GitHub Releases for newer versions of O-CMS and applies updates
 * safely: download, verify, stage, backup, swap, rollback-on-failure.
 *
 * Policy: only files inside UPDATABLE_PATHS are ever written. Everything
 * else (data/, uploads/, extensions/, non-default themes) is untouched.
 *
 * @package O-CMS
 */
class Updater {
    const GITHUB_OWNER = 'b84an';
    const GITHUB_REPO = 'o-cms';
    const CACHE_TTL = 21600; // 6 hours
    const HTTP_TIMEOUT = 8;
    const DOWNLOAD_TIMEOUT = 120;
    const USER_AGENT = 'O-CMS-Updater';
    const MIN_PHP = '8.0.0';
    const MIN_FREE_SPACE = 10485760; // 10 MB

    /**
     * Whitelist of paths (relative to OCMS_ROOT) that the updater may overwrite.
     * Prefix with '/' for exact-file, or end with '/' for recursive directory.
     */
    const UPDATABLE_PATHS = [
        '/index.php',
        '/install.php',
        '/.htaccess',
        '/logo.png',
        '/LICENSE',
        '/README.md',
        '/README.it.md',
        '/CHANGELOG.md',
        '/ARCHITECTURE.md',
        'core/',
        'admin/',
        'assets/ocms/',
        'themes/flavor/',
    ];

    /**
     * Cartelle interamente gestite dal CMS: i file che la nuova release non contiene
     * più vengono rimossi. I temi restano esclusi (possono contenere modifiche dell'utente).
     */
    const PRUNABLE_PATHS = ['core/', 'admin/', 'assets/ocms/'];

    private string $cacheFile;
    private string $logFile = '';

    public function __construct() {
        $this->cacheFile = OCMS_ROOT . '/data/cache/updater.json';
    }

    public function currentVersion(): string {
        return defined('OCMS_VERSION') ? OCMS_VERSION : '0.0.0';
    }

    /**
     * Get update status. Uses cache unless $force is true.
     */
    public function getStatus(bool $force = false): array {
        $current = $this->currentVersion();
        $cached = $this->readCache();

        if (!$force && $cached && (time() - ($cached['checked_at'] ?? 0)) < self::CACHE_TTL) {
            $cached['from_cache'] = true;
            $cached['current'] = $current;
            $cached['update_available'] = $this->isNewer($cached['latest'] ?? null, $current);
            return $cached;
        }

        $fetched = $this->fetchLatestRelease();
        if ($fetched['error'] !== null) {
            if ($cached) {
                $cached['from_cache'] = true;
                $cached['error'] = $fetched['error'];
                $cached['current'] = $current;
                $cached['update_available'] = $this->isNewer($cached['latest'] ?? null, $current);
                return $cached;
            }
            return [
                'current' => $current,
                'latest' => null,
                'update_available' => false,
                'download_url' => null,
                'release_url' => null,
                'changelog' => null,
                'published_at' => null,
                'checked_at' => time(),
                'error' => $fetched['error'],
                'from_cache' => false,
            ];
        }

        $status = [
            'current' => $current,
            'latest' => $fetched['tag'],
            'update_available' => $this->isNewer($fetched['tag'], $current),
            'download_url' => $fetched['download_url'],
            'sha256_url' => $fetched['sha256_url'] ?? null,
            'zip_name' => $fetched['zip_name'] ?? null,
            'release_url' => $fetched['release_url'],
            'changelog' => $fetched['changelog'],
            'published_at' => $fetched['published_at'],
            'checked_at' => time(),
            'error' => null,
            'from_cache' => false,
        ];

        $this->writeCache($status);
        return $status;
    }

    public function isNewer(?string $latest, string $current): bool {
        if (!$latest) return false;
        return version_compare(ltrim($latest, 'vV'), ltrim($current, 'vV'), '>');
    }

    /**
     * Run preflight compatibility checks.
     *
     * @return array{issues:array<string>, warnings:array<string>, ok:bool}
     */
    public function preflightCheck(): array {
        $issues = [];
        $warnings = [];

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            $issues[] = 'PHP ' . self::MIN_PHP . '+ richiesto (rilevato: ' . PHP_VERSION . ')';
        }

        if (!class_exists('ZipArchive')) {
            $issues[] = 'Estensione PHP "zip" non disponibile (ZipArchive mancante)';
        }

        if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
            $issues[] = 'Né curl né allow_url_fopen disponibili — impossibile scaricare';
        }

        $free = @disk_free_space(OCMS_ROOT);
        if ($free !== false && $free < self::MIN_FREE_SPACE) {
            $issues[] = 'Spazio disco insufficiente (necessari almeno 10 MB liberi)';
        }

        foreach (['core', 'admin', 'themes/flavor'] as $dir) {
            $path = OCMS_ROOT . '/' . $dir;
            if (!is_dir($path)) continue;
            if (!is_writable($path)) {
                $issues[] = "Cartella non scrivibile: $dir/";
            }
        }

        foreach (['index.php', '.htaccess'] as $file) {
            $path = OCMS_ROOT . '/' . $file;
            if (is_file($path) && !is_writable($path)) {
                $issues[] = "File non scrivibile: $file";
            }
        }

        // Warnings (non-blocking)
        $rootHtaccess = OCMS_ROOT . '/.htaccess';
        if (is_file($rootHtaccess)) {
            $shipped = file_get_contents(__DIR__ . '/../.htaccess');
            $current = file_get_contents($rootHtaccess);
            if ($shipped !== null && $current !== $shipped && $shipped !== false) {
                // We can't reliably detect customization post-update because core/../.htaccess IS the same file.
                // So this warning is informational only.
            }
        }

        $installedMarker = OCMS_ROOT . '/data/.installed';
        if (!is_file($installedMarker)) {
            $warnings[] = 'Marker .installed assente: l\'installazione potrebbe non essere completa';
        }

        return [
            'issues' => $issues,
            'warnings' => $warnings,
            'ok' => empty($issues),
        ];
    }

    /**
     * Apply an update from a GitHub release asset URL.
     *
     * Orchestrates: download → extract → validate → backup → swap → cleanup.
     * On failure during backup/swap, attempts automatic rollback.
     *
     * @return array{success:bool, message:string, log_file:?string, backup_dir:?string}
     */
    public function applyUpdate(string $downloadUrl, string $targetVersion, ?string $sha256Url = null, ?string $zipName = null): array {
        $ts = date('Ymd-His');
        $this->logFile = OCMS_ROOT . '/data/logs/update-' . $ts . '.log';
        $this->log("=== Update started ===");
        $this->log("Current version: " . $this->currentVersion());
        $this->log("Target version: $targetVersion");
        $this->log("Source: $downloadUrl");

        $cacheDir = OCMS_ROOT . '/data/cache';
        $backupsDir = OCMS_ROOT . '/data/backups';
        $zipFile = $cacheDir . '/update-' . $ts . '.zip';
        $stagingDir = $cacheDir . '/update-staging-' . $ts;
        $backupDir = $backupsDir . '/pre-update-' . ltrim($this->currentVersion(), 'vV') . '-' . $ts;

        @mkdir($cacheDir, 0755, true);
        @mkdir($backupsDir, 0755, true);

        $maintenanceWasOn = $this->setMaintenanceMode(true);
        $this->log("Maintenance mode ON");
        // Se PHP muore a metà (fatal, limite di tempo) il catch non gira: senza questo
        // il sito resterebbe in manutenzione per il pubblico finché qualcuno non se ne accorge
        $finished = false;
        register_shutdown_function(function () use (&$finished, $maintenanceWasOn) {
            if (!$finished && !$maintenanceWasOn) {
                $this->setMaintenanceMode(false);
                $this->log("Interruzione imprevista: manutenzione disattivata. Controlla il sito e il backup.");
            }
        });

        try {
            // Step 1: Download
            $this->log("Step 1/6: downloading release package...");
            if (!$this->downloadFile($downloadUrl, $zipFile)) {
                throw new RuntimeException('Download fallito o file vuoto');
            }
            $size = filesize($zipFile);
            $this->log("Downloaded $size bytes to $zipFile");

            // Integrità: lo SHA-256 del pacchetto deve combaciare con quello pubblicato nella release
            if (!$sha256Url) {
                throw new RuntimeException('La release non pubblica il checksum SHA-256 del pacchetto: aggiornamento automatico rifiutato');
            }
            $sumText = $this->httpGet($sha256Url);
            $expected = $sumText !== null ? $this->parseChecksum($sumText, (string)$zipName) : null;
            if ($expected === null) {
                throw new RuntimeException('Checksum SHA-256 non leggibile');
            }
            $actual = hash_file('sha256', $zipFile);
            if (!hash_equals($expected, $actual)) {
                throw new RuntimeException('Checksum SHA-256 non corrispondente: pacchetto corrotto o alterato');
            }
            $this->log("SHA-256 verificato: $actual");

            // Step 2: Extract
            $this->log("Step 2/6: extracting archive...");
            @mkdir($stagingDir, 0755, true);
            $zip = new ZipArchive();
            $openResult = $zip->open($zipFile);
            if ($openResult !== true) {
                throw new RuntimeException('Apertura zip fallita (codice ' . $openResult . ')');
            }
            // Nessun percorso assoluto o con ".." (zip slip)
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string)$zip->getNameIndex($i);
                if ($entry === '' || $entry[0] === '/' || str_contains($entry, '\\') || preg_match('#(^|/)\.\.(/|$)#', $entry)) {
                    $zip->close();
                    throw new RuntimeException('Archivio con percorsi non validi: ' . $entry);
                }
            }
            if (!$zip->extractTo($stagingDir)) {
                $zip->close();
                throw new RuntimeException('Estrazione zip fallita');
            }
            $zip->close();
            $this->log("Extracted to $stagingDir");

            // Step 3: Locate release root (detect top-level prefix like "o-cms/")
            $releaseRoot = $this->detectReleaseRoot($stagingDir);
            if ($releaseRoot === null) {
                throw new RuntimeException('Struttura dell\'archivio non riconosciuta (core/App.php non trovato)');
            }
            $this->log("Release root: $releaseRoot");

            // Step 4: Validate
            $this->log("Step 3/6: validating staged files...");
            $required = ['core/App.php', 'admin/index.php', 'index.php'];
            foreach ($required as $f) {
                if (!is_file($releaseRoot . '/' . $f)) {
                    throw new RuntimeException("File richiesto assente nel pacchetto: $f");
                }
            }

            // Step 5: Backup current files
            $this->log("Step 4/6: backing up current files to $backupDir");
            @mkdir($backupDir, 0755, true);
            foreach (self::UPDATABLE_PATHS as $rel) {
                $src = OCMS_ROOT . $this->normalizeRel($rel);
                if (!file_exists($src)) continue;
                $dst = $backupDir . $this->normalizeRel($rel);
                $this->copyRecursive($src, $dst);
            }

            // Step 6: Swap files
            $this->log("Step 5/6: applying new files...");
            $copied = 0;
            foreach (self::UPDATABLE_PATHS as $rel) {
                $src = $releaseRoot . $this->normalizeRel($rel);
                if (!file_exists($src)) {
                    $this->log("  skip (not in release): $rel");
                    continue;
                }
                $dst = OCMS_ROOT . $this->normalizeRel($rel);
                // Il .htaccess contiene regole proprie del sito (redirect 301...): si
                // conserva il blocco PERSONALIZZAZIONI; senza blocco non si tocca affatto
                if ($rel === '/.htaccess' && is_file($dst)) {
                    $merged = $this->mergeHtaccess((string)file_get_contents($dst), (string)file_get_contents($src));
                    if ($merged === null) {
                        $this->log("  skip .htaccess: manca il blocco PERSONALIZZAZIONI, aggiornalo a mano confrontandolo con quello della release");
                        continue;
                    }
                    file_put_contents($dst, $merged);
                    $copied++;
                    $this->log("  applied: .htaccess (personalizzazioni conservate)");
                    continue;
                }
                // Ensure parent dir exists
                @mkdir(dirname($dst), 0755, true);
                $n = $this->copyRecursive($src, $dst);
                $copied += $n;
                $this->log("  applied: $rel ($n files)");
            }
            $this->log("Total files copied: $copied");

            // File rimossi nella nuova release: solo nelle cartelle interamente del CMS
            $pruned = 0;
            foreach (self::PRUNABLE_PATHS as $rel) {
                $newDir = $releaseRoot . $this->normalizeRel($rel);
                $curDir = OCMS_ROOT . $this->normalizeRel($rel);
                if (!is_dir($newDir) || !is_dir($curDir)) continue;
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($curDir, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if (!$f->isFile()) continue;
                    $relPath = substr($f->getPathname(), strlen($curDir));
                    if (!file_exists($newDir . $relPath)) {
                        if (@unlink($f->getPathname())) { $pruned++; $this->log("  removed (not in release): " . $rel . ltrim($relPath, '/')); }
                    }
                }
            }
            $this->log("Obsolete files removed: $pruned");

            // Step 7: Cleanup staging
            $this->log("Step 6/6: cleaning up staging...");
            $this->rrmdir($stagingDir);
            @unlink($zipFile);

            // Invalidate cache so next check shows new state
            @unlink($this->cacheFile);

            if (!$maintenanceWasOn) {
                $this->setMaintenanceMode(false);
                $this->log("Maintenance mode OFF");
            } else {
                $this->log("Maintenance mode left ON (was ON before update)");
            }

            $finished = true;
            $this->log("=== Update completed successfully ===");
            return [
                'success' => true,
                'message' => "Aggiornamento a $targetVersion completato. $copied file aggiornati.",
                'log_file' => basename($this->logFile),
                'backup_dir' => basename($backupDir),
            ];

        } catch (Throwable $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Attempting rollback from $backupDir");
            $restored = 0;
            if (is_dir($backupDir)) {
                foreach (self::UPDATABLE_PATHS as $rel) {
                    $src = $backupDir . $this->normalizeRel($rel);
                    if (!file_exists($src)) continue;
                    $dst = OCMS_ROOT . $this->normalizeRel($rel);
                    $n = $this->copyRecursive($src, $dst);
                    $restored += $n;
                }
                $this->log("Rollback restored $restored files");
            } else {
                $this->log("No backup to restore (failure occurred before backup step)");
            }

            // Cleanup
            if (is_dir($stagingDir)) $this->rrmdir($stagingDir);
            if (is_file($zipFile)) @unlink($zipFile);

            if (!$maintenanceWasOn) {
                $this->setMaintenanceMode(false);
            }
            $finished = true;

            return [
                'success' => false,
                'message' => 'Aggiornamento fallito: ' . $e->getMessage() . ' (rollback eseguito)',
                'log_file' => basename($this->logFile),
                'backup_dir' => is_dir($backupDir) ? basename($backupDir) : null,
            ];
        }
    }

    /**
     * List update log files (newest first).
     *
     * @return array<array{name:string, size:int, mtime:int}>
     */
    public function listLogs(): array {
        $dir = OCMS_ROOT . '/data/logs';
        if (!is_dir($dir)) return [];
        $files = glob($dir . '/update-*.log') ?: [];
        $items = [];
        foreach ($files as $f) {
            $items[] = [
                'name' => basename($f),
                'size' => filesize($f) ?: 0,
                'mtime' => filemtime($f) ?: 0,
            ];
        }
        usort($items, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $items;
    }

    public function readLog(string $filename): ?string {
        // Sanitize: only allow update-*.log names
        if (!preg_match('/^update-[0-9a-zA-Z\-]+\.log$/', $filename)) return null;
        $path = OCMS_ROOT . '/data/logs/' . $filename;
        if (!is_file($path)) return null;
        return file_get_contents($path) ?: '';
    }

    // ─── Internals ───

    private function fetchLatestRelease(): array {
        $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest',
            self::GITHUB_OWNER, self::GITHUB_REPO);
        $body = $this->httpGet($url);
        if ($body === null) return ['error' => 'Impossibile contattare GitHub'];

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['tag_name'])) {
            return ['error' => 'Risposta GitHub non valida'];
        }

        // Pacchetto = primo asset .zip della release; checksum = asset "<nome>.sha256"
        // (o SHA256SUMS). Senza checksum l'aggiornamento automatico viene rifiutato.
        $downloadUrl = null;
        $zipName = null;
        $sums = [];
        foreach ((is_array($data['assets'] ?? null) ? $data['assets'] : []) as $asset) {
            $name = (string)($asset['name'] ?? '');
            $url = (string)($asset['browser_download_url'] ?? '');
            if ($url === '') continue;
            if ($downloadUrl === null && str_ends_with(strtolower($name), '.zip')) { $downloadUrl = $url; $zipName = $name; }
            if (str_ends_with(strtolower($name), '.sha256') || strtoupper($name) === 'SHA256SUMS') $sums[$name] = $url;
        }
        $sha256Url = null;
        if ($zipName !== null) {
            $sha256Url = $sums[$zipName . '.sha256'] ?? ($sums['SHA256SUMS'] ?? null);
        }

        return [
            'error' => null,
            'tag' => $data['tag_name'],
            'download_url' => $downloadUrl,
            'sha256_url' => $sha256Url,
            'zip_name' => $zipName,
            'release_url' => $data['html_url'] ?? null,
            'changelog' => $data['body'] ?? '',
            'published_at' => $data['published_at'] ?? null,
        ];
    }

    protected function httpGet(string $url): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 300) return null;
            return $body;
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'timeout' => self::HTTP_TIMEOUT,
                'header' => "User-Agent: " . self::USER_AGENT . "\r\nAccept: application/vnd.github+json\r\n",
                'ignore_errors' => true,
            ]]);
            $body = @file_get_contents($url, false, $ctx);
            return $body === false ? null : $body;
        }
        return null;
    }

    protected function downloadFile(string $url, string $dest): bool {
        @set_time_limit(self::DOWNLOAD_TIMEOUT + 30);
        if (function_exists('curl_init')) {
            $fp = fopen($dest, 'wb');
            if (!$fp) return false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => self::DOWNLOAD_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_USERAGENT => self::USER_AGENT,
            ]);
            $ok = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            if (!$ok || $code < 200 || $code >= 300) {
                @unlink($dest);
                return false;
            }
            return filesize($dest) > 0;
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'timeout' => self::DOWNLOAD_TIMEOUT,
                'header' => "User-Agent: " . self::USER_AGENT . "\r\n",
                'follow_location' => 1,
            ]]);
            $data = @file_get_contents($url, false, $ctx);
            if ($data === false) return false;
            return file_put_contents($dest, $data) > 0;
        }
        return false;
    }

    /**
     * Detect where the release actually starts inside $stagingDir.
     * Handles both cases: flat (core/ at root) or prefixed (o-cms/core/ at root).
     */
    private function detectReleaseRoot(string $stagingDir): ?string {
        if (is_file($stagingDir . '/core/App.php')) return $stagingDir;
        $entries = glob($stagingDir . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($entries as $dir) {
            if (is_file($dir . '/core/App.php')) return $dir;
        }
        return null;
    }

    private function normalizeRel(string $rel): string {
        return '/' . ltrim(rtrim($rel, '/'), '/');
    }

    /**
     * Copy file or directory recursively. Returns number of files copied.
     */
    private function copyRecursive(string $src, string $dst): int {
        if (is_file($src)) {
            @mkdir(dirname($dst), 0755, true);
            return copy($src, $dst) ? 1 : 0;
        }
        if (is_dir($src)) {
            @mkdir($dst, 0755, true);
            $count = 0;
            $items = scandir($src) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $count += $this->copyRecursive($src . '/' . $item, $dst . '/' . $item);
            }
            return $count;
        }
        return 0;
    }

    private function rrmdir(string $dir): void {
        if (!is_dir($dir)) return;
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) $this->rrmdir($path);
            else @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Set maintenance mode in config.json. Returns previous state.
     */
    private function setMaintenanceMode(bool $on): bool {
        $configFile = OCMS_ROOT . '/data/config.json';
        if (!is_file($configFile)) return false;
        $cfg = json_decode((string)file_get_contents($configFile), true);
        if (!is_array($cfg)) return false; // config illeggibile: meglio non toccarla
        $prev = !empty($cfg['maintenance_mode']);
        $cfg['maintenance_mode'] = $on;
        // scrittura atomica: un config.json troncato renderebbe il sito inservibile
        $tmp = $configFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $configFile);
        } else {
            @unlink($tmp);
        }
        return $prev;
    }

    /**
     * Inserisce nel .htaccess nuovo il blocco PERSONALIZZAZIONI di quello attuale.
     * Restituisce null se quello attuale non ha il blocco (regole sconosciute da non perdere).
     */
    public function mergeHtaccess(string $current, string $release): ?string {
        $re = '/^# === PERSONALIZZAZIONI DEL SITO ===\R.*?^# === FINE PERSONALIZZAZIONI ===$/ms';
        if (!preg_match($re, $current, $cur)) return null;
        if (!preg_match($re, $release)) return $release . "\n" . $cur[0] . "\n";
        return preg_replace_callback($re, fn() => $cur[0], $release, 1);
    }

    /**
     * Estrae lo SHA-256 da un file checksum: "<hash>" oppure righe "<hash>  <nome file>".
     */
    private function parseChecksum(string $text, string $zipName): ?string {
        foreach (preg_split('/\R/', trim($text)) as $line) {
            if (!preg_match('/^([a-f0-9]{64})(?:\s+\*?(\S.*))?$/i', trim($line), $m)) continue;
            if (empty($m[2]) || $zipName === '' || basename(trim($m[2])) === $zipName) return strtolower($m[1]);
        }
        return null;
    }

    private function log(string $line): void {
        if (!$this->logFile) return;
        @mkdir(dirname($this->logFile), 0755, true);
        @file_put_contents($this->logFile, '[' . date('H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND);
    }

    private function readCache(): ?array {
        if (!is_file($this->cacheFile)) return null;
        $raw = @file_get_contents($this->cacheFile);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function writeCache(array $status): void {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($this->cacheFile, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
