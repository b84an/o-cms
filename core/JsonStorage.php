<?php
/**
 * O-CMS — Motore di storage JSON
 *
 * Gestisce lettura/scrittura di file JSON con lock per sicurezza concorrenza.
 * Ogni "collezione" corrisponde a una sottocartella di data/.
 */
class JsonStorage {
    private string $basePath;

    public function __construct(?string $basePath = null) {
        $this->basePath = $basePath ?? ocms_data_path();
    }

    /**
     * Legge un singolo record da una collezione
     * @param string $collection Es: "pages", "articles", "users"
     * @param string $id Nome del file (senza .json)
     * @return array|null
     */
    public function find(string $collection, string $id): ?array {
        $file = $this->filePath($collection, $id);
        if (!file_exists($file)) {
            return null;
        }
        $content = file_get_contents($file);
        return json_decode($content, true);
    }

    /**
     * Legge tutti i record di una collezione
     * @param string $collection
     * @param callable|null $filter Funzione filtro opzionale
     * @param string $sortBy Campo per ordinamento
     * @param string $sortDir 'asc' o 'desc'
     * @return array
     */
    public function findAll(string $collection, ?callable $filter = null, string $sortBy = 'created_at', string $sortDir = 'desc'): array {
        $dir = $this->collectionPath($collection);
        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        foreach (glob($dir . '/*.json') as $file) {
            $content = file_get_contents($file);
            $item = json_decode($content, true);
            if ($item === null) continue;

            if ($filter && !$filter($item)) continue;

            $items[] = $item;
        }

        // Ordinamento
        usort($items, function ($a, $b) use ($sortBy, $sortDir) {
            $valA = $a[$sortBy] ?? '';
            $valB = $b[$sortBy] ?? '';
            $cmp = is_numeric($valA) && is_numeric($valB)
                ? $valA <=> $valB
                : strcmp((string)$valA, (string)$valB);
            return $sortDir === 'desc' ? -$cmp : $cmp;
        });

        return $items;
    }

    /**
     * Salva un record in una collezione
     * @param string $collection
     * @param string $id Nome del file (senza .json)
     * @param array $data
     * @return bool
     */
    public function save(string $collection, string $id, array $data): bool {
        $file = $this->filePath($collection, $id);
        return $this->withLock($file, fn() => $this->writeJson($file, $data));
    }

    /**
     * Legge, modifica e riscrive un documento sotto lock esclusivo.
     * $fn riceve il documento attuale (o null se non esiste) e restituisce
     * il nuovo array da salvare, oppure null per non scrivere nulla.
     * Restituisce il documento salvato, o null se non è stato scritto.
     */
    public function update(string $collection, string $id, callable $fn): ?array {
        $file = $this->filePath($collection, $id);
        return $this->withLock($file, function () use ($file, $fn) {
            $current = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
            $next = $fn(is_array($current) ? $current : null);
            if (!is_array($next)) return null;
            return $this->writeJson($file, $next) ? $next : null;
        });
    }

    /**
     * Come update() ma su un file qualsiasi relativo a data/.
     */
    public function updateFile(string $relativePath, callable $fn): ?array {
        $file = $this->safeRelative($relativePath);
        return $this->withLock($file, function () use ($file, $fn) {
            $current = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
            $next = $fn(is_array($current) ? $current : null);
            if (!is_array($next)) return null;
            return $this->writeJson($file, $next) ? $next : null;
        });
    }

    /**
     * Esegue $fn tenendo un lock esclusivo legato al file di destinazione.
     * I file di lock stanno in data/cache/locks/ per non sporcare le collezioni.
     */
    private function withLock(string $file, callable $fn): mixed {
        $lockDir = $this->basePath . '/cache/locks';
        if (!is_dir($lockDir)) @mkdir($lockDir, 0755, true);
        $lock = @fopen($lockDir . '/' . md5($file) . '.lock', 'c');
        if ($lock === false) {
            return $fn(); // senza lock piuttosto che non salvare
        }
        try {
            flock($lock, LOCK_EX);
            return $fn();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Scrittura atomica: file temporaneo univoco nella stessa cartella, poi rename.
     */
    private function writeJson(string $file, array $data): bool {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return false;
        }
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json) === false) {
            @unlink($tmp);
            return false;
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /**
     * Elimina un record
     */
    public function delete(string $collection, string $id): bool {
        $file = $this->filePath($collection, $id);
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }

    /**
     * Verifica se un record esiste
     */
    public function exists(string $collection, string $id): bool {
        return file_exists($this->filePath($collection, $id));
    }

    /**
     * Conta i record in una collezione
     */
    public function count(string $collection, ?callable $filter = null): int {
        if ($filter) {
            return count($this->findAll($collection, $filter));
        }
        $dir = $this->collectionPath($collection);
        if (!is_dir($dir)) {
            return 0;
        }
        return count(glob($dir . '/*.json'));
    }

    /**
     * Legge un file JSON singolo (non collezione), es: config.json
     */
    public function readFile(string $relativePath): ?array {
        $file = $this->safeRelative($relativePath);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Scrive un file JSON singolo
     */
    public function writeFile(string $relativePath, array $data): bool {
        $file = $this->safeRelative($relativePath);
        return $this->withLock($file, fn() => $this->writeJson($file, $data));
    }

    /**
     * Percorso assoluto dentro data/, senza possibilità di uscirne con "..".
     */
    private function safeRelative(string $relativePath): string {
        $parts = array_filter(explode('/', str_replace('\\', '/', $relativePath)), fn($p) => $p !== '' && $p !== '.' && $p !== '..');
        return $this->basePath . '/' . implode('/', $parts);
    }

    private function collectionPath(string $collection): string {
        return $this->basePath . '/' . $collection;
    }

    private function filePath(string $collection, string $id): string {
        // Previeni path traversal
        $safeId = basename($id);
        return $this->collectionPath($collection) . '/' . $safeId . '.json';
    }
}
