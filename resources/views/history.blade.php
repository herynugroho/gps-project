<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Perjalanan - {{ $device->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #map { height: 100vh; width: 100%; }
        .info-box { position: absolute; top: 20px; left: 20px; z-index: 1000; }
    </style>
</head>
<body class="bg-gray-100">

    <!-- Floating Info Panel -->
    <div class="info-box bg-white p-4 rounded-lg shadow-xl w-80">
        <div class="flex items-center gap-3 border-b pb-3 mb-3">
            <a href="/" class="text-gray-500 hover:text-blue-600"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h1 class="font-bold text-lg leading-tight">{{ $device->name }}</h1>
                <p class="text-xs text-gray-500">{{ $device->plate_number }}</p>
            </div>
        </div>
        
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Total Titik Data</span>
                <span class="font-bold" id="total-points">Memuat...</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Update Terakhir</span>
                <span class="font-bold" id="last-update">-</span>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t">
            <button onclick="window.location.reload()" class="w-full bg-blue-100 text-blue-700 py-2 rounded hover:bg-blue-200 text-sm font-semibold">
                <i class="fa-solid fa-sync mr-1"></i> Refresh Data
            </button>
        </div>
    </div>

    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([-5.147665, 119.432731], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© Prima GPS'
        }).addTo(map);

        const imei = "{{ $device->imei }}";

        // Load Jejak Perjalanan
        fetch(`/api/history/${imei}`)
            .then(res => res.json())
            .then(data => {
                if(data.length === 0) {
                    alert('Belum ada data perjalanan untuk mobil ini.');
                    return;
                }

                // 1. Gambar Garis (Polyline)
                const latlngs = data.map(d => [d.latitude, d.longitude]);
                const polyline = L.polyline(latlngs, {color: 'blue', weight: 4, opacity: 0.7}).addTo(map);
                
                // Zoom peta agar pas dengan seluruh jalur
                map.fitBounds(polyline.getBounds());

                // 2. Tandai Titik Awal (Hijau) & Akhir (Merah/Mobil)
                const start = data[0];
                const end = data[data.length - 1];

                // Marker Start
                L.marker([start.latitude, start.longitude]).addTo(map)
                    .bindPopup("Start Point: " + start.gps_time);

                // Marker Current/End (Icon Mobil)
                const carIcon = L.divIcon({
                    className: 'custom-car',
                    html: `<div style="transform: rotate(${end.course}deg);" class="w-8 h-8 bg-blue-600 rounded-full border-2 border-white shadow flex items-center justify-center text-white"><i class="fa-solid fa-location-arrow text-xs"></i></div>`,
                    iconSize: [32, 32]
                });

                L.marker([end.latitude, end.longitude], {icon: carIcon}).addTo(map)
                    .bindPopup(`<b>Posisi Terkini</b><br>Speed: ${end.speed} km/h<br>${end.gps_time}`).openPopup();

                // Update Info Panel
                document.getElementById('total-points').innerText = data.length + " titik";
                document.getElementById('last-update').innerText = end.gps_time;
            });
    </script>
</body>
</html>