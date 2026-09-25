<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Laporan Riwayat: {{ $device->name }}</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; height: 100dvh; margin: 0; display: flex; flex-direction: column; overflow: hidden; }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .highlight-row { background-color: #fffbeb !important; border-left: 4px solid #f59e0b !important; transition: all 0.3s ease; }
        
        /* Layout Desktop Split View */
        @media (min-width: 1024px) {
            .main-wrapper { flex-direction: row !important; }
            .side-panel { width: 480px !important; height: 100% !important; max-height: none !important; border-top: none !important; border-right: 1px solid #e2e8f0; order: -1; position: relative !important; }
            .mobile-only { display: none !important; }
        }

        /* --- PERBAIKAN MODE CETAK (PRINT) --- */
        @media print {
            .no-print { display: none !important; }
            
            #map-container { position: static !important; height: 350px !important; width: 100% !important; flex: none !important; margin-bottom: 20px; border: 2px solid #e2e8f0; border-radius: 8px;}
            body, html { overflow: visible !important; height: auto !important; }
            
            .main-wrapper, .side-panel { 
                height: auto !important; 
                overflow: visible !important; 
                display: block !important; 
                width: 100% !important; 
                position: static !important;
                box-shadow: none !important;
                border: none !important;
            }

            #bottom-sheet, #detail-list-container, #detail-list {
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                position: static !important;
                box-shadow: none !important;
                border: none !important;
            }

            .print-only { display: block !important; }
            
            .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; page-break-inside: auto; }
            .report-table tr { page-break-inside: avoid; page-break-after: auto; }
            .report-table thead { display: table-header-group; }
            .report-table th, .report-table td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; font-size: 11px; color: #0f172a;}
            .report-header { text-align: center; margin-bottom: 20px; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="bg-slate-50 relative">

    <div class="print-only report-header">
        <h1 style="font-size: 24px; font-weight: 900; color: #0f172a; text-transform: uppercase;">LAPORAN PERSINGGAHAN ARMADA</h1>
        <p style="font-size: 14px; color: #64748b;">Unit: {{ $device->name }} ({{ $device->plate_number }}) | Periode: <span id="print-date">-</span></p>
        <hr style="margin: 20px 0; border: 1px solid #e2e8f0;">
    </div>

    <div class="flex flex-col lg:flex-row h-full w-full overflow-hidden relative main-wrapper">

        <div id="map-container" class="absolute inset-0 lg:relative lg:flex-1 z-0">
            <div id="map" class="w-full h-full"></div>

            <!-- FLOATING MAP CONTROLS -->
            <div class="absolute right-3 top-24 lg:bottom-8 lg:top-auto z-[500] flex flex-col gap-2">
                <button onclick="map.zoomIn()" title="Perbesar" class="w-10 h-10 md:w-11 md:h-11 bg-white/95 backdrop-blur-md text-slate-800 rounded-xl shadow-lg border border-slate-200/60 flex items-center justify-center font-bold active:scale-95 transition hover:bg-slate-50">
                    <i class="fa-solid fa-plus text-xs md:text-sm"></i>
                </button>
                <button onclick="map.zoomOut()" title="Perkecil" class="w-10 h-10 md:w-11 md:h-11 bg-white/95 backdrop-blur-md text-slate-800 rounded-xl shadow-lg border border-slate-200/60 flex items-center justify-center font-bold active:scale-95 transition hover:bg-slate-50">
                    <i class="fa-solid fa-minus text-xs md:text-sm"></i>
                </button>
                <button onclick="fitRoute()" title="Fokus Keseluruhan Rute" class="w-10 h-10 md:w-11 md:h-11 bg-white/95 backdrop-blur-md text-blue-600 rounded-xl shadow-lg border border-slate-200/60 flex items-center justify-center font-bold active:scale-95 transition hover:bg-slate-50">
                    <i class="fa-solid fa-expand text-xs md:text-sm"></i>
                </button>
            </div>
        </div>
        
        <aside class="flex flex-col w-full lg:w-[480px] z-20 shrink-0 lg:h-full lg:shadow-2xl pointer-events-none lg:pointer-events-auto bg-transparent lg:bg-white order-1 side-panel">
            
            <div class="p-4 lg:p-6 bg-slate-900 text-white shrink-0 pointer-events-auto shadow-lg lg:shadow-none z-30 no-print">
                <div class="flex items-center justify-between mb-2 lg:mb-6">
                    <div class="flex items-center gap-3">
                        <a href="/" class="w-9 h-9 md:w-10 md:h-10 flex items-center justify-center rounded-xl bg-slate-800 border border-slate-700 hover:bg-slate-700 transition active:scale-95">
                            <i class="fa-solid fa-chevron-left text-xs md:text-sm"></i>
                        </a>
                        <div>
                            <h1 class="font-black text-xs md:text-sm uppercase leading-none tracking-tight">{{ $device->name }}</h1>
                            <p class="text-[9px] md:text-[10px] text-blue-400 font-bold mt-1 uppercase tracking-widest font-mono">{{ $device->plate_number }}</p>
                        </div>
                    </div>
                    <button onclick="window.print()" class="text-[9px] font-black uppercase bg-slate-800 px-3 py-2 rounded-lg border border-slate-700 hover:bg-slate-700 transition active:scale-95">
                        <i class="fa-solid fa-print mr-1"></i> Cetak
                    </button>
                </div>

                <div class="space-y-3 hidden lg:block mt-4 lg:mt-0" id="filter-box">
                    <select id="mode-selector" onchange="toggleInputs()" class="w-full bg-slate-800 text-[11px] font-black uppercase px-4 py-3 rounded-xl border border-slate-700 outline-none">
                        <option value="today">Hari Ini</option>
                        <option value="single">Tanggal Spesifik</option>
                        <option value="range">Rentang Tanggal</option>
                    </select>

                    <div id="input-single" class="hidden">
                        <input type="date" id="date-single" class="w-full bg-slate-800 border border-slate-700 rounded-xl py-2.5 px-4 text-xs font-bold text-blue-400">
                    </div>
                    <div id="input-range" class="hidden grid grid-cols-2 gap-2">
                        <input type="date" id="date-start" class="bg-slate-800 border border-slate-700 rounded-xl py-2.5 px-4 text-xs font-bold text-blue-400">
                        <input type="date" id="date-end" class="bg-slate-800 border border-slate-700 rounded-xl py-2.5 px-4 text-xs font-bold text-blue-400">
                    </div>
                    <button onclick="updateHistory()" id="btn-update" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg">
                        Tampilkan Riwayat
                    </button>
                </div>
                
                <button onclick="document.getElementById('filter-box').classList.toggle('hidden')" class="lg:hidden w-full mt-2 text-[10px] text-slate-300 bg-slate-800 hover:bg-slate-700 font-bold uppercase flex justify-center items-center gap-2 border border-slate-700 py-2 rounded-xl transition active:scale-95">
                    <i class="fa-solid fa-filter text-blue-400"></i> Ubah Periode Tanggal
                </button>
            </div>

            <div class="flex-1 lg:hidden no-print"></div>

            <div class="bg-white pointer-events-auto rounded-t-3xl lg:rounded-none shadow-[0_-15px_30px_rgba(0,0,0,0.15)] lg:shadow-none flex flex-col z-30 transition-all duration-300 h-[135px] lg:h-full lg:flex-1 lg:min-h-0" id="bottom-sheet">
                
                <div class="w-full flex flex-col items-center pt-2.5 pb-1 lg:hidden cursor-pointer no-print select-none active:opacity-75" onclick="toggleSheet()">
                    <div class="w-12 h-1.5 bg-slate-200 hover:bg-slate-300 rounded-full mb-1 transition"></div>
                    <div class="text-[9px] font-black uppercase text-slate-400 tracking-wider flex items-center gap-1.5">
                        <span id="sheet-toggle-text">Lihat Detail Singgah</span>
                        <i id="sheet-toggle-icon" class="fa-solid fa-chevron-up text-[8px] transition-transform"></i>
                    </div>
                </div>

                <div class="px-4 pb-3 pt-1 lg:p-5 border-b border-slate-100 shrink-0">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 lg:gap-3">
                        <div class="bg-slate-50 p-2 lg:p-3 rounded-2xl border border-slate-100 text-center flex flex-col justify-center">
                            <p class="text-[8px] text-slate-400 font-black uppercase mb-1 tracking-widest">Sinyal</p>
                            <p class="font-black text-slate-800 text-sm leading-none" id="stat-points">0</p>
                        </div>
                        <div class="bg-amber-50 p-2 lg:p-3 rounded-2xl border border-amber-100 text-center flex flex-col justify-center">
                            <p class="text-[8px] text-amber-500 font-black uppercase mb-1 tracking-widest">Parkir</p>
                            <p class="font-black text-amber-600 text-sm leading-none" id="stat-parking">0</p>
                        </div>
                        <div class="bg-blue-50 p-2 lg:p-3 rounded-2xl border border-blue-100 text-center flex flex-col justify-center">
                            <p class="text-[8px] text-blue-500 font-black uppercase mb-1 tracking-widest">Jarak</p>
                            <p class="font-black text-blue-600 text-sm leading-none"><span id="stat-dist">0</span> <small class="text-[8px]">km</small></p>
                        </div>
                        <div class="bg-emerald-50 p-2 lg:p-3 rounded-2xl border border-emerald-100 text-center flex flex-col justify-center">
                            <p class="text-[8px] text-emerald-500 font-black uppercase mb-1 tracking-widest">Est. BBM</p>
                            <p class="font-black text-emerald-600 text-sm leading-none mb-1"><span id="stat-fuel-liters">0</span> <small class="text-[8px]">L</small></p>
                            <p class="text-[8px] font-bold text-emerald-700 bg-emerald-100/50 rounded-md py-0.5" id="stat-fuel-cost">Rp 0</p>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 lg:p-5 no-scrollbar bg-white lg:min-h-0" id="detail-list-container">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 no-print">Detail Persinggahan</h3>
                    <div class="overflow-hidden rounded-2xl border border-slate-100 shadow-sm" id="detail-list">
                        <table class="min-w-full divide-y divide-slate-100 report-table">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-3 text-center text-[9px] font-black text-slate-400 uppercase" width="6%">No</th>
                                    <th class="px-3 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Mulai</th>
                                    <th class="px-3 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Durasi</th>
                                    <th class="px-3 py-3 text-left text-[9px] font-black text-slate-400 uppercase">Koordinat</th>
                                    <th class="px-3 py-3 text-right text-[9px] font-black text-slate-400 uppercase no-print">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="parking-list" class="bg-white divide-y divide-slate-50">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </aside>

    </div>

    <script>
        const FUEL_RATIO = {{ $device->fuel_ratio ?? 10.0 }}; 
        const FUEL_PRICE_PER_LITER = 10000; 

        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        // PERBAIKAN: Mengubah struktur single path menjadi array penampung multi-line
        var pathLines = [];
        var markers = [];
        var parkingMarkers = [];
        var isSheetCollapsed = true;
        var points = [];

        function toggleInputs() {
            const mode = document.getElementById('mode-selector').value;
            document.getElementById('input-single').classList.toggle('hidden', mode !== 'single');
            document.getElementById('input-range').classList.toggle('hidden', mode !== 'range');
        }

        function toggleSheet() {
            const sheet = document.getElementById('bottom-sheet');
            const toggleText = document.getElementById('sheet-toggle-text');
            const toggleIcon = document.getElementById('sheet-toggle-icon');
            
            isSheetCollapsed = !isSheetCollapsed;
            if (isSheetCollapsed) {
                sheet.style.height = "135px";
                if (toggleText) toggleText.innerText = "Lihat Detail Singgah";
                if (toggleIcon) toggleIcon.classList.remove('rotate-180');
            } else {
                sheet.style.height = "65vh";
                if (toggleText) toggleText.innerText = "Tutup Detail";
                if (toggleIcon) toggleIcon.classList.add('rotate-180');
            }
        }

        function fitRoute() {
            if (points && points.length > 0) {
                map.fitBounds(L.polyline(points).getBounds(), { padding: [50, 50] });
            }
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

            if(window.innerWidth < 1024) document.getElementById('filter-box').classList.add('hidden');

            params += `&_t=${Date.now()}`;
            btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i>';
            btn.disabled = true;

            loadHistory(params, displayDate, mode).finally(() => {
                btn.innerHTML = 'Tampilkan Riwayat';
                btn.disabled = false;
            });
        }

        async function loadHistory(queryString, displayDate = "Hari Ini", mode = "today") {
            // PERBAIKAN: Hapus semua potongan garis rute lama di peta
            if (pathLines.length > 0) {
                pathLines.forEach(line => map.removeLayer(line));
            }
            pathLines = [];

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
                
                if (!Array.isArray(data) || data.length === 0) {
                    parkingTable.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-300 text-xs italic">Data perjalanan tidak ditemukan untuk periode ini.</td></tr>';
                    document.getElementById('stat-points').innerText = '0';
                    document.getElementById('stat-parking').innerText = '0';
                    document.getElementById('stat-dist').innerText = '0';
                    document.getElementById('stat-fuel-liters').innerText = '0';
                    document.getElementById('stat-fuel-cost').innerText = 'Rp 0';
                    return;
                }

                // PERBAIKAN: Palette Warna Rute Unik untuk Membedakan Jalur Antar Titik Singgah
                const routeColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#14b8a6'];
                
                points = [];
                let pEvents = [];
                let lastP = null;
                let totalD = 0; 
                
                let currentSegmentPoints = [];
                let segmentIndex = 0;

                data.forEach((p) => {
                    const lat = parseFloat(p.latitude);
                    const lng = parseFloat(p.longitude);
                    if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                        return;
                    }
                    const pos = [lat, lng];
                    points.push(pos);
                    currentSegmentPoints.push(pos);

                    if (lastP) {
                        const t1 = new Date(p.gps_time.replace(' ', 'T')).getTime();
                        const t2 = new Date(lastP.gps_time.replace(' ', 'T')).getTime();
                        const timeDiff = t1 - t2;
                        const pointDist = L.latLng([lastP.latitude, lastP.longitude]).distanceTo(pos);

                        if (timeDiff > 300000) { 
                            // Filter blank spot: jika pergeseran > 500m dan kendaraan melaju > 15 km/h, bukan parkir
                            const isMovingLoss = (pointDist > 500 && ((lastP.speed && lastP.speed > 15) || (p.speed && p.speed > 15)));

                            if (!isMovingLoss) {
                                const lastEvent = pEvents.length > 0 ? pEvents[pEvents.length - 1] : null;
                                // Smart Merge: jika lokasi berdekatan (< 100m) dengan parkir sebelumnya, perpanjang sesi
                                if (lastEvent && L.latLng([lastEvent.lat, lastEvent.lng]).distanceTo([lastP.latitude, lastP.longitude]) < 100) {
                                    lastEvent.dur = t1 - new Date(lastEvent.start.replace(' ', 'T')).getTime();
                                } else {
                                    pEvents.push({ 
                                        lat: lastP.latitude, 
                                        lng: lastP.longitude, 
                                        start: lastP.gps_time, 
                                        dur: timeDiff 
                                    });
                                    
                                    // PERBAIKAN: Gambar jalur segmen ini sebelum beralih ke segmen pasca parkir berikutnya
                                    if (currentSegmentPoints.length > 1) {
                                        let poly = L.polyline(currentSegmentPoints, { 
                                            color: routeColors[segmentIndex % routeColors.length], 
                                            weight: 5, 
                                            opacity: 0.85 
                                        }).addTo(map);
                                        pathLines.push(poly);
                                    }

                                    // Reset koordinat segmen baru dimulai dari lokasi parkir ini
                                    currentSegmentPoints = [pos];
                                    segmentIndex++;
                                }
                            }
                        }

                        // Filter anomali lonjakan teleportasi (> 50 km antara 2 titik)
                        if (pointDist < 50000) {
                            // Filter GPS Drift saat berhenti: hanya hitung jika kecepatan > 2 km/h atau pergeseran > 10m
                            if ((p.speed && p.speed > 2) || pointDist > 10) {
                                totalD += pointDist;
                            }
                        }
                    }
                    lastP = p;
                });

                // Gambar potongan jalur segmen yang paling terakhir
                if (currentSegmentPoints.length > 1) {
                    let poly = L.polyline(currentSegmentPoints, { 
                        color: routeColors[segmentIndex % routeColors.length], 
                        weight: 5, 
                        opacity: 0.85 
                    }).addTo(map);
                    pathLines.push(poly);
                }

                // Pembuat isi tabel detail persinggahan (Batch rendering untuk performa cepat)
                let lastDateLabel = '';
                let tableHtml = '';

                pEvents.forEach((evt, i) => {
                    const rowId = `row-${i}`;
                    const dateLabel = evt.start.substring(0, 10);
                    const timeLabel = evt.start.substring(11, 16);
                    const durLabel = Math.floor(evt.dur/60000) + ' mnt';
                    const latLngLabel = `${parseFloat(evt.lat).toFixed(5)}, <br>${parseFloat(evt.lng).toFixed(5)}`;
                    const gUrl = `https://www.google.com/maps/search/?api=1&query=${evt.lat},${evt.lng}`;
                    
                    let currentTrackColor = routeColors[i % routeColors.length];

                    if (dateLabel !== lastDateLabel) {
                        tableHtml += `
                            <tr class="bg-slate-100 font-bold no-print">
                                <td colspan="5" class="px-3 py-2 text-[10px] text-slate-500 bg-slate-100 font-black text-center tracking-wider">
                                    <i class="fa-solid fa-calendar-days mr-1"></i> TANGGAL: ${dateLabel}
                                </td>
                            </tr>
                        `;
                        lastDateLabel = dateLabel;
                    }

                    tableHtml += `
                        <tr id="${rowId}" onclick="focusLocation(${evt.lat}, ${evt.lng}, '${rowId}')" class="cursor-pointer hover:bg-slate-50 transition border-l-4 border-transparent group">
                            <td class="px-3 py-4 text-[11px] font-bold text-slate-400 text-center">${i + 1}</td>
                            <td class="px-3 py-4 text-[11px] font-bold text-slate-700">
                                <span class="inline-block w-2 h-2 rounded-full me-1" style="background-color: ${currentTrackColor}"></span>
                                ${timeLabel}
                            </td>
                            <td class="px-3 py-4 text-[10px] font-black text-amber-500 uppercase">${durLabel}</td>
                            <td class="px-3 py-4 text-[9px] font-mono text-slate-400">${latLngLabel}</td>
                            <td class="px-3 py-4 text-right no-print">
                                <a href="${gUrl}" target="_blank" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-blue-50 text-blue-500 hover:bg-blue-600 hover:text-white transition-all">
                                    <i class="fa-solid fa-location-arrow text-[10px]"></i>
                                </a>
                            </td>
                        </tr>
                    `;

                    // PERBAIKAN: Mengganti Huruf 'P' Menjadi Nomor Urut Marker (P1, P2, P3...) Berwarna Sinkron
                    const m = L.marker([evt.lat, evt.lng], {
                        icon: L.divIcon({ 
                            className: 'custom-marker-wrapper', 
                            html: `<div style="background-color: ${currentTrackColor}; color: white; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 10px; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.35);">P${i + 1}</div>`, 
                            iconSize: [26, 26], 
                            iconAnchor: [13, 13] 
                        })
                    }).addTo(map);

                    // Perbaikan teks button tooltip di dalam popup agar berwarna putih kontras
                    m.bindPopup(`
                        <div class="p-2 min-w-[140px]">
                            <p class="text-[10px] font-black uppercase mb-1" style="color: ${currentTrackColor}">AREA PARKIR P${i + 1}</p>
                            <p class="text-[11px] font-bold text-slate-800">Mulai: ${timeLabel} WITA</p>
                            <p class="text-[11px] font-bold text-slate-800">Durasi: ${durLabel}</p>
                            <div class="mt-2 text-center">
                                <a href="${gUrl}" target="_blank" class="inline-block bg-blue-600 px-2 py-1 rounded text-[9px] font-bold text-center shadow-md shadow-blue-200" style="color: white !important; text-decoration: none !important;">
                                    <i class="fa-solid fa-map-location-dot mr-1"></i> LIHAT GOOGLE MAPS
                                </a>
                            </div>
                        </div>
                    `);
                    m.on('click', () => highlightRow(rowId));
                    parkingMarkers.push(m);
                });

                parkingTable.innerHTML = tableHtml;

                if (points.length > 0) {
                    map.fitBounds(L.polyline(points).getBounds(), { padding: [50, 50] });
                }
                
                // --- UPDATE STATISTIK UI ---
                const distKm = totalD / 1000;
                const estLiters = distKm / FUEL_RATIO;
                const estCost = estLiters * FUEL_PRICE_PER_LITER;

                document.getElementById('stat-points').innerText = points.length.toLocaleString();
                document.getElementById('stat-parking').innerText = pEvents.length;
                document.getElementById('stat-dist').innerText = distKm.toFixed(2);
                
                document.getElementById('stat-fuel-liters').innerText = estLiters.toFixed(1);
                document.getElementById('stat-fuel-cost').innerText = 'Rp ' + estCost.toLocaleString('id-ID', {maximumFractionDigits: 0});

            } catch (err) { console.error(err); }
        }

        function focusLocation(lat, lng, rowId) { 
            if(isSheetCollapsed && window.innerWidth < 1024) toggleSheet();
            map.flyTo([lat, lng], 17, { duration: 1.5 }); 
            highlightRow(rowId); 
        }
        
        function highlightRow(rowId) {
            document.querySelectorAll('#parking-list tr').forEach(tr => tr.classList.remove('highlight-row'));
            const row = document.getElementById(rowId);
            if (row) { 
                row.classList.add('highlight-row'); 
                requestAnimationFrame(() => {
                    const container = document.getElementById('detail-list-container');
                    const rowTop = row.offsetTop;
                    container.scrollTo({ top: rowTop - 60, behavior: 'smooth' });
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const d = new Date();
            const today = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, '0') + "-" + String(d.getDate()).padStart(2, '0');
            document.getElementById('date-single').value = today;
            document.getElementById('date-start').value = today;
            document.getElementById('date-end').value = today;
            loadHistory('range=today', 'Hari Ini', 'today');
        });
    </script>
</body>
</html>