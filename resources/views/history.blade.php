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
        body { font-family: 'Inter', sans-serif; height: 100dvh; margin: 0; display: flex; flex-direction: column; overflow: hidden; }
        #map { height: 100%; width: 100%; z-index: 1; }
        .parking-marker { background: #f59e0b; color: white; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-size: 10px; cursor: pointer; transition: transform 0.2s; }
        .parking-marker:hover { transform: scale(1.2); z-index: 1000 !important; }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .highlight-row { background-color: #fef3c7 !important; border-left: 4px solid #f59e0b !important; transition: all 0.3s ease; }
        
        /* Desktop Sidebar Layout */
        @media (min-width: 1024px) {
            .main-wrapper { flex-direction: row !important; }
            .side-panel { width: 400px !important; height: 100% !important; max-height: none !important; border-top: none !important; border-right: 1px solid #e2e8f0; order: -1; position: relative !important; transform: none !important; }
            .mobile-nav { display: none !important; }
            .desktop-header { display: block !important; }
        }

        @media print {
            .no-print { display: none !important; }
            #map { height: 400px !important; width: 100% !important; flex: none !important; }
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

    <!-- Print Header -->
    <div class="print-only report-header">
        <h1 style="font-size: 24px; font-weight: 900; color: #0f172a;">LAPORAN RIWAYAT PERJALANAN PRIMATRACK</h1>
        <p style="font-size: 14px; color: #64748b;">Unit: {{ $device->name }} | Periode: <span id="print-date">-</span></p>
    </div>

    <div class="main-wrapper flex flex-col flex-1 overflow-hidden">
        
        <!-- Mobile Navigation -->
        <nav class="bg-slate-900 text-white p-4 shadow-xl z-20 shrink-0 no-print md:hidden">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center gap-3">
                    <a href="/" class="w-9 h-9 flex items-center justify-center rounded-xl bg-slate-800 border border-slate-700">
                        <i class="fa-solid fa-chevron-left text-sm"></i>
                    </a>
                    <h1 class="font-black text-xs uppercase">{{ $device->name }}</h1>
                </div>
                <button onclick="window.print()" class="text-[10px] font-black uppercase text-blue-400">Cetak</button>
            </div>
            
            <div class="flex gap-2">
                <select id="mobile-mode" onchange="syncMode(this.value); toggleInputs()" class="bg-slate-800 text-[10px] font-black uppercase px-3 py-3 rounded-xl flex-1 outline-none border border-slate-700">
                    <option value="today">Hari Ini</option>
                    <option value="single">Tanggal Tertentu</option>
                    <option value="range">Rentang Tanggal</option>
                </select>
                <button onclick="updateHistory()" class="bg-blue-600 px-4 rounded-xl text-[10px] font-black uppercase">Update</button>
            </div>
        </nav>

        <!-- Sidebar / Bottom Panel -->
        <aside id="side-panel" class="side-panel bg-white shadow-[0_-15px_35px_rgba(0,0,0,0.1)] z-20 overflow-y-auto max-h-[45vh] flex flex-col no-scrollbar">
            
            <!-- Desktop Header (Hidden on Mobile) -->
            <div class="hidden desktop-header p-6 bg-slate-900 text-white shrink-0 no-print">
                <div class="flex items-center gap-4 mb-6">
                    <a href="/" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-800 border border-slate-700 hover:bg-slate-700 transition">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                    <div>
                        <h1 class="font-black text-sm uppercase leading-none tracking-tight">{{ $device->name }}</h1>
                        <p class="text-[10px] text-blue-400 font-bold mt-1 uppercase tracking-widest">{{ $device->plate_number }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Filter Mode</label>
                        <select id="mode-selector" onchange="syncMode(this.value); toggleInputs()" class="w-full bg-slate-800 text-[11px] font-black uppercase px-4 py-3 rounded-xl border border-slate-700 outline-none cursor-pointer">
                            <option value="today">Hari Ini</option>
                            <option value="single">Tanggal Tertentu</option>
                            <option value="range">Rentang Tanggal</option>
                        </select>
                    </div>

                    <div id="filter-inputs" class="space-y-3">
                        <div id="input-single" class="hidden">
                            <input type="date" id="date-single" class="w-full bg-slate-800 border border-slate-700 rounded-xl py-3 px-4 text-xs font-bold text-blue-400">
                        </div>
                        <div id="input-range" class="hidden grid grid-cols-2 gap-2">
                            <input type="date" id="date-start" class="bg-slate-800 border border-slate-700 rounded-xl py-3 px-4 text-xs font-bold text-blue-400">
                            <input type="date" id="date-end" class="bg-slate-800 border border-slate-700 rounded-xl py-3 px-4 text-xs font-bold text-blue-400">
                        </div>
                        <button onclick="updateHistory()" id="btn-update" class="w-full bg-blue-600 hover:bg-blue-700 py-4 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg transition-all active:scale-95">
                            Cari Riwayat
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats (Sticky) -->
            <div class="sticky top-0 bg-white border-b border-slate-100 p-5 z-30 no-print">
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 text-center">
                        <p class="text-[8px] text-slate-400 font-black uppercase mb-1">Sinyal</p>
                        <p class="font-black text-slate-800 text-sm" id="stat-points">0</p>
                    </div>
                    <div class="bg-amber-50 p-3 rounded-2xl border border-amber-100 text-center">
                        <p class="text-[8px] text-amber-500 font-black uppercase mb-1">Parkir</p>
                        <p class="font-black text-amber-500 text-sm" id="stat-parking">0</p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-2xl border border-blue-100 text-center">
                        <p class="text-[8px] text-blue-500 font-black uppercase mb-1">Jarak</p>
                        <p class="font-black text-blue-500 text-sm"><span id="stat-dist">0</span> <small class="text-[9px]">km</small></p>
                    </div>
                </div>
            </div>

            <!-- Detail List -->
            <div class="p-5 flex-1 overflow-y-auto no-scrollbar">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Detail Persinggahan</h3>
                    <button onclick="window.print()" class="hidden md:block text-[9px] font-black text-blue-500 uppercase underline">Download PDF</button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-100">
                    <table class="min-w-full divide-y divide-slate-100 report-table">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Mulai</th>
                                <th class="px-4 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Lama</th>
                                <th class="px-4 py-3 text-right text-[9px] font-black text-slate-400 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="parking-list" class="bg-white divide-y divide-slate-50">
                            <!-- JS Content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>

        <!-- Map Container -->
        <div class="flex-1 relative">
            <div id="map"></div>
            
            <!-- Mobile Toggle Filter (Icon Only) -->
            <div class="md:hidden absolute top-4 right-4 z-[500]">
                <div class="flex flex-col gap-2">
                    <button onclick="document.querySelector('nav').scrollIntoView({behavior:'smooth'})" class="bg-white w-10 h-10 rounded-xl shadow-xl flex items-center justify-center text-slate-800 border border-slate-100">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var pathLine = null;
        var markers = [];
        var parkingMarkers = [];

        function syncMode(val) {
            document.getElementById('mode-selector').value = val;
            document.getElementById('mobile-mode').value = val;
        }

        function toggleInputs() {
            const mode = document.getElementById('mode-selector').value;
            document.getElementById('input-single').classList.toggle('hidden', mode !== 'single');
            document.getElementById('input-range').classList.toggle('hidden', mode !== 'range');
        }

        function updateHistory() {
            const mode = document.getElementById('mode-selector').value;
            const btn = document.getElementById('btn-update');
            let params = "";
            let displayDate = "";
            
            if (mode === 'today') {
                params = "range=today";
                displayDate = "Hari Ini";
            } else if (mode === 'single') {
                const date = document.getElementById('date-single').value;
                if(!date) return alert("Pilih tanggal terlebih dahulu");
                params = `date=${date}`;
                displayDate = date;
            } else if (mode === 'range') {
                const start = document.getElementById('date-start').value;
                const end = document.getElementById('date-end').value;
                if(!start || !end) return alert("Pilih rentang tanggal lengkap");
                params = `start=${start}&end=${end}`;
                displayDate = `${start} s/d ${end}`;
            }

            // Tambahkan cache-buster agar data benar-benar refresh dari server
            params += `&_=${Date.now()}`;

            btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin mr-1"></i> Mencari...';
            btn.disabled = true;

            loadHistory(params, displayDate).finally(() => {
                btn.innerHTML = 'Cari Riwayat';
                btn.disabled = false;
                // Auto scroll to stats on mobile
                if(window.innerWidth < 1024) document.getElementById('side-panel').scrollIntoView({behavior:'smooth'});
            });
        }

        async function loadHistory(queryString, displayDate = "Hari Ini") {
            // Bersihkan data lama
            if (pathLine) map.removeLayer(pathLine);
            markers.forEach(m => map.removeLayer(m));
            parkingMarkers.forEach(m => map.removeLayer(m));
            markers = [];
            parkingMarkers = [];
            
            document.getElementById('print-date').innerText = displayDate;

            try {
                const url = `/api/history/{{ $device->imei }}?${queryString}`;
                const response = await fetch(url);
                const data = await response.json();
                
                const parkingTable = document.getElementById('parking-list');
                parkingTable.innerHTML = '';
                
                if (!data || data.length === 0) {
                    parkingTable.innerHTML = '<tr><td colspan="3" class="p-10 text-center text-slate-300 italic text-xs">Data perjalanan tidak ditemukan.</td></tr>';
                    document.getElementById('stat-points').innerText = '0';
                    document.getElementById('stat-parking').innerText = '0';
                    document.getElementById('stat-dist').innerText = '0';
                    return;
                }

                let filteredPoints = [];
                let parkingEvents = [];
                let lastP = null;
                let totalDist = 0;

                data.forEach((p) => {
                    const currentPos = [parseFloat(p.latitude), parseFloat(p.longitude)];
                    if (lastP) {
                        const timeDiff = new Date(p.gps_time.replace(' ', 'T') + 'Z') - new Date(lastP.gps_time.replace(' ', 'T') + 'Z');
                        // Logika: Jika diam > 5 menit
                        if (timeDiff > 300000) { 
                            parkingEvents.push({ 
                                lat: lastP.latitude, 
                                lng: lastP.longitude, 
                                startTime: lastP.gps_time, 
                                duration: timeDiff 
                            });
                        }
                        totalDist += L.latLng([lastP.latitude, lastP.longitude]).distanceTo(currentPos);
                    }
                    filteredPoints.push(currentPos);
                    lastP = p;
                });

                // Gambar Polyline
                pathLine = L.polyline(filteredPoints, { color: '#3b82f6', weight: 6, opacity: 0.8, lineJoin: 'round' }).addTo(map);
                
                // Marker Awal & Akhir
                markers.push(L.circleMarker(filteredPoints[0], { radius: 7, fillColor: "#22c55e", color: "#fff", weight: 3, fillOpacity: 1 }).addTo(map).bindPopup("Titik Awal"));
                markers.push(L.circleMarker(filteredPoints[filteredPoints.length-1], { radius: 7, fillColor: "#ef4444", color: "#fff", weight: 3, fillOpacity: 1 }).addTo(map).bindPopup("Titik Akhir"));

                // Parking Render
                parkingEvents.forEach((evt, i) => {
                    const rowId = `parking-row-${i}`;
                    const startTime = new Date(evt.startTime.replace(' ', 'T') + 'Z').toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
                    const duration = Math.floor(evt.duration/60000) + ' mnt';
                    const gmaps = `https://www.google.com/maps?q=${evt.lat},${evt.lng}`;

                    // Tabel
                    parkingTable.innerHTML += `
                        <tr id="${rowId}" onclick="focusParking(${evt.lat}, ${evt.lng}, '${rowId}')" class="cursor-pointer hover:bg-slate-50 transition-all border-l-4 border-transparent">
                            <td class="px-4 py-3 text-[10px] font-bold text-slate-700">${startTime}</td>
                            <td class="px-4 py-3 text-[10px] font-black text-amber-500 uppercase">${duration}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="${gmaps}" target="_blank" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                                    <i class="fa-solid fa-location-arrow text-[10px]"></i>
                                </a>
                            </td>
                        </tr>
                    `;

                    // Marker
                    const pMarker = L.marker([evt.lat, evt.lng], {
                        icon: L.divIcon({ className: 'parking-marker', html: 'P', iconSize: [18, 18], iconAnchor: [9, 9] })
                    }).addTo(map);

                    pMarker.bindPopup(`
                        <div class="p-1">
                            <p class="text-[10px] font-black text-amber-600 uppercase mb-1">Parkir #${i+1}</p>
                            <p class="text-[11px] font-bold">Mulai: ${startTime}</p>
                            <p class="text-[11px] font-bold">Durasi: ${duration}</p>
                            <a href="${gmaps}" target="_blank" class="block mt-2 text-center bg-blue-600 text-white text-[9px] font-black py-1.5 rounded uppercase">Google Maps</a>
                        </div>
                    `);

                    pMarker.on('click', () => highlightAndScrollToRow(rowId));
                    parkingMarkers.push(pMarker);
                });

                map.fitBounds(pathLine.getBounds(), { padding: [40, 40] });
                document.getElementById('stat-points').innerText = data.length;
                document.getElementById('stat-parking').innerText = parkingEvents.length;
                document.getElementById('stat-dist').innerText = (totalDist / 1000).toFixed(2);

            } catch (error) {
                console.error("History Error:", error);
                alert("Terjadi kesalahan saat memuat data.");
            }
        }

        function focusParking(lat, lng, rowId) {
            map.flyTo([lat, lng], 17);
            highlightAndScrollToRow(rowId);
        }

        function highlightAndScrollToRow(rowId) {
            document.querySelectorAll('#parking-list tr').forEach(tr => tr.classList.remove('highlight-row'));
            const row = document.getElementById(rowId);
            if (row) {
                row.classList.add('highlight-row');
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Init
        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date-single').value = today;
            document.getElementById('date-start').value = today;
            document.getElementById('date-end').value = today;
            loadHistory('range=today');
        });
    </script>
</body>
</html>