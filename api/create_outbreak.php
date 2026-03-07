<?php
/**
 * Create Outbreak API
 * Creates an outbreak/alert from a report
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = getCurrentUserRole();
if ($role !== 'admin' && $role !== 'health_worker') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$report_id = $_POST['report_id'] ?? null;

if (!$report_id) {
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit();
}

try {
    $db = getDB();
    
    // Get report details
    $stmt = $db->prepare("SELECT r.*, l.location_id, l.location_name, l.latitude, l.longitude 
                          FROM reports r 
                          LEFT JOIN locations l ON r.location_id = l.location_id 
                          WHERE r.id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit();
    }
    
    // Check if active outbreak already exists for this disease
    $stmt = $db->prepare("SELECT * FROM outbreaks WHERE disease_name = ? AND status = 'active'");
    $stmt->execute([$report['disease_name']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Active outbreak already exists for this disease']);
        exit();
    }
    
    // Create outbreak
    $stmt = $db->prepare("INSERT INTO outbreaks (disease_name, location_id, first_case_date, alert_date, status, notes, affected_radius_km, latitude, longitude) 
                          VALUES (?, ?, NOW(), NOW(), 'active', ?, ?, ?, ?)");
    $stmt->execute([
        $report['disease_name'],
        $report['location_id'],
        'Created from report #' . $report_id . ' - Severity: ' . $report['severity'] . ', Location: ' . ($report['location_name'] ?? 'Unknown'),
        isset($report['affected_radius']) ? floatval($report['affected_radius']) : 10.0,
        $report['latitude'] ?? null,
        $report['longitude'] ?? null
    ]);
    
    $outbreak_id = $db->lastInsertId();
    
    // Update report status
    $stmt = $db->prepare("UPDATE reports SET status = 'analyzed' WHERE id = ?");
    $stmt->execute([$report_id]);
    
    // Log the alert creation
    $user = getCurrentUser();
    $stmt = $db->prepare("INSERT INTO alert_history (outbreak_id, alert_type, message, sent_at) 
                          VALUES (?, ?, ?, NOW())");
    $stmt->execute([
        $outbreak_id,
        'outbreak_created',
        'Outbreak created by ' . $user['username'] . ' for report #' . $report_id
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Outbreak created successfully',
        'outbreak_id' => $outbreak_id
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage());
}
exit();
