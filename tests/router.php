<?php
// Router per `php -S`: imita il .htaccess con il sito montato sotto /s
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = $_SERVER['DOCUMENT_ROOT'];
if (!str_starts_with($uri, '/s/') && $uri !== '/s') { http_response_code(404); echo 'fuori da /s'; return true; }
$rel = substr($uri, 2); // da /...
if (preg_match('#^/(core|data)/|^/admin/views/|^/themes/[^/]+/templates/#', $rel)) { http_response_code(403); echo 'Forbidden'; return true; }
if (preg_match('#^/uploads/.*\.(php\d?|phtml|pht|phar|phps|pgif|cgi|fcgi|shtml)$#i', $rel)) { http_response_code(403); echo 'Forbidden'; return true; }
$file = $root . $uri;
if ($rel !== '/' && is_file($file) && !str_ends_with($file, '.php')) return false;
// come Apache: un file .php esistente (es. le mini-app in sottocartelle) viene eseguito
if (is_file($file) && str_ends_with($file, '.php') && !preg_match('#^/(index\.php|admin/index\.php)$#', $rel)) {
    $_SERVER['SCRIPT_NAME'] = $uri; $_SERVER['SCRIPT_FILENAME'] = $file; chdir(dirname($file)); require $file; return true;
}
if (str_starts_with($rel, '/admin') || str_starts_with($rel, '/api/')) { $_SERVER['SCRIPT_NAME'] = '/s/admin/index.php'; chdir($root.'/s/admin'); require $root . '/s/admin/index.php'; return true; }
$_SERVER['SCRIPT_NAME'] = '/s/index.php'; chdir($root.'/s'); require $root . '/s/index.php'; return true;
