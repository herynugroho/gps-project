<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Riwayat: {{ $device->name }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; height: 100dvh; display: flex; flex-direction: column; overflow: hidden; margin: 0; }
        #map { flex: 1; width: 100%; z-index: 1; }
        .btn-filter.active { background-color: #3b82f6; color: white; border-color: #3b82f6; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4); }
        .parking-marker { background: #f59e0b; color: white; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-size: 10px; }
    </style>
</head>
<body class="bg-slate-50">

    <nav class="bg-slate-900 text-white p-4 shadow-xl z-20 shrink-0">
        <div class="flex justify-between items-start mb-4">
            <div class="flex items-center gap-3">
                <a href="/" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-800 border border-slate-700 active:scale-90 transition">
                    <i class="fa-solid fa-chevron-left text-sm"></i>
                </a>
                <div>
                    <h1 class="font-black text-sm uppercase leading-none tracking-tight">{{ $device->name }}</h1>
                    <p class="text-[10px] text-blue-400 font-bold mt-1 uppercase tracking-tighter">{{ $device->plate_number }}</p>
                </div>
            </div>
            <div class="flex flex-col items-end">
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="text-[10px] font-black tracking-tighter uppercase italic">PRIMA GPS</span>
                    <div class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></div>
                </div>
                <span class="text-[8px] bg-slate-800 px-2 py-1 rounded text-slate-400 font-mono tracking-tighter border border-slate-700">FID: {{ $device->factory_id }}</span>
            </div>
        </div>

        <div class="flex gap-2 bg-slate-800 p-1 rounded-2xl border border-slate-700/50">
            <button onclick="changeRange('today')" id="tab-today" class="btn-filter active flex-1 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">Hari Ini</button>
            <button onclick="changeRange('week')" id="tab-week" class="btn-filter flex-1 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all ml-1">Pekan Ini</button>
        </div>
    </nav>

    <div id="map"></div>

    <div class="bg-white border-t border-slate-100 shrink-0 shadow-[0_-15px_35px_rgba(0,0,0,0.12)] z-20 p-5">
        <div class="flex justify-around items-center">
            <div class="text-center flex-1">
                <p class="text-[8px] text-slate-400 font-black uppercase mb-1.5 tracking-widest">Titik Sinyal</p>
                <p class="font-black text-slate-800 text-lg leading-none" id="stat-points">0</p>
            </div>
            <div class="w-[1px] h-8 bg-slate-100"></div>
            <div class="text-center flex-1">
                <p class="text-[8px] text-amber-500 font-black uppercase mb-1.5 tracking-widest">Total Parkir</p>
                <p class="font-black text-amber-500 text-lg leading-none" id="stat-parking">0</p>
            </div>
            <div class="w-[1px] h-8 bg-slate-100"></div>
            <div class="text-center flex-1">
                <p class="text-[8px] text-slate-400 font-black uppercase mb-1.5 tracking-widest">Est. Jarak</p>
                <p class="font-black text-slate-800 text-lg leading-none"><span id="stat-dist">0</span> <small class="text-[9px] text-slate-400 ml-0.5">km</small></p>
            </div>
        </div>
        <div style="padding-bottom: env(safe-area-inset-bottom)"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var pathLine = null;
        var markers = [];

        function formatWita(timeString) {
            if (!timeString) return "-";
            const date = new Date(timeString.replace(' ', 'T') + 'Z');
            return date.toLocaleTimeString('id-ID', { hour12: false });
        }

        function formatDuration(ms) {
            const totalMinutes = Math.floor(ms / 60000);
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            return hours > 0 ? `${hours} jam ${minutes} menit` : `${minutes} menit`;
        }

        function loadHistory(range) {
            if (pathLine) map.removeLayer(pathLine);
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            fetch(`/api/history/{{ $device->imei }}?range=${range}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) return;

                    let filteredPoints = [];
                    let parkingEvents = [];
                    let lastP = null;

                    data.forEach((p, index) => {
                        const currentPos = [parseFloat(p.latitude), parseFloat(p.longitude)];
                        
                        if (lastP) {
                            const timeDiff = new Date(p.gps_time.replace(' ', 'T') + 'Z') - new Date(lastP.gps_time.replace(' ', 'T') + 'Z');
                            
                            // LOGIKA BARU: Jika jeda waktu antar data > 5 menit, dianggap parkir
                            if (timeDiff > 300000) { 
                                parkingEvents.push({
                                    latitude: lastP.latitude,
                                    longitude: lastP.longitude,
                                    startTime: lastP.gps_time,
                                    duration: timeDiff
                                });
                            }
                        }

                        filteredPoints.push(currentPos);
                        lastP = p;
                    });

                    pathLine = L.polyline(filteredPoints, { color: '#3b82f6', weight: 6, opacity: 0.85, lineJoin: 'round' }).addTo(map);
                    
                    // Marker Awal & Akhir
                    markers.push(L.circleMarker(filteredPoints[0], { radius: 7, fillColor: "#22c55e", color: "#fff", weight: 3, fillOpacity: 1 }).addTo(map));
                    markers.push(L.circleMarker(filteredPoints[filteredPoints.length-1], { radius: 7, fillColor: "#ef4444", color: "#fff", weight: 3, fillOpacity: 1 }).addTo(map));

                    // Marker Parkir
                    parkingEvents.forEach(evt => {
                        let m = L.marker([evt.latitude, evt.longitude], {
                            icon: L.divIcon({ className: 'parking-marker', html: 'P', iconSize: [22, 22], iconAnchor: [11, 11] })
                        }).addTo(map).bindPopup(`
                            <div class="p-1">
                                <b class="text-amber-600 uppercase text-[10px]">Area Parkir</b><br>
                                <span class="text-[11px]">Mulai: ${formatWita(evt.startTime)}</span><br>
                                <span class="text-[11px] font-bold">Durasi: ${formatDuration(evt.duration)}</span>
                            </div>
                        `);
                        markers.push(m);
                    });

                    map.fitBounds(pathLine.getBounds(), { padding: [60, 60] });
                    document.getElementById('stat-points').innerText = data.length.toLocaleString();
                    document.getElementById('stat-parking').innerText = parkingEvents.length;
                    
                    let dist = 0;
                    for (let i = 0; i < filteredPoints.length - 1; i++) {
                        dist += L.latLng(filteredPoints[i]).distanceTo(L.latLng(filteredPoints[i+1]));
                    }
                    document.getElementById('stat-dist').innerText = (dist / 1000).toFixed(2);
                });
        }

        function changeRange(range) {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + range).classList.add('active');
            loadHistory(range);
        }

        loadHistory('today');
    </script>
</body>
</html>