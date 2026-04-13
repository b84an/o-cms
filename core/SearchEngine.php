<?php
/**
 * O-CMS — Search Engine
 *
 * Full-text search across all CMS content with:
 * - Automatic indexing of all content types
 * - Weighted relevance scoring (title ranks higher than body)
 * - Filters by type, status, category, date range
 * - Result highlighting with contextual snippets
 * - Fuzzy matching (Levenshtein tolerance)
 * - Autocomplete suggestions
 *
 * @package O-CMS
 * @version 1.0.0
 */
class SearchEngine {
    private App $app;
    private string $indexPath;

    // Weight per field (higher = more relevant)
    private const WEIGHTS = [
        'title'        => 10,
        'slug'         => 5,
        'excerpt'      => 6,
        'content'      => 2,
        'name'         => 10,
        'display_name' => 8,
        'username'     => 6,
        'email'        => 4,
        'description'  => 4,
        'label'        => 7,
        'tags'         => 5,
        'category'     => 5,
    ];

    // Italian and English stop words (ignored during search)
    private const STOP_WORDS = [
        'il','lo','la','i','gli','le','un','uno','una','di','del','dello','della',
        'dei','degli','delle','a','al','allo','alla','ai','agli','alle','da','dal',
        'dallo','dalla','dai','dagli','dalle','in','nel','nello','nella','nei','negli',
        'nelle','con','su','sul','sullo','sulla','sui','sugli','sulle','per','tra',
        'fra','e','o','ma','che','non','è','sono','ha','ho','come','più','anche',
        'the','and','or','is','in','to','of','for','a','an','with','on','at','from',
    ];

    /**
     * @param App $app The main application instance
     */
    public function __construct(App $app) {
        $this->app = $app;
        $this->indexPath = ocms_data_path('cache/search-index.json');
    }

    /**
     * Perform a full-text search and return results sorted by relevance.
     *
     * @param string $query   The search query string
     * @param array  $options Search options (types, status, category, date_from, date_to, limit, offset)
     * @return array Result set with 'results', 'total', 'query', and 'tokens'
     */
    public function search(string $query, array $options = []): array {
        $query = trim($query);
        if (strlen($query) < 2) return ['results' => [], 'total' => 0, 'query' => $query];

        // Options
        $types = $options['types'] ?? []; // ['page','article','user','category','menu','media','form']
        $status = $options['status'] ?? ''; // 'published', 'draft', ''
        $category = $options['category'] ?? '';
        $dateFrom = $options['date_from'] ?? '';
        $dateTo = $options['date_to'] ?? '';
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;

        // Tokenize the query
        $tokens = $this->tokenize($query);
        $queryLower = $this->normalizeText($query);

        // Collect all indexable documents
        $documents = $this->getAllDocuments();

        // Calculate score for each document
        $scored = [];
        foreach ($documents as $doc) {
            // Type filter
            if (!empty($types) && !in_array($doc['type'], $types)) continue;

            // Status filter
            if ($status && isset($doc['status']) && $doc['status'] !== $status) continue;

            // Category filter
            if ($category && ($doc['category'] ?? '') !== $category) continue;

            // Date filter
            if ($dateFrom && ($doc['date'] ?? '') < $dateFrom) continue;
            if ($dateTo && ($doc['date'] ?? '') > $dateTo) continue;

            // Calculate relevance
            $score = $this->calculateScore($doc, $tokens, $queryLower);
            if ($score > 0) {
                $doc['score'] = $score;
                $doc['highlights'] = $this->highlight($doc, $tokens);
                $scored[] = $doc;
            }
        }

        // Sort by relevance
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        $total = count($scored);
        $results = array_slice($scored, $offset, $limit);

        return [
            'results' => $results,
            'total' => $total,
            'query' => $query,
            'tokens' => $tokens,
        ];
    }

    /**
     * Quick search (for sidebar/autocomplete) -- returns max 8 mixed results.
     *
     * @param string $query The search query
     * @return array Search results (same format as search())
     */
    public function quickSearch(string $query): array {
        return $this->search($query, ['limit' => 8]);
    }

    /**
     * Return autocomplete suggestions based on titles and tags.
     *
     * @param string $query The partial search query
     * @param int    $limit Maximum number of suggestions (default: 5)
     * @return array List of suggestion strings
     */
    public function suggest(string $query, int $limit = 5): array {
        if (strlen($query) < 2) return [];

        $queryLower = $this->normalizeText($query);
        $suggestions = [];

        // Collect all titles/names
        $docs = $this->getAllDocuments();
        foreach ($docs as $doc) {
            $title = $this->normalizeText($doc['title'] ?? '');
            if (str_contains($title, $queryLower)) {
                $suggestions[$doc['title']] = ($suggestions[$doc['title']] ?? 0) + 1;
            }
            // Tags
            foreach ($doc['tags'] ?? [] as $tag) {
                if (str_contains($this->normalizeText($tag), $queryLower)) {
                    $suggestions[$tag] = ($suggestions[$tag] ?? 0) + 1;
                }
            }
        }

        arsort($suggestions);
        return array_slice(array_keys($suggestions), 0, $limit);
    }

    /**
     * Collect all searchable documents from every CMS content type.
     *
     * @return array List of normalized document arrays
     */
    private function getAllDocuments(): array {
        $docs = [];

        // Pages
        foreach ($this->app->storage->findAll('pages') as $p) {
            $docs[] = [
                'type' => 'page',
                'id' => $p['slug'],
                'title' => $p['title'] ?? '',
                'slug' => $p['slug'] ?? '',
                'content' => strip_tags($p['content'] ?? ''),
                'excerpt' => $p['meta']['description'] ?? '',
                'status' => $p['status'] ?? 'draft',
                'date' => $p['updated_at'] ?? $p['created_at'] ?? '',
                'author' => $p['author'] ?? '',
                'url' => ocms_base_url() . '/' . ($p['slug'] ?? ''),
                'admin_url' => ocms_base_url() . '/admin/pages/edit/' . ($p['slug'] ?? ''),
                'icon' => '&#128196;',
                'type_label' => 'Pagina',
            ];
        }

        // Articles
        foreach ($this->app->storage->findAll('articles') as $a) {
            $docs[] = [
                'type' => 'article',
                'id' => $a['slug'],
                'title' => $a['title'] ?? '',
                'slug' => $a['slug'] ?? '',
                'content' => strip_tags($a['content'] ?? ''),
                'excerpt' => $a['excerpt'] ?? '',
                'status' => $a['status'] ?? 'draft',
                'category' => $a['category'] ?? '',
                'tags' => $a['tags'] ?? [],
                'date' => $a['updated_at'] ?? $a['created_at'] ?? '',
                'author' => $a['author'] ?? '',
                'cover_image' => $a['cover_image'] ?? '',
                'url' => ocms_base_url() . '/blog/' . ($a['slug'] ?? ''),
                'admin_url' => ocms_base_url() . '/admin/articles/edit/' . ($a['slug'] ?? ''),
                'icon' => '&#9998;',
                'type_label' => 'Articolo',
            ];
        }

        // Lessons
        foreach ($this->app->storage->findAll('lessons') as $l) {
            $docs[] = [
                'type' => 'lesson',
                'id' => $l['slug'],
                'title' => $l['title'] ?? '',
                'slug' => $l['slug'] ?? '',
                'description' => $l['description'] ?? '',
                'tags' => $l['tags'] ?? [],
                'status' => $l['status'] ?? 'draft',
                'date' => $l['updated_at'] ?? $l['created_at'] ?? '',
                'author' => $l['author'] ?? '',
                'url' => ocms_base_url() . '/lezione/' . ($l['slug'] ?? ''),
                'admin_url' => ocms_base_url() . '/admin/lessons/edit/' . ($l['slug'] ?? ''),
                'icon' => '&#128218;',
                'type_label' => 'Lezione',
            ];
        }

        // Categories
        foreach ($this->app->storage->findAll('categories') as $c) {
            $docs[] = [
                'type' => 'category',
                'id' => $c['slug'],
                'title' => $c['name'] ?? '',
                'slug' => $c['slug'] ?? '',
                'description' => $c['description'] ?? '',
                'date' => $c['created_at'] ?? '',
                'admin_url' => ocms_base_url() . '/admin/categories',
                'icon' => '&#128193;',
                'type_label' => 'Categoria',
            ];
        }

        // Users
        foreach ($this->app->storage->findAll('users') as $u) {
            $docs[] = [
                'type' => 'user',
                'id' => $u['username'],
                'title' => $u['display_name'] ?? $u['username'],
                'username' => $u['username'] ?? '',
                'display_name' => $u['display_name'] ?? '',
                'email' => $u['email'] ?? '',
                'date' => $u['created_at'] ?? '',
                'admin_url' => ocms_base_url() . '/admin/users/edit/' . ($u['username'] ?? ''),
                'icon' => '&#128100;',
                'type_label' => 'Utente',
            ];
        }

        // Media
        foreach ($this->app->storage->findAll('media') as $m) {
            $docs[] = [
                'type' => 'media',
                'id' => $m['id'],
                'title' => $m['original_name'] ?? $m['filename'] ?? '',
                'name' => $m['original_name'] ?? '',
                'date' => $m['uploaded_at'] ?? '',
                'url' => ocms_base_url() . ($m['url'] ?? ''),
                'admin_url' => ocms_base_url() . '/admin/media',
                'icon' => str_starts_with($m['mime_type'] ?? '', 'image/') ? '&#127748;' : '&#128196;',
                'type_label' => 'Media',
            ];
        }

        // Forms
        foreach ($this->app->storage->findAll('forms') as $f) {
            $docs[] = [
                'type' => 'form',
                'id' => $f['slug'],
                'title' => $f['name'] ?? '',
                'slug' => $f['slug'] ?? '',
                'date' => $f['created_at'] ?? '',
                'admin_url' => ocms_base_url() . '/admin/forms/edit/' . ($f['slug'] ?? ''),
                'icon' => '&#128221;',
                'type_label' => 'Form',
            ];
        }

        // Menus
        foreach ($this->app->storage->findAll('menus') as $m) {
            // Also index individual menu item labels
            $labels = [];
            foreach ($m['items'] ?? [] as $item) {
                $labels[] = $item['label'] ?? '';
                foreach ($item['children'] ?? [] as $child) {
                    $labels[] = $child['label'] ?? '';
                }
            }
            $docs[] = [
                'type' => 'menu',
                'id' => $m['name'],
                'title' => $m['label'] ?? $m['name'],
                'name' => $m['name'] ?? '',
                'label' => implode(' ', $labels),
                'admin_url' => ocms_base_url() . '/admin/menus/edit/' . ($m['name'] ?? ''),
                'icon' => '&#9776;',
                'type_label' => 'Menu',
            ];
        }

        return $docs;
    }

    /**
     * Calculate the relevance score of a document for the given tokens.
     *
     * @param string $fullQuery The normalized full query string
     * @return float The computed relevance score
     */
    private function calculateScore(array $doc, array $tokens, string $fullQuery): float {
        $score = 0;

        foreach (self::WEIGHTS as $field => $weight) {
            $value = $doc[$field] ?? '';
            if (is_array($value)) $value = implode(' ', $value);
            if (!$value) continue;

            $normalized = $this->normalizeText($value);

            // Exact match of the full query (high bonus)
            if (str_contains($normalized, $fullQuery)) {
                $score += $weight * 3;

                // Bonus if the field starts with the query
                if (str_starts_with($normalized, $fullQuery)) {
                    $score += $weight * 2;
                }
            }

            // Individual token matching
            foreach ($tokens as $token) {
                if (str_contains($normalized, $token)) {
                    $score += $weight;

                    // Bonus for whole-word match
                    if (preg_match('/\b' . preg_quote($token, '/') . '\b/iu', $normalized)) {
                        $score += $weight * 0.5;
                    }
                }
            }

            // Fuzzy match (Levenshtein distance) for the title field
            if ($field === 'title' && strlen($fullQuery) >= 4) {
                $words = explode(' ', $normalized);
                foreach ($words as $word) {
                    if (strlen($word) < 3) continue;
                    foreach ($tokens as $token) {
                        if (strlen($token) < 3) continue;
                        $dist = levenshtein(substr($token, 0, 10), substr($word, 0, 10));
                        if ($dist <= 2 && $dist > 0) {
                            $score += ($weight * 0.3) / $dist;
                        }
                    }
                }
            }
        }

        return round($score, 2);
    }

    /**
     * Generate highlighted excerpts with matching tokens emphasized.
     *
     * @param array $doc    The document to highlight
     * @param array $tokens The search tokens
     * @return array Highlighted text fragments keyed by field name
     */
    private function highlight(array $doc, array $tokens): array {
        $highlights = [];

        // Highlight in the title
        $title = $doc['title'] ?? '';
        if ($title) {
            $highlights['title'] = $this->highlightText($title, $tokens);
        }

        // Highlight in excerpt/description
        $excerpt = $doc['excerpt'] ?? $doc['description'] ?? '';
        if ($excerpt) {
            $highlights['excerpt'] = $this->highlightText($excerpt, $tokens);
        }

        // Highlight in content (contextual excerpt)
        $content = $doc['content'] ?? '';
        if ($content && $tokens) {
            $highlights['content'] = $this->extractContextualSnippet($content, $tokens, 200);
        }

        return $highlights;
    }

    /**
     * Wrap matching tokens in <mark> tags within a text string.
     *
     * @param string $text   The input text
     * @param array  $tokens Tokens to highlight
     * @return string HTML with highlighted matches
     */
    private function highlightText(string $text, array $tokens): string {
        $escaped = htmlspecialchars($text);
        foreach ($tokens as $token) {
            $pattern = '/(' . preg_quote(htmlspecialchars($token), '/') . ')/iu';
            $escaped = preg_replace($pattern, '<mark>$1</mark>', $escaped);
        }
        return $escaped;
    }

    /**
     * Extract a text snippet centered around the first match.
     *
     * @param string $text   The full text to extract from
     * @param array  $tokens Search tokens to locate
     * @param int    $length Snippet length in characters (default: 200)
     * @return string Highlighted snippet with ellipsis
     */
    private function extractContextualSnippet(string $text, array $tokens, int $length = 200): string {
        $textLower = $this->normalizeText($text);

        // Find the position of the first match
        $firstPos = strlen($text);
        foreach ($tokens as $token) {
            $pos = strpos($textLower, $token);
            if ($pos !== false && $pos < $firstPos) {
                $firstPos = $pos;
            }
        }

        // Extract the snippet
        $start = max(0, $firstPos - (int)($length / 3));
        $snippet = substr($text, $start, $length);

        // Clean up start/end boundaries
        if ($start > 0) $snippet = '…' . ltrim($snippet);
        if ($start + $length < strlen($text)) $snippet = rtrim($snippet) . '…';

        return $this->highlightText($snippet, $tokens);
    }

    /**
     * Tokenize a query string into meaningful words, filtering out stop words.
     *
     * @param string $query The raw query
     * @return array List of normalized token strings
     */
    private function tokenize(string $query): array {
        $query = $this->normalizeText($query);
        $words = preg_split('/\s+/', $query);
        $words = array_filter($words, function ($w) {
            return strlen($w) >= 2 && !in_array($w, self::STOP_WORDS);
        });
        return array_values(array_unique($words));
    }

    /**
     * Normalize text for comparison (lowercase, strip tags, remove accents).
     *
     * @param string $text The input text
     * @return string Normalized text
     */
    private function normalizeText(string $text): string {
        $text = strip_tags($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        // Remove accents
        $accents = ['à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
                     'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
                     'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c','ß'=>'ss'];
        $text = strtr($text, $accents);
        return trim($text);
    }

    /**
     * Return available filter options for the search UI.
     *
     * @return array Associative array with 'types', 'categories', and 'statuses'
     */
    public function getFilterOptions(): array {
        return [
            'types' => [
                'page' => 'Pagine',
                'article' => 'Articoli',
                'lesson' => 'Lezioni',
                'category' => 'Categorie',
                'user' => 'Utenti',
                'media' => 'Media',
                'form' => 'Form',
                'menu' => 'Menu',
            ],
            'categories' => array_map(fn($c) => $c['name'], $this->app->storage->findAll('categories', null, 'name', 'asc')),
            'statuses' => ['published' => 'Pubblicato', 'draft' => 'Bozza'],
        ];
    }
}
