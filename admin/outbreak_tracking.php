<?php
require_once '../includes/header.php';
require_once '../config/database.php';

requireRole('admin', '../auth/login.php');

$db = getDB();
$statusFilter = $_GET['status'] ?? 'all';
$whereClause = "";
if ($statusFilter != 'all') {
    $whereClause = " AND o.status = '" . $statusFilter . "'";
}
$outbreaks = $db->query("SELECT o.*, l.location_name, l.latitude, l.longitude 
                         FROM outbreaks o 
                         LEFT JOIN locations l ON o.location_id = l.location_id 
                         WHERE 1=1 $whereClause");

// Get reported diseases for the map
$reports = $db->query("SELECT r.id, r.disease_name, r.severity, r.status, r.created_at, r.latitude, r.longitude,
                        l.location_name
                        FROM reports r
                        LEFT JOIN locations l ON r.location_id = l.location_id
                        WHERE (r.latitude IS NOT NULL AND r.longitude IS NOT NULL)
                        ORDER BY r.created_at DESC
                        LIMIT 100");

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
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}
#outbreakMap {
    height: 500px;
    width: 100%;
    min-height: 500px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: 2px solid #4da8da;
    z-index: 1;
}
.leaflet-container {
    height: 500px !important;
    width: 100% !important;
    border-radius: 8px;
}
.page-header {
    background: linear-gradient(135deg, #4da8da, #0077be);
    color: white;
    padding: 30px 0;
    margin-bottom: 30px;
}
.page-header h1 {
    margin: 0;
    font-size: 2rem;
}
.alert-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}
.alert-badge.active {
    background: #fee2e2;
    color: #dc2626;
}
.alert-badge.investigating {
    background: #fef3c7;
    color: #d97706;
}
.alert-badge.contained {
    background: #d1fae5;
    color: #059669;
}
.alert-badge.resolved {
    background: #e5e7eb;
    color: #6b7280;
}
</style>

<div class="container" style="padding: 20px; max-width: 1400px; margin: 0 auto;">
    <div class="page-header">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 20px;">
            <h1><i class="fas fa-exclamation-triangle"></i> Outbreak & Alert Tracking</h1>
            <div style="display: flex; gap: 10px; align-items: center;">
                <select id="statusFilter" onchange="filterOutbreaks()" style="padding: 8px; border: 1px solid white; border-radius: 4px; background: rgba(255,255,255,0.2); color: white;">
                    <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="investigating" <?= $statusFilter == 'investigating' ? 'selected' : '' ?>>Investigating</option>
                    <option value="contained" <?= $statusFilter == 'contained' ? 'selected' : '' ?>>Contained</option>
                    <option value="resolved" <?= $statusFilter == 'resolved' ? 'selected' : '' ?>>Resolved</option>
                </select>
                <a href="dashboard.php" style="padding: 8px 15px; background: rgba(255,255,255,0.2); color: white; border-radius: 4px; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($outbreaks->rowCount() == 0): ?>
        <div style="background: white; padding: 30px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 15px;"></i>
            <p style="font-size: 1.2rem; color: #10b981;">No outbreaks found.</p>
            <p style="color: #666;">The system is currently free of disease outbreaks.</p>
        </div>
    <?php else: ?>
        <div id="outbreakMap" style="height: 500px; margin-bottom: 30px;">
            <div style="height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f9ff; color: #4da8da;">
                <div style="text-align: center;">
                    <i class="fas fa-map-marked-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>Loading outbreak map...</p>
                </div>
            </div>
        </div>
        
        <!-- Legend -->
        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="margin: 0 0 10px 0; color: #333;"><i class="fas fa-info-circle"></i> Map Legend</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                <div><strong>Outbreaks:</strong></div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 20px; height: 20px; background: #dc2626; border-radius: 50%;"></div> Active
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 20px; height: 20px; background: #d97706; border-radius: 50%;"></div> Investigating
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 20px; height: 20px; background: #059669; border-radius: 50%;"></div> Contained
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 20px; height: 20px; background: #6b7280; border-radius: 50%;"></div> Resolved
                </div>
                <div style="margin-left: 20px;"><strong>Disease Reports:</strong></div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 16px; height: 16px; background: #dc2626; border-radius: 50%;"></div> Severe
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 16px; height: 16px; background: #f97316; border-radius: 50%;"></div> Moderate
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 16px; height: 16px; background: #22c55e; border-radius: 50%;"></div> Mild
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
            <?php while ($outbreak = $outbreaks->fetch()): ?>
                <?php 
                $statusColor = '#4da8da';
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
                    $statusColor = '#6b7280';
                    $statusBg = '#e5e7eb';
                }
                ?>
                <div style="background: white; padding: 20px; border-radius: 8px; border-left: 5px solid <?= $statusColor ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <h3 style="color: <?= $statusColor ?>; margin: 0;"><?= htmlspecialchars($outbreak['disease_name']) ?></h3>
                        <span class="alert-badge <?= $outbreak['status'] ?>">
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
                        <?php if ($outbreak['latitude'] && $outbreak['longitude']): ?>
                        <button onclick="focusLocation(<?= $outbreak['latitude'] ?>, <?= $outbreak['longitude'] ?>)" style="flex: 1; padding: 10px; background: #4da8da; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-map"></i> View on Map
                        </button>
                        <?php endif; ?>
                        <?php if ($outbreak['status'] != 'resolved'): ?>
                        <button onclick="updateStatus(<?= $outbreak['outbreak_id'] ?>, 'resolved')" style="padding: 10px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-check"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Recent Disease Reports Section -->
        <?php if (count($reportData) > 0): ?>
        <div style="margin-top: 30px;">
            <h3 style="color: #4da8da; margin-bottom: 20px;"><i class="fas fa-file-medical"></i> Recent Disease Reports</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                <?php foreach (array_slice($reportData, 0, 12) as $report): ?>
                    <?php 
                    $sevColors = ['severe' => '#dc2626', 'moderate' => '#f97316', 'mild' => '#22c55e'];
                    $sevColor = $sevColors[$report['severity']] ?? '#4da8da';
                    ?>
                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid <?= $sevColor ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <h4 style="margin: 0; color: #333;"><?= htmlspecialchars($report['disease']) ?></h4>
                            <span style="padding: 3px 8px; border-radius: 10px; font-size: 0.75rem; background: <?= $sevColor ?>; color: white; text-transform: uppercase;">
                                <?= $report['severity'] ?>
                            </span>
                        </div>
                        <p style="color: #666; margin: 8px 0; font-size: 0.9rem;">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($report['location']) ?>
                        </p>
                        <p style="color: #999; margin: 0; font-size: 0.8rem;">
                            Report #<?= $report['id'] ?> - <?= ucfirst($report['status']) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
var map = null;
var markers = [];

document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing outbreak map...');
    
    // Check if Leaflet is loaded
    if (typeof L === 'undefined') {
        console.error('Leaflet not loaded, loading from CDN...');
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);
        
        var script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = function() { initMap(); };
        script.onerror = function() { console.error('Failed to load Leaflet!'); alert('Failed to load map library.'); };
        document.head.appendChild(script);
    } else {
        initMap();
    }
});

function initMap() {
    console.log('Initializing outbreak map...');
    var mapElement = document.getElementById('outbreakMap');
    if (mapElement) {
        try {
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
            
            console.log('Outbreak map initialized successfully');
        } catch (e) {
            console.error('Error initializing map:', e);
        }
    }
}
        
        // Outbreak data
        var outbreakData = <?php 
            $outbreakStmt = $db->query("SELECT o.*, l.location_name, l.latitude, l.longitude 
                             FROM outbreaks o 
                             LEFT JOIN locations l ON o.location_id = l.location_id 
                             WHERE 1=1 $whereClause");
            $data = [];
            while ($o = $outbreakStmt->fetch()) {
                if ($o['latitude'] && $o['longitude']) {
                    $data[] = [
                        'lat' => (float)$o['latitude'],
                        'lng' => (float)$o['longitude'],
                        'name' => $o['disease_name'],
                        'location' => $o['location_name'],
                        'status' => $o['status'],
                        'type' => 'outbreak'
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
            'resolved': '#6b7280'
        };
        
        var severityColors = {
            'severe': '#dc2626',
            'moderate': '#f97316',
            'mild': '#22c55e'
        };
        
        // Add outbreak markers (larger circles)
        outbreakData.forEach(function(o) {
            var color = outbreakColors[o.status] || '#4da8da';
            var marker = L.circleMarker([o.lat, o.lng], {
                radius: 18,
                fillColor: color,
                color: '#ffffff',
                weight: 3,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map);
            marker.bindPopup('<strong>OUTBREAK: ' + o.name + '</strong><br>' + (o.location || 'Unknown') + '<br>Status: ' + o.status + '<br><em>Click to investigate</em>');
            markers.push(marker);
        });
        
        // Add reported disease markers (smaller circles)
        reportData.forEach(function(r) {
            var color = severityColors[r.severity] || '#4da8da';
            var marker = L.circleMarker([r.lat, r.lng], {
                radius: 10,
                fillColor: color,
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.7
            }).addTo(map);
            marker.bindPopup('<strong>Report #' + r.id + '</strong><br>Disease: ' + r.disease + '<br>Severity: ' + r.severity + '<br>Status: ' + r.status + '<br>Location: ' + r.location);
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

function focusLocation(lat, lng) {
    if (map) {
        map.setView([lat, lng], 12);
    }
}

function filterOutbreaks() {
    var status = document.getElementById('statusFilter').value;
    window.location.href = '?status=' + status;
}

function updateStatus(outbreakId, newStatus) {
    if (confirm('Are you sure you want to mark this outbreak as ' + newStatus + '?')) {
        // Would typically make an AJAX call here to update the status
        alert('Status update functionality would be implemented here.');
        location.reload();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
