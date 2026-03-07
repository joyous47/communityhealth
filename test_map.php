<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Map</title>
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    />
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
        }

        #map {
            height: 500px;
            width: 100%;
            border: 2px solid #4da8da;
            border-radius: 8px;
        }

        .error,
        .success {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .error {
            color: red;
            background: #fee2e2;
        }

        .success {
            color: green;
            background: #d1fae5;
        }
    </style>
</head>
<body>
    <h1>Simple Map Test</h1>
    <div id="status"></div>
    <div id="map"></div>

    <div style="margin-top: 20px;">
        <h3>Test Controls:</h3>
        <button onclick="testLeaflet()">Test Leaflet</button>
        <button onclick="addTestMarker()">Add Test Marker</button>
        <button onclick="addOutbreakCircle()">Add Outbreak Circle</button>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map = null;
        const statusDiv = document.getElementById('status');

        function showStatus(message, isError = false) {
            statusDiv.innerHTML = isError
                ? `<div class="error">${message}</div>`
                : `<div class="success">${message}</div>`;
            console.log(message);
        }

        function initMap() {
            try {
                showStatus('Initializing map...');
                map = L.map('map').setView([-1.2864, 36.8172], 6);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                showStatus('Map initialized successfully — center: Kenya.');
            } catch (e) {
                showStatus(`Error: ${e.message}`, true);
                console.error(e);
            }
        }

        function testLeaflet() {
            if (typeof L !== 'undefined') {
                showStatus(`Leaflet loaded (v${L.version})`);
            } else {
                showStatus('Leaflet is NOT loaded!', true);
            }
        }

        function addTestMarker() {
            if (!map) {
                showStatus('Map not initialized!', true);
                return;
            }
            const marker = L.marker([-1.2921, 36.8219]).addTo(map);
            marker.bindPopup('<b>Nairobi</b><br>Test marker').openPopup();
            map.setView([-1.2921, 36.8219], 10);
            showStatus('Added test marker at Nairobi');
        }

        function addOutbreakCircle() {
            if (!map) {
                showStatus('Map not initialized!', true);
                return;
            }
            const circle = L.circle([-4.0435, 39.6682], {
                color: '#dc2626',
                fillColor: '#dc2626',
                fillOpacity: 0.2,
                radius: 15000
            }).addTo(map);
            circle.bindPopup('<b>Cholera Outbreak</b><br>Mombasa<br>15km radius');

            const center = L.circleMarker([-4.0435, 39.6682], {
                radius: 10,
                fillColor: '#dc2626',
                color: '#ffffff',
                weight: 2
            }).addTo(map);
            center.bindPopup('<b>Outbreak Center</b><br>Mombasa');

            map.setView([-4.0435, 39.6682], 8);
            showStatus('Added outbreak circle at Mombasa (15 km radius)');
        }

        window.onload = initMap;
    </script>
</body>
</html>
