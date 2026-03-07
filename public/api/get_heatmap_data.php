<?php
/**
 * Public Heatmap Data API
 * Provides data for disease heatmaps without requiring authentication
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();
$disease = $_GET['disease'] ?? 'all';
$days = (int)($_GET['days'] ?? 30);

$response = ['points' => [], 'outbreaks' => [], 'stats' => [
    'total_reports' => 0, 
    'active_alerts' => 0, 
    'resolved_cases' => 0,
    'affected_areas' => 0
]];

try {
    // Get detailed report data with disease names
    $sql = "SELECT r.id, r.disease_name, r.severity, r.status, r.latitude, r.longitude, r.created_at,
                   l.location_name
            FROM reports r
            LEFT JOIN locations l ON r.location_id = l.location_id
            WHERE r.latitude IS NOT NULL 
            AND r.longitude IS NOT NULL
            AND r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params = [$days];
    
    if ($disease != 'all') {
        $sql .= " AND r.disease_name = ?";
        $params[] = $disease;
    }
    
    $sql .= " ORDER BY r.created_at DESC LIMIT 200";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        // Determine intensity based on severity
        $intensity = 1;
        if ($row['severity'] == 'severe') $intensity = 4;
        elseif ($row['severity'] == 'moderate') $intensity = 2;
        else $intensity = 1;
        
        $response['points'][] = [
            'id' => $row['id'],
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude'],
            'disease' => $row['disease_name'],
            'severity' => $row['severity'],
            'status' => $row['status'],
            'location' => $row['location_name'] ?? 'Unknown',
            'intensity' => $intensity
        ];
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $response['stats']['total_reports'] = (int)$stmt->fetch()['total'];
    
    // Reports with pending or analyzed status are considered "active"
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reports WHERE status IN ('pending', 'analyzed') AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $response['stats']['active_alerts'] = (int)$stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reports WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $response['stats']['resolved_cases'] = (int)$stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT CONCAT(latitude, longitude)) as total FROM reports WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $response['stats']['affected_areas'] = (int)$stmt->fetch()['total'];
    
    // Get active outbreaks with affected areas
    $outbreakSql = "SELECT o.*, l.location_name, l.latitude as loc_lat, l.longitude as loc_lng 
                    FROM outbreaks o 
                    LEFT JOIN locations l ON o.location_id = l.location_id 
                    WHERE o.status = 'active'";
    $outbreakStmt = $db->query($outbreakSql);
    while ($outbreak = $outbreakStmt->fetch()) {
        $lat = $outbreak['latitude'] ?? $outbreak['loc_lat'];
        $lng = $outbreak['longitude'] ?? $outbreak['loc_lng'];
        if ($lat && $lng) {
            $response['outbreaks'][] = [
                'id' => $outbreak['outbreak_id'],
                'disease' => $outbreak['disease_name'],
                'location' => $outbreak['location_name'] ?? 'Unknown',
                'lat' => (float)$lat,
                'lng' => (float)$lng,
                'radius' => (float)($outbreak['affected_radius_km'] ?? 10),
                'status' => $outbreak['status'],
                'alert_date' => $outbreak['alert_date'],
                'cases_confirmed' => $outbreak['cases_confirmed'] ?? 0
            ];
        }
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit();
?>
