<?php

ob_start();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_outbreak') {
    require_once '../config/database.php';
    require_once '../includes/functions.php';
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
   
    if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
        header("Location: ../auth/login.php");
        exit();
    }
    
    $report_id = (int)$_POST['report_id'];
    
    try {
        $db = getDB();
        
        
        $stmt = $db->prepare("SELECT r.*, l.location_id, l.location_name 
                              FROM reports r 
                              LEFT JOIN locations l ON r.location_id = l.location_id 
                              WHERE r.id = ?");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch();
        
        if ($report) {
            
            $checkStmt = $db->prepare("SELECT * FROM outbreaks WHERE disease_name = ? AND status = 'active'");
            $checkStmt->execute([$report['disease_name']]);
            
            if (!$checkStmt->fetch()) {
                
                $insertStmt = $db->prepare("INSERT INTO outbreaks (disease_name, location_id, first_case_date, alert_date, status, notes, affected_radius_km, latitude, longitude) 
                                            VALUES (?, ?, NOW(), NOW(), 'active', ?, ?, ?, ?)");
                $insertStmt->execute([
                    $report['disease_name'],
                    $report['location_id'],
                    'Created from report #' . $report_id . ' - Severity: ' . $report['severity'] . ', Location: ' . ($report['location_name'] ?? 'Unknown'),
                    isset($_POST['affected_radius']) ? floatval($_POST['affected_radius']) : 10.0,
                    $report['latitude'] ?? null,
                    $report['longitude'] ?? null
                ]);
                
                
                $updateStmt = $db->prepare("UPDATE reports SET status = 'analyzed' WHERE id = ?");
                $updateStmt->execute([$report_id]);
                
                $_SESSION['success_message'] = "Outbreak created successfully for Report #" . $report_id;
            } else {
                $_SESSION['error_message'] = "Active outbreak already exists for this disease";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error creating outbreak: " . $e->getMessage();
    }
    
    header("Location: alert_management.php");
    exit();
}

require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('admin', '../auth/login.php');

$db = getDB();


$severityFilter = $_GET['severity'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';

$whereClause = "1=1";
$params = [];

if ($severityFilter !== 'all') {
    $whereClause .= " AND r.severity = ?";
    $params[] = $severityFilter;
}

if ($statusFilter !== 'all') {
    $whereClause .= " AND r.status = ?";
    $params[] = $statusFilter;
}

$stmt = $db->prepare("SELECT r.*, l.location_name, l.latitude, l.longitude
                      FROM reports r
                      LEFT JOIN locations l ON r.location_id = l.location_id
                      WHERE $whereClause
                      ORDER BY r.created_at DESC
                      LIMIT 50");
$stmt->execute($params);
$reports = $stmt->fetchAll();

$outbreaks = $db->query("SELECT o.*, l.location_name 
                          FROM outbreaks o 
                          LEFT JOIN locations l ON o.location_id = l.location_id 
                          ORDER BY o.alert_date DESC
                          LIMIT 20")->fetchAll();
?>

<style>
.page-header {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    padding: 30px 0;
    margin-bottom: 30px;
}
.alert-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-left: 4px solid #ef4444;
}
.alert-card.severe {
    border-left-color: #dc2626;
}
.alert-card.moderate {
    border-left-color: #f97316;
}
.alert-card.mild {
    border-left-color: #22c55e;
}
.severity-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}
.severity-badge.severe {
    background: #fee2e2;
    color: #dc2626;
}
.severity-badge.moderate {
    background: #ffedd5;
    color: #f97316;
}
.severity-badge.mild {
    background: #dcfce7;
    color: #22c55e;
}
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-badge.pending {
    background: #fef9c3;
    color: #a16207;
}
.status-badge.analyzed {
    background: #dbeafe;
    color: #1d4ed8;
}
.status-badge.completed {
    background: #dcfce7;
    color: #15803d;
}
.outbreak-badge {
    background: #fee2e2;
    color: #dc2626;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
.btn-create-outbreak {
    background: #ef4444;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
}
.btn-create-outbreak:hover {
    background: #dc2626;
}
.btn-create-outbreak:disabled {
    background: #ccc;
    cursor: not-allowed;
}
</style>

<div class="container" style="padding: 20px; max-width: 1400px; margin: 0 auto;">
    <div class="page-header">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 20px;">
            <div>
                <h1><i class="fas fa-bell"></i> Alert Management</h1>
                <p>Create outbreaks from severe reports and monitor active alerts</p>
            </div>
            <a href="dashboard.php" style="padding: 8px 15px; background: rgba(255,255,255,0.2); color: white; border-radius: 4px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div style="background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
   
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0;"><i class="fas fa-filter"></i> Filters</h3>
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap;">
            <div>
                <label>Severity:</label>
                <select name="severity" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all">All</option>
                    <option value="severe" <?= $severityFilter === 'severe' ? 'selected' : '' ?>>Severe</option>
                    <option value="moderate" <?= $severityFilter === 'moderate' ? 'selected' : '' ?>>Moderate</option>
                    <option value="mild" <?= $severityFilter === 'mild' ? 'selected' : '' ?>>Mild</option>
                </select>
            </div>
            <div>
                <label>Status:</label>
                <select name="status" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all">All</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="analyzed" <?= $statusFilter === 'analyzed' ? 'selected' : '' ?>>Analyzed</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <button type="submit" style="padding: 8px 20px; background: #4da8da; color: white; border: none; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-search"></i> Filter
            </button>
        </form>
    </div>
    
    
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="color: #dc2626; margin-top: 0;"><i class="fas fa-exclamation-triangle"></i> Active Outbreaks (<?php echo count($outbreaks); ?>)</h3>
        
        <?php if (!empty($outbreaks)): ?>
            
            <div id="outbreakMap" style="height: 400px; width: 100%; border-radius: 8px; margin-bottom: 20px; border: 2px solid #dc2626;"></div>
            
         
            <div style="background: #fef2f2; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #333;"><i class="fas fa-info-circle"></i> Affected Areas Legend</h4>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <div style="width: 24px; height: 24px; background: rgba(220, 38, 38, 0.3); border: 2px solid #dc2626; border-radius: 50%;"></div>
                        <span>Affected Area (10km)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <div style="width: 16px; height: 16px; background: #dc2626; border-radius: 50%;"></div>
                        <span>Outbreak Center</span>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                <?php foreach ($outbreaks as $outbreak): ?>
                    <div style="background: #fef2f2; padding: 15px; border-radius: 8px; border-left: 4px solid #dc2626;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <h4 style="margin: 0; color: #dc2626;"><?php echo htmlspecialchars($outbreak['disease_name']); ?></h4>
                            <span style="background: #dc2626; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem;"><?php echo ucfirst($outbreak['status']); ?></span>
                        </div>
                        <p style="color: #666; margin: 10px 0;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($outbreak['location_name'] ?? 'Unknown'); ?></p>
                        <p style="color: #999; font-size: 0.85rem; margin: 0;">
                            <i class="fas fa-ruler-horizontal"></i> Affected Radius: <?php echo isset($outbreak['affected_radius_km']) ? htmlspecialchars($outbreak['affected_radius_km']) : '10'; ?> km
                        </p>
                        <p style="color: #999; font-size: 0.85rem; margin: 5px 0 0 0;">Alert Date: <?php echo date('M d, Y', strtotime($outbreak['alert_date'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #666;">No active outbreaks.</p>
        <?php endif; ?>
    </div>
    
   
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="color: #4da8da; margin-top: 0;"><i class="fas fa-file-medical"></i> Reports for Review</h3>
        
        <?php if (empty($reports)): ?>
            <p style="color: #666;">No reports found.</p>
        <?php else: ?>
            <?php foreach ($reports as $report): ?>
                <div class="alert-card <?php echo $report['severity']; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div>
                            <h4 style="margin: 0; color: #333;">
                                Report #<?php echo $report['id']; ?> - <?php echo htmlspecialchars($report['disease_name']); ?>
                            </h4>
                            <p style="color: #666; margin: 5px 0;">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($report['location_name'] ?? $report['location'] ?? 'Unknown'); ?>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <span class="severity-badge <?php echo $report['severity']; ?>"><?php echo $report['severity']; ?></span>
                            <span class="status-badge <?php echo $report['status']; ?>" style="margin-left: 5px;"><?php echo $report['status']; ?></span>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                        <div style="color: #999; font-size: 0.85rem;">
                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?>
                            <?php if ($report['latitude'] && $report['longitude']): ?>
                                | <i class="fas fa-coordinates"></i> <?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" style="display: inline; align-items: center; gap: 10px;">
                            <input type="hidden" name="action" value="create_outbreak">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <label style="font-size: 0.85rem; color: #666;">Radius (km):</label>
                            <input type="number" name="affected_radius" value="10" min="1" max="100" style="width: 60px; padding: 4px; border: 1px solid #ddd; border-radius: 4px;">
                            <button type="submit" class="btn-create-outbreak" onclick="return confirm('Create outbreak alert for this report?');">
                                <i class="fas fa-bell"></i> Create Alert
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php 

ob_end_flush();
require_once '../includes/footer.php'; 
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
var map = null;

document.addEventListener('DOMContentLoaded', function() {
    initMap();
});

function initMap() {
    if (document.getElementById('outbreakMap')) {
        map = L.map('outbreakMap', {
            center: [-1.2864, 36.8172],
            zoom: 6,
            zoomControl: true,
            attributionControl: true
        });
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        
       
        var outbreakData = <?php 
            $outbreakStmt = $db->query("SELECT o.*, l.location_name, l.latitude as loc_lat, l.longitude as loc_lng 
                             FROM outbreaks o 
                             LEFT JOIN locations l ON o.location_id = l.location_id 
                             WHERE o.status = 'active'");
            $data = [];
            while ($o = $outbreakStmt->fetch()) {
                $lat = $o['latitude'] ?? $o['loc_lat'];
                $lng = $o['longitude'] ?? $o['loc_lng'];
                if ($lat && $lng) {
                    $data[] = [
                        'lat' => (float)$lat,
                        'lng' => (float)$lng,
                        'name' => $o['disease_name'],
                        'location' => $o['location_name'],
                        'radius' => (float)($o['affected_radius_km'] ?? 10),
                        'status' => $o['status'],
                        'alert_date' => date('M d, Y', strtotime($o['alert_date']))
                    ];
                }
            }
            echo json_encode($data);
        ?>;
        
       
        outbreakData.forEach(function(o) {
            
            var affectedArea = L.circle([o.lat, o.lng], {
                radius: o.radius * 1000, 
                fillColor: '#dc2626',
                fillOpacity: 0.2,
                color: '#dc2626',
                weight: 2,
                opacity: 0.8
            }).addTo(map);
            
            affectedArea.bindPopup('<strong>OUTBREAK: ' + o.name + '</strong><br>' + 
                'Location: ' + (o.location || 'Unknown') + '<br>' +
                'Affected Radius: ' + o.radius + ' km<br>' +
                'Alert Date: ' + o.alert_date + '<br>' +
                '<em>Area at risk</em>');
            
            var centerMarker = L.circleMarker([o.lat, o.lng], {
                radius: 10,
                fillColor: '#dc2626',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(map);
            
            centerMarker.bindPopup('<strong>CENTER: ' + o.name + '</strong><br>' + 
                'Location: ' + (o.location || 'Unknown') + '<br>' +
                '<em>Outbreak epicenter</em>');
        });
        
        
        if (outbreakData.length > 0) {
            var bounds = L.latLngBounds(outbreakData.map(o => [o.lat, o.lng]));
            map.fitBounds(bounds, {padding: [50, 50]});
        }
    }
}
</script>
