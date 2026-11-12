<?php
/**
 * O-CMS — Motore di Ricerca
 *
 * Ricerca full-text su tutti i contenuti con:
 * - Indicizzazione automatica
 * - Rilevanza pesata (titolo vale più del contenuto)
 * - Filtri per tipo, stato, categoria, data
 * - Evidenziazione risultati
 * - Ricerca fuzzy (tolleranza errori)
 * - Suggerimenti
 */
class SearchEngine {
    private App $app;
    private string $indexPath;

    // Pesi per campo (più alto = più rilevante)
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

    // Stop words italiane (ignorate nella ricerca)
    private const STOP_WORDS = [
        'il','lo','la','i','gli','le','un','uno','una','di','del','dello','della',
        'dei','degli','delle','a','al','allo','alla','ai','agli','alle','da','dal',
        'dallo','dalla','dai','dagli','dalle','in','nel','nello','nella','nei','negli',
        'nelle','con','su','sul','sullo','sulla','sui','sugli','sulle','per','tra',
        'fra','e','o','ma','che','non','è','sono','ha','ho','come','più','anche',
        'the','and','or','is','in','to','of','for','a','an','with','on','at','from',
    ];

    public function __construct(App $app) {
        $this->app = $app;
        $this->indexPath = ocms_data_path('cache/search-index.json');
    }

    /**
     * Ricerca principale — restituisce risultati ordinati per rilevanza
     */
    public function search(string $query, array $options = []): array {
        $query = trim($query);
        if (strlen($query) < 2) return ['results' => [], 'total' => 0, 'query' => $query];

        // Opzioni
        $types = $options['types'] ?? []; // ['page','article','user','category','menu','media','form']
        $status = $options['status'] ?? ''; // 'published', 'draft', ''
        $category = $options['category'] ?? '';
        $dateFrom = $options['date_from'] ?? '';
        $dateTo = $options['date_to'] ?? '';
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;

        // Tokenizza la query
        $tokens = $this->tokenize($query);
        $queryLower = $this->normalizeText($query);

        // Raccogli tutti i documenti indicizzabili
        $documents = $this->getAllDocuments($types);

        // Calcola score per ogni documento
        $scored = [];
        foreach ($documents as $doc) {
            // Filtro tipo
            if (!empty($types) && !in_array($doc['type'], $types)) continue;

            // Filtro stato
            if ($status && isset($doc['status']) && $doc['status'] !== $status) continue;

            // Filtro categoria
            if ($category && ($doc['category'] ?? '') !== $category) continue;

            // Filtro data
            if ($dateFrom && ($doc['date'] ?? '') < $dateFrom) continue;
            if ($dateTo && ($doc['date'] ?? '') > $dateTo) continue;

            // Calcola rilevanza
            $score = $this->calculateScore($doc, $tokens, $queryLower);
            if ($score > 0) {
                $doc['score'] = $score;
                $doc['highlights'] = $this->highlight($doc, $tokens);
                $scored[] = $doc;
            }
        }

        // Ordina per rilevanza
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
     * Ricerca rapida (per sidebar) — restituisce max 8 risultati misti
     */
    public function quickSearch(string $query): array {
        return $this->search($query, ['limit' => 8]);
    }

    /**
     * Suggerimenti di completamento
     */
    public function suggest(string $query, int $limit = 5): array {
        if (strlen($query) < 2) return [];

        $queryLower = $this->normalizeText($query);
        $suggestions = [];

        // Raccogli tutti i titoli/nomi
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
     * Raccoglie tutti i documenti ricercabili dal CMS
     */
    private function getAllDocuments(array $types = []): array {
        $docs = [];
        // Si leggono solo le collezioni richieste: la ricerca pubblica non deve
        // decodificare utenti, media e form a ogni query
        $want = fn(string $type) => empty($types) || in_array($type, $types, true);

        // Pagine
        if ($want('page')) foreach ($this->app->storage->findAll('pages') as $p) {
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

        // Articoli
        if ($want('article')) foreach ($this->app->storage->findAll('articles') as $a) {
            $docs[] = [
                'type' => 'article',
                'id' => $a['slug'],
                'title' => $a['title'] ?? '',
                'slug' => $a['slug'] ?? '',
                'content' => strip_tags($a['content'] ?? ''),
                'excerpt' => $a['excerpt'] ?? '',
                'status' => ocms_is_future($a['publish_at'] ?? '') ? 'scheduled' : ($a['status'] ?? 'draft'),
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

        // Lezioni
        if ($want('lesson')) foreach ($this->app->storage->findAll('lessons') as $l) {
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

        // Categorie
        if ($want('category')) foreach ($this->app->storage->findAll('categories') as $c) {
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

        // Utenti
        if ($want('user')) foreach ($this->app->storage->findAll('users') as $u) {
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
        if ($want('media')) foreach ($this->app->storage->findAll('media') as $m) {
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

        // Form
        if ($want('form')) foreach ($this->app->storage->findAll('forms') as $f) {
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

        // Menu
        if ($want('menu')) foreach ($this->app->storage->findAll('menus') as $m) {
            // Indicizza anche le voci del menu
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
     * Calcola lo score di rilevanza di un documento per i token dati
     */
    private function calculateScore(array $doc, array $tokens, string $fullQuery): float {
        $score = 0;

        foreach (self::WEIGHTS as $field => $weight) {
            $value = $doc[$field] ?? '';
            if (is_array($value)) $value = implode(' ', $value);
            if (!$value) continue;

            $normalized = $this->normalizeText($value);

            // Match esatta della query completa (bonus alto)
            if (str_contains($normalized, $fullQuery)) {
                $score += $weight * 3;

                // Bonus se inizia con la query
                if (str_starts_with($normalized, $fullQuery)) {
                    $score += $weight * 2;
                }
            }

            // Match per singoli token
            foreach ($tokens as $token) {
                if (str_contains($normalized, $token)) {
                    $score += $weight;

                    // Bonus per match di parola intera
                    if (preg_match('/\b' . preg_quote($token, '/') . '\b/iu', $normalized)) {
                        $score += $weight * 0.5;
                    }
                }
            }

            // Fuzzy match (distanza di Levenshtein) per il titolo
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
     * Genera highlight (estratti con match evidenziati)
     */
    private function highlight(array $doc, array $tokens): array {
        $highlights = [];

        // Highlight sul titolo
        $title = $doc['title'] ?? '';
        if ($title) {
            $highlights['title'] = $this->highlightText($title, $tokens);
        }

        // Highlight su excerpt/description
        $excerpt = $doc['excerpt'] ?? $doc['description'] ?? '';
        if ($excerpt) {
            $highlights['excerpt'] = $this->highlightText($excerpt, $tokens);
        }

        // Highlight sul contenuto (estratto contestuale)
        $content = $doc['content'] ?? '';
        if ($content && $tokens) {
            $highlights['content'] = $this->extractContextualSnippet($content, $tokens, 200);
        }

        return $highlights;
    }

    /**
     * Evidenzia i token in un testo
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
     * Estrae un frammento di testo attorno al primo match
     */
    private function extractContextualSnippet(string $text, array $tokens, int $length = 200): string {
        $textLower = $this->normalizeText($text);

        // Trova la posizione del primo match
        $firstPos = strlen($text);
        foreach ($tokens as $token) {
            $pos = strpos($textLower, $token);
            if ($pos !== false && $pos < $firstPos) {
                $firstPos = $pos;
            }
        }

        // Estrai il frammento
        $start = max(0, $firstPos - (int)($length / 3));
        $snippet = substr($text, $start, $length);

        // Pulisci inizio/fine
        if ($start > 0) $snippet = '…' . ltrim($snippet);
        if ($start + $length < strlen($text)) $snippet = rtrim($snippet) . '…';

        return $this->highlightText($snippet, $tokens);
    }

    /**
     * Tokenizza una query in parole significative
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
     * Normalizza testo per il confronto
     */
    private function normalizeText(string $text): string {
        $text = strip_tags($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        // Rimuovi accenti
        $accents = ['à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
                     'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
                     'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c','ß'=>'ss'];
        $text = strtr($text, $accents);
        return trim($text);
    }

    /**
     * Restituisce le categorie disponibili per i filtri
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
