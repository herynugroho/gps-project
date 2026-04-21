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
        
        .parking-marker { background: #f59e0b; color: white; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-size: 10px; cursor: pointer; transition: all 0.2s; }
        .parking-marker:hover { transform: scale(1.3); z-index: 1000 !important; background: #d97706; }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .highlight-row { background-color: #fffbeb !important; border-left: 4px solid #f59e0b !important; transition: all 0.3s ease; }
        
        /* Layout Desktop Split View */
        @media (min-width: 1024px) {
            .main-wrapper { flex-direction: row !important; }
            .side-panel { width: 480px !important; height: 100% !important; max-height: none !important; border-top: none !important; border-right: 1px solid #e2e8f0; order: -1; position: relative !important; }
            .mobile-only { display: none !important; }
        }

        @media print {
            .no-print { display: none !important; }
            #map { height: 400px !important; width: 100% !important; flex: none !important; }
            body { overflow: visible !important; height: auto !important; }
            .print-only { display: block !important; }
            .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .report-table th, .report-table td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 11px; }
            .report-header { text-align: center; margin-bottom: 20px; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="bg-slate-50">

    <!-- Header Laporan (Cetak) -->
    <div class="print-only report-header">
        <h1 style="font-size: 24px; font-weight: 900; color: #0f172a; text-transform: uppercase;">LAPORAN PERSINGGAHAN ARMADA</h1>
        <p style="font-size: 14px; color: #64748b;">Unit: {{ $device->name }} ({{ $device->plate_number }}) | Periode: <span id="print-date">-</span></p>
        <hr style="margin: 20px 0; border: 1px solid #e2e8f0;">
    </div>

    <div class="main-wrapper flex flex-col flex-1 overflow-hidden">
        
        <!-- SIDEBAR / CONTROL PANEL -->
        <aside id="side-panel" class="side-panel bg-white shadow-2xl z-20 flex flex-col overflow-hidden">
            
            <div class="p-6 bg-slate-900 text-white shrink-0 no-print">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <a href="/" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-800 border border-slate-700 hover:bg-slate-700 transition">
                            <i class="fa-solid fa-chevron-left text-sm"></i>
                        </a>
                        <div>
                            <h1 class="font-black text-sm uppercase leading-none tracking-tight">{{ $device->name }}</h1>
                            <p class="text-[10px] text-blue-400 font-bold mt-1 uppercase tracking-widest">{{ $device->plate_number }}</p>
                        </div>
                    </div>
                    <button onclick="window.print()" class="text-[9px] font-black uppercase bg-slate-800 px-3 py-2 rounded-lg border border-slate-700">
                        <i class="fa-solid fa-print mr-1"></i> Cetak Laporan
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Filter Waktu</label>
                        <select id="mode-selector" onchange="toggleInputs()" class="w-full bg-slate-800 text-[11px] font-black uppercase px-4 py-3.5 rounded-xl border border-slate-700 outline-none">
                            <option value="today">Hari Ini</option>
                            <option value="single">Tanggal Spesifik</option>
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
                        <button onclick="updateHistory()" id="btn-update" class="w-full bg-blue-600 hover:bg-blue-700 py-4 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg">
                            Tampilkan Riwayat
                        </button>
                    </div>
                </div>
            </div>

            <div class="sticky top-0 bg-white border-b border-slate-100 p-5 z-30 no-print shadow-sm">
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100 text-center">
                        <p class="text-[8px] text-slate-400 font-black uppercase mb-1 tracking-widest">Sinyal</p>
                        <p class="font-black text-slate-800 text-sm leading-none" id="stat-points">0</p>
                    </div>
                    <div class="bg-amber-50 p-3.5 rounded-2xl border border-amber-100 text-center">
                        <p class="text-[8px] text-amber-500 font-black uppercase mb-1 tracking-widest">Parkir</p>
                        <p class="font-black text-amber-600 text-sm leading-none" id="stat-parking">0</p>
                    </div>
                    <div class="bg-blue-50 p-3.5 rounded-2xl border border-blue-100 text-center">
                        <p class="text-[8px] text-blue-500 font-black uppercase mb-1 tracking-widest">Jarak</p>
                        <p class="font-black text-blue-600 text-sm leading-none"><span id="stat-dist">0</span> <small class="text-[8px]">km</small></p>
                    </div>
                </div>
            </div>

            <div class="p-5 flex-1 overflow-y-auto no-scrollbar bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Detail Persinggahan</h3>
                </div>

                <div id="detail-list" class="overflow-hidden rounded-2xl border border-slate-100 shadow-sm">
                    <table class="min-w-full divide-y divide-slate-100 report-table">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3.5 text-left text-[9px] font-black text-slate-400 uppercase">Mulai (WITA)</th>
                                <th class="px-4 py-3.5 text-left text-[9px] font-black text-slate-400 uppercase">Durasi</th>
                                <th class="px-4 py-3.5 text-left text-[9px] font-black text-slate-400 uppercase">Koordinat</th>
                                <th class="px-4 py-3.5 text-right text-[9px] font-black text-slate-400 uppercase no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="parking-list" class="bg-white divide-y divide-slate-50">
                            <!-- JS Content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>

        <div class="flex-1 relative">
            <div id="map"></div>
        </div>

    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var pathLine = null;
        var markers = [];
        var parkingMarkers = [];

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
            
            if (mode === 'today') { params = "range=today"; displayDate = "Hari Ini"; } 
            else if (mode === 'single') {
                const date = document.getElementById('date-single').value;
                if(!date) return alert("Pilih tanggal!");
                params = `date=${date}`; displayDate = date;
            } else if (mode === 'range') {
                const start = document.getElementById('date-start').value;
                const end = document.getElementById('date-end').value;
                if(!start || !end) return alert("Pilih rentang lengkap!");
                params = `start=${start}&end=${end}`; displayDate = `${start} s/d ${end}`;
            }

            params += `&_t=${Date.now()}`;
            btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i>';
            btn.disabled = true;

            loadHistory(params, displayDate).finally(() => {
                btn.innerHTML = 'Tampilkan Riwayat';
                btn.disabled = false;
            });
        }

        async function loadHistory(queryString, displayDate = "Hari Ini") {
            if (pathLine) map.removeLayer(pathLine);
            markers.forEach(m => map.removeLayer(m));
            parkingMarkers.forEach(m => map.removeLayer(m));
            markers = []; parkingMarkers = [];
            
            document.getElementById('print-date').innerText = displayDate;

            try {
                const url = `/api/history/{{ $device->imei }}?${queryString}`;
                const response = await fetch(url);
                const data = await response.json();
                
                const parkingTable = document.getElementById('parking-list');
                parkingTable.innerHTML = '';
                
                if (!data || data.length === 0) {
                    parkingTable.innerHTML = '<tr><td colspan="4" class="p-12 text-center text-slate-300 text-xs italic">Data tidak ditemukan.</td></tr>';
                    return;
                }

                let points = [];
                let pEvents = [];
                let lastP = null;
                let totalD = 0;

                data.forEach((p) => {
                    const pos = [parseFloat(p.latitude), parseFloat(p.longitude)];
                    if (lastP) {
                        const timeDiff = new Date(p.gps_time.replace(' ', 'T') + 'Z') - new Date(lastP.gps_time.replace(' ', 'T') + 'Z');
                        if (timeDiff > 300000) { 
                            pEvents.push({ lat: lastP.latitude, lng: lastP.longitude, start: lastP.gps_time, dur: timeDiff });
                        }
                        totalD += L.latLng([lastP.latitude, lastP.longitude]).distanceTo(pos);
                    }
                    points.push(pos);
                    lastP = p;
                });

                pathLine = L.polyline(points, { color: '#3b82f6', weight: 6, opacity: 0.8 }).addTo(map);

                pEvents.forEach((evt, i) => {
                    const rowId = `row-${i}`;
                    const timeLabel = new Date(evt.start.replace(' ', 'T') + 'Z').toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
                    const durLabel = Math.floor(evt.dur/60000) + ' mnt';
                    const latLngLabel = `${parseFloat(evt.lat).toFixed(5)}, ${parseFloat(evt.lng).toFixed(5)}`;
                    const gUrl = `https://www.google.com/maps?q=${evt.lat},${evt.lng}`;

                    parkingTable.innerHTML += `
                        <tr id="${rowId}" onclick="focusLocation(${evt.lat}, ${evt.lng}, '${rowId}')" class="cursor-pointer hover:bg-slate-50 transition border-l-4 border-transparent group">
                            <td class="px-4 py-4 text-[11px] font-bold text-slate-700">${timeLabel}</td>
                            <td class="px-4 py-4 text-[10px] font-black text-amber-500 uppercase">${durLabel}</td>
                            <td class="px-4 py-4 text-[10px] font-mono text-slate-400">${latLngLabel}</td>
                            <td class="px-4 py-4 text-right no-print">
                                <a href="${gUrl}" target="_blank" class="w-8 h-8 inline-flex items-center justify-center rounded-xl bg-blue-50 text-blue-500 group-hover:bg-blue-600 group-hover:text-white transition-all">
                                    <i class="fa-solid fa-location-arrow text-[10px]"></i>
                                </a>
                            </td>
                        </tr>
                    `;

                    const m = L.marker([evt.lat, evt.lng], {
                        icon: L.divIcon({ className: 'parking-marker', html: 'P', iconSize: [22, 22], iconAnchor: [11, 11] })
                    }).addTo(map);

                    m.bindPopup(`
                        <div class="p-2 min-w-[140px]">
                            <p class="text-[10px] font-black text-amber-600 uppercase mb-1">PARKIR #${i+1}</p>
                            <p class="text-[12px] font-bold">Mulai: ${timeLabel}</p>
                            <p class="text-[12px] font-bold">Durasi: ${durLabel}</p>
                            <p class="text-[10px] text-slate-400 mt-1">${latLngLabel}</p>
                            <hr class="my-2 border-slate-100">
                            <a href="${gUrl}" target="_blank" class="block w-full text-center bg-blue-600 text-white text-[10px] font-black py-2 rounded-xl uppercase">Google Maps</a>
                        </div>
                    `);
                    m.on('click', () => highlightRow(rowId));
                    parkingMarkers.push(m);
                });

                map.fitBounds(pathLine.getBounds(), { padding: [50, 50] });
                document.getElementById('stat-points').innerText = data.length.toLocaleString();
                document.getElementById('stat-parking').innerText = pEvents.length;
                document.getElementById('stat-dist').innerText = (totalD / 1000).toFixed(2);

            } catch (err) { console.error(err); }
        }

        function focusLocation(lat, lng, rowId) { map.flyTo([lat, lng], 17, { duration: 1.5 }); highlightRow(rowId); }
        function highlightRow(rowId) {
            document.querySelectorAll('#parking-list tr').forEach(tr => tr.classList.remove('highlight-row'));
            const row = document.getElementById(rowId);
            if (row) { row.classList.add('highlight-row'); row.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        }

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