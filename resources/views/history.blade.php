<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat: {{ $device->name }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        #map { flex: 1; z-index: 0; }
        .btn-filter.active { background-color: #3b82f6; color: white; border-color: #3b82f6; }
        
        /* Style untuk Marker Parkir */
        .parking-marker {
            background: #f59e0b;
            color: white;
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body class="bg-slate-50 h-screen flex flex-col overflow-hidden">

    <!-- Header & Filters -->
    <nav class="bg-slate-900 text-white p-4 shadow-2xl z-10 shrink-0">
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-3">
                <a href="/" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-800 hover:bg-slate-700 transition">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <div>
                    <h1 class="font-black text-sm uppercase tracking-tight leading-none">{{ $device->name }}</h1>
                    <p class="text-[10px] text-blue-400 font-bold mt-1 uppercase">{{ $device->plate_number }}</p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[9px] bg-slate-800 px-2 py-1 rounded text-slate-400 font-mono">FID: {{ $device->factory_id }}</span>
            </div>
        </div>

        <!-- Filter Tab -->
        <div class="flex gap-2 bg-slate-800 p-1 rounded-2xl">
            <button onclick="changeRange('today')" id="tab-today" class="btn-filter active flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all">Hari Ini</button>
            <button onclick="changeRange('week')" id="tab-week" class="btn-filter flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all">Pekan Ini</button>
        </div>
    </nav>

    <!-- Map Area -->
    <div id="map"></div>

    <!-- Stats Floating Card: 3 Kolom -->
    <div class="bg-white border-t border-slate-100 p-4 flex justify-around items-center shrink-0 shadow-[0_-10px_30px_rgba(0,0,0,0.05)] z-10">
        <div class="text-center flex-1">
            <p class="text-[9px] text-slate-400 font-black uppercase mb-1">Titik Sinyal</p>
            <p class="font-black text-slate-800 text-lg" id="stat-points">0</p>
        </div>
        <div class="w-[1px] h-8 bg-slate-100"></div>
        <div class="text-center flex-1">
            <p class="text-[9px] text-slate-400 font-black uppercase mb-1">Total Parkir</p>
            <p class="font-black text-amber-500 text-lg" id="stat-parking">0</p>
        </div>
        <div class="w-[1px] h-8 bg-slate-100"></div>
        <div class="text-center flex-1">
            <p class="text-[9px] text-slate-400 font-black uppercase mb-1">Est. Jarak</p>
            <p class="font-black text-slate-800 text-lg"><span id="stat-dist">0</span> <small class="text-[10px] font-normal italic">km</small></p>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var pathLine = null;
        var markers = [];

        function changeRange(range) {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + range).classList.add('active');
            loadHistory(range);
        }

        function loadHistory(range) {
            // Bersihkan data lama
            if (pathLine) map.removeLayer(pathLine);
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            fetch(`/api/history/{{ $device->imei }}?range=${range}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        alert('Tidak ada riwayat untuk periode ini.');
                        document.getElementById('stat-points').innerText = '0';
                        document.getElementById('stat-dist').innerText = '0';
                        document.getElementById('stat-parking').innerText = '0';
                        return;
                    }

                    // --- LOGIC FILTERING DATA ---
                    let filteredPoints = [];
                    let parkingEvents = [];
                    let lastPoint = null;
                    let stayCount = 0;

                    data.forEach((p, index) => {
                        const currentLat = parseFloat(p.latitude).toFixed(5);
                        const currentLng = parseFloat(p.longitude).toFixed(5);

                        if (lastPoint && lastPoint.lat === currentLat && lastPoint.lng === currentLng) {
                            // Jika titik sama, hitung sebagai durasi parkir
                            stayCount++;
                        } else {
                            // Jika kendaraan mulai bergerak lagi setelah diam lama (>15 data/sekitar 5 menit)
                            if (stayCount > 15 && lastPoint) {
                                parkingEvents.push({
                                    lat: lastPoint.lat,
                                    lng: lastPoint.lng,
                                    time: lastPoint.time
                                });
                            }
                            
                            filteredPoints.push([parseFloat(p.latitude), parseFloat(p.longitude)]);
                            lastPoint = { lat: currentLat, lng: currentLng, time: p.gps_time };
                            stayCount = 0;
                        }
                    });

                    // Gambar Garis Perjalanan (Gunakan filteredPoints agar tidak berat)
                    pathLine = L.polyline(filteredPoints, {
                        color: '#3b82f6',
                        weight: 6,
                        opacity: 0.8,
                        lineCap: 'round',
                        lineJoin: 'round'
                    }).addTo(map);

                    // Marker Awal (Start)
                    var startIcon = L.circleMarker(filteredPoints[0], {
                        radius: 8, fillColor: "#22c55e", color: "#fff", weight: 3, opacity: 1, fillOpacity: 1
                    }).addTo(map).bindPopup("<b>Titik Awal Perjalanan</b>");
                    markers.push(startIcon);

                    // Marker Akhir (Finish)
                    var endIcon = L.circleMarker(filteredPoints[filteredPoints.length - 1], {
                        radius: 8, fillColor: "#ef4444", color: "#fff", weight: 3, opacity: 1, fillOpacity: 1
                    }).addTo(map).bindPopup("<b>Posisi Terakhir</b>");
                    markers.push(endIcon);

                    // Tambahkan Ikon Parkir (P) di peta
                    parkingEvents.forEach(evt => {
                        let pMarker = L.marker([evt.lat, evt.lng], {
                            icon: L.divIcon({
                                className: 'parking-marker',
                                html: '<i class="fa-solid fa-p"></i>',
                                iconSize: [24, 24],
                                iconAnchor: [12, 12]
                            })
                        }).addTo(map).bindPopup(`<b>Area Parkir</b><br>Waktu: ${new Date(evt.time).toLocaleTimeString('id-ID')}`);
                        markers.push(pMarker);
                    });

                    // Zoom ke jalur
                    map.fitBounds(pathLine.getBounds(), { padding: [50, 50] });

                    // Update Statistik
                    document.getElementById('stat-points').innerText = data.length; // Raw data tetap ditampilkan
                    document.getElementById('stat-parking').innerText = parkingEvents.length;
                    document.getElementById('stat-dist').innerText = (calculateDistance(filteredPoints)).toFixed(2);
                });
        }

        function calculateDistance(coords) {
            let total = 0;
            for (let i = 0; i < coords.length - 1; i++) {
                total += L.latLng(coords[i]).distanceTo(L.latLng(coords[i+1]));
            }
            return total / 1000;
        }

        loadHistory('today');
    </script>
</body>
</html>