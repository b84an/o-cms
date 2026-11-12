<?php
/**
 * O-CMS — Funzioni Utilità
 */

/**
 * Genera un UUID v4
 */
function ocms_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Genera uno slug URL-safe da una stringa
 */
function ocms_slug(string $text): string {
    // Usa intl se disponibile, altrimenti fallback manuale
    if (function_exists('transliterator_transliterate')) {
        $result = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $text = $result ?: (function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text));
    } else {
        // Fallback: rimuovi accenti comuni e converti in minuscolo
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
 * Sanitizza una stringa per output HTML
 */
function ocms_escape(mixed $value): string {
    // Accetta qualsiasi tipo: un valore inatteso nei dati (es. una risposta
    // salvata come array) non deve mandare in errore 500 un'intera pagina
    if ($value === null) return '';
    if (is_bool($value)) $value = $value ? '1' : '';
    elseif (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Restituisce il timestamp corrente in formato ISO 8601
 */
function ocms_now(): string {
    return date('c');
}

/**
 * Genera un token CSRF e lo salva in sessione
 */
function ocms_csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Restituisce un campo hidden con il token CSRF
 */
function ocms_csrf_field(): string {
    $token = ocms_escape(ocms_csrf_token());
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

/**
 * Verifica il token CSRF dalla richiesta POST
 */
function ocms_csrf_verify(): bool {
    return ocms_csrf_check($_POST['_csrf_token'] ?? '');
}

/**
 * Confronta un token con quello di sessione. Un token di sessione vuoto non
 * vale mai: prima hash_equals('', '') faceva passare le richieste senza token
 * fatte con una sessione appena creata.
 */
function ocms_csrf_check($token): bool {
    $expected = $_SESSION['_csrf_token'] ?? '';
    return is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

/**
 * Verifica il token CSRF ovunque sia stato inviato: campo POST, header
 * X-CSRF-Token o campo _csrf_token nel corpo JSON.
 */
function ocms_csrf_verify_request(): bool {
    if (ocms_csrf_check($_POST['_csrf_token'] ?? null)) return true;
    if (ocms_csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) return true;
    $json = json_decode((string)file_get_contents('php://input'), true);
    return is_array($json) && ocms_csrf_check($json['_csrf_token'] ?? null);
}

/**
 * Redirect con header Location
 */
function ocms_redirect(string $url, int $code = 302): void {
    http_response_code($code);
    header('Location: ' . $url);
    exit;
}

/**
 * Restituisce il percorso base del CMS
 */
function ocms_base_path(): string {
    return dirname(__DIR__);
}

/**
 * Restituisce il percorso alla cartella data
 */
function ocms_data_path(string $sub = ''): string {
    $path = ocms_base_path() . '/data';
    if ($sub) {
        $path .= '/' . ltrim($sub, '/');
    }
    return $path;
}

/**
 * Restituisce l'URL base del sito
 */
function ocms_base_url(): string {
    $config = ocms_config();
    return rtrim($config['site_url'] ?? '', '/');
}

/**
 * Restituisce l'URL assoluto del sito (con scheme + host)
 */
function ocms_absolute_url(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = ocms_base_url();
    if ($path !== '' && $path[0] !== '/') $path = '/' . $path;
    return $scheme . '://' . $host . $base . $path;
}

/**
 * Carica la configurazione globale (con cache statica)
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
 * Flash message — salva un messaggio nella sessione
 */
function ocms_flash_set(string $type, string $message): void {
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Flash message — recupera e cancella i messaggi
 */
function ocms_flash_get(): array {
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/**
 * Tronca una stringa alla lunghezza specificata
 */
function ocms_truncate(string $text, int $length = 160): string {
    $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($len <= $length) {
        return $text;
    }
    return (function_exists('mb_substr') ? mb_substr($text, 0, $length) : substr($text, 0, $length)) . '…';
}

/**
 * Formatta una data ISO in formato leggibile
 */
function ocms_format_date(string $date, string $format = 'd/m/Y H:i'): string {
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * FoglioFirme: verifica se un evento è scaduto in base ad auto_close_at
 */
function ocms_fogliofirme_is_expired(array $sheet): bool {
    $close = trim((string)($sheet['auto_close_at'] ?? ''));
    if ($close === '') return false;
    try {
        $dt = new DateTime($close, new DateTimeZone(ocms_config()['timezone'] ?? 'Europe/Rome'));
        $now = new DateTime('now', new DateTimeZone(ocms_config()['timezone'] ?? 'Europe/Rome'));
        return $now >= $dt;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * FoglioFirme: URL pubblico dell'API di generazione QR (esterna, nessun peso lato server).
 * L'URL codifica il link pubblico di firma.
 */
function ocms_fogliofirme_qr_url(string $data, int $size = 400): string {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
        . '&margin=10&ecc=M&data=' . urlencode($data);
}

/**
 * Invia email tramite il metodo configurato nelle impostazioni (SMTP o PHP mail)
 */
function ocms_send_mail(string $to, string $subject, string $htmlBody, string $replyTo = ''): bool {
    $config = ocms_config();
    $smtp = $config['smtp'] ?? [];
    $method = $smtp['method'] ?? 'smtp';
    $fromEmail = trim((string)($smtp['from_email'] ?? ''));
    $fromName = ocms_mail_header_safe((string)($smtp['from_name'] ?? ($config['site_name'] ?? 'O-CMS')));

    // Indirizzi validi e senza a capo: è la difesa contro l'header injection
    // e contro i comandi SMTP infilati nei campi dei form.
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $replyTo = trim($replyTo);
    if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = '';
    }
    $subject = ocms_mail_header_safe($subject);

    if ($method === 'php_mail') {
        return ocms_send_mail_php($to, $subject, $htmlBody, $fromEmail, $fromName, $replyTo);
    }

    return ocms_send_mail_smtp($to, $subject, $htmlBody, $smtp, $fromEmail, $fromName, $replyTo);
}

/**
 * Rende sicuro un valore da mettere in un header email: niente CR, LF né caratteri di controllo.
 */
function ocms_mail_header_safe(string $value): string {
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    return trim(function_exists('mb_substr') ? mb_substr($value, 0, 250) : substr($value, 0, 250));
}

/**
 * Codifica un header in UTF-8 (RFC 2047) se contiene caratteri non ASCII.
 */
function ocms_mail_encode_header(string $value): string {
    if (preg_match('/[^\x20-\x7E]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return $value;
}

/**
 * Prepara il corpo per il comando DATA: righe CRLF e dot-stuffing (RFC 5321 §4.5.2),
 * così nessuna riga del corpo può chiudere il messaggio in anticipo.
 */
function ocms_mail_body_safe(string $body): string {
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
        if ($line !== '' && $line[0] === '.') $line = '.' . $line;
    }
    unset($line);
    return implode("\r\n", $lines);
}

/**
 * Invia email con PHP mail()
 */
function ocms_send_mail_php(string $to, string $subject, string $htmlBody, string $fromEmail, string $fromName, string $replyTo = ''): bool {
    $subject = ocms_mail_encode_header(ocms_mail_header_safe($subject));
    $headers = "From: " . ocms_mail_encode_header(ocms_mail_header_safe($fromName)) . " <{$fromEmail}>\r\n";
    $headers .= "Reply-To: " . ($replyTo !== '' ? $replyTo : $fromEmail) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return @mail($to, $subject, $htmlBody, $headers);
}

/**
 * Invia email tramite SMTP diretto
 */
function ocms_send_mail_smtp(string $to, string $subject, string $htmlBody, array $smtp, string $fromEmail, string $fromName, string $replyTo = ''): bool {
    $subject = ocms_mail_header_safe($subject);
    $fromName = ocms_mail_header_safe($fromName);
    if (empty($smtp['host'])) {
        return false;
    }

    $host = $smtp['host'];
    $port = (int)($smtp['port'] ?? 587);
    $user = $smtp['username'] ?? '';
    $pass = $smtp['password'] ?? '';
    $encryption = $smtp['encryption'] ?? 'tls';

    // Connessione
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

    $headers = "From: " . ocms_mail_encode_header($fromName) . " <{$fromEmail}>\r\n";
    $headers .= "To: {$to}\r\n";
    if ($replyTo !== '') $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "Subject: " . ocms_mail_encode_header($subject) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $message = $headers . "\r\n" . ocms_mail_body_safe($htmlBody) . "\r\n.";
    $resp = $send($message);

    $send('QUIT');
    fclose($socket);

    return strpos($resp, '250') !== false;
}

/**
 * Ridimensiona un'immagine a dimensioni fisse con crop centrato (GD)
 * Salva sempre in JPEG. Restituisce il percorso di destinazione o false.
 */
function ocms_resize_cover(string $srcPath, string $destPath, int $targetW = 1200, int $targetH = 630, int $quality = 85): bool {
    $info = getimagesize($srcPath);
    if (!$info) return false;

    $srcW = $info[0];
    $srcH = $info[1];
    $mime = $info['mime'];

    // Carica immagine sorgente
    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($srcPath),
        'image/png'  => imagecreatefrompng($srcPath),
        'image/gif'  => imagecreatefromgif($srcPath),
        'image/webp' => imagecreatefromwebp($srcPath),
        default      => false,
    };
    if (!$src) return false;

    // Calcola crop centrato per riempire il target mantenendo il rapporto
    $srcRatio = $srcW / $srcH;
    $targetRatio = $targetW / $targetH;

    if ($srcRatio > $targetRatio) {
        // Immagine più larga: crop orizzontale
        $cropH = $srcH;
        $cropW = (int)($srcH * $targetRatio);
        $cropX = (int)(($srcW - $cropW) / 2);
        $cropY = 0;
    } else {
        // Immagine più alta: crop verticale
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
 * Genera captcha matematico semplice
 */
function ocms_captcha_generate(): array {
    // Una sola domanda per pagina: prima ogni form (es. ogni "Rispondi" ai commenti)
    // ne generava una nuova e solo l'ultima restava valida
    static $current = null;
    if ($current !== null) return $current;
    $a = random_int(1, 20);
    $b = random_int(1, 20);
    $ops = ['+', '-'];
    $op = $ops[array_rand($ops)];
    if ($op === '-' && $b > $a) { [$a, $b] = [$b, $a]; }
    $answer = ($op === '+') ? $a + $b : $a - $b;
    $_SESSION['_captcha_answer'] = $answer;
    return $current = ['question' => "{$a} {$op} {$b} = ?", 'answer' => $answer];
}

/**
 * Verifica la risposta captcha
 */
function ocms_captcha_verify(string $input): bool {
    $expected = $_SESSION['_captcha_answer'] ?? null;
    unset($_SESSION['_captcha_answer']);
    if ($expected === null) return false;
    return (int)$input === (int)$expected;
}

/**
 * Sanitizza HTML permettendo solo tag sicuri (per contenuti articoli/pagine)
 * Rimuove script, iframe, on* attributes, javascript: URLs
 */
function ocms_sanitize_html(string $html): string {
    // Tag HTML permessi
    $allowed = '<h1><h2><h3><h4><h5><h6><p><br><hr><div><span><a><strong><b><em><i><u><s><del>'
             . '<ul><ol><li><blockquote><pre><code><table><thead><tbody><tr><th><td>'
             . '<img><figure><figcaption><video><source><audio><sub><sup><mark><small>'
             . '<section><article><header><footer><nav><aside><main><details><summary>'
             . '<dl><dt><dd><abbr><cite><q><kbd><samp><var><time><caption><colgroup><col><tfoot><picture><track>'
             . '<style>';

    // Tag SVG permessi: solo forme, testo, gradienti e filtri.
    // Volutamente ESCLUSI perche' sono vettori XSS: <script>, <foreignObject>,
    // <animate>, <set>, <animateTransform>, <use>, <symbol>, <image>.
    // strip_tags confronta i nomi senza distinguere maiuscole e minuscole,
    // quindi <linearGradient> combacia con <lineargradient> qui sotto.
    $allowed .= '<svg><g><defs><marker><path><rect><circle><ellipse><line><polyline><polygon>'
              . '<text><tspan><textpath><title><desc><lineargradient><radialgradient><stop>'
              . '<clippath><mask><pattern><filter><fegaussianblur><fedropshadow><feoffset>'
              . '<femerge><femergenode>';

    $html = strip_tags($html, $allowed);

    // Attributi pericolosi, controllati tag per tag (mai nel testo: prima le regex
    // globali mangiavano anche esempi di codice come "on_click = foo" nei <pre>).
    // Il pattern dei tag rispetta le virgolette, così un ">" dentro un attributo
    // non nasconde quello che viene dopo.
    $html = preg_replace_callback(
        '/<[a-zA-Z][a-zA-Z0-9:-]*(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/',
        fn($m) => ocms_sanitize_tag($m[0]),
        $html
    );

    // Secondo passaggio "fail closed": tag malformati (virgolette non chiuse) sfuggono
    // al pattern preciso qui sopra. Un "<" seguito da una lettera nell'HTML è sempre
    // un tag (il testo usa &lt;), quindi qui si può essere brutali senza toccare il testo.
    $html = preg_replace_callback('/<[a-zA-Z][^>]*>?/', function ($m) {
        $tag = preg_replace('/([\s\/"\'])on[a-z0-9_-]*\s*=/i', '$1data-blocked=', $m[0]);
        return preg_replace('/(java|vb)script\s*:/i', 'blocked:', $tag);
    }, $html);

    // Ripulisci l'interno dei blocchi <style>: ora che sono permessi servono a
    // scrivere le infografiche SVG, ma il CSS ha i suoi vettori classici.
    $html = preg_replace_callback('/(<style\b[^>]*>)(.*?)(<\/style\s*>)/is', function ($m) {
        $css = $m[2];
        $css = preg_replace('/@import\b[^;{}]*;?/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '(', $css);
        $css = preg_replace('/behavior\s*:/i', '', $css);
        $css = preg_replace('/javascript\s*:/i', '', $css);
        return $m[1] . $css . $m[3];
    }, $html);

    return ocms_strip_editor_base($html);
}

/**
 * Toglie la base URL che l'editor aggiunge ai percorsi delle immagini per mostrarle
 * (src="/s/uploads/..." torna src="/uploads/..."), così i dati restano uniformi.
 */
function ocms_strip_editor_base(string $html): string {
    $bu = ocms_base_url();
    if ($bu !== '' && $bu !== '/') {
        $html = str_replace('src="' . $bu . '/uploads/', 'src="/uploads/', $html);
        $html = str_replace('src="' . $bu . '/data/', 'src="/data/', $html);
    }
    return $html;
}

/**
 * Ripulisce un singolo tag di apertura: toglie gli handler on* e neutralizza
 * gli URL javascript:/vbscript:/data: (anche se offuscati con entità o spazi).
 * Gli attributi innocui restano identici al byte.
 */
function ocms_sanitize_tag(string $tag): string {
    if (!preg_match('/^<[a-zA-Z][a-zA-Z0-9:-]*/', $tag, $nm)) return $tag;
    $head = $nm[0];
    $rest = substr($tag, strlen($head));
    $rest = preg_replace_callback(
        '/([^\s"\'>\/=]+)(\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?/',
        function ($m) {
            $name = strtolower($m[1]);
            if (str_starts_with($name, 'on')) return '';
            if (preg_match('/(^|:)(href|src|action|formaction|poster|background|xlink:href|data)$/', $name) && isset($m[2])) {
                $raw = trim(preg_replace('/^\s*=\s*/', '', $m[2]), '"\'');
                if (ocms_url_is_dangerous($raw, in_array($name, ['src', 'poster'], true))) {
                    return $m[1] . '="#"';
                }
            }
            if ($name === 'style' && isset($m[2]) && preg_match('/expression\s*\(|behavior\s*:|javascript\s*:/i', ocms_decode_entities_loose($m[2]))) {
                return '';
            }
            return $m[0];
        },
        $rest
    );
    return $head . $rest;
}

/**
 * Decodifica le entità come fa il browser, anche quelle numeriche senza ";".
 */
function ocms_decode_entities_loose(string $s): string {
    $s = preg_replace_callback('/&#(x[0-9a-f]+|[0-9]+);?/i', function ($m) {
        $cp = ($m[1][0] === 'x' || $m[1][0] === 'X') ? hexdec(substr($m[1], 1)) : (int)$m[1];
        if ($cp <= 0 || $cp > 0x10FFFF) return '';
        return html_entity_decode('&#' . (int)$cp . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }, $s);
    return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * true se l'URL usa uno schema eseguibile (javascript:, vbscript:) o data:
 * (ammesso solo per immagini raster in src/poster se $allowDataImage).
 */
function ocms_url_is_dangerous(string $url, bool $allowDataImage = false): bool {
    $u = strtolower(preg_replace('/[\x00-\x20\x7F]+/', '', ocms_decode_entities_loose($url)));
    if (str_starts_with($u, 'javascript:') || str_starts_with($u, 'vbscript:')) return true;
    if (str_starts_with($u, 'data:')) {
        // le immagini raster incorporate (base64) nelle <img> sono innocue; SVG no
        return !($allowDataImage && preg_match('#^data:image/(png|jpe?g|gif|webp|avif);base64,#', $u));
    }
    return false;
}

/**
 * Contenuto HTML salvato dall'admin: gli amministratori possono scrivere HTML libero
 * (iframe dei video, script delle infografiche, form come nella pagina contatto),
 * come già con le API; editor e publisher passano dal sanitizer.
 * Prima il sanitizer valeva per tutti e il salvataggio distruggeva quei contenuti.
 */
function ocms_content_for_role($app, string $html): string {
    return $app->auth->hasRole('administrator') ? ocms_strip_editor_base($html) : ocms_sanitize_html($html);
}

/**
 * Rate limiter semplice basato su sessione
 * Restituisce true se l'azione è permessa, false se troppo frequente
 */
function ocms_rate_limit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $sessionKey = '_rate_' . $key;
    $now = time();

    $attempts = $_SESSION[$sessionKey] ?? [];
    // Filtra tentativi scaduti
    $attempts = array_filter($attempts, fn($t) => $t > ($now - $windowSeconds));

    if (count($attempts) >= $maxAttempts) {
        return false;
    }

    $attempts[] = $now;
    $_SESSION[$sessionKey] = $attempts;
    return true;
}

/**
 * Valore testuale dal POST: un array al posto di una stringa diventa il default
 * (prima trim() su un array mandava la pagina in errore 500).
 */
function ocms_post_str(string $key, string $default = ''): string {
    $v = $_POST[$key] ?? null;
    if (is_string($v)) return $v;
    if (is_int($v) || is_float($v)) return (string)$v;
    return $default;
}

/**
 * true se la stringa è usabile come nome di file/cartella di un contenuto:
 * niente separatori, niente "." o "..", niente caratteri di controllo.
 */
function ocms_is_safe_slug(string $s): bool {
    return $s !== '' && $s !== '.' && $s !== '..' && strlen($s) <= 200
        && strpbrk($s, "/\\") === false && !preg_match('/[\x00-\x1F]/', $s);
}

/**
 * Galleria di un articolo: solo una lista di immagini con campi testuali noti.
 * Dati con forma diversa (chiavi strane, oggetti annidati) venivano stampati
 * nell'admin e permettevano di iniettare attributi HTML.
 */
function ocms_clean_gallery($gallery): array {
    $out = [];
    foreach (is_array($gallery) ? $gallery : [] as $img) {
        if (!is_array($img)) continue;
        $clean = [];
        foreach (['url', 'thumb', 'name', 'caption', 'alt', 'filename'] as $k) {
            if (isset($img[$k]) && is_scalar($img[$k])) $clean[$k] = mb_substr((string)$img[$k], 0, 1000);
        }
        if (!empty($clean['url']) && !ocms_url_is_dangerous($clean['url'], true)
            && (empty($clean['thumb']) || !ocms_url_is_dangerous($clean['thumb'], true))) {
            $out[] = $clean;
        }
    }
    return $out;
}

/**
 * Elenco di tag: accetta array o stringa separata da virgole, restituisce stringhe pulite.
 */
function ocms_clean_tags($tags): array {
    if (is_string($tags)) $tags = explode(',', $tags);
    $out = [];
    foreach (is_array($tags) ? $tags : [] as $t) {
        if (is_scalar($t) && ($t = trim((string)$t)) !== '') $out[] = mb_substr($t, 0, 100);
    }
    return array_values(array_unique($out));
}

/**
 * Meta SEO: sempre {title, description, og_image} di stringhe.
 */
function ocms_clean_meta($meta): array {
    $meta = is_array($meta) ? $meta : [];
    $out = [];
    foreach (['title', 'description', 'og_image'] as $k) {
        $out[$k] = is_scalar($meta[$k] ?? null) ? mb_substr(trim((string)$meta[$k]), 0, 500) : '';
    }
    return $out;
}

/**
 * Confronto di un codice d'accesso scritto a mano: senza distinguere maiuscole
 * e minuscole e ignorando gli spazi ai lati ("collegio26" vale "COLLEGIO26").
 */
function ocms_code_matches(string $expected, string $given): bool {
    $norm = fn(string $s) => function_exists('mb_strtoupper') ? mb_strtoupper(trim($s)) : strtoupper(trim($s));
    return $expected !== '' && hash_equals($norm($expected), $norm($given));
}

/**
 * Restituisce uno slug libero nella collezione: se $slug è già usato da un
 * documento diverso da $ownSlug aggiunge -2, -3, ...
 */
function ocms_unique_slug(JsonStorage $storage, string $collection, string $slug, string $ownSlug = ''): string {
    $candidate = $slug;
    $n = 2;
    while ($candidate !== $ownSlug && $storage->exists($collection, $candidate)) {
        $candidate = $slug . '-' . $n++;
    }
    return $candidate;
}

/**
 * Salva la versione precedente di un contenuto in data/revisions/{slug}/.
 * Se lo slug cambia, la cartella delle revisioni viene spostata insieme al contenuto.
 */
function ocms_save_revision(array $previous, string $oldSlug, string $newSlug): void {
    $oldDir = ocms_data_path('revisions/' . basename($oldSlug));
    $newDir = ocms_data_path('revisions/' . basename($newSlug));
    if ($oldSlug !== $newSlug && is_dir($oldDir) && !file_exists($newDir)) {
        @rename($oldDir, $newDir);
    }
    if (!is_dir($newDir)) @mkdir($newDir, 0755, true);
    $file = $newDir . '/' . date('Y-m-d_H-i-s') . '.json';
    if (file_exists($file)) $file = $newDir . '/' . date('Y-m-d_H-i-s') . '-' . substr(bin2hex(random_bytes(3)), 0, 4) . '.json';
    @file_put_contents($file, json_encode($previous, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * true se la data indicata è nel futuro (per la pubblicazione programmata).
 * Confronta istanti reali, non stringhe: funziona con qualsiasi fuso orario.
 */
function ocms_is_future(?string $date): bool {
    if ($date === null || $date === '') return false;
    $ts = strtotime($date);
    return $ts !== false && $ts > time();
}

/**
 * true se un articolo è visibile al pubblico: pubblicato e non programmato nel futuro.
 */
function ocms_is_public(array $item): bool {
    return ($item['status'] ?? '') === 'published' && !ocms_is_future($item['publish_at'] ?? '');
}

/**
 * Genera la sitemap XML con URL assoluti: solo contenuti pubblicati e non programmati
 * (pagine, articoli, lezioni, gallerie, quiz), con lastmod.
 */
function ocms_generate_sitemap(JsonStorage $storage): string {
    $esc = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $rows = [[ocms_absolute_url('/'), '', '1.0']];
    foreach (['blog', 'lezioni', 'gallerie', 'quiz'] as $p) $rows[] = [ocms_absolute_url('/' . $p), '', '0.8'];
    $groups = [['pages', '/', '0.7'], ['articles', '/blog/', '0.7'], ['lessons', '/lezione/', '0.6'],
               ['galleries', '/galleria/', '0.5'], ['quizzes', '/quiz/', '0.5']];
    foreach ($groups as [$coll, $prefix, $prio]) {
        $items = $storage->findAll($coll, fn($x) => ocms_is_public($x + ['status' => 'published']) && !empty($x['slug']));
        foreach ($items as $x) {
            if ($coll === 'pages' && $x['slug'] === 'home') continue;
            $lastmod = substr((string)($x['updated_at'] ?? $x['created_at'] ?? ''), 0, 10);
            $rows[] = [ocms_absolute_url($prefix . $x['slug']), $lastmod, $prio];
        }
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($rows as [$loc, $lastmod, $prio]) {
        $xml .= '  <url><loc>' . $esc($loc) . '</loc>'
              . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastmod) ? '<lastmod>' . $lastmod . '</lastmod>' : '')
              . '<priority>' . $prio . '</priority></url>' . "\n";
    }
    return $xml . "</urlset>\n";
}

/**
 * robots.txt dal testo delle impostazioni, con la riga Sitemap assoluta sempre presente.
 */
function ocms_build_robots(string $robots): string {
    $robots = rtrim(str_replace("\r\n", "\n", $robots));
    if ($robots === '') $robots = "User-agent: *\nAllow: /";
    if (!preg_match('/^\s*Sitemap\s*:/mi', $robots)) {
        $robots .= "\n\nSitemap: " . ocms_absolute_url('/sitemap.xml');
    }
    return $robots . "\n";
}

/**
 * Cella CSV sicura per Excel/LibreOffice: un valore che inizia con = + - @ (o tab/CR)
 * verrebbe interpretato come formula. Un apostrofo davanti lo rende testo.
 * I numeri veri (es. "-3" o "12.5") restano numeri.
 */
function ocms_csv_safe(mixed $value): string {
    $s = (string)$value;
    if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false && !is_numeric($s)) {
        return "'" . $s;
    }
    return $s;
}

/**
 * Trasforma l'HTML di un contenuto in righe di testo confrontabili
 * (un paragrafo, titolo o voce d'elenco per riga).
 */
function ocms_html_to_lines(string $html): array {
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $html = preg_replace('#<br\s*/?>|</(p|div|h[1-6]|li|tr|blockquote|pre|figcaption|table|ul|ol)>#i', "\n", $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_map('trim', explode("\n", str_replace("\r", '', $text)));
    return array_values(array_filter($lines, fn($l) => $l !== ''));
}

/**
 * Diff riga per riga (LCS) tra due elenchi di righe.
 * Restituisce [['op' => '=', '-' o '+', 'text' => ...], ...].
 * Prefisso e suffisso comuni vengono saltati prima del calcolo; oltre
 * $maxCells celle (contenuti enormi) ricade su un confronto grossolano.
 */
function ocms_line_diff(array $old, array $new, int $maxCells = 3000000): array {
    $pre = 0;
    $nOld = count($old); $nNew = count($new);
    while ($pre < $nOld && $pre < $nNew && $old[$pre] === $new[$pre]) $pre++;
    $suf = 0;
    while ($suf < $nOld - $pre && $suf < $nNew - $pre && $old[$nOld - 1 - $suf] === $new[$nNew - 1 - $suf]) $suf++;
    $a = array_slice($old, $pre, $nOld - $pre - $suf);
    $b = array_slice($new, $pre, $nNew - $pre - $suf);
    $out = [];
    foreach (array_slice($old, 0, $pre) as $l) $out[] = ['op' => '=', 'text' => $l];
    $n = count($a); $m = count($b);
    if ($n * $m > $maxCells) {
        foreach ($a as $l) $out[] = ['op' => '-', 'text' => $l];
        foreach ($b as $l) $out[] = ['op' => '+', 'text' => $l];
    } else {
        // tabella LCS su righe (array di interi per riga: memoria contenuta)
        $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $L[$i][$j] = $a[$i] === $b[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
            }
        }
        $i = 0; $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) { $out[] = ['op' => '=', 'text' => $a[$i]]; $i++; $j++; }
            elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) { $out[] = ['op' => '-', 'text' => $a[$i]]; $i++; }
            else { $out[] = ['op' => '+', 'text' => $b[$j]]; $j++; }
        }
        for (; $i < $n; $i++) $out[] = ['op' => '-', 'text' => $a[$i]];
        for (; $j < $m; $j++) $out[] = ['op' => '+', 'text' => $b[$j]];
    }
    foreach (array_slice($old, $nOld - $suf) as $l) $out[] = ['op' => '=', 'text' => $l];
    return $out;
}

/**
 * Segreto dell'installazione, generato alla prima richiesta. Sta in un file a parte
 * (data/secret.json, non accessibile dal web) e MAI in config.json, che viene
 * mostrato in giro (API /config, impostazioni, backup condivisi).
 */
function ocms_app_secret(): string {
    static $secret = null;
    if ($secret !== null) return $secret;
    $new = bin2hex(random_bytes(32));
    $saved = (new JsonStorage())->updateFile('secret.json', function ($d) use ($new) {
        if (is_array($d) && !empty($d['app_secret']) && is_string($d['app_secret'])) return null;
        return ['app_secret' => $new, 'created_at' => date('c')];
    });
    if ($saved === null) {
        $d = (new JsonStorage())->readFile('secret.json');
        $saved = is_array($d) ? $d : ['app_secret' => $new];
    }
    return $secret = (string)$saved['app_secret'];
}

/**
 * Token firmato per un uso preciso (es. il link di proiezione di un FoglioFirme).
 */
function ocms_signed_token(string $purpose): string {
    return substr(hash_hmac('sha256', $purpose, ocms_app_secret()), 0, 32);
}

/**
 * Cancella una cartella con tutto il contenuto. Non segue i link simbolici.
 */
function ocms_rmdir_recursive(string $dir): bool {
    if (!is_dir($dir) || is_link($dir)) return false;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        if ($f->isDir() && !$f->isLink()) @rmdir($f->getPathname());
        else @unlink($f->getPathname());
    }
    return @rmdir($dir);
}

/**
 * Estensioni che il server potrebbe eseguire o interpretare come configurazione.
 * Non devono mai finire in uploads/, in nessun punto del nome (es. foto.php.jpg).
 */
function ocms_upload_dangerous_ext(): array {
    // .py, .sh, .bat ecc. restano ammessi: nelle lezioni sono materiale da scaricare,
    // Apache non li esegue (e uploads/.htaccess blocca comunque PHP e CGI)
    return ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps', 'pgif',
            'cgi', 'fcgi', 'asp', 'aspx', 'jsp', 'shtml', 'stm', 'htaccess', 'htpasswd', 'ini'];
}

/**
 * Controlla il nome di un file caricato.
 * $allowedExt = whitelist dell'estensione finale (vuota = qualsiasi estensione non pericolosa).
 * Rifiuta anche i file che iniziano con un punto (.htaccess, .user.ini).
 */
function ocms_upload_name_ok(string $name, array $allowedExt = []): bool {
    $name = basename(str_replace('\\', '/', $name));
    if ($name === '' || $name[0] === '.') return false;
    $parts = explode('.', strtolower($name));
    if (count($parts) < 2) return empty($allowedExt);
    $dangerous = ocms_upload_dangerous_ext();
    foreach (array_slice($parts, 1) as $seg) {
        if (in_array($seg, $dangerous, true)) return false;
    }
    $ext = end($parts);
    return empty($allowedExt) || in_array($ext, $allowedExt, true);
}

/**
 * Nome sicuro per i materiali delle lezioni: un sorgente da far scaricare
 * (Esempio.jsp, index.php, config.ini) non viene scartato ma reso innocuo
 * come testo (Esempio_jsp.txt), così il server non lo esegue mai.
 */
function ocms_lesson_safe_name(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename(str_replace('\\', '/', $name))) ?? '';
    if ($name === '' || $name === '.' || $name === '..') return '';
    if ($name[0] === '.') $name = '_' . substr($name, 1);
    if (!ocms_upload_name_ok($name)) {
        // niente estensioni eseguibili in nessun punto del nome (Apache con più
        // estensioni potrebbe eseguire anche "index.php.txt"): index.php → index_php.txt
        $parts = explode('.', $name);
        $base = array_shift($parts);
        $name = $base . ($parts ? '_' . implode('_', $parts) : '') . '.txt';
    }
    return ocms_upload_name_ok($name) ? $name : '';
}

/**
 * true se la richiesta superava post_max_size: PHP in quel caso svuota
 * $_POST e $_FILES e il controllo CSRF fallirebbe con un messaggio fuorviante.
 */
function ocms_upload_too_big(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && empty($_FILES)
        && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
        && !str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'json');
}

/**
 * Estensioni coerenti con un tipo MIME rilevato da finfo (per la libreria media).
 */
function ocms_ext_for_mime(string $mime): array {
    $map = [
        'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/gif' => ['gif'], 'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'application/pdf' => ['pdf'], 'text/plain' => ['txt', 'csv', 'md'], 'application/zip' => ['zip'],
        'video/mp4' => ['mp4', 'm4v'], 'video/webm' => ['webm'], 'audio/mpeg' => ['mp3'], 'audio/mp3' => ['mp3'],
        'audio/ogg' => ['ogg', 'oga'], 'audio/wav' => ['wav'], 'audio/x-wav' => ['wav'],
    ];
    return $map[$mime] ?? [];
}

/**
 * true se la richiesta arriva da una pagina di questo stesso sito
 * (header Origin o, in mancanza, Referer con lo stesso host).
 */
function ocms_same_origin_request(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $src = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($src === '' || $host === '') return false;
    $srcHost = strtolower((string)parse_url($src, PHP_URL_HOST));
    $srcPort = parse_url($src, PHP_URL_PORT);
    if ($srcPort) $srcHost .= ':' . $srcPort;
    return $srcHost === $host;
}

/**
 * Pagina da cui arriva la richiesta, solo se è di questo sito; altrimenti la home.
 * Evita di usare il modulo come trampolino verso siti esterni (open redirect).
 */
function ocms_safe_referer(): string {
    $home = ocms_base_url() . '/';
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') return $home;
    $host = strtolower((string)parse_url($ref, PHP_URL_HOST));
    $port = parse_url($ref, PHP_URL_PORT);
    if ($port) $host .= ':' . $port;
    if ($host !== strtolower((string)($_SERVER['HTTP_HOST'] ?? ''))) return $home;
    $path = (string)parse_url($ref, PHP_URL_PATH);
    $query = parse_url($ref, PHP_URL_QUERY);
    return ($path !== '' ? $path : '/') . ($query ? '?' . $query : '');
}

/**
 * Rate limit lato server per indirizzo IP (non aggirabile cambiando sessione).
 * Le scuole escono su Internet con un solo IP per tutte le aule:
 * per questo i limiti per IP vanno tenuti larghi e affiancati a quelli per sessione.
 * $scope sostituisce l'IP quando il limite è globale (es. per username).
 * Restituisce true se l'azione è consentita (e la conteggia).
 */
function ocms_rate_limit_ip(string $key, int $maxAttempts, int $windowSeconds, ?string $scope = null): bool {
    $ip = $scope ?? ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $file = 'cache/ratelimit/' . substr(hash('sha256', $key . '|' . $ip), 0, 32) . '.json';
    $allowed = true;
    $now = time();
    try {
        (new JsonStorage())->updateFile($file, function ($d) use ($now, $windowSeconds, $maxAttempts, &$allowed) {
            $hits = array_values(array_filter($d['hits'] ?? [], fn($t) => is_int($t) && $t > $now - $windowSeconds));
            if (count($hits) >= $maxAttempts) { $allowed = false; return null; }
            $hits[] = $now;
            return ['hits' => $hits];
        });
    } catch (\Throwable $e) {
        return true; // se lo storage non funziona non blocchiamo gli utenti
    }
    return $allowed;
}

/**
 * Controlla senza conteggiare se una chiave per IP ha già superato il limite.
 */
function ocms_rate_limited_ip(string $key, int $maxAttempts, int $windowSeconds, ?string $scope = null): bool {
    $ip = $scope ?? ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $file = ocms_data_path('cache/ratelimit/' . substr(hash('sha256', $key . '|' . $ip), 0, 32) . '.json');
    if (!is_file($file)) return false;
    $d = json_decode((string)@file_get_contents($file), true);
    $now = time();
    $hits = array_filter($d['hits'] ?? [], fn($t) => is_int($t) && $t > $now - $windowSeconds);
    return count($hits) >= $maxAttempts;
}

/**
 * Traccia una visita (anonimizzata) — salva aggregati giornalieri
 */
function ocms_track_visit(string $path, string $type = 'page'): void {
    $date = date('Y-m-d');
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $refHost = $ref ? (parse_url($ref, PHP_URL_HOST) ?? '') : '';
    $ownHost = $_SERVER['HTTP_HOST'] ?? '';
    $hour = (int)date('G');

    // Lettura e scrittura sotto lock: prima una lettura concorrente poteva
    // trovare il file troncato e azzerare le statistiche del giorno.
    try {
        (new JsonStorage())->updateFile('analytics/' . $date . '.json', function ($data) use ($date, $path, $refHost, $ownHost, $hour) {
            if (!$data) $data = ['date' => $date, 'total' => 0, 'pages' => [], 'referrers' => []];
            $data['total'] = ($data['total'] ?? 0) + 1;
            // anche i percorsi (pagine inesistenti inventate) hanno un tetto
            if (isset($data['pages'][$path]) || count($data['pages'] ?? []) < 2000) {
                $data['pages'][$path] = ($data['pages'][$path] ?? 0) + 1;
            }
            // Referrer: solo nomi di host plausibili e al massimo 300 diversi al giorno
            // (prima chiunque poteva gonfiare il file inventando Referer a raffica)
            if ($refHost && $refHost !== $ownHost && strlen($refHost) <= 100
                && preg_match('/^[a-z0-9.-]+$/i', $refHost)
                && (isset($data['referrers'][$refHost]) || count($data['referrers'] ?? []) < 300)) {
                $data['referrers'][$refHost] = ($data['referrers'][$refHost] ?? 0) + 1;
            }
            $data['hours'][$hour] = ($data['hours'][$hour] ?? 0) + 1;
            return $data;
        });
    } catch (\Throwable $e) {
        // le statistiche non devono mai rompere la pagina
    }
}

/**
 * Carica statistiche per un range di giorni
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
 * Ridimensiona immagine per thumbnail galleria (crop quadrato o libero)
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

    // Fit within maxW x maxH mantenendo aspect ratio
    $ratio = min($maxW / $srcW, $maxH / $srcH);
    if ($ratio >= 1) {
        // Immagine più piccola del target: copia senza resize
        $destW = $srcW;
        $destH = $srcH;
    } else {
        $destW = (int)($srcW * $ratio);
        $destH = (int)($srcH * $ratio);
    }

    $dest = imagecreatetruecolor($destW, $destH);

    // Preserva trasparenza per PNG
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
 * Restituisce un tag <style> con la variabile CSS --site-width dalla configurazione.
 */
function ocms_site_width_style(): string {
    $config = ocms_config();
    $value = $config['site_width_value'] ?? '90';
    $unit = $config['site_width_unit'] ?? '%';
    // Sanitize: solo numeri (e punto per decimali)
    $value = preg_replace('/[^0-9.]/', '', $value);
    if ($value === '' || floatval($value) <= 0) $value = '90';
    if (!in_array($unit, ['%', 'px'])) $unit = '%';
    return '<style>:root{--site-width:' . $value . $unit . ';}</style>';
}

/**
 * Sostituisce gli shortcode [gallery:slug] nel contenuto con la galleria renderizzata.
 */
function ocms_render_gallery_shortcode(string $content): string {
    if (strpos($content, '[gallery:') === false) return $content;
    $storage = new JsonStorage();
    return preg_replace_callback('/\[gallery:([a-z0-9_-]+)\]/', function($m) use ($storage) {
        $gallery = $storage->find('galleries', $m[1]);
        if (!$gallery || ($gallery['status'] ?? '') !== 'published' || empty($gallery['images'])) {
            return '';
        }
        $slugRaw = (string)$m[1];
        $base = ocms_base_url() . '/uploads/gallery/' . rawurlencode($slugRaw) . '/';
        // identificatore JS valido (prima conteneva "-": errore di sintassi, la lightbox non partiva)
        $uid = 'gembed_' . preg_replace('/[^a-z0-9_]/', '_', $slugRaw);
        $html = '<div id="' . $uid . '" style="column-count:3;column-gap:12px;margin:24px 0;">';
        $urls = [];
        $captions = [];
        $n = 0;
        foreach ((array)$gallery['images'] as $img) {
            if (!is_array($img) || !is_string($img['filename'] ?? null) || !ocms_is_safe_slug(basename($img['filename']))) continue;
            $file = rawurlencode(basename($img['filename']));
            $caption = is_scalar($img['title'] ?? null) ? (string)$img['title'] : '';
            $html .= '<img src="' . ocms_escape($base . 'thumb_' . $file) . '" alt="' . ocms_escape($caption) . '" loading="lazy" style="width:100%;border-radius:8px;margin-bottom:12px;cursor:pointer;break-inside:avoid;display:block;" onclick="' . $uid . 'Open(' . $n . ')">';
            $urls[] = $base . $file;
            $captions[] = $caption;
            $n++;
        }
        if ($n === 0) return '';
        // dati per il JS sempre via json_encode con escape di < > & ' ": nessun testo può chiudere lo <script>
        $jsFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        $html .= '</div>';
        // Lightbox + JS
        $html .= '<div id="' . $uid . '-lb" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.92);align-items:center;justify-content:center;cursor:pointer;" onclick="if(event.target===this)' . $uid . 'Close()">';
        $html .= '<img id="' . $uid . '-img" style="max-width:90vw;max-height:90vh;border-radius:8px;cursor:default;" onclick="event.stopPropagation()">';
        $html .= '<div id="' . $uid . '-cap" style="position:absolute;bottom:24px;left:50%;transform:translateX(-50%);color:#fff;font-size:0.9rem;text-align:center;text-shadow:0 1px 4px #000;"></div>';
        $html .= '<div style="position:absolute;top:16px;right:20px;color:#fff;font-size:1.5rem;cursor:pointer;opacity:0.7;" onclick="' . $uid . 'Close()">&#10005;</div>';
        $html .= '<div style="position:absolute;left:16px;top:50%;color:#fff;font-size:2rem;cursor:pointer;opacity:0.7;transform:translateY(-50%);" onclick="event.stopPropagation();' . $uid . 'Nav(-1)">&#10094;</div>';
        $html .= '<div style="position:absolute;right:16px;top:50%;color:#fff;font-size:2rem;cursor:pointer;opacity:0.7;transform:translateY(-50%);" onclick="event.stopPropagation();' . $uid . 'Nav(1)">&#10095;</div>';
        $html .= '</div>';
        $html .= '<script>(function(){var u=' . json_encode($urls, $jsFlags) . ',c=' . json_encode($captions, $jsFlags) . ',ci=0,lb=document.getElementById("' . $uid . '-lb"),im=document.getElementById("' . $uid . '-img"),cp=document.getElementById("' . $uid . '-cap");';
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
 * Sostituisce gli shortcode [form:slug] nel contenuto con il form renderizzato.
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
 * Restituisce l'URL della cover image di un articolo, con fallback al logo del sito.
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
 * Filtra le voci di menu, escludendo quelle con published === false.
 * Filtra ricorsivamente anche i children.
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
