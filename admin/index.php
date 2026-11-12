<?php
/**
 * O-CMS — Entry Point Admin Panel
 */
error_reporting(E_ALL);
// In produzione gli errori vanno nel log, non nella pagina (mostrerebbero percorsi
// e dettagli interni). Per vederli basta creare il file vuoto data/.debug
$_ocmsDebug = is_file(__DIR__ . '/data/.debug') || is_file(dirname(__DIR__) . '/data/.debug');
ini_set('display_errors', $_ocmsDebug ? '1' : '0');
ini_set('log_errors', '1');

define('OCMS_VERSION', '2.0.3');
define('OCMS_ROOT', dirname(__DIR__));

require_once OCMS_ROOT . '/core/App.php';

$app = App::getInstance();
$app->runAdmin();
