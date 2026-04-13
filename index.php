<?php
/**
 * O-CMS — Frontend Entry Point
 *
 * Bootstraps the application: sets error reporting, defines root constants,
 * and hands off to the main App controller.
 *
 * @package O-CMS
 * @version 1.0.0
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('OCMS_VERSION', '1.0.0');
define('OCMS_ROOT', __DIR__);

require_once OCMS_ROOT . '/core/App.php';

$app = App::getInstance();
$app->run();
