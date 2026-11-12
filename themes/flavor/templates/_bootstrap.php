<?php
/**
 * Flavor — funzioni condivise dai template.
 * Incluso (require_once) da _header.php e dai template che servono le funzioni
 * prima dell'header. Tutte le funzioni sono protette da function_exists perché
 * un altro tema può ricadere sui template di flavor nella stessa richiesta.
 */
require_once __DIR__ . '/_tag-label.php';
require_once __DIR__ . '/_seo-meta.php';

if (!function_exists('flavor_theme_url')) {
    /** URL della cartella del tema (funziona anche se il tema viene copiato con un altro nome). */
    function flavor_theme_url(): string {
        return ocms_base_url() . '/themes/' . rawurlencode(basename(dirname(__DIR__)));
    }

    /** URL di un asset del tema con versione per l'invalidazione della cache. */
    function flavor_asset(string $rel): string {
        $fs = dirname(__DIR__) . '/assets/' . $rel;
        $v = is_file($fs) ? (string) filemtime($fs) : '1';
        return flavor_theme_url() . '/assets/' . $rel . '?v=' . $v;
    }

    /** URL di un asset del design system condiviso (assets/ocms/). */
    function flavor_ocms_asset(string $rel): string {
        $fs = ocms_base_path() . '/assets/ocms/' . $rel;
        $v = is_file($fs) ? (string) filemtime($fs) : '1';
        return ocms_base_url() . '/assets/ocms/' . $rel . '?v=' . $v;
    }

    /** Percorso relativo alla radice del sito, senza base URL duplicata (/s/uploads → /uploads). */
    function flavor_media_rel(string $path): string {
        $path = trim($path);
        if ($path === '' || preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        $base = ocms_base_url();
        if ($base !== '' && strncmp($path, $base . '/', strlen($base) + 1) === 0) {
            $path = substr($path, strlen($base));
        }
        return $path;
    }

    /** URL pubblico di un file media salvato nei dati. */
    function flavor_media(string $path): string {
        $rel = flavor_media_rel($path);
        if ($rel === '' || $rel[0] !== '/' || str_starts_with($rel, '//')) {
            return $rel;
        }
        return ocms_base_url() . $rel;
    }

    /** Dimensioni reali di un'immagine locale: [larghezza, altezza] oppure [0, 0]. */
    function flavor_img_size(string $path): array {
        static $cache = [];
        $rel = flavor_media_rel($path);
        if ($rel === '' || $rel[0] !== '/' || str_starts_with($rel, '//') || strpos($rel, '..') !== false) {
            return [0, 0];
        }
        if (!isset($cache[$rel])) {
            $fs = ocms_base_path() . rawurldecode($rel);
            $info = is_file($fs) ? @getimagesize($fs) : false;
            $cache[$rel] = $info ? [(int) $info[0], (int) $info[1]] : [0, 0];
        }
        return $cache[$rel];
    }

    /** Testo semplice da un campo che può contenere HTML o entità (&nbsp;, &egrave;...). */
    function flavor_plain($v): string {
        if (!is_scalar($v)) return '';
        $t = html_entity_decode(strip_tags((string) $v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $t) ?? '');
    }

    /** Minuti di lettura stimati (220 parole al minuto, minimo 1). */
    function flavor_read_time(string $html): int {
        $text = trim(html_entity_decode(strip_tags(preg_replace('#<(script|style|svg)\b.*?</\1>#is', ' ', $html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') return 1;
        $words = preg_split('/\s+/u', $text) ?: [];
        return max(1, (int) round(count($words) / 220));
    }

    /** Data in italiano: "12 novembre 2026" (o "12 nov 2026" se $short). */
    function flavor_date(?string $iso, bool $short = false): string {
        if (!$iso) return '';
        try {
            $d = new DateTime($iso);
        } catch (\Throwable $e) {
            return '';
        }
        $long = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        $m = $long[(int) $d->format('n') - 1];
        return $d->format('j') . ' ' . ($short ? mb_substr($m, 0, 3) : $m) . ' ' . $d->format('Y');
    }

    /** Valore per l'attributo datetime. */
    function flavor_datetime(?string $iso): string {
        if (!$iso) return '';
        try {
            return (new DateTime($iso))->format(DateTime::ATOM);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** URL di una voce di menu: assoluto, relativo alla base o null per i segnaposto "#". */
    function flavor_menu_url(string $url): ?string {
        $url = trim($url);
        if ($url === '' || $url === '#') return null;
        if (preg_match('#^(https?:|mailto:|tel:)#i', $url)) return $url;
        if ($url[0] === '#') return $url;
        if ($url[0] !== '/') $url = '/' . $url;
        $base = ocms_base_url();
        if ($base !== '' && ($url === $base || strncmp($url, $base . '/', strlen($base) + 1) === 0)) {
            return $url;
        }
        return $base . $url;
    }

    /** Nome sicuro per view-transition-name (deve essere un identificatore CSS). */
    function flavor_vt_name(string $slug): string {
        return 'cover-' . trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)) ?? '', '-');
    }

    /** Contenuto HTML pronto: percorsi /uploads con la base del sito, shortcode galleria e form. */
    function flavor_content(string $html): string {
        $base = ocms_base_url();
        if ($base !== '') {
            $html = str_replace(['src="/uploads/', "src='/uploads/", 'href="/uploads/'], ['src="' . $base . '/uploads/', "src='" . $base . '/uploads/', 'href="' . $base . '/uploads/'], $html);
        }
        return ocms_render_form_shortcode(ocms_render_gallery_shortcode($html));
    }

    /** Messaggi flash come avvisi accessibili. */
    function flavor_flashes(array $flashes): string {
        $out = '';
        foreach ($flashes as $f) {
            $type = ($f['type'] ?? '') === 'error' ? 'error' : (($f['type'] ?? '') === 'success' ? 'success' : 'info');
            $role = $type === 'error' ? 'alert' : 'status';
            $out .= '<div class="notice notice-' . $type . '" role="' . $role . '">' . flavor_icon($type === 'error' ? 'alert' : 'check') . '<span>' . ocms_escape($f['message'] ?? '') . '</span></div>';
        }
        return $out;
    }

    /** Icone SVG in linea (stroke = currentColor, decorative). */
    function flavor_icon(string $name, int $size = 18): string {
        static $paths = [
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'moon'     => '<path d="M20.5 14.2A8.5 8.5 0 1 1 9.8 3.5a7 7 0 0 0 10.7 10.7z"/>',
            'menu'     => '<path d="M4 7h16M4 12h16M4 17h10"/>',
            'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
            'chevron'  => '<path d="m6 9 6 6 6-6"/>',
            'left'     => '<path d="m15 18-6-6 6-6"/>',
            'right'    => '<path d="m9 18 6-6-6-6"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
            'star'     => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
            'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            'file'     => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>',
            'html'     => '<path d="m8 9-4 3 4 3M16 9l4 3-4 3M13.5 6l-3 12"/>',
            'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-9 8"/>',
            'audio'    => '<path d="M9 18V6l11-2v12"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
            'video'    => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/>',
            'pdf'      => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M8.5 13h7M8.5 17h5"/>',
            'archive'  => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/>',
            'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
            'download' => '<path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/>',
            'lock'     => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            'check'    => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            'alert'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/>',
            'list'     => '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
            'timer'    => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M9.5 2h5"/>',
            'reply'    => '<path d="M10 8 5 12l5 4"/><path d="M5 12h9a5 5 0 0 1 5 5v1"/>',
            'copy'     => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
            'home'     => '<path d="m3 11 9-7 9 7"/><path d="M5 10v10h14V10"/>',
            'pen'      => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
            'qr'       => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v.01M14 21h.01M17.5 17.5H21V21h-3.5z"/>',
        ];
        $p = $paths[$name] ?? $paths['file'];
        return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }

    /** Copertina di un contenuto: <img> con dimensioni reali, oppure un segnaposto tipografico. */
    function flavor_cover(array $item, array $o = []): string {
        $title = (string) ($item['title'] ?? '');
        $cover = is_string($item['cover_image'] ?? null) ? trim($item['cover_image']) : '';
        $vt    = !empty($o['vt']) ? ' style="--vt: ' . ocms_escape(flavor_vt_name((string) ($item['slug'] ?? ''))) . '"' : '';
        $class = $o['class'] ?? 'cover';
        if ($cover !== '') {
            [$w, $h] = flavor_img_size($cover);
            if ($w <= 0 || $h <= 0) { $w = 1200; $h = 630; }
            $eager = !empty($o['eager']);
            return '<img class="' . ocms_escape($class) . '" src="' . ocms_escape(flavor_media($cover)) . '" alt="' . ocms_escape($o['alt'] ?? '') . '"'
                . ' width="' . $w . '" height="' . $h . '"'
                . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"'
                . (!empty($o['sizes']) ? ' sizes="' . ocms_escape($o['sizes']) . '"' : '')
                . $vt . '>';
        }
        // Segnaposto: iniziale del titolo su un gradiente che varia con lo slug
        $hue = hexdec(substr(md5((string) ($item['slug'] ?? $title)), 0, 2)) % 3;
        $initial = mb_strtoupper(mb_substr(trim($title) !== '' ? trim($title) : '?', 0, 1));
        return '<div class="' . ocms_escape($class) . ' cover-placeholder ph-' . $hue . '" aria-hidden="true"' . $vt . '><span>' . ocms_escape($initial) . '</span></div>';
    }

    /** Card di un articolo per griglie (blog, home, correlati). */
    function flavor_article_card(array $a, array $o = []): string {
        $url   = ocms_base_url() . '/blog/' . rawurlencode((string) $a['slug']);
        $title = ocms_escape($a['title'] ?? '');
        $variant = $o['variant'] ?? 'standard';
        $heading = $o['heading'] ?? 'h2';
        $mins  = flavor_read_time((string) ($a['content'] ?? ''));
        $cat   = (string) ($a['category'] ?? '');
        $catName = $o['catNames'][$cat] ?? ($cat !== '' ? ucfirst(str_replace('-', ' ', $cat)) : '');

        $h  = '<article class="card card-' . ocms_escape($variant) . '">';
        $h .= '<div class="card-media">' . flavor_cover($a, ['class' => 'cover', 'vt' => true, 'eager' => !empty($o['eager']), 'sizes' => $variant === 'lead' ? '(min-width: 900px) 60vw, 100vw' : '(min-width: 900px) 33vw, 100vw']);
        if ($catName !== '') {
            $h .= '<span class="card-cat">' . ocms_escape($catName) . '</span>';
        }
        $h .= '</div><div class="card-body">';
        if (!empty($a['featured']) && empty($o['hideFeatured'])) {
            $h .= '<span class="card-flag">' . flavor_icon('star', 14) . '<span>In evidenza</span></span>';
        }
        $h .= '<' . $heading . ' class="card-title"><a href="' . $url . '">' . $title . '</a></' . $heading . '>';
        if ($variant !== 'compact' && !empty($a['excerpt'])) {
            $h .= '<p class="card-excerpt">' . ocms_escape(ocms_truncate(flavor_plain($a['excerpt']), $variant === 'lead' ? 220 : 140)) . '</p>';
        }
        $h .= '<div class="card-meta">';
        if (!empty($a['created_at'])) {
            $h .= '<time datetime="' . ocms_escape(flavor_datetime($a['created_at'])) . '">' . ocms_escape(flavor_date($a['created_at'], true)) . '</time>';
        }
        if ($variant !== 'compact') {
            $h .= '<span>' . flavor_icon('clock', 14) . $mins . ' min</span>';
        }
        $h .= '</div></div></article>';
        return $h;
    }

    /** Iniziali per l'avatar dei commenti (nessuna richiesta a servizi esterni). */
    function flavor_initials(string $name): string {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $ini = '';
        foreach (array_slice(array_filter($parts), 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $ini !== '' ? $ini : '?';
    }
}
