<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sms_service.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$phoneNumber = $input['phone'] ?? '';
$message = $input['message'] ?? '';

// Validate required fields
if (empty($phoneNumber) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone number and message are required']);
    exit;
}

// Initialize SMS service
$smsService = new SMSService();

$success = $smsService->sendSMS($phoneNumber, $message);

// Log the SMS
try {
    $stmt = $db->prepare("
        INSERT INTO sms_log (phone_number, message, direction, status, created_at)
        VALUES (?, ?, 'outgoing', ?, NOW())
    ");
    $status = $success ? 'sent' : 'failed';
    $stmt->execute([$phoneNumber, $message, $status]);
} catch (PDOException $e) {
}

if ($success) {
    echo json_encode(['success' => true, 'message' => 'SMS sent successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $smsService->getLastError()]);
}
