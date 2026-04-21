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
        .parking-marker { background: #f59e0b; color: white; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-size: 10px; cursor: pointer; }
        
        .sticky-stats { position: sticky; top: 0; background: white; z-index: 30; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        
        .highlight-row { background-color: #fef3c7 !important; transition: background-color 0.5s ease; }
        
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

    <!-- Header Laporan (Cetak) -->
    <div class="print-only report-header">
        <h1 style="font-size: 24px; font-weight: 900; color: #0f172a;">LAPORAN RIWAYAT PERJALANAN PRIMATRACK</h1>
        <p style="font-size: 14px; color: #64748b;">Unit: {{ $device->name }} | Periode: <span id="print-date">-</span></p>
        <hr style="margin: 20px 0; border: 1px solid #e2e8f0;">
    </div>

    <!-- Navigasi & Filter -->
    <nav class="bg-slate-900 text-white p-4 shadow-xl z-20 shrink-0 no-print">
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-3">
                <a href="/" class="w-9 h-9 flex items-center justify-center rounded-xl bg-slate-800 border border-slate-700">
                    <i class="fa-solid fa-chevron-left text-sm"></i>
                </a>
                <div>
                    <h1 class="font-black text-xs uppercase leading-none tracking-tight">{{ $device->name }}</h1>
                    <p class="text-[9px] text-blue-400 font-bold mt-1 uppercase tracking-tighter">{{ $device->plate_number }}</p>
                </div>
            </div>
            <button onclick="window.print()" class="bg-slate-800 text-white px-3 py-2 rounded-xl text-[9px] font-black uppercase border border-slate-700">
                <i class="fa-solid fa-print mr-1"></i> Cetak
            </button>
        </div>

        <!-- Mode Selection -->
        <div class="space-y-3">
            <div class="flex gap-2 bg-slate-800 p-1 rounded-2xl border border-slate-700/50">
                <select id="mode-selector" onchange="toggleInputs()" class="bg-transparent text-[10px] font-black uppercase tracking-widest flex-1 px-3 py-2 outline-none cursor-pointer">
                    <option value="today" class="bg-slate-900">Hari Ini</option>
                    <option value="single" class="bg-slate-900">Tanggal Tertentu</option>
                    <option value="range" class="bg-slate-900">Rentang Tanggal</option>
                </select>
            </div>

            <!-- Conditional Inputs -->
            <div id="filter-inputs" class="flex flex-col gap-2">
                <div id="input-single" class="hidden">
                    <input type="date" id="date-single" class="w-full bg-slate-800 border border-slate-700 rounded-xl py-2 px-4 text-[10px] font-black uppercase text-blue-400 outline-none">
                </div>
                <div id="input-range" class="hidden grid grid-cols-2 gap-2">
                    <input type="date" id="date-start" class="w-full bg-slate-800 border border-slate-700 rounded-xl py-2 px-4 text-[10px] font-black uppercase text-blue-400 outline-none">
                    <input type="date" id="date-end" class="w-full bg-slate-800 border border-slate-700 rounded-xl py-2 px-4 text-[10px] font-black uppercase text-blue-400 outline-none">
                </div>
                <button onclick="updateHistory()" id="btn-update" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg transition-all active:scale-95">
                    <i class="fa-solid fa-magnifying-glass mr-1"></i> Update Riwayat
                </button>
            </div>
        </div>
    </nav>

    <!-- Peta -->
    <div id="map"></div>

    <!-- Statistik Floating & Detail Collapsible -->
    <div id="scroll-container" class="bg-white border-t border-slate-100 shrink-0 shadow-[0_-15px_35px_rgba(0,0,0,0.1)] z-20 overflow-y-auto max-h-[45vh] no-scrollbar">
        
        <!-- Sticky Stats Header -->
        <div class="sticky-stats p-5 border-b border-slate-50 no-print">
            <div class="flex justify-around items-center">
                <div class="text-center flex-1">
                    <p class="text-[8px] text-slate-400 font-black uppercase mb-1 tracking-widest">Sinyal</p>
                    <p class="font-black text-slate-800 text-sm leading-none" id="stat-points">0</p>
                </div>
                <div class="w-[1px] h-6 bg-slate-100"></div>
                <div class="text-center flex-1">
                    <p class="text-[8px] text-amber-500 font-black uppercase mb-1 tracking-widest">Parkir</p>
                    <p class="font-black text-amber-500 text-sm leading-none" id="stat-parking">0</p>
                </div>
                <div class="w-[1px] h-6 bg-slate-100"></div>
                <div class="text-center flex-1">
                    <p class="text-[8px] text-blue-500 font-black uppercase mb-1 tracking-widest">Jarak</p>
                    <p class="font-black text-blue-500 text-sm leading-none"><span id="stat-dist">0</span> <small class="text-[8px]">km</small></p>
                </div>
                <div class="pl-4">
                    <button onclick="toggleCollapse()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400">
                        <i id="collapse-icon" class="fa-solid fa-chevron-up"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Collapsible Content -->
        <div id="detail-content" class="p-5 pt-0">
            <div class="print-only mb-6">
                <h3 class="text-sm font-bold text-slate-800 uppercase mb-4">Detail Persinggahan Unit</h3>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-100">
                <table class="min-w-full divide-y divide-slate-100 report-table" id="table-report">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Mulai</th>
                            <th class="px-4 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Durasi</th>
                            <th class="px-4 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="parking-list" class="bg-white divide-y divide-slate-50">
                        <!-- Data JS -->
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 text-[9px] text-slate-300 font-medium italic no-print text-center">
                *Riwayat dihitung berdasarkan data interval 5 menit GpsServer V7.7
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
        var parkingMarkers = [];
        var isCollapsed = false;

        function toggleInputs() {
            const mode = document.getElementById('mode-selector').value;
            document.getElementById('input-single').classList.toggle('hidden', mode !== 'single');
            document.getElementById('input-range').classList.toggle('hidden', mode !== 'range');
        }

        function toggleCollapse() {
            isCollapsed = !isCollapsed;
            const content = document.getElementById('detail-content');
            const icon = document.getElementById('collapse-icon');
            content.classList.toggle('hidden', isCollapsed);
            icon.classList.replace(isCollapsed ? 'fa-chevron-up' : 'fa-chevron-down', isCollapsed ? 'fa-chevron-down' : 'fa-chevron-up');
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

            btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin mr-1"></i> Memuat...';
            btn.disabled = true;

            loadHistory(params, displayDate).finally(() => {
                btn.innerHTML = '<i class="fa-solid fa-magnifying-glass mr-1"></i> Update Riwayat';
                btn.disabled = false;
            });
        }

        async function loadHistory(queryString, displayDate = "Hari Ini") {
            // Bersihkan Peta
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
                    parkingTable.innerHTML = '<tr><td colspan="3" class="p-8 text-center text-slate-300 italic text-xs">Data tidak ditemukan pada periode ini.</td></tr>';
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
                        if (timeDiff > 300000) { // Jeda > 5 menit
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

                // Gambar Jalur
                pathLine = L.polyline(filteredPoints, { color: '#3b82f6', weight: 5, opacity: 0.7, lineJoin: 'round' }).addTo(map);
                
                // Marker Start & End
                markers.push(L.circleMarker(filteredPoints[0], { radius: 6, fillColor: "#22c55e", color: "#fff", weight: 2, fillOpacity: 1 }).addTo(map).bindPopup("Titik Awal"));
                markers.push(L.circleMarker(filteredPoints[filteredPoints.length-1], { radius: 6, fillColor: "#ef4444", color: "#fff", weight: 2, fillOpacity: 1 }).addTo(map).bindPopup("Titik Akhir"));

                // Parking Table & Markers
                parkingEvents.forEach((evt, i) => {
                    const rowId = `parking-row-${i}`;
                    const timeLabel = new Date(evt.startTime.replace(' ', 'T') + 'Z').toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
                    const durLabel = Math.floor(evt.duration/60000) + ' mnt';
                    const googleUrl = `https://www.google.com/maps?q=${evt.lat},${evt.lng}`;

                    // Tabel Row
                    parkingTable.innerHTML += `
                        <tr id="${rowId}" class="transition-colors duration-300">
                            <td class="px-4 py-3 text-[10px] font-bold text-slate-700">${timeLabel}</td>
                            <td class="px-4 py-3 text-[10px] font-black text-amber-500 uppercase">${durLabel}</td>
                            <td class="px-4 py-3">
                                <a href="${googleUrl}" target="_blank" class="text-[9px] font-black text-blue-500 flex items-center gap-1 uppercase">
                                    <i class="fa-solid fa-location-dot"></i> Google Maps
                                </a>
                            </td>
                        </tr>
                    `;

                    // Peta Marker
                    const pMarker = L.marker([evt.lat, evt.lng], {
                        icon: L.divIcon({ className: 'parking-marker', html: 'P', iconSize: [18, 18], iconAnchor: [9, 9] })
                    }).addTo(map);

                    // Popup Content
                    const popupHtml = `
                        <div class="p-2 min-w-[120px]">
                            <p class="text-[10px] font-black text-amber-600 uppercase mb-1">Parkir #${i+1}</p>
                            <p class="text-[11px] font-bold text-slate-800">Mulai: ${timeLabel}</p>
                            <p class="text-[11px] font-bold text-slate-800">Durasi: ${durLabel}</p>
                            <hr class="my-2 border-slate-100">
                            <a href="${googleUrl}" target="_blank" class="block w-full text-center bg-blue-600 text-white text-[9px] font-black py-1.5 rounded uppercase tracking-wider">
                                <i class="fa-solid fa-location-arrow mr-1"></i> Google Maps
                            </a>
                        </div>
                    `;
                    pMarker.bindPopup(popupHtml);

                    // Sorot tabel saat marker diklik
                    pMarker.on('click', function() {
                        highlightAndScrollToRow(rowId);
                    });

                    parkingMarkers.push(pMarker);
                });

                map.fitBounds(pathLine.getBounds(), { padding: [30, 30] });
                document.getElementById('stat-points').innerText = data.length;
                document.getElementById('stat-parking').innerText = parkingEvents.length;
                document.getElementById('stat-dist').innerText = (totalDist / 1000).toFixed(2);

            } catch (error) {
                console.error("Error fetching history:", error);
                alert("Gagal mengambil data riwayat perjalanan.");
            }
        }

        function highlightAndScrollToRow(rowId) {
            // Hilangkan sorotan lama
            document.querySelectorAll('#parking-list tr').forEach(tr => tr.classList.remove('highlight-row'));
            
            const row = document.getElementById(rowId);
            if (row) {
                // Tambahkan sorotan baru
                row.classList.add('highlight-row');
                
                // Jika sedang dalam keadaan collapse, buka dulu
                if (isCollapsed) toggleCollapse();
                
                // Scroll ke baris tersebut di dalam kontainer
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Init
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('date-single').valueAsDate = new Date();
            document.getElementById('date-start').valueAsDate = new Date();
            document.getElementById('date-end').valueAsDate = new Date();
            loadHistory('range=today');
        });
    </script>
</body>
</html>