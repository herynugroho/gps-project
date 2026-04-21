<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Laporan Riwayat: {{ $device->name }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; height: 100dvh; display: flex; flex-direction: column; overflow: hidden; margin: 0; }
        #map { flex: 1; width: 100%; z-index: 1; }
        .btn-filter.active { background-color: #3b82f6; color: white; border-color: #3b82f6; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4); }
        .parking-marker { background: #f59e0b; color: white; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-size: 10px; }
        
        /* Styling Khusus Cetak */
        @media print {
            .no-print { display: none !important; }
            #map { height: 400px !important; flex: none !important; }
            body { overflow: visible !important; height: auto !important; }
            .print-only { display: block !important; }
            .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .report-table th, .report-table td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
            .report-header { text-align: center; margin-bottom: 20px; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="bg-slate-50">

    <!-- Header Laporan (Hanya muncul saat cetak) -->
    <div class="print-only report-header">
        <h1 style="font-size: 24px; font-weight: 900; color: #0f172a;">LAPORAN RIWAYAT PERJALANAN PRIMATRACK</h1>
        <p style="font-size: 14px; color: #64748b;">Unit: {{ $device->name }} ({{ $device->plate_number }}) | Tanggal: <span id="print-date">-</span></p>
        <hr style="margin: 20px 0; border: 1px solid #e2e8f0;">
    </div>

    <!-- Navigasi Atas (Hidden saat cetak) -->
    <nav class="bg-slate-900 text-white p-4 shadow-xl z-20 shrink-0 no-print">
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
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase flex items-center gap-2 shadow-lg">
                <i class="fa-solid fa-print"></i> Cetak Laporan
            </button>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div class="flex gap-1 bg-slate-800 p-1 rounded-2xl border border-slate-700/50">
                <button onclick="changeRange('today')" id="tab-today" class="btn-filter active flex-1 py-2.5 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all">Hari Ini</button>
                <button onclick="changeRange('yesterday')" id="tab-yesterday" class="btn-filter flex-1 py-2.5 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all">Kemarin</button>
            </div>
            <div class="relative">
                <input type="date" id="date-picker" onchange="loadCustomDate(this.value)" 
                    class="w-full bg-slate-800 border border-slate-700/50 rounded-2xl py-2.5 px-4 text-[10px] font-black uppercase text-blue-400 outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </nav>

    <!-- Peta -->
    <div id="map"></div>

    <!-- Statistik & Tabel Riwayat -->
    <div class="bg-white border-t border-slate-100 shrink-0 shadow-[0_-15px_35px_rgba(0,0,0,0.12)] z-20 p-5 overflow-y-auto max-h-[40vh] no-scrollbar">
        <div class="flex justify-around items-center mb-6 no-print">
            <div class="text-center flex-1">
                <p class="text-[8px] text-slate-400 font-black uppercase mb-1.5 tracking-widest">Total Sinyal</p>
                <p class="font-black text-slate-800 text-lg leading-none" id="stat-points">0</p>
            </div>
            <div class="w-[1px] h-8 bg-slate-100"></div>
            <div class="text-center flex-1">
                <p class="text-[8px] text-amber-500 font-black uppercase mb-1.5 tracking-widest">Titik Parkir</p>
                <p class="font-black text-amber-500 text-lg leading-none" id="stat-parking">0</p>
            </div>
            <div class="w-[1px] h-8 bg-slate-100"></div>
            <div class="text-center flex-1">
                <p class="text-[8px] text-blue-500 font-black uppercase mb-1.5 tracking-widest">Jarak Tempuh</p>
                <p class="font-black text-blue-500 text-lg leading-none"><span id="stat-dist">0</span> <small class="text-[9px] ml-0.5">km</small></p>
            </div>
        </div>

        <div class="print-only mb-6">
            <h3 class="text-sm font-bold text-slate-800 uppercase mb-4">Ringkasan Persinggahan Kendaraan</h3>
        </div>

        <!-- Tabel Detail Persinggahan -->
        <div class="overflow-hidden rounded-2xl border border-slate-100">
            <table class="min-w-full divide-y divide-slate-100 report-table">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Mulai</th>
                        <th class="px-4 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Durasi</th>
                        <th class="px-4 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Lokasi (Lat, Long)</th>
                    </tr>
                </thead>
                <tbody id="parking-list" class="bg-white divide-y divide-slate-50">
                    <!-- Data diisi via JS -->
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 text-[9px] text-slate-300 font-medium italic no-print">
            *Data persinggahan dihitung otomatis berdasarkan jeda koordinat > 5 menit (Sesuai GpsServer V7.7).
        </div>
        <div style="padding-bottom: env(safe-area-inset-bottom)"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var pathLine = null;
        var markers = [];
        var currentRange = 'today';

        function formatWita(timeString) {
            if (!timeString) return "-";
            const date = new Date(timeString.replace(' ', 'T') + 'Z');
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
        }

        function formatDuration(ms) {
            const totalMinutes = Math.floor(ms / 60000);
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            return hours > 0 ? `${hours} jam ${minutes} mnt` : `${minutes} mnt`;
        }

        function loadHistory(range, customDate = null) {
            if (pathLine) map.removeLayer(pathLine);
            markers.forEach(m => map.removeLayer(m));
            markers = [];
            
            const url = customDate 
                ? `/api/history/{{ $device->imei }}?date=${customDate}`
                : `/api/history/{{ $device->imei }}?range=${range}`;

            document.getElementById('print-date').innerText = customDate || range.toUpperCase();

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    const parkingTable = document.getElementById('parking-list');
                    parkingTable.innerHTML = '';
                    
                    if (data.length === 0) {
                        parkingTable.innerHTML = '<tr><td colspan="3" class="p-10 text-center text-slate-300 italic text-xs">Tidak ada data perjalanan pada periode ini.</td></tr>';
                        document.getElementById('stat-points').innerText = '0';
                        document.getElementById('stat-parking').innerText = '0';
                        document.getElementById('stat-dist').innerText = '0';
                        return;
                    }

                    let filteredPoints = [];
                    let parkingEvents = [];
                    let lastP = null;
                    let totalDist = 0;

                    data.forEach((p, index) => {
                        const currentPos = [parseFloat(p.latitude), parseFloat(p.longitude)];
                        
                        if (lastP) {
                            const timeDiff = new Date(p.gps_time.replace(' ', 'T') + 'Z') - new Date(lastP.gps_time.replace(' ', 'T') + 'Z');
                            
                            // Logika Sinkron GpsServer V7.7: Jeda > 5 menit dianggap parkir
                            if (timeDiff > 300000) { 
                                parkingEvents.push({
                                    latitude: lastP.latitude,
                                    longitude: lastP.longitude,
                                    startTime: lastP.gps_time,
                                    duration: timeDiff
                                });
                            }
                            totalDist += L.latLng([lastP.latitude, lastP.longitude]).distanceTo(currentPos);
                        }

                        filteredPoints.push(currentPos);
                        lastP = p;
                    });

                    // Gambar Jalur Biru
                    pathLine = L.polyline(filteredPoints, { color: '#3b82f6', weight: 6, opacity: 0.8, lineJoin: 'round' }).addTo(map);
                    
                    // Marker START & END
                    markers.push(L.circleMarker(filteredPoints[0], { radius: 6, fillColor: "#22c55e", color: "#fff", weight: 3, fillOpacity: 1 }).addTo(map).bindPopup("Titik Awal"));
                    markers.push(L.circleMarker(filteredPoints[filteredPoints.length-1], { radius: 6, fillColor: "#ef4444", color: "#fff", weight: 3, fillOpacity: 1 }).addTo(map).bindPopup("Titik Akhir"));

                    // Generate Tabel & Marker Parkir
                    parkingEvents.forEach((evt, i) => {
                        // Tambah ke Tabel
                        const row = `
                            <tr>
                                <td class="px-4 py-3 text-xs font-bold text-slate-700">${formatWita(evt.startTime)}</td>
                                <td class="px-4 py-3 text-xs font-black text-amber-500 uppercase">${formatDuration(evt.duration)}</td>
                                <td class="px-4 py-3 text-[10px] font-mono text-slate-400">${evt.latitude.toFixed(5)}, ${evt.longitude.toFixed(5)}</td>
                            </tr>
                        `;
                        parkingTable.innerHTML += row;

                        // Tambah ke Peta
                        let m = L.marker([evt.latitude, evt.longitude], {
                            icon: L.divIcon({ className: 'parking-marker', html: 'P', iconSize: [20, 20], iconAnchor: [10, 10] })
                        }).addTo(map).bindPopup(`
                            <div class="p-1">
                                <b class="text-amber-600 text-[10px]">PERSINGGAHAN #${i+1}</b><br>
                                <span class="text-[11px]">Sejak: ${formatWita(evt.startTime)}</span><br>
                                <span class="text-[11px] font-bold">Lama: ${formatDuration(evt.duration)}</span>
                            </div>
                        `);
                        markers.push(m);
                    });

                    map.fitBounds(pathLine.getBounds(), { padding: [50, 50] });
                    document.getElementById('stat-points').innerText = data.length;
                    document.getElementById('stat-parking').innerText = parkingEvents.length;
                    document.getElementById('stat-dist').innerText = (totalDist / 1000).toFixed(2);
                });
        }

        function changeRange(range) {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + range).classList.add('active');
            document.getElementById('date-picker').value = '';
            loadHistory(range);
        }

        function loadCustomDate(date) {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            loadHistory(null, date);
        }

        // Set default date picker ke hari ini
        document.getElementById('date-picker').valueAsDate = new Date();
        loadHistory('today');
    </script>
</body>
</html>