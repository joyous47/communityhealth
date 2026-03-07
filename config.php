<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/includes/functions.php';

define('SITE_NAME', 'CHMEWS');
define('SITE_URL', 'http://localhost/chmews');

date_default_timezone_set('Africa/Nairobi');

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>