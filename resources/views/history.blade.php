<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>History: {{ $device->name }} - Prima GPS</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        #map { flex: 1; z-index: 0; }
        .filter-btn.active { background-color: #1e293b; color: white; border-color: #1e293b; }
    </style>
</head>
<body class="bg-slate-50 h-screen flex flex-col overflow-hidden">

    <!-- Header Navigation -->
    <nav class="bg-slate-900 text-white p-4 flex justify-between items-center shadow-xl shrink-0 z-10">
        <div class="flex items-center gap-3">
            <a href="/" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-800 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="font-black text-sm uppercase tracking-tight leading-none">{{ $device->name }}</h1>
                <p class="text-[10px] text-blue-400 font-bold uppercase mt-1 tracking-tighter">{{ $device->plate_number }}</p>
            </div>
        </div>
        <div class="flex bg-slate-800 p-1 rounded-xl border border-slate-700">
            <button onclick="loadHistory('today')" id="btn-today" class="filter-btn active px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition-all">Hari Ini</button>
            <button onclick="loadHistory('week')" id="btn-week" class="filter-btn px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition-all ml-1">Pekan Ini</button>
        </div>
    </nav>

    <!-- Map Container -->
    <div id="map"></div>

    <!-- Stats Bar -->
    <div class="bg-white border-t border-slate-200 p-4 flex justify-around items-center shrink-0 z-10 shadow-[0_-10px_20px_rgba(0,0,0,0.05)]">
        <div class="text-center">
            <p class="text-[9px] text-slate-400 font-black uppercase">Titik Data</p>
            <p class="font-black text-slate-800" id="stat-points">0</p>
        </div>
        <div class="h-8 w-[1px] bg-slate-100"></div>
        <div class="text-center">
            <p class="text-[9px] text-slate-400 font-black uppercase">Jarak Est.</p>
            <p class="font-black text-slate-800" id="stat-distance">0 <span class="text-[10px] font-normal italic">km</span></p>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var polyline = null;
        var startMarker = null;
        var endMarker = null;

        function loadHistory(range) {
            // Update UI Button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + range).classList.add('active');

            // Fetch Data
            fetch(`/api/history/{{ $device->imei }}?range=${range}`)
                .then(res => res.json())
                .then(data => {
                    // Bersihkan peta dari jalur lama
                    if (polyline) map.removeLayer(polyline);
                    if (startMarker) map.removeLayer(startMarker);
                    if (endMarker) map.removeLayer(endMarker);

                    if (data.length === 0) {
                        alert('Tidak ada riwayat perjalanan ditemukan untuk periode ini.');
                        document.getElementById('stat-points').innerText = '0';
                        return;
                    }

                    // Mapping koordinat
                    var latlngs = data.map(pos => [parseFloat(pos.latitude), parseFloat(pos.longitude)]);

                    // Gambar Jalur Baru (Polyline)
                    polyline = L.polyline(latlngs, {
                        color: '#3b82f6', 
                        weight: 6, 
                        opacity: 0.8,
                        lineJoin: 'round'
                    }).addTo(map);

                    // Marker Start (Hijau) & Finish (Merah)
                    startMarker = L.circleMarker(latlngs[0], {
                        radius: 8, fillColor: "#22c55e", color: "#fff", weight: 3, opacity: 1, fillOpacity: 1
                    }).addTo(map).bindPopup("Titik Awal");

                    endMarker = L.circleMarker(latlngs[latlngs.length - 1], {
                        radius: 8, fillColor: "#ef4444", color: "#fff", weight: 3, opacity: 1, fillOpacity: 1
                    }).addTo(map).bindPopup("Posisi Terakhir");

                    // Zoom ke jalur
                    map.fitBounds(polyline.getBounds(), { padding: [50, 50] });

                    // Update Statistik
                    document.getElementById('stat-points').innerText = data.length;
                    
                    // Hitung jarak sederhana (opsional)
                    let dist = calculateDistance(latlngs);
                    document.getElementById('stat-distance').innerText = dist.toFixed(2);
                });
        }

        // Fungsi hitung jarak antar koordinat
        function calculateDistance(points) {
            let total = 0;
            for (let i = 0; i < points.length - 1; i++) {
                total += L.latLng(points[i]).distanceTo(L.latLng(points[i+1]));
            }
            return total / 1000; // Konversi ke km
        }

        // Muat data hari ini saat pertama buka
        loadHistory('today');
    </script>
</body>
</html>