<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$diseaseFilter = $_GET['disease'] ?? 'all';
$severityFilter = $_GET['severity'] ?? 'all';
$countyFilter = $_GET['county'] ?? 'all';

try {
    $sql = "
        SELECT 
            r.id,
            r.disease_name,
            r.symptoms,
            r.severity,
            r.status,
            r.created_at,
            l.location_name,
            l.latitude,
            l.longitude,
            l.county,
            l.sub_county,
            l.risk_level
        FROM reports r
        LEFT JOIN locations l ON r.location_id = l.location_id
        WHERE DATE(r.created_at) BETWEEN ? AND ?
    ";
    
    $params = [$dateFrom, $dateTo];
    
    if ($diseaseFilter !== 'all') {
        $sql .= " AND r.disease_name = ?";
        $params[] = $diseaseFilter;
    }
    
    if ($severityFilter !== 'all') {
        $sql .= " AND r.severity = ?";
        $params[] = $severityFilter;
    }
    
    if ($countyFilter !== 'all') {
        $sql .= " AND l.county = ?";
        $params[] = $countyFilter;
    }
    
    $sql .= " ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    $heatmapData = [];
    $countyStats = [];
    $diseaseStats = [];
    $severityStats = [
        'low' => 0,
        'medium' => 0,
        'high' => 0,
        'critical' => 0
    ];
    
    foreach ($reports as $report) {
        $lat = $report['latitude'] ?? null;
        $lng = $report['longitude'] ?? null;
        
        if ($lat && $lng) {
            $intensity = match($report['severity']) {
                'critical' => 1.0,
                'high' => 0.75,
                'medium' => 0.5,
                'low' => 0.25,
                default => 0.3
            };
            
            $heatmapData[] = [
                'lat' => floatval($lat),
                'lng' => floatval($lng),
                'intensity' => $intensity,
                'disease' => $report['disease_name'],
                'severity' => $report['severity'],
                'date' => $report['created_at'],
                'county' => $report['county'] ?? 'Unknown'
            ];
        }
        
        $county = $report['county'] ?? 'Unknown';
        if (!isset($countyStats[$county])) {
            $countyStats[$county] = 0;
        }
        $countyStats[$county]++;
        
        $disease = $report['disease_name'] ?? 'Unknown';
        if (!isset($diseaseStats[$disease])) {
            $diseaseStats[$disease] = 0;
        }
        $diseaseStats[$disease]++;
        
        $severity = strtolower($report['severity'] ?? 'low');
        if (isset($severityStats[$severity])) {
            $severityStats[$severity]++;
        }
    }
    
    $diseases = array_keys($diseaseStats);
    arsort($diseaseStats);
    
    $counties = array_keys($countyStats);
    arsort($counties);
    
    $outbreakSql = "SELECT o.*, l.location_name, l.latitude as loc_lat, l.longitude as loc_lng 
                    FROM outbreaks o 
                    LEFT JOIN locations l ON o.location_id = l.location_id 
                    WHERE o.status = 'active'";
    $outbreakStmt = $db->query($outbreakSql);
    $outbreaksData = [];
    while ($outbreak = $outbreakStmt->fetch()) {
        $lat = $outbreak['latitude'] ?? $outbreak['loc_lat'];
        $lng = $outbreak['longitude'] ?? $outbreak['loc_lng'];
        if ($lat && $lng) {
            $outbreaksData[] = [
                'id' => $outbreak['outbreak_id'],
                'disease' => $outbreak['disease_name'],
                'location' => $outbreak['location_name'] ?? 'Unknown',
                'lat' => floatval($lat),
                'lng' => floatval($lng),
                'radius' => floatval($outbreak['affected_radius_km'] ?? 10),
                'status' => $outbreak['status'],
                'alert_date' => $outbreak['alert_date'],
                'cases_confirmed' => $outbreak['cases_confirmed'] ?? 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'heatmap' => $heatmapData,
        'outbreaks' => $outbreaksData,
        'statistics' => [
            'total' => count($reports),
            'by_county' => array_slice($countyStats, 0, 10),
            'by_disease' => array_slice($diseaseStats, 0, 10),
            'by_severity' => $severityStats
        ],
        'filters' => [
            'diseases' => $diseases,
            'counties' => $counties
        ],
        'date_range' => [
            'from' => $dateFrom,
            'to' => $dateTo
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
