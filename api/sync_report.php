<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No data provided']);
    exit;
}

$requiredFields = ['disease_name', 'symptoms', 'location_id', 'severity'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $stmt = $db->prepare("
        INSERT INTO reports (citizen_id, disease_name, symptoms, location_id, severity, status, report_method, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', 'offline', NOW())
    ");
    
    $symptomsJson = is_array($input['symptoms']) 
        ? json_encode($input['symptoms']) 
        : $input['symptoms'];
    
    $stmt->execute([
        $input['citizen_id'] ?? null,
        $input['disease_name'],
        $symptomsJson,
        $input['location_id'],
        $input['severity']
    ]);
    
    $reportId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'report_id' => $reportId,
        'message' => 'Report synced successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
