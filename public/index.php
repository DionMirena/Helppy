<?php
declare(strict_types=1);

session_start();

define('APP_ROOT', dirname(__DIR__));
define('CONFIG', require APP_ROOT . '/config/config.php');

if (CONFIG['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

echo "Helppy.com bootstrapped. " . date('c');
