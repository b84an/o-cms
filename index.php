<?php
/**
 * O-CMS — Entry Point Frontend
 */
error_reporting(E_ALL);
// In produzione gli errori vanno nel log, non nella pagina (mostrerebbero percorsi
// e dettagli interni). Per vederli basta creare il file vuoto data/.debug
$_ocmsDebug = is_file(__DIR__ . '/data/.debug') || is_file(dirname(__DIR__) . '/data/.debug');
ini_set('display_errors', $_ocmsDebug ? '1' : '0');
ini_set('log_errors', '1');

define('OCMS_VERSION', '2.0.3');
define('OCMS_ROOT', __DIR__);

// Sito non ancora installato: si va al wizard invece di mostrare un 404
if (!is_file(OCMS_ROOT . '/data/config.json') && is_file(OCMS_ROOT . '/install.php')) {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    header('Location: ' . $base . '/install.php', true, 302);
    exit;
}

require_once OCMS_ROOT . '/core/App.php';

$app = App::getInstance();
$app->run();
