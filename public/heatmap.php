<?php
ob_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    header("Location: ../auth/login.php?error=" . urlencode("Please login to view heatmap"));
    exit();
}

require_once '../includes/header.php';

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'sw'])) {
    $_SESSION['user_lang'] = $_GET['lang'];
    setcookie('user_lang', $_GET['lang'], time() + (86400 * 30), '/');
    $redirectUrl = str_replace(['?lang=en', '?lang=sw', '&lang=en', '&lang=sw'], '', $_SERVER['REQUEST_URI']);
    header('Location: ' . ($redirectUrl ?: 'heatmap.php'));
    exit;
}

$db = getDB();
?>

<style>
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}
#map {
    height: 500px;
    width: 100%;
    min-height: 500px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: 2px solid #0ea5e9;
    z-index: 1;
    display: block !important;
}
.leaflet-container {
    height: 500px !important;
    width: 100% !important;
    border-radius: 8px;
}
</style>

<div class="container" style="padding: 20px; max-width: 1400px; margin: 0 auto; display: block;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: #0ea5e9;"><i class="fas fa-map-marked-alt"></i> <?php echo t('health_heatmap'); ?></h1>
        <div style="display: flex; gap: 10px; align-items: center;">
                <?php $currentLang = $_SESSION['user_lang'] ?? $_COOKIE['user_lang'] ?? 'en'; ?>
                <a href="?lang=en" style="padding: 6px 12px; border-radius: 4px; text-decoration: none; background: <?php echo $currentLang === 'en' ? '#0ea5e9' : 'white'; ?>; color: <?php echo $currentLang === 'en' ? 'white' : '#0ea5e9'; ?>; border: 1px solid #0ea5e9;">EN</a>
                <a href="?lang=sw" style="padding: 6px 12px; border-radius: 4px; text-decoration: none; background: <?php echo $currentLang === 'sw' ? '#0ea5e9' : 'white'; ?>; color: <?php echo $currentLang === 'sw' ? 'white' : '#0ea5e9'; ?>; border: 1px solid #0ea5e9;">SW</a>
            <select id="diseaseFilter" style="padding: 8px; border: 1px solid #0ea5e9; border-radius: 4px; background: white;">
                <option value="all"><?php echo t('all_diseases'); ?></option>
                <option value="cholera">Cholera</option>
                <option value="malaria">Malaria</option>
                <option value="typhoid">Typhoid</option>
                <option value="dengue">Dengue</option>
                <option value="covid">COVID-19</option>
            </select>
            <select id="dateFilter" style="padding: 8px; border: 1px solid #0ea5e9; border-radius: 4px; background: white;">
                <option value="7"><?php echo t('last_7_days'); ?></option>
                <option value="30" selected><?php echo t('last_30_days'); ?></option>
                <option value="90"><?php echo t('last_90_days'); ?></option>
                <option value="365"><?php echo t('last_year'); ?></option>
            </select>
            <button onclick="updateMap()" style="padding: 8px 15px; background: #0ea5e9; color: white; border: none; border-radius: 4px; cursor: pointer;">
                <i class="fas fa-sync-alt"></i> <?php echo t('update_map'); ?>
            </button>
        </div>
    </div>
    
    <div id="map" style="height: 500px; width: 100%;">
        <div style="height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f9ff; color: #0ea5e9;">
            <div style="text-align: center;">
                <i class="fas fa-map-marked-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>Loading map...</p>
                <p style="font-size: 0.9rem; color: #666;">If map doesn't load, check browser console for errors</p>
            </div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px;">
        <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-top: 4px solid #0ea5e9;">
            <h3 style="color: #64748b; margin: 0 0 10px 0; font-size: 1rem;">Total Reports</h3>
            <p style="font-size: 2rem; font-weight: bold; color: #0ea5e9; margin: 0;" id="totalReports">0</p>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-top: 4px solid #ef4444;">
            <h3 style="color: #64748b; margin: 0 0 10px 0; font-size: 1rem;">Active Alerts</h3>
            <p style="font-size: 2rem; font-weight: bold; color: #ef4444; margin: 0;" id="activeAlerts">0</p>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-top: 4px solid #10b981;">
            <h3 style="color: #64748b; margin: 0 0 10px 0; font-size: 1rem;">Resolved Cases</h3>
            <p style="font-size: 2rem; font-weight: bold; color: #10b981; margin: 0;" id="resolvedCases">0</p>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-top: 4px solid #8b5cf6;">
            <h3 style="color: #64748b; margin: 0 0 10px 0; font-size: 1rem;">Affected Areas</h3>
            <p style="font-size: 2rem; font-weight: bold; color: #8b5cf6; margin: 0;" id="affectedAreas">0</p>
        </div>
    </div>
    
    <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="color: #0ea5e9; margin: 0;"><i class="fas fa-info-circle"></i> Legend</h3>
        <div style="display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 20px; height: 20px; background: rgba(14, 165, 233, 0.3); border-radius: 50%;"></div>
                <span>Low Activity</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 20px; height: 20px; background: rgba(14, 165, 233, 0.6); border-radius: 50%;"></div>
                <span>Medium Activity</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 20px; height: 20px; background: rgba(14, 165, 233, 0.9); border-radius: 50%;"></div>
                <span>High Activity</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 20px; height: 20px; background: #ef4444; border-radius: 50%;"></div>
                <span>Alert</span>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
var map = null;
var heatLayer = null;
var markersLayer = null;

document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing map...');
    
    // Check if Leaflet is loaded
    if (typeof L === 'undefined') {
        console.error('Leaflet library not loaded!');
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);
        
        var script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = function() { initMap(); };
        script.onerror = function() { console.error('Failed to load Leaflet!'); alert('Failed to load map library. Please check your internet connection.'); };
        document.head.appendChild(script);
    } else {
        initMap();
    }
});

function initMap() {
    console.log('Initializing map...');
    var mapElement = document.getElementById('map');
    if (mapElement) {
        try {
            map = L.map('map', {
                center: [-1.2864, 36.8172],
                zoom: 6,
                zoomControl: true,
                attributionControl: true
            });
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);
            
            console.log('Map initialized successfully');
            updateMap();
        } catch (e) {
            console.error('Error initializing map:', e);
            alert('Error initializing map: ' + e.message);
        }
    } else {
        console.error('Map element not found!');
    }
}

function updateMap() {
    if (!map) return;
    
    var disease = document.getElementById('diseaseFilter').value;
    var days = document.getElementById('dateFilter').value;
    
    console.log('Updating map with disease:', disease, 'days:', days);
    
    fetch(`api/get_heatmap_data.php?disease=${disease}&days=${days}`)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Map data received:', data);
            
            if (heatLayer) {
                map.removeLayer(heatLayer);
                heatLayer = null;
            }
            if (markersLayer) {
                map.removeLayer(markersLayer);
                markersLayer = null;
            }
            
            // Process disease reports
            if (data.points && data.points.length > 0) {
                markersLayer = L.layerGroup().addTo(map);
                
                var severityColors = {
                    'severe': '#dc2626',
                    'moderate': '#f97316',
                    'mild': '#22c55e'
                };
                
                data.points.forEach(function(p) {
                    var color = p.intensity > 2 ? '#dc2626' : (p.intensity > 1 ? '#f97316' : '#0ea5e9');
                    if (p.severity) {
                        color = severityColors[p.severity] || color;
                    }
                    var radius = Math.min(p.intensity * 3, 20);
                    
                    var marker = L.circleMarker([p.lat, p.lng], {
                        radius: radius,
                        fillColor: color,
                        color: '#ffffff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.7
                    });
                    var popupContent = '<strong>Disease: ' + (p.disease || 'Unknown') + '</strong><br>';
                    popupContent += 'Cases: ' + p.intensity + '<br>';
                    if (p.severity) popupContent += 'Severity: ' + p.severity + '<br>';
                    if (p.location) popupContent += 'Location: ' + p.location + '<br>';
                    popupContent += 'Lat: ' + p.lat.toFixed(4) + ', Lng: ' + p.lng.toFixed(4);
                    marker.bindPopup(popupContent);
                    markersLayer.addLayer(marker);
                });
            }
            
            // Display outbreaks with affected areas
            if (data.outbreaks && data.outbreaks.length > 0) {
                console.log('Found outbreaks:', data.outbreaks.length);
                data.outbreaks.forEach(function(o) {
                    // Draw affected area circle
                    var affectedArea = L.circle([o.lat, o.lng], {
                        radius: o.radius * 1000, // Convert km to meters
                        fillColor: '#dc2626',
                        fillOpacity: 0.15,
                        color: '#dc2626',
                        weight: 2,
                        opacity: 0.6
                    }).addTo(map);
                    
                    affectedArea.bindPopup('<div style="min-width:150px;">' +
                        '<strong style="color:#dc2626;">OUTBREAK ALERT</strong><hr>' +
                        '<strong>Disease:</strong> ' + o.disease + '<br>' +
                        '<strong>Location:</strong> ' + (o.location || 'Unknown') + '<br>' +
                        '<strong>Affected Radius:</strong> ' + o.radius + ' km<br>' +
                        '<strong>Confirmed Cases:</strong> ' + o.cases_confirmed + '<br>' +
                        '<strong>Alert Date:</strong> ' + o.alert_date + '<br>' +
                        '<em>Area at risk</em></div>');
                    
                    // Add center marker
                    var centerMarker = L.circleMarker([o.lat, o.lng], {
                        radius: 8,
                        fillColor: '#dc2626',
                        color: '#ffffff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.9
                    }).addTo(map);
                    
                    centerMarker.bindPopup('<div style="min-width:150px;">' +
                        '<strong style="color:#dc2626;">Outbreak Center</strong><hr>' +
                        '<strong>Disease:</strong> ' + o.disease + '<br>' +
                        '<strong>Location:</strong> ' + (o.location || 'Unknown') + '</div>');
                });
            }
            
            // Fit bounds
            var allMarkers = [];
            if (data.points) {
                data.points.forEach(function(p) { allMarkers.push([p.lat, p.lng]); });
            }
            if (data.outbreaks) {
                data.outbreaks.forEach(function(o) { allMarkers.push([o.lat, o.lng]); });
            }
            if (allMarkers.length > 0) {
                var bounds = L.latLngBounds(allMarkers);
                map.fitBounds(bounds, {padding: [50, 50]});
            }
            
            // Update stats
            document.getElementById('totalReports').textContent = data.stats.total_reports || 0;
            document.getElementById('activeAlerts').textContent = data.outbreaks ? data.outbreaks.length : 0;
            document.getElementById('resolvedCases').textContent = data.stats.resolved_cases || 0;
            document.getElementById('affectedAreas').textContent = data.outbreaks ? data.outbreaks.length : 0;
        })
}

setInterval(function() {
    if (map) updateMap();
}, 300000);
</script>

<?php require_once '../includes/footer.php'; ob_end_flush(); ?>
