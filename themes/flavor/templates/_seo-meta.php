<?php
/**
 * Open Graph, Twitter card e link canonico.
 *
 * Trappole note (non reintrodurle):
 * - og:image deve essere un URL assoluto. I contenuti salvano percorsi come
 *   /uploads/images/x.webp (senza la base del sito) ma alcune lezioni li salvano
 *   già con la base (/s/uploads/...): si normalizza togliendo la base se presente,
 *   altrimenti si otterrebbe /s/s/uploads/...
 * - og:url NON si costruisce con ocms_absolute_url(): REQUEST_URI contiene già la
 *   base del sito e si otterrebbe di nuovo /s/s/. Si usa schema + host + REQUEST_URI
 *   senza query string.
 * - Le cover sono WebP: i crawler (Facebook, WhatsApp) spesso non ne deducono le
 *   dimensioni, quindi si emettono og:image:width/height/type letti dal file.
 */
if (!function_exists('flavor_seo_meta')) {
    function flavor_seo_meta(array $o = []): void {
        $config   = ocms_config();
        $siteName = (string) ($config['site_name'] ?? 'O-CMS');
        $e = static fn($v) => ocms_escape((string) $v);

        $title = trim((string) ($o['title'] ?? '')) ?: $siteName;
        $desc  = trim((string) ($o['description'] ?? '')) ?: (string) ($config['site_description'] ?? '');
        $desc  = ocms_truncate(trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode(strip_tags($desc), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''), 300);
        $type  = (string) ($o['type'] ?? 'website');

        $img = $o['image'] ?? '';
        $img = is_string($img) ? trim($img) : '';
        if ($img === '' && is_file(ocms_base_path() . '/logo.png')) {
            $img = '/logo.png';
        }

        $imgW = $imgH = 0;
        $imgType = '';
        if ($img !== '' && !preg_match('#^https?://#i', $img)) {
            if ($img[0] !== '/') {
                $img = '/' . $img;
            }
            $base = ocms_base_url();
            if ($base !== '' && strncmp($img, $base . '/', strlen($base) + 1) === 0) {
                $img = substr($img, strlen($base));
            }
            $fsPath = ocms_base_path() . $img;
            if (strpos($img, '..') === false && is_file($fsPath) && ($info = @getimagesize($fsPath))) {
                $imgW    = (int) $info[0];
                $imgH    = (int) $info[1];
                $imgType = (string) ($info['mime'] ?? '');
            }
            $img = ocms_absolute_url($img);
        }

        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $uri    = (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $url    = $host !== '' ? ($https ? 'https' : 'http') . '://' . $host . $uri : '';

        $out = [];
        $out[] = '<meta property="og:type" content="' . $e($type) . '">';
        $out[] = '<meta property="og:site_name" content="' . $e($siteName) . '">';
        $out[] = '<meta property="og:locale" content="' . $e(($config['language'] ?? 'it') === 'it' ? 'it_IT' : ($config['language'] ?? 'it')) . '">';
        $out[] = '<meta property="og:title" content="' . $e($title) . '">';
        if ($desc !== '') {
            $out[] = '<meta property="og:description" content="' . $e($desc) . '">';
        }
        if ($url !== '') {
            $out[] = '<meta property="og:url" content="' . $e($url) . '">';
            $out[] = '<link rel="canonical" href="' . $e($url) . '">';
        }
        if ($img !== '') {
            $out[] = '<meta property="og:image" content="' . $e($img) . '">';
            $out[] = '<meta property="og:image:alt" content="' . $e($title) . '">';
            if ($imgW > 0 && $imgH > 0) {
                $out[] = '<meta property="og:image:width" content="' . $imgW . '">';
                $out[] = '<meta property="og:image:height" content="' . $imgH . '">';
            }
            if ($imgType !== '') {
                $out[] = '<meta property="og:image:type" content="' . $e($imgType) . '">';
            }
        }
        if ($type === 'article') {
            foreach (['published' => 'article:published_time', 'modified' => 'article:modified_time'] as $k => $prop) {
                if (!empty($o[$k]) && is_string($o[$k])) {
                    $out[] = '<meta property="' . $prop . '" content="' . $e($o[$k]) . '">';
                }
            }
            if (!empty($o['author']) && is_string($o['author'])) {
                $out[] = '<meta property="article:author" content="' . $e($o['author']) . '">';
            }
        }
        $out[] = '<meta name="twitter:card" content="' . ($img !== '' ? 'summary_large_image' : 'summary') . '">';
        $out[] = '<meta name="twitter:title" content="' . $e($title) . '">';
        if ($desc !== '') {
            $out[] = '<meta name="twitter:description" content="' . $e($desc) . '">';
        }
        if ($img !== '') {
            $out[] = '<meta name="twitter:image" content="' . $e($img) . '">';
        }
        echo "\n    " . implode("\n    ", $out) . "\n";
    }
}
