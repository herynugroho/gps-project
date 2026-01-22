<!DOCTYPE html>
<html>
<head>
    <title>GPS Tracker Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 100vh; width: 100%; }
        .leaflet-div-icon { background: transparent; border: none; }
    </style>
</head>
<body class="bg-gray-100">

    <div class="absolute top-4 left-4 z-[9999] bg-white p-4 rounded shadow-lg w-64">
        <h1 class="font-bold text-xl mb-2">Live Tracking</h1>
        <div id="status-panel">
            <p class="text-sm text-gray-500">Menunggu Data...</p>
        </div>
        <div class="mt-4 text-xs text-gray-400">
            Auto-refresh setiap 3 detik
        </div>
    </div>

    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Inisialisasi Peta (Default Makassar)
        var map = L.map('map').setView([-5.147665, 119.432731], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        var markers = {};

        function fetchData() {
            fetch('/api/gps-data')
                .then(response => response.json())
                .then(data => {
                    const panel = document.getElementById('status-panel');
                    panel.innerHTML = ''; // Clear list

                    data.forEach(device => {
                        var lat = parseFloat(device.latitude);
                        var lng = parseFloat(device.longitude);

                        // Update List Panel
                        panel.innerHTML += `
                            <div class="border-b py-2">
                                <p class="font-bold">${device.name || device.imei}</p>
                                <p class="text-xs text-green-600 font-mono">${device.speed} km/h</p>
                                <p class="text-[10px] text-gray-400">${device.gps_time}</p>
                            </div>
                        `;

                        // Update Marker di Peta
                        if (markers[device.imei]) {
                            markers[device.imei].setLatLng([lat, lng]);
                        } else {
                            var marker = L.marker([lat, lng]).addTo(map)
                                .bindPopup(`<b>${device.plate_number}</b><br>Speed: ${device.speed} km/h`);
                            markers[device.imei] = marker;
                            map.flyTo([lat, lng], 15); // Auto fokus ke mobil yang baru update
                        }
                    });
                })
                .catch(err => console.error(err));
        }

        // Refresh setiap 3 detik
        setInterval(fetchData, 3000);
        fetchData();
    </script>
</body>
</html>