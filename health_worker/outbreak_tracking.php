<?php
require_once '../includes/header.php';
requireRole('health_worker');

$db = getDB();
$statusFilter = $_GET['status'] ?? 'active';
$whereClause = "";
if ($statusFilter != 'all') {
    $whereClause = " AND o.status = '" . $statusFilter . "'";
}
$outbreaks = $db->query("SELECT o.*, l.location_name, l.latitude, l.longitude 
                         FROM outbreaks o 
                         LEFT JOIN locations l ON o.location_id = l.location_id 
                         WHERE 1=1 $whereClause");

// Get recent reports with location data for the map
$reports = $db->query("SELECT r.id, r.disease_name, r.severity, r.status, r.created_at, r.latitude, r.longitude,
                        l.location_name
                        FROM reports r
                        LEFT JOIN locations l ON r.location_id = l.location_id
                        WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL
                        ORDER BY r.created_at DESC
                        LIMIT 50");

$reportData = [];
while ($report = $reports->fetch()) {
    if ($report['latitude'] && $report['longitude']) {
        $reportData[] = [
            'id' => $report['id'],
            'disease' => $report['disease_name'],
            'severity' => $report['severity'],
            'status' => $report['status'],
            'lat' => (float)$report['latitude'],
            'lng' => (float)$report['longitude'],
            'location' => $report['location_name'] ?? $report['location'] ?? 'Unknown'
        ];
    }
}
?>

<style>
#outbreakMap {
    height: 400px !important;
    width: 100%;
    min-height: 400px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: 2px solid #0ea5e9;
    z-index: 1;
}
.leaflet-container {
    height: 100% !important;
    width: 100% !important;
    border-radius: 8px;
}
</style>

<div class="container" style="padding: 20px; max-width: 1400px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: #0ea5e9;"><i class="fas fa-exclamation-triangle"></i> Outbreak Tracking</h1>
        <div style="display: flex; gap: 10px;">
            <select id="statusFilter" onchange="filterOutbreaks()" style="padding: 8px; border: 1px solid #0ea5e9; border-radius: 4px; background: white;">
                <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="investigating" <?= $statusFilter == 'investigating' ? 'selected' : '' ?>>Investigating</option>
                <option value="contained" <?= $statusFilter == 'contained' ? 'selected' : '' ?>>Contained</option>
                <option value="resolved" <?= $statusFilter == 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
        </div>
    </div>
    
    <?php if ($outbreaks->rowCount() == 0): ?>
        <div style="background: white; padding: 30px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 15px;"></i>
            <p style="font-size: 1.2rem; color: #10b981;">No outbreaks found.</p>
        </div>
    <?php else: ?>
        <div id="outbreakMap"></div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php while ($outbreak = $outbreaks->fetch()): ?>
                <?php 
                $statusColor = '#0ea5e9';
                $statusBg = '#e0f2fe';
                if ($outbreak['status'] == 'active') {
                    $statusColor = '#dc2626';
                    $statusBg = '#fee2e2';
                } elseif ($outbreak['status'] == 'investigating') {
                    $statusColor = '#d97706';
                    $statusBg = '#fef3c7';
                } elseif ($outbreak['status'] == 'contained') {
                    $statusColor = '#059669';
                    $statusBg = '#d1fae5';
                } elseif ($outbreak['status'] == 'resolved') {
                    $statusColor = '#059669';
                    $statusBg = '#d1fae5';
                }
                ?>
                <div style="background: white; padding: 20px; border-radius: 8px; border-left: 5px solid <?= $statusColor ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <h3 style="color: <?= $statusColor ?>; margin: 0;"><?= htmlspecialchars($outbreak['disease_name']) ?></h3>
                        <span style="padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; background: <?= $statusBg ?>; color: <?= $statusColor ?>;">
                            <?= ucfirst($outbreak['status']) ?>
                        </span>
                    </div>
                    <p style="color: #64748b; margin: 10px 0;"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($outbreak['location_name'] ?? 'Unknown') ?></p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 15px 0;">
                        <div>
                            <small style="color: #94a3b8;">First Case</small>
                            <p style="margin: 0; color: #334155;"><?= date('M d, Y', strtotime($outbreak['first_case_date'])) ?></p>
                        </div>
                        <div>
                            <small style="color: #94a3b8;">Alert Date</small>
                            <p style="margin: 0; color: #334155;"><?= date('M d, Y', strtotime($outbreak['alert_date'])) ?></p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="investigate.php?id=<?= $outbreak['outbreak_id'] ?>" style="flex: 1; padding: 10px; background: #0ea5e9; color: white; text-align: center; border-radius: 4px; text-decoration: none;">
                            <i class="fas fa-search"></i> Investigate
                        </a>
                        <?php if ($outbreak['latitude'] && $outbreak['longitude']): ?>
                        <button onclick="focusLocation(<?= $outbreak['latitude'] ?>, <?= $outbreak['longitude'] ?>)" style="padding: 10px; background: #e0f2fe; color: #0284c7; border: none; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-map"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
var map = null;
var markers = [];

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
            $data = [];
            // Re-fetch data for JavaScript since query() was already executed
            $outbreakStmt = $db->query("SELECT o.*, l.location_name, l.latitude, l.longitude 
                             FROM outbreaks o 
                             LEFT JOIN locations l ON o.location_id = l.location_id 
                             WHERE 1=1 $whereClause");
            while ($o = $outbreakStmt->fetch()) {
                if ($o['latitude'] && $o['longitude']) {
                    $data[] = [
                        'lat' => (float)$o['latitude'],
                        'lng' => (float)$o['longitude'],
                        'name' => $o['disease_name'],
                        'location' => $o['location_name'],
                        'status' => $o['status']
                    ];
                }
            }
            echo json_encode($data);
        ?>;
        
        // Reported diseases data
        var reportData = <?php echo json_encode($reportData); ?>;
        
        var outbreakColors = {
            'active': '#dc2626',
            'investigating': '#d97706',
            'contained': '#059669',
            'resolved': '#059669'
        };
        
        var severityColors = {
            'severe': '#dc2626',
            'moderate': '#f97316',
            'mild': '#22c55e'
        };
        
        // Add outbreak markers (larger circles)
        outbreakData.forEach(function(o) {
            var color = outbreakColors[o.status] || '#0ea5e9';
            var marker = L.circleMarker([o.lat, o.lng], {
                radius: 14,
                fillColor: color,
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map);
            marker.bindPopup('<strong>OUTBREAK: ' + o.name + '</strong><br>' + (o.location || 'Unknown') + '<br>Status: ' + o.status);
            markers.push(marker);
        });
        
        // Add reported disease markers (smaller circles)
        reportData.forEach(function(r) {
            var color = severityColors[r.severity] || '#0ea5e9';
            var marker = L.circleMarker([r.lat, r.lng], {
                radius: 8,
                fillColor: color,
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.7
            }).addTo(map);
            marker.bindPopup('<strong>Report #' + r.id + '</strong><br>Disease: ' + r.disease + '<br>Severity: ' + r.severity + '<br>Status: ' + r.status);
            markers.push(marker);
        });
        
        // Fit bounds to show all markers
        var allData = [...outbreakData, ...reportData];
        if (allData.length > 0) {
            var bounds = L.latLngBounds(allData.map(o => [o.lat, o.lng]));
            map.fitBounds(bounds, {padding: [50, 50]});
        }
    }
}
        });
        
        if (outbreakData.length > 0) {
            var bounds = L.latLngBounds(outbreakData.map(o => [o.lat, o.lng]));
            map.fitBounds(bounds, {padding: [50, 50]});
        }
    }
}

function focusLocation(lat, lng) {
    if (map) {
        map.setView([lat, lng], 12);
    }
}

function filterOutbreaks() {
    var status = document.getElementById('statusFilter').value;
    window.location.href = '?status=' + status;
}
</script>

<?php require_once '../includes/footer.php'; ?>
