<?php
/**
 * O-CMS — JSON Storage Engine
 *
 * Handles reading and writing JSON files with file locking for concurrency safety.
 * Each "collection" maps to a subdirectory under data/.
 *
 * @package O-CMS
 * @version 1.0.0
 */
class JsonStorage {
    private string $basePath;

    /**
     * @param string|null $basePath Base directory for data storage (defaults to data/)
     */
    public function __construct(?string $basePath = null) {
        $this->basePath = $basePath ?? ocms_data_path();
    }

    /**
     * Read a single record from a collection.
     *
     * @param string $collection Collection name (e.g. "pages", "articles", "users")
     * @param string $id         Record identifier / filename without .json
     * @return array|null The decoded data or null if not found
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
     * Read all records from a collection, with optional filtering and sorting.
     *
     * @param string        $collection Collection name
     * @param callable|null $filter     Optional filter callback (receives item, returns bool)
     * @param string        $sortBy     Field name to sort by
     * @param string        $sortDir    Sort direction: 'asc' or 'desc'
     * @return array List of matching records
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

        // Sort results
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
     * Save a record to a collection (create or overwrite).
     *
     * Uses atomic write (tmp file + rename) with exclusive lock.
     *
     * @param string $collection Collection name
     * @param string $id         Record identifier / filename without .json
     * @param array  $data       The data to encode as JSON
     * @return bool True on success
     */
    public function save(string $collection, string $id, array $data): bool {
        $dir = $this->collectionPath($collection);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $this->filePath($collection, $id);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Atomic write with exclusive lock
        $tmp = $file . '.tmp';
        $written = file_put_contents($tmp, $json, LOCK_EX);
        if ($written === false) {
            return false;
        }
        return rename($tmp, $file);
    }

    /**
     * Delete a record from a collection.
     *
     * @param string $collection Collection name
     * @param string $id         Record identifier
     * @return bool True if the file was deleted
     */
    public function delete(string $collection, string $id): bool {
        $file = $this->filePath($collection, $id);
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }

    /**
     * Check whether a record exists in a collection.
     *
     * @param string $collection Collection name
     * @param string $id         Record identifier
     * @return bool
     */
    public function exists(string $collection, string $id): bool {
        return file_exists($this->filePath($collection, $id));
    }

    /**
     * Count records in a collection, with optional filtering.
     *
     * @param string        $collection Collection name
     * @param callable|null $filter     Optional filter callback
     * @return int
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
     * Read a standalone JSON file (not a collection record), e.g. config.json.
     *
     * @param string $relativePath Path relative to the base data directory
     * @return array|null Decoded data or null if not found
     */
    public function readFile(string $relativePath): ?array {
        $file = $this->basePath . '/' . ltrim($relativePath, '/');
        if (!file_exists($file)) {
            return null;
        }
        return json_decode(file_get_contents($file), true);
    }

    /**
     * Write a standalone JSON file (not a collection record).
     *
     * @param string $relativePath Path relative to the base data directory
     * @param array  $data         Data to encode as JSON
     * @return bool True on success
     */
    public function writeFile(string $relativePath, array $data): bool {
        $file = $this->basePath . '/' . ltrim($relativePath, '/');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }
        return rename($tmp, $file);
    }

    /**
     * Build the filesystem path for a collection directory.
     *
     * @param string $collection Collection name
     * @return string Absolute directory path
     */
    private function collectionPath(string $collection): string {
        return $this->basePath . '/' . $collection;
    }

    /**
     * Build the filesystem path for a specific record file.
     *
     * @param string $collection Collection name
     * @param string $id         Record identifier
     * @return string Absolute file path
     */
    private function filePath(string $collection, string $id): string {
        // Prevent path traversal
        $safeId = basename($id);
        return $this->collectionPath($collection) . '/' . $safeId . '.json';
    }
}
