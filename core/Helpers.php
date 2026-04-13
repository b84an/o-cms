<?php
/**
 * O-CMS — Utility Functions
 *
 * Collection of global helper functions used throughout the CMS: UUID generation,
 * slug creation, escaping, CSRF protection, email sending, image resizing,
 * analytics tracking, shortcode rendering, and more.
 *
 * @package O-CMS
 * @version 1.0.0
 */

/**
 * Generate a UUID v4 (random).
 *
 * @return string A 36-character UUID string
 */
function ocms_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Generate a URL-safe slug from a string.
 *
 * @param string $text The input text to slugify
 * @return string Lowercase, hyphenated slug
 */
function ocms_slug(string $text): string {
    // Use intl extension if available, otherwise manual fallback
    if (function_exists('transliterator_transliterate')) {
        $result = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $text = $result ?: (function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text));
    } else {
        // Fallback: remove common accents and convert to lowercase
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        $accents = ['à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
                     'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
                     'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c','ß'=>'ss'];
        $text = strtr($text, $accents);
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Escape a string for safe HTML output.
 *
 * @param string $value The raw string
 * @return string HTML-escaped string
 */
function ocms_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Return the current timestamp in ISO 8601 format.
 *
 * @return string ISO 8601 date string
 */
function ocms_now(): string {
    return date('c');
}

/**
 * Generate a CSRF token and store it in the session.
 *
 * @return string The CSRF token
 */
function ocms_csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Return an HTML hidden input field containing the CSRF token.
 *
 * @return string HTML <input> element
 */
function ocms_csrf_field(): string {
    $token = ocms_escape(ocms_csrf_token());
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

/**
 * Verify the CSRF token from the current POST request.
 *
 * @return bool True if the token matches
 */
function ocms_csrf_verify(): bool {
    $token = $_POST['_csrf_token'] ?? '';
    return hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

/**
 * Redirect to a URL via Location header and exit.
 *
 * @param string $url  The target URL
 * @param int    $code HTTP status code (default: 302)
 * @return void
 */
function ocms_redirect(string $url, int $code = 302): void {
    http_response_code($code);
    header('Location: ' . $url);
    exit;
}

/**
 * Return the filesystem base path of the CMS installation.
 *
 * @return string Absolute directory path
 */
function ocms_base_path(): string {
    return dirname(__DIR__);
}

/**
 * Return the filesystem path to the data directory.
 *
 * @param string $sub Optional subdirectory to append
 * @return string Absolute path
 */
function ocms_data_path(string $sub = ''): string {
    $path = ocms_base_path() . '/data';
    if ($sub) {
        $path .= '/' . ltrim($sub, '/');
    }
    return $path;
}

/**
 * Return the site base URL (e.g. '/s').
 *
 * @return string URL path without trailing slash
 */
function ocms_base_url(): string {
    $config = ocms_config();
    return rtrim($config['site_url'] ?? '', '/');
}

/**
 * Load the global site configuration (statically cached).
 *
 * @return array Configuration key-value pairs
 */
function ocms_config(): array {
    static $config = null;
    if ($config === null) {
        $file = ocms_data_path('config.json');
        $config = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    }
    return $config;
}

/**
 * Store a flash message in the session.
 *
 * @param string $type    Message type (e.g. 'success', 'error', 'warning')
 * @param string $message The message text
 * @return void
 */
function ocms_flash_set(string $type, string $message): void {
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear all flash messages from the session.
 *
 * @return array List of flash message arrays with 'type' and 'message'
 */
function ocms_flash_get(): array {
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/**
 * Truncate a string to the specified length, appending an ellipsis.
 *
 * @param string $text   The input string
 * @param int    $length Maximum length (default: 160)
 * @return string Truncated string
 */
function ocms_truncate(string $text, int $length = 160): string {
    $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($len <= $length) {
        return $text;
    }
    return (function_exists('mb_substr') ? mb_substr($text, 0, $length) : substr($text, 0, $length)) . '…';
}

/**
 * Format an ISO date string into a human-readable format.
 *
 * @param string $date   ISO 8601 date string
 * @param string $format PHP date format (default: 'd/m/Y H:i')
 * @return string Formatted date
 */
function ocms_format_date(string $date, string $format = 'd/m/Y H:i'): string {
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Send an email using the configured method (SMTP or PHP mail).
 *
 * @param string $to       Recipient email address
 * @param string $subject  Email subject
 * @param string $htmlBody HTML body content
 * @return bool True on success
 */
function ocms_send_mail(string $to, string $subject, string $htmlBody): bool {
    $config = ocms_config();
    $smtp = $config['smtp'] ?? [];
    $method = $smtp['method'] ?? 'smtp';
    $fromEmail = $smtp['from_email'] ?? '';
    $fromName = $smtp['from_name'] ?? ($config['site_name'] ?? 'O-CMS');

    if (empty($fromEmail)) {
        return false;
    }

    if ($method === 'php_mail') {
        return ocms_send_mail_php($to, $subject, $htmlBody, $fromEmail, $fromName);
    }

    return ocms_send_mail_smtp($to, $subject, $htmlBody, $smtp, $fromEmail, $fromName);
}

/**
 * Send an email using PHP's built-in mail() function.
 *
 * @param string $to        Recipient email address
 * @param string $subject   Email subject
 * @param string $htmlBody  HTML body content
 * @param string $fromEmail Sender email address
 * @param string $fromName  Sender display name
 * @return bool True on success
 */
function ocms_send_mail_php(string $to, string $subject, string $htmlBody, string $fromEmail, string $fromName): bool {
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return @mail($to, $subject, $htmlBody, $headers);
}

/**
 * Send an email via direct SMTP socket connection.
 *
 * @param string $to        Recipient email address
 * @param string $subject   Email subject
 * @param string $htmlBody  HTML body content
 * @param array  $smtp      SMTP configuration (host, port, username, password, encryption)
 * @param string $fromEmail Sender email address
 * @param string $fromName  Sender display name
 * @return bool True on success
 */
function ocms_send_mail_smtp(string $to, string $subject, string $htmlBody, array $smtp, string $fromEmail, string $fromName): bool {
    if (empty($smtp['host'])) {
        return false;
    }

    $host = $smtp['host'];
    $port = (int)($smtp['port'] ?? 587);
    $user = $smtp['username'] ?? '';
    $pass = $smtp['password'] ?? '';
    $encryption = $smtp['encryption'] ?? 'tls';

    // Connection
    $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 10);

    $read = function () use ($socket) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    };

    $send = function (string $cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $read(); // banner

    $send('EHLO localhost');

    // STARTTLS
    if ($encryption === 'tls') {
        $send('STARTTLS');
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            fclose($socket);
            return false;
        }
        $send('EHLO localhost');
    }

    // AUTH
    if ($user && $pass) {
        $send('AUTH LOGIN');
        $send(base64_encode($user));
        $resp = $send(base64_encode($pass));
        if (strpos($resp, '235') === false) {
            fclose($socket);
            return false;
        }
    }

    $send("MAIL FROM:<{$fromEmail}>");
    $send("RCPT TO:<{$to}>");
    $send('DATA');

    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $message = $headers . "\r\n" . $htmlBody . "\r\n.";
    $resp = $send($message);

    $send('QUIT');
    fclose($socket);

    return strpos($resp, '250') !== false;
}

/**
 * Resize an image to fixed dimensions with centered crop (GD library).
 * Always saves as JPEG.
 *
 * @param string $srcPath  Source image path
 * @param string $destPath Destination image path
 * @param int    $targetW  Target width in pixels (default: 1200)
 * @param int    $targetH  Target height in pixels (default: 630)
 * @param int    $quality  JPEG quality 0-100 (default: 85)
 * @return bool True on success
 */
function ocms_resize_cover(string $srcPath, string $destPath, int $targetW = 1200, int $targetH = 630, int $quality = 85): bool {
    $info = getimagesize($srcPath);
    if (!$info) return false;

    $srcW = $info[0];
    $srcH = $info[1];
    $mime = $info['mime'];

    // Load source image
    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($srcPath),
        'image/png'  => imagecreatefrompng($srcPath),
        'image/gif'  => imagecreatefromgif($srcPath),
        'image/webp' => imagecreatefromwebp($srcPath),
        default      => false,
    };
    if (!$src) return false;

    // Calculate centered crop to fill the target while preserving aspect ratio
    $srcRatio = $srcW / $srcH;
    $targetRatio = $targetW / $targetH;

    if ($srcRatio > $targetRatio) {
        // Image is wider: horizontal crop
        $cropH = $srcH;
        $cropW = (int)($srcH * $targetRatio);
        $cropX = (int)(($srcW - $cropW) / 2);
        $cropY = 0;
    } else {
        // Image is taller: vertical crop
        $cropW = $srcW;
        $cropH = (int)($srcW / $targetRatio);
        $cropX = 0;
        $cropY = (int)(($srcH - $cropH) / 2);
    }

    $dest = imagecreatetruecolor($targetW, $targetH);
    imagecopyresampled($dest, $src, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);

    $result = imagejpeg($dest, $destPath, $quality);

    imagedestroy($src);
    imagedestroy($dest);

    return $result;
}

/**
 * Generate a simple math captcha question.
 *
 * @return array Associative array with 'question' (string) and 'answer' (int)
 */
function ocms_captcha_generate(): array {
    $a = random_int(1, 20);
    $b = random_int(1, 20);
    $ops = ['+', '-'];
    $op = $ops[array_rand($ops)];
    if ($op === '-' && $b > $a) { [$a, $b] = [$b, $a]; }
    $answer = ($op === '+') ? $a + $b : $a - $b;
    $_SESSION['_captcha_answer'] = $answer;
    return ['question' => "{$a} {$op} {$b} = ?", 'answer' => $answer];
}

/**
 * Verify a captcha answer against the session-stored expected value.
 *
 * @param string $input The user-submitted answer
 * @return bool True if correct
 */
function ocms_captcha_verify(string $input): bool {
    $expected = $_SESSION['_captcha_answer'] ?? null;
    unset($_SESSION['_captcha_answer']);
    if ($expected === null) return false;
    return (int)$input === (int)$expected;
}

/**
 * Sanitize HTML by allowing only safe tags (for article/page content).
 * Strips script, iframe, on* attributes, and javascript: URLs.
 *
 * @param string $html The raw HTML to sanitize
 * @return string Sanitized HTML
 */
function ocms_sanitize_html(string $html): string {
    // Allowed tags
    $allowed = '<h1><h2><h3><h4><h5><h6><p><br><hr><div><span><a><strong><b><em><i><u><s><del>'
             . '<ul><ol><li><blockquote><pre><code><table><thead><tbody><tr><th><td>'
             . '<img><figure><figcaption><video><source><audio><sub><sup><mark><small>';
    $html = strip_tags($html, $allowed);

    // Remove on* attributes (onclick, onerror, onload, etc.)
    $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $html);

    // Remove javascript: and data: in href/src attributes
    $html = preg_replace('/href\s*=\s*["\']?\s*javascript\s*:/i', 'href="', $html);
    $html = preg_replace('/src\s*=\s*["\']?\s*javascript\s*:/i', 'src="', $html);
    $html = preg_replace('/src\s*=\s*["\']?\s*data\s*:/i', 'src="', $html);

    // Remove style attributes containing expression/behavior
    $html = preg_replace('/style\s*=\s*["\'][^"\']*expression\s*\([^"\']*["\']/i', '', $html);
    $html = preg_replace('/style\s*=\s*["\'][^"\']*behavior\s*:[^"\']*["\']/i', '', $html);

    // Strip base URL from image paths (the editor adds them for preview purposes)
    $bu = ocms_base_url();
    if ($bu !== '' && $bu !== '/') {
        $html = str_replace('src="' . $bu . '/uploads/', 'src="/uploads/', $html);
        $html = str_replace('src="' . $bu . '/data/', 'src="/data/', $html);
    }

    return $html;
}

/**
 * Simple session-based rate limiter.
 * Returns true if the action is allowed, false if too frequent.
 *
 * @param string $key            Unique key for the rate-limited action
 * @param int    $maxAttempts    Maximum attempts allowed in the window
 * @param int    $windowSeconds  Time window in seconds (default: 300)
 * @return bool True if the action is permitted
 */
function ocms_rate_limit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $sessionKey = '_rate_' . $key;
    $now = time();

    $attempts = $_SESSION[$sessionKey] ?? [];
    // Filter out expired attempts
    $attempts = array_filter($attempts, fn($t) => $t > ($now - $windowSeconds));

    if (count($attempts) >= $maxAttempts) {
        return false;
    }

    $attempts[] = $now;
    $_SESSION[$sessionKey] = $attempts;
    return true;
}

/**
 * Track an anonymized page visit -- saves daily aggregates.
 *
 * @param string $path The visited URL path
 * @param string $type Content type (default: 'page')
 * @return void
 */
function ocms_track_visit(string $path, string $type = 'page'): void {
    $date = date('Y-m-d');
    $file = ocms_data_path('analytics/' . $date . '.json');

    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!$data) $data = ['date' => $date, 'total' => 0, 'pages' => [], 'referrers' => []];

    $data['total']++;
    $data['pages'][$path] = ($data['pages'][$path] ?? 0) + 1;

    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref) {
        $host = parse_url($ref, PHP_URL_HOST) ?? '';
        $ownHost = $_SERVER['HTTP_HOST'] ?? '';
        if ($host && $host !== $ownHost) {
            $data['referrers'][$host] = ($data['referrers'][$host] ?? 0) + 1;
        }
    }

    // Hour of the day (0-23)
    $hour = (int)date('G');
    $data['hours'][$hour] = ($data['hours'][$hour] ?? 0) + 1;

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Load aggregated analytics for a range of days.
 *
 * @param int $days Number of past days to include (default: 30)
 * @return array Aggregated analytics data (days, total, pages, referrers, hours)
 */
function ocms_analytics_range(int $days = 30): array {
    $result = ['days' => [], 'total' => 0, 'pages' => [], 'referrers' => [], 'hours' => array_fill(0, 24, 0)];
    $dir = ocms_data_path('analytics');

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $file = $dir . '/' . $date . '.json';
        $dayData = file_exists($file) ? json_decode(file_get_contents($file), true) : null;

        $views = $dayData['total'] ?? 0;
        $result['days'][] = ['date' => $date, 'views' => $views];
        $result['total'] += $views;

        if ($dayData) {
            foreach ($dayData['pages'] ?? [] as $p => $c) {
                $result['pages'][$p] = ($result['pages'][$p] ?? 0) + $c;
            }
            foreach ($dayData['referrers'] ?? [] as $r => $c) {
                $result['referrers'][$r] = ($result['referrers'][$r] ?? 0) + $c;
            }
            foreach ($dayData['hours'] ?? [] as $h => $c) {
                $result['hours'][(int)$h] += $c;
            }
        }
    }

    arsort($result['pages']);
    arsort($result['referrers']);
    $result['pages'] = array_slice($result['pages'], 0, 20, true);
    $result['referrers'] = array_slice($result['referrers'], 0, 15, true);

    return $result;
}

/**
 * Resize an image for gallery thumbnails (fit within max dimensions, preserving aspect ratio).
 *
 * @param string $srcPath  Source image path
 * @param string $destPath Destination image path
 * @param int    $maxW     Maximum width in pixels
 * @param int    $maxH     Maximum height in pixels
 * @param int    $quality  Output quality 0-100 (default: 85)
 * @return bool True on success
 */
function ocms_resize_image(string $srcPath, string $destPath, int $maxW, int $maxH, int $quality = 85): bool {
    $info = getimagesize($srcPath);
    if (!$info) return false;

    $srcW = $info[0];
    $srcH = $info[1];
    $mime = $info['mime'];

    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($srcPath),
        'image/png'  => imagecreatefrompng($srcPath),
        'image/gif'  => imagecreatefromgif($srcPath),
        'image/webp' => imagecreatefromwebp($srcPath),
        default      => false,
    };
    if (!$src) return false;

    // Fit within maxW x maxH while preserving aspect ratio
    $ratio = min($maxW / $srcW, $maxH / $srcH);
    if ($ratio >= 1) {
        // Image is smaller than target: copy without resizing
        $destW = $srcW;
        $destH = $srcH;
    } else {
        $destW = (int)($srcW * $ratio);
        $destH = (int)($srcH * $ratio);
    }

    $dest = imagecreatetruecolor($destW, $destH);

    // Preserve transparency for PNG
    if ($mime === 'image/png') {
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
    }

    imagecopyresampled($dest, $src, 0, 0, 0, 0, $destW, $destH, $srcW, $srcH);

    $result = match ($mime) {
        'image/png'  => imagepng($dest, $destPath, 8),
        default      => imagejpeg($dest, $destPath, $quality),
    };

    imagedestroy($src);
    imagedestroy($dest);
    return $result;
}

/**
 * Return a <style> tag setting the CSS variable --site-width from site configuration.
 *
 * @return string HTML <style> element
 */
function ocms_site_width_style(): string {
    $config = ocms_config();
    $value = $config['site_width_value'] ?? '90';
    $unit = $config['site_width_unit'] ?? '%';
    // Sanitize: allow only digits and decimal point
    $value = preg_replace('/[^0-9.]/', '', $value);
    if ($value === '' || floatval($value) <= 0) $value = '90';
    if (!in_array($unit, ['%', 'px'])) $unit = '%';
    return '<style>:root{--site-width:' . $value . $unit . ';}</style>';
}

/**
 * Replace [gallery:slug] shortcodes in content with rendered gallery HTML.
 *
 * @param string $content HTML content possibly containing gallery shortcodes
 * @return string Content with shortcodes replaced by gallery markup
 */
function ocms_render_gallery_shortcode(string $content): string {
    if (strpos($content, '[gallery:') === false) return $content;
    $storage = new JsonStorage();
    return preg_replace_callback('/\[gallery:([a-z0-9_-]+)\]/', function($m) use ($storage) {
        $gallery = $storage->find('galleries', $m[1]);
        if (!$gallery || ($gallery['status'] ?? '') !== 'published' || empty($gallery['images'])) {
            return '';
        }
        $slug = ocms_escape($gallery['slug']);
        $base = ocms_base_url() . '/uploads/gallery/' . $slug . '/';
        $uid = 'gembed-' . $slug;
        $images = $gallery['images'];
        $html = '<div id="' . $uid . '" style="column-count:3;column-gap:12px;margin:24px 0;">';
        $jsUrls = [];
        $jsCaptions = [];
        foreach ($images as $i => $img) {
            $thumb = $base . 'thumb_' . ocms_escape($img['filename']);
            $full = $base . ocms_escape($img['filename']);
            $title = ocms_escape($img['title'] ?? '');
            $html .= '<img src="' . $thumb . '" alt="' . $title . '" style="width:100%;border-radius:8px;margin-bottom:12px;cursor:pointer;break-inside:avoid;display:block;" onclick="' . $uid . 'Open(' . $i . ')">';
            $jsUrls[] = '"' . $full . '"';
            $jsCaptions[] = '"' . addslashes($img['title'] ?? '') . '"';
        }
        $html .= '</div>';
        // Lightbox + JS
        $html .= '<div id="' . $uid . '-lb" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.92);align-items:center;justify-content:center;cursor:pointer;" onclick="if(event.target===this)' . $uid . 'Close()">';
        $html .= '<img id="' . $uid . '-img" style="max-width:90vw;max-height:90vh;border-radius:8px;cursor:default;" onclick="event.stopPropagation()">';
        $html .= '<div id="' . $uid . '-cap" style="position:absolute;bottom:24px;left:50%;transform:translateX(-50%);color:#fff;font-size:0.9rem;text-align:center;text-shadow:0 1px 4px #000;"></div>';
        $html .= '<div style="position:absolute;top:16px;right:20px;color:#fff;font-size:1.5rem;cursor:pointer;opacity:0.7;" onclick="' . $uid . 'Close()">&#10005;</div>';
        $html .= '<div style="position:absolute;left:16px;top:50%;color:#fff;font-size:2rem;cursor:pointer;opacity:0.7;transform:translateY(-50%);" onclick="event.stopPropagation();' . $uid . 'Nav(-1)">&#10094;</div>';
        $html .= '<div style="position:absolute;right:16px;top:50%;color:#fff;font-size:2rem;cursor:pointer;opacity:0.7;transform:translateY(-50%);" onclick="event.stopPropagation();' . $uid . 'Nav(1)">&#10095;</div>';
        $html .= '</div>';
        $html .= '<script>(function(){var u=[' . implode(',', $jsUrls) . '],c=[' . implode(',', $jsCaptions) . '],ci=0,lb=document.getElementById("' . $uid . '-lb"),im=document.getElementById("' . $uid . '-img"),cp=document.getElementById("' . $uid . '-cap");';
        $html .= 'window.' . $uid . 'Open=function(i){ci=i;im.src=u[ci];cp.textContent=c[ci];lb.style.display="flex";};';
        $html .= 'window.' . $uid . 'Close=function(){lb.style.display="none";};';
        $html .= 'window.' . $uid . 'Nav=function(d){ci=(ci+d+u.length)%u.length;im.src=u[ci];cp.textContent=c[ci];};';
        $html .= 'document.addEventListener("keydown",function(e){if(lb.style.display==="none")return;if(e.key==="Escape")' . $uid . 'Close();if(e.key==="ArrowRight")' . $uid . 'Nav(1);if(e.key==="ArrowLeft")' . $uid . 'Nav(-1);});';
        $html .= '})();</script>';
        // Responsive columns
        $html .= '<style>@media(max-width:1024px){#' . $uid . '{column-count:2!important;}}@media(max-width:600px){#' . $uid . '{column-count:1!important;}}</style>';
        return $html;
    }, $content);
}

/**
 * Replace [form:slug] shortcodes in content with rendered form HTML.
 *
 * @param string $content HTML content possibly containing form shortcodes
 * @return string Content with shortcodes replaced by form markup
 */
function ocms_render_form_shortcode(string $content): string {
    if (strpos($content, '[form:') === false) return $content;
    $storage = new JsonStorage();
    return preg_replace_callback('/\[form:([a-z0-9_-]+)\]/', function($m) use ($storage) {
        $form = $storage->find('forms', $m[1]);
        if (!$form || empty($form['fields'])) return '';

        $slug = ocms_escape($form['slug']);
        $action = ocms_base_url() . '/form/submit/' . $slug;
        $submitLabel = ocms_escape($form['settings']['submit_label'] ?? 'Invia');

        // Captcha
        $a = rand(2, 9);
        $b = rand(2, 9);
        $_SESSION['form_captcha_' . $slug] = (string)($a + $b);

        $html = '<form method="POST" action="' . $action . '" style="max-width:600px;margin:24px 0;">';
        $html .= ocms_csrf_field();

        foreach ($form['fields'] as $field) {
            $name = ocms_escape($field['name'] ?? '');
            $label = ocms_escape($field['label'] ?? $name);
            $placeholder = ocms_escape($field['placeholder'] ?? '');
            $required = !empty($field['required']) ? 'required' : '';
            $type = $field['type'] ?? 'text';
            $reqStar = !empty($field['required']) ? ' *' : '';

            $html .= '<div style="margin-bottom:16px;">';
            if ($type !== 'checkbox') {
                $html .= '<label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:6px;color:var(--text-muted);">' . $label . $reqStar . '</label>';
            }

            $inputStyle = 'width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:1rem;font-family:inherit;';

            if ($type === 'textarea') {
                $html .= '<textarea name="' . $name . '" placeholder="' . $placeholder . '" ' . $required . ' rows="4" style="' . $inputStyle . 'resize:vertical;"></textarea>';
            } elseif ($type === 'select') {
                $html .= '<select name="' . $name . '" ' . $required . ' style="' . $inputStyle . '">';
                $html .= '<option value="">— Seleziona —</option>';
                foreach ($field['options'] ?? [] as $opt) {
                    $html .= '<option value="' . ocms_escape($opt) . '">' . ocms_escape($opt) . '</option>';
                }
                $html .= '</select>';
            } elseif ($type === 'radio') {
                foreach ($field['options'] ?? [] as $opt) {
                    $html .= '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;cursor:pointer;"><input type="radio" name="' . $name . '" value="' . ocms_escape($opt) . '" ' . $required . '> ' . ocms_escape($opt) . '</label>';
                }
            } elseif ($type === 'checkbox') {
                $html .= '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" name="' . $name . '" value="1"> ' . $label . '</label>';
            } else {
                $html .= '<input type="' . ocms_escape($type) . '" name="' . $name . '" placeholder="' . $placeholder . '" ' . $required . ' style="' . $inputStyle . '">';
            }
            $html .= '</div>';
        }

        // Captcha
        $html .= '<div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;">';
        $html .= '<label style="font-size:0.9rem;color:var(--text-muted);white-space:nowrap;">Quanto fa ' . $a . ' + ' . $b . '?</label>';
        $html .= '<input type="text" name="_captcha" required placeholder="?" style="width:80px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:1rem;text-align:center;">';
        $html .= '</div>';

        $html .= '<button type="submit" style="padding:12px 32px;background:var(--primary,#6366f1);color:white;border:none;border-radius:10px;font-weight:700;font-size:1rem;cursor:pointer;font-family:inherit;">' . $submitLabel . '</button>';
        $html .= '</form>';

        return $html;
    }, $content);
}

/**
 * Return the cover image URL for an article, with fallback to the site logo.
 *
 * @param array $article The article data array
 * @return string Full URL to the cover image
 */
function ocms_cover_url(array $article): string {
    if (!empty($article['cover_image'])) {
        return ocms_base_url() . $article['cover_image'];
    }
    $config = ocms_config();
    $theme = $config['theme'] ?? 'flavor';
    return ocms_base_url() . '/themes/' . $theme . '/assets/images/logo.png';
}

/**
 * Filter menu items, excluding those with published === false.
 * Recursively filters children as well.
 *
 * @param array $items Menu items array
 * @return array Filtered menu items
 */
function ocms_filter_menu_items(array $items): array {
    $filtered = [];
    foreach ($items as $item) {
        if (($item['published'] ?? true) === false) continue;
        if (!empty($item['children'])) {
            $item['children'] = ocms_filter_menu_items($item['children']);
        }
        $filtered[] = $item;
    }
    return $filtered;
}
