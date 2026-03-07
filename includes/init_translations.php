<?php
require_once __DIR__ . '/../config/language.php';
$lang = new Language();

function t($key) {
    global $lang;
    return $lang->get($key);
}
?>
