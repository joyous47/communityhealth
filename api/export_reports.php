<?php
/**
 * Export Reports API
 * Generates PDF and Excel reports from report data
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$format = $_GET['format'] ?? 'csv';
$reportType = $_GET['type'] ?? 'reports';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Get data based on report type
switch ($reportType) {
    case 'reports':
        $data = getReportsData($db, $dateFrom, $dateTo);
        $filename = 'chmews_reports_' . date('Ymd');
        break;
    case 'analyses':
        $data = getAnalysesData($db, $dateFrom, $dateTo);
        $filename = 'chmews_analyses_' . date('Ymd');
        break;
    case 'recommendations':
        $data = getRecommendationsData($db, $dateFrom, $dateTo);
        $filename = 'chmews_recommendations_' . date('Ymd');
        break;
    case 'summary':
        $data = getSummaryData($db, $dateFrom, $dateTo);
        $filename = 'chmews_summary_' . date('Ymd');
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report type']);
        exit;
}

// Export based on format
switch ($format) {
    case 'csv':
        exportCSV($data, $filename);
        break;
    case 'json':
        exportJSON($data, $filename);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid format']);
}

function getReportsData($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT 
            r.id,
            r.disease_name,
            r.symptoms,
            r.severity,
            r.status,
            r.report_method,
            r.created_at,
            l.location_name,
            l.county,
            u.username as citizen_name
        FROM reports r
        LEFT JOIN locations l ON r.location_id = l.location_id
        LEFT JOIN users u ON r.citizen_id = u.id
        WHERE DATE(r.created_at) BETWEEN ? AND ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll();
}

function getAnalysesData($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.analysis_details,
            a.severity,
            a.created_at,
            r.disease_name as report_disease,
            u.username as health_worker
        FROM analyses a
        LEFT JOIN reports r ON a.report_id = r.id
        LEFT JOIN users u ON a.health_worker_id = u.id
        WHERE DATE(a.created_at) BETWEEN ? AND ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll();
}

function getRecommendationsData($db, $dateFrom, $dateTo) {
    $stmt = $db->prepare("
        SELECT 
            rec.id,
            rec.recommendation_text,
            rec.created_at,
            r.disease_name,
            u.username as health_worker
        FROM recommendations rec
        LEFT JOIN analyses a ON rec.analysis_id = a.id
        LEFT JOIN reports r ON a.report_id = r.id
        LEFT JOIN users u ON rec.health_worker_id = u.id
        WHERE DATE(rec.created_at) BETWEEN ? AND ?
        ORDER BY rec.created_at DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    return $stmt->fetchAll();
}

function getSummaryData($db, $dateFrom, $dateTo) {
    $summary = [];
    
    // Total reports
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $summary['total_reports'] = $stmt->fetch()['count'];
    
    // By status
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM reports WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
    $stmt->execute([$dateFrom, $dateTo]);
    $summary['by_status'] = $stmt->fetchAll();
    
    // By severity
    $stmt = $db->prepare("SELECT severity, COUNT(*) as count FROM reports WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY severity");
    $stmt->execute([$dateFrom, $dateTo]);
    $summary['by_severity'] = $stmt->fetchAll();
    
    // By county
    $stmt = $db->prepare("
        SELECT l.county, COUNT(*) as count 
        FROM reports r 
        LEFT JOIN locations l ON r.location_id = l.location_id 
        WHERE DATE(r.created_at) BETWEEN ? AND ? 
        GROUP BY l.county 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $summary['top_counties'] = $stmt->fetchAll();
    
    // Top diseases
    $stmt = $db->prepare("
        SELECT disease_name, COUNT(*) as count 
        FROM reports 
        WHERE DATE(created_at) BETWEEN ? AND ? 
        GROUP BY disease_name 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $summary['top_diseases'] = $stmt->fetchAll();
    
    return $summary;
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (empty($data)) {
        fputcsv($output, ['No data available']);
    } else {
        // Header row
        fputcsv($output, array_keys($data[0]));
        
        // Data rows
        foreach ($data as $row) {
            // Decode symptoms JSON if present
            if (isset($row['symptoms'])) {
                $symptoms = json_decode($row['symptoms'], true);
                if (is_array($symptoms)) {
                    $row['symptoms'] = implode(', ', array_column($symptoms, 'name'));
                }
            }
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function exportJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    echo json_encode($data, JSON_PRETTY_PRINT);
}
