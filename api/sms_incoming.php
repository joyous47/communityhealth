<?php


header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sms_service.php';

$database = new Database();
$db = $database->getConnection();


$smsService = new SMSService();


$input = json_decode(file_get_contents('php://input'), true);


if (empty($input)) {
    $input = $_POST;
}


$from = $input['from'] ?? $input['phoneNumber'] ?? '';
$message = $input['message'] ?? $input['text'] ?? '';


if (empty($from) && isset($input['From'])) {
    $from = $input['From'];
}
if (empty($message) && isset($input['Body'])) {
    $message = $input['Body'];
}


if (empty($from) || empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    
    $reportData = $smsService->parseReportMessage($message);
    
    if ($reportData) {
    
        $stmt = $db->prepare("
            INSERT INTO reports (citizen_id, disease_name, symptoms, location_id, severity, status, report_method, created_at)
            VALUES (NULL, ?, ?, NULL, ?, 'pending', 'sms', NOW())
        ");
        
        $symptomsJson = json_encode($reportData['symptoms']);
        $diseaseName = !empty($reportData['symptoms']) 
            ? $reportData['symptoms'][0]['name'] 
            : 'Unknown';
        
        $stmt->execute([
            $diseaseName,
            $symptomsJson,
            $reportData['severity']
        ]);
        
        $reportId = $db->lastInsertId();
        

        $logStmt = $db->prepare("
            INSERT INTO sms_log (phone_number, message, direction, status, report_id, created_at)
            VALUES (?, ?, 'incoming', 'processed', ?, NOW())
        ");
        $logStmt->execute([$from, $message, $reportId]);
        
        
        if (SMS_CONFIRMATION_ENABLED) {
            $confirmationMsg = $smsService->createConfirmationMessage($reportId, 'en');
            $smsService->sendSMS($from, $confirmationMsg);
        }
        
        
        if (in_array($reportData['severity'], ['HIGH', 'CRITICAL'])) {
            notifyHealthWorkers($reportData, $db, $smsService);
        }
        
        echo json_encode([
            'status' => 'success',
            'report_id' => $reportId,
            'message' => 'Report received successfully'
        ]);
    } else {
    
        $logStmt = $db->prepare("
            INSERT INTO sms_log (phone_number, message, direction, status, created_at)
            VALUES (?, ?, 'incoming', 'invalid', NOW())
        ");
        $logStmt->execute([$from, $message]);
        

        $helpMsg = "To report: SMS CHMEWS <SYMPTOMS> <LOCATION> <SEVERITY>\nExample: CHMEWS FEVER COUGH NAIROBI MEDIUM\nSymptoms: FEVER, COUGH, COLD, HEADACHE, DIARRHEA, etc.";
        $smsService->sendSMS($from, $helpMsg);
        
        echo json_encode([
            'status' => 'invalid_format',
            'message' => 'Invalid message format'
        ]);
    }
    
} catch (Exception $e) {

    $logStmt = $db->prepare("
        INSERT INTO sms_log (phone_number, message, direction, status, error_message, created_at)
        VALUES (?, ?, 'incoming', 'error', ?, NOW())
    ");
    $logStmt->execute([$from, $message, $e->getMessage()]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}


function notifyHealthWorkers($reportData, $db, $smsService) {
    
    $stmt = $db->prepare("
        SELECT phone_number 
        FROM users 
        WHERE role = 'health_worker' 
        AND phone_number IS NOT NULL
        LIMIT 10
    ");
    $stmt->execute();
    $workers = $stmt->fetchAll();
    
    $alertMsg = $smsService->createAlertMessage(
        $reportData['location'],
        $reportData['severity']
    );
    
    foreach ($workers as $worker) {
        $smsService->sendSMS($worker['phone_number'], $alertMsg);
    }
}
