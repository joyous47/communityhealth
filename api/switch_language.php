<?php
header('Content-Type: application/json');
require_once '../config/language.php';

$input = json_decode(file_get_contents('php://input'), true);
$lang = $input['language'] ?? 'en';

$language = new Language();
$success = $language->setLanguage($lang);

echo json_encode(['success' => $success, 'language' => $lang]);
?>
