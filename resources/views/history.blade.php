<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Riwayat: {{ $device->name }} - Prima GPS</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            height: 100vh;
            /* Penting untuk mobile: menangani tinggi layar secara dinamis */
            height: -webkit-fill-available;
        }
        #map { flex: 1; z-index: 0; width: 100%; }
        
        .btn-filter.active { background-color: #3b82f6; color: white; border-color: #3b82f6; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }
        
        /* Gaya untuk Penanda Parkir */
        .parking-marker {
            background: #f59e0b;
            color: white;
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            font-size: 10px;
        }

        /* Safe area untuk HP modern (iPhone X ke atas atau Android terbaru) */
        .safe-pb {
            padding-bottom: calc(1rem + env(safe-area-inset-bottom));
        }
    </style>
</head>
<body class="bg-slate-50 flex flex-col overflow-hidden">

    <!-- Header & Navigation -->
    <nav class="bg-slate-900 text-white p-4 shadow-2xl z-20 shrink-0">
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-3">
                <a href="/" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-800 hover:bg-slate-700 transition active:scale-90">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <div>
                    <h1 class="font-black text-sm uppercase tracking-tight leading-none">{{ $device->name }}</h1>
                    <p class="text-[10px] text-blue-400 font-bold mt-1 uppercase tracking-tighter">{{ $device->plate_number }}</p>
                </div>
            </div>
            <div class="text-right flex flex-col items-end">
                <span class="text-[8px] bg-slate-800 px-2 py-1 rounded text-slate-400 font-mono tracking-tighter">FID: {{ $device->factory_id }}</span>
            </div>
        </div>

        <!-- Filter Tab -->
        <div class="flex gap-2 bg-slate-800 p-1 rounded-2xl border border-slate-700/50">
            <button onclick="changeRange('today')" id="tab-today" class="btn-filter active flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all">Hari Ini</button>
            <button onclick="changeRange('week')" id="tab-week" class="btn-filter flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all">Pekan Ini</button>
        </div>
    </nav>

    <!-- Map Container (Mengambil sisa ruang layar) -->
    <div id="map"></div>

    <!-- Stats Bar (Berada di atas sistem navigasi HP) -->
    <div class="bg-white border-t border-slate-100 flex justify-around items-center shrink-0 shadow-[0_-10px_30px_rgba(0,0,0,0.1)] z-20 safe-pb p-4">
        <div class="text-center flex-1">
            <p class="text-[8px] text-slate-400 font-black uppercase mb-1 tracking-widest">Titik Sinyal</p>
            <p class="font-black text-slate-800 text-lg leading-none" id="stat-points">0</p>
        </div>
        <div class="w-[1px] h-8 bg-slate-100"></div>
        <div class="text-center flex-1">
            <p class="text-[8px] text-slate-400 font-black uppercase mb-1 tracking-widest text-amber-500">Total Parkir</p>
            <p class="font-black text-amber-500 text-lg leading-none" id="stat-parking">0</p>
        </div>
        <div class="w-[1px] h-8 bg-slate-100"></div>
        <div class="text-center flex-1">
            <p class="text-[8px] text-slate-400 font-black uppercase mb-1 tracking-widest">Est. Jarak</p>
            <p class="font-black text-slate-800 text-lg leading-none"><span id="stat-dist">0</span> <small class="text-[10px] font-normal italic">km</small></p>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Setup Map Dasar
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
            // Loading state sederhana
            document.getElementById('stat-points').innerText = "...";
            
            if (pathLine) map.removeLayer(pathLine);
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            fetch(`/api/history/{{ $device->imei }}?range=${range}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        alert('Tidak ada riwayat untuk periode ini.');
                        resetStats();
                        return;
                    }

                    let filteredPoints = [];
                    let parkingEvents = [];
                    let lastPoint = null;
                    let stayCount = 0;

                    data.forEach((p, index) => {
                        const currentLat = parseFloat(p.latitude).toFixed(5);
                        const currentLng = parseFloat(p.longitude).toFixed(5);

                        // Cek apakah posisi sama dengan sebelumnya
                        if (lastPoint && lastPoint.lat === currentLat && lastPoint.lng === currentLng) {
                            stayCount++;
                        } else {
                            // Jika sudah berhenti lama (>10 data ~ 3-5 menit), catat sebagai parkir
                            if (stayCount > 10 && lastPoint) {
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

                    // Gambar Polyline
                    pathLine = L.polyline(filteredPoints, {
                        color: '#3b82f6',
                        weight: 5,
                        opacity: 0.8,
                        lineCap: 'round',
                        lineJoin: 'round'
                    }).addTo(map);

                    // Marker Awal & Akhir
                    var startIcon = L.circleMarker(filteredPoints[0], {
                        radius: 6, fillColor: "#22c55e", color: "#fff", weight: 2, opacity: 1, fillOpacity: 1
                    }).addTo(map).bindPopup("<b>Awal Perjalanan</b>");
                    markers.push(startIcon);

                    var endIcon = L.circleMarker(filteredPoints[filteredPoints.length - 1], {
                        radius: 6, fillColor: "#ef4444", color: "#fff", weight: 2, opacity: 1, fillOpacity: 1
                    }).addTo(map).bindPopup("<b>Posisi Akhir</b>");
                    markers.push(endIcon);

                    // Marker Parkir (P)
                    parkingEvents.forEach(evt => {
                        let pMarker = L.marker([evt.lat, evt.lng], {
                            icon: L.divIcon({
                                className: 'parking-marker',
                                html: '<i class="fa-solid fa-p"></i>',
                                iconSize: [20, 20],
                                iconAnchor: [10, 10]
                            })
                        }).addTo(map).bindPopup(`<b>Parkir di Sini</b><br>Jam: ${new Date(evt.time).toLocaleTimeString('id-ID')}`);
                        markers.push(pMarker);
                    });

                    // Fit Map View
                    map.fitBounds(pathLine.getBounds(), { padding: [40, 40] });

                    // Update Statistik di Bar Bawah
                    document.getElementById('stat-points').innerText = data.length;
                    document.getElementById('stat-parking').innerText = parkingEvents.length;
                    document.getElementById('stat-dist').innerText = (calculateDistance(filteredPoints)).toFixed(2);
                })
                .catch(err => {
                    console.error(err);
                    resetStats();
                });
        }

        function calculateDistance(coords) {
            let total = 0;
            for (let i = 0; i < coords.length - 1; i++) {
                total += L.latLng(coords[i]).distanceTo(L.latLng(coords[i+1]));
            }
            return total / 1000;
        }

        function resetStats() {
            document.getElementById('stat-points').innerText = "0";
            document.getElementById('stat-dist').innerText = "0";
            document.getElementById('stat-parking').innerText = "0";
        }

        // Load data default saat pertama kali dibuka
        loadHistory('today');
    </script>
</body>
</html>