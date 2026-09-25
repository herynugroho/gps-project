<!DOCTYPE html>
<html lang="id">
<head>
    <!-- PWA Setup -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="/icon.png">
    
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>PRIMA TRACK - Monitoring System</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- MarkerCluster Library (Penting untuk handle ratusan armada) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; overflow: hidden; height: 100dvh; margin: 0; }
        #map { height: 100%; width: 100%; z-index: 0; }
        .custom-icon { background: none; border: none; }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .safe-bottom { padding-bottom: max(1.75rem, env(safe-area-inset-bottom)); }
        .safe-top { padding-top: max(0.5rem, env(safe-area-inset-top)); }
        
        .pill-filter { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .pill-filter.active { background-color: #0f172a !important; color: #ffffff !important; border-color: #3b82f6 !important; transform: scale(1.03); box-shadow: 0 4px 12px rgba(15,23,42,0.25); }

        /* Label Styling */
        .marker-label-container { display: flex; flex-direction: column; align-items: center; position: relative; bottom: 30px; transition: all 0.3s; }
        .marker-label { background: white; border: 2px solid #0f172a; border-radius: 6px; padding: 2px 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); white-space: nowrap; font-weight: 800; font-size: 10px; color: #0f172a; text-transform: uppercase; display: flex; align-items: center; gap: 4px; }
        .marker-dot { width: 14px; height: 14px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
        
        /* Sembunyikan label saat zoom out agar tidak menumpuk */
        .leaflet-zoom-animated .marker-label { opacity: 0; transform: scale(0.5); pointer-events: none; }
        .zoom-detailed .marker-label { opacity: 1; transform: scale(1); pointer-events: auto; }

        .status-moving { background-color: #22c55e; }
        .status-acc-on { background-color: #3b82f6; }
        .status-stop { background-color: #ef4444; }
        
        /* Custom Cluster Style PrimaTrack */
        .primal-cluster { background: rgba(11, 17, 32, 0.9); border: 2px solid #3b82f6; border-radius: 50%; color: white; font-weight: 900; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(59, 130, 246, 0.4); font-size: 14px; }
        
        .pulse { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation { 0% { transform: scale(0.9); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }
        #detail-panel { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1001; }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden relative">

    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[1001] opacity-0 pointer-events-none transition-opacity duration-300 md:hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white border-r border-slate-200 flex flex-col z-[1002] transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300 shadow-2xl md:shadow-none">
        
        <!-- Header Sidebar -->
        <div class="p-6 bg-[#0B1120] text-white shrink-0 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <svg viewBox="0 0 512 512" class="w-10 h-10 rounded-xl shadow-[0_0_15px_rgba(59,130,246,0.3)]">
                    <rect width="512" height="512" fill="#0B1120" />
                    <g transform="translate(-19, -29)">
                        <path d="M120 110 h 120 c 71.8 0 130 58.2 130 130 v 0 c 0 71.8 -58.2 130 -130 130 h -50 v 90 c 0 11.05 -8.95 20 -20 20 h -40 c -11.05 0 -20 -8.95 -20 -20 V 130 c 0 -11.05 8.95 -20 20 -20 z M 190 190 v 100 h 50 c 27.6 0 50 -22.4 50 -50 v 0 c 0 -27.6 -22.4 -50 -50 -50 h -50 z" fill="#FFFFFF" />
                        <circle cx="410" cy="440" r="40" fill="#3B82F6" />
                    </g>
                </svg>
                <div>
                    <h1 class="font-black text-sm uppercase tracking-wider leading-none">PRIMA TRACK<span class="text-blue-500">.</span></h1>
                    <p class="text-[9px] text-blue-300 mt-1 uppercase font-bold italic">Enterprise Edition</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden w-8 h-8 rounded-lg bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition border border-slate-700 active:scale-95">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Search Bar -->
        <div class="px-4 pt-4 pb-2 shrink-0">
            <div class="relative">
                <input type="text" id="searchInput" onkeyup="filterUnits()" placeholder="Cari armada atau plat..." class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-4 text-[11px] font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all shadow-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-3.5 text-slate-400"></i>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 pb-4 no-scrollbar" id="unit-list">
             <p class="text-center text-slate-400 text-[10px] py-10 font-bold uppercase tracking-widest">Memuat Armada...</p>
        </div>

        <div class="p-5 border-t border-slate-100 bg-white flex flex-col gap-3 shrink-0">
            <a href="/devices" class="flex items-center justify-center gap-2 w-full py-3.5 bg-slate-900 rounded-xl text-[10px] font-black text-white hover:bg-slate-800 transition uppercase">
                <i class="fa-solid fa-gear text-blue-400"></i> Kelola Armada
            </a>
            <a href="/management/verifikasi" class="flex items-center justify-center gap-2 w-full py-3.5 bg-slate-900 rounded-xl text-[10px] font-black text-white hover:bg-slate-800 transition uppercase">
                <i class="fa-solid fa-gear text-blue-400"></i> Verifikasi Parkir
            </a>
            <form method="POST" action="{{ url('/logout') }}" class="m-0">
                @csrf
                <button type="submit" class="flex items-center justify-center gap-2 w-full py-3.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-[10px] font-black transition uppercase border border-red-100">
                    <i class="fa-solid fa-power-off"></i> Keluar Sistem
                </button>
            </form>
        </div>
    </aside>

    <!-- MAIN MAP -->
    <main class="flex-1 relative flex flex-col h-full">
        
        <!-- MOBILE FLOATING HEADER -->
        <div class="absolute top-2 left-2 right-2 z-[500] md:hidden flex flex-col gap-2 pointer-events-none safe-top">
            <div class="flex items-center justify-between bg-white/95 backdrop-blur-md px-3 py-2 rounded-2xl shadow-xl border border-white/60 pointer-events-auto">
                <button onclick="toggleSidebar()" class="w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center active:scale-95 shadow-md transition">
                    <i class="fa-solid fa-bars-staggered text-sm"></i>
                </button>
                <div class="flex items-center gap-2">
                    <div class="flex flex-col text-right justify-center">
                        <h1 class="font-black text-slate-900 text-xs tracking-tight uppercase leading-none">PRIMA TRACK<span class="text-blue-600">.</span></h1>
                        <span class="text-[8px] text-slate-500 font-bold uppercase tracking-widest mt-0.5" id="mobile-fleet-count">0 Unit</span>
                    </div>
                    <div class="w-8 h-8 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xs font-black shadow-md shadow-blue-500/30">
                        P
                    </div>
                </div>
            </div>

            <!-- Horizontal Filter Chips (Mobile) -->
            <div class="flex gap-1.5 overflow-x-auto no-scrollbar pointer-events-auto px-0.5 py-0.5">
                <button onclick="setFilterStatus('all')" id="pill-all" class="pill-filter active whitespace-nowrap px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-900 text-white shadow-md border border-slate-700">
                    Semua (<span id="count-all">0</span>)
                </button>
                <button onclick="setFilterStatus('moving')" id="pill-moving" class="pill-filter whitespace-nowrap px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/95 backdrop-blur-md text-emerald-700 shadow-md border border-emerald-200 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Jalan (<span id="count-moving">0</span>)
                </button>
                <button onclick="setFilterStatus('acc_on')" id="pill-acc_on" class="pill-filter whitespace-nowrap px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/95 backdrop-blur-md text-blue-700 shadow-md border border-blue-200 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Idle (<span id="count-acc_on">0</span>)
                </button>
                <button onclick="setFilterStatus('stop')" id="pill-stop" class="pill-filter whitespace-nowrap px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/95 backdrop-blur-md text-rose-700 shadow-md border border-rose-200 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> Parkir (<span id="count-stop">0</span>)
                </button>
            </div>
        </div>

        <div id="map"></div>

        <!-- FLOATING MAP CONTROLS -->
        <div class="absolute right-3 bottom-24 md:bottom-8 z-[500] flex flex-col gap-2">
            <button onclick="map.zoomIn()" title="Perbesar" class="w-10 h-10 md:w-11 md:h-11 bg-white/95 backdrop-blur-md text-slate-800 rounded-xl shadow-lg border border-slate-200/60 flex items-center justify-center font-bold active:scale-95 transition hover:bg-slate-50">
                <i class="fa-solid fa-plus text-xs md:text-sm"></i>
            </button>
            <button onclick="map.zoomOut()" title="Perkecil" class="w-10 h-10 md:w-11 md:h-11 bg-white/95 backdrop-blur-md text-slate-800 rounded-xl shadow-lg border border-slate-200/60 flex items-center justify-center font-bold active:scale-95 transition hover:bg-slate-50">
                <i class="fa-solid fa-minus text-xs md:text-sm"></i>
            </button>
            <button onclick="fitAllFleet()" title="Fokus Semua Armada" class="w-10 h-10 md:w-11 md:h-11 bg-white/95 backdrop-blur-md text-blue-600 rounded-xl shadow-lg border border-slate-200/60 flex items-center justify-center font-bold active:scale-95 transition hover:bg-slate-50">
                <i class="fa-solid fa-expand text-xs md:text-sm"></i>
            </button>
        </div>

        <!-- DETAIL PANEL (MOBILE BOTTOM SHEET) -->
        <div id="detail-panel" class="absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-96 bg-white shadow-2xl p-6 md:p-7 transform translate-y-[120%] rounded-t-[2.5rem] md:rounded-3xl border-t md:border border-slate-100 safe-bottom">
            <div onclick="closeDetail()" class="w-14 h-1.5 bg-slate-200 hover:bg-slate-300 rounded-full mx-auto mb-4 md:hidden cursor-pointer active:scale-90 transition"></div>
            
            <div class="flex items-center gap-4 mb-4">
                <div id="det-icon-bg" class="w-14 h-14 bg-green-500 rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl shrink-0">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="font-black text-slate-800 uppercase leading-tight text-lg truncate" id="det-name">-</h2>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] text-slate-500 font-mono font-bold bg-slate-100 px-2 py-0.5 rounded" id="det-plate">-</span>
                        <span id="det-module-badge" class="text-[8px] font-black px-2 py-0.5 rounded-full bg-slate-900 text-white uppercase italic">GT06N 4G</span>
                    </div>
                </div>
                <button onclick="closeDetail()" class="bg-slate-100 w-9 h-9 rounded-full flex items-center justify-center text-slate-400 hover:text-red-500 transition shrink-0 active:scale-90">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100 text-center shadow-sm">
                    <p class="text-[8px] text-slate-400 uppercase font-black mb-1 tracking-widest">Status Mesin</p>
                    <div id="det-acc" class="flex items-center justify-center gap-1.5 font-black text-xs text-blue-600">
                        <i class="fa-solid fa-key"></i> <span>HIDUP</span>
                    </div>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100 text-center shadow-sm">
                    <p class="text-[8px] text-slate-400 uppercase font-black mb-1 tracking-widest">Kecepatan</p>
                    <p class="font-black text-xl text-slate-800 leading-none mt-1"><span id="det-speed">0</span> <small class="text-[9px] text-slate-400 font-normal">km/h</small></p>
                </div>
            </div>

            <div class="border border-blue-100 bg-blue-50/30 rounded-xl p-3 flex justify-between items-center mb-4">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-blue-500 text-xs"></i>
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Update:</span>
                </div>
                <span id="det-time" class="text-[9px] font-black text-slate-700">--:-- WITA</span>
            </div>

            <div class="flex flex-col gap-2">
                <div class="grid grid-cols-2 gap-2">
                    <a id="det-gmaps-link" href="#" target="_blank" class="w-full bg-blue-50 hover:bg-blue-100 text-blue-600 py-3.5 rounded-2xl text-[10px] font-black flex items-center justify-center gap-2 uppercase transition active:scale-95 border border-blue-200">
                        <i class="fa-solid fa-location-arrow text-xs"></i> Rute Map
                    </a>
                    <button onclick="goToHistory()" class="w-full bg-slate-900 hover:bg-slate-800 text-white py-3.5 rounded-2xl text-[10px] font-black shadow-lg flex items-center justify-center gap-2 uppercase transition active:scale-95">
                        <i class="fa-solid fa-route text-blue-400 text-xs"></i> Riwayat
                    </button>
                </div>
                <button id="btn-toggle-relay" onclick="toggleRelay()" class="w-full bg-red-500 hover:bg-red-600 text-white py-3.5 rounded-2xl text-[10px] font-black shadow-md flex items-center justify-center gap-2 uppercase transition active:scale-95">
                    <i class="fa-solid fa-power-off text-xs"></i> Matikan Mesin
                </button>
            </div>
        </div>
    </main>

    <script>
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var markers = {};
        var selectedImei = null;
        var currentFilter = 'all';
        var latestFleetData = [];
        
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'Prima Track' }).addTo(map);

        // INISIALISASI CLUSTER GROUP (Solusi untuk puluhan/ratusan armada)
        var clusterGroup = L.markerClusterGroup({
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: true,
            maxClusterRadius: 40,
            iconCreateFunction: function(cluster) {
                return L.divIcon({ 
                    html: cluster.getChildCount(), 
                    className: 'primal-cluster', 
                    iconSize: L.point(40, 40) 
                });
            }
        });
        map.addLayer(clusterGroup);

        map.on('zoomend', function() {
            if (map.getZoom() >= 15) {
                document.body.classList.add('zoom-detailed');
            } else {
                document.body.classList.remove('zoom-detailed');
            }
        });

        function toggleSidebar() { 
            sidebar.classList.toggle('-translate-x-full'); 
            overlay.classList.toggle('opacity-0');
            overlay.classList.toggle('pointer-events-none');
        }

        function setFilterStatus(status) {
            currentFilter = status;
            document.querySelectorAll('.pill-filter').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.getElementById('pill-' + status);
            if (activeBtn) activeBtn.classList.add('active');
            filterUnits();
        }

        function fitAllFleet() {
            const validCoords = [];
            latestFleetData.forEach(unit => {
                const lat = parseFloat(unit.latitude);
                const lng = parseFloat(unit.longitude);
                if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    validCoords.push([lat, lng]);
                }
            });
            if (validCoords.length > 0) {
                map.fitBounds(L.latLngBounds(validCoords), { padding: [50, 50], maxZoom: 15 });
            }
        }

        function filterUnits() {
            let search = document.getElementById('searchInput').value.toLowerCase();
            let items = document.getElementsByClassName('unit-card');

            for (let i = 0; i < items.length; i++) {
                let text = items[i].getAttribute('data-search') || '';
                let status = items[i].getAttribute('data-status') || '';

                let matchSearch = text.includes(search);
                let matchFilter = (currentFilter === 'all' || status === currentFilter);

                items[i].style.display = (matchSearch && matchFilter) ? "block" : "none";
            }
        }

        function formatWita(gpsTime) {
            if(!gpsTime) return "--:-- WITA";
            try { return gpsTime.substring(11, 16) + " WITA"; } catch(e) { return "--:-- WITA"; }
        }

        function updateUI() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    latestFleetData = data;
                    let listHtml = '';
                    let countMoving = 0, countAccOn = 0, countStop = 0;

                    data.forEach(unit => {
                        const lat = unit.latitude ? parseFloat(unit.latitude) : null;
                        const lng = unit.longitude ? parseFloat(unit.longitude) : null;
                        const speed = Math.round(unit.speed || 0);
                        const accOn = unit.acc_status == 1;
                        const isGT06N = unit.module_type === 'GT06N';

                        let statusCategory = 'stop';
                        let statusColor = 'status-stop';

                        if (speed >= 5) {
                            statusCategory = 'moving';
                            statusColor = 'status-moving';
                            countMoving++;
                        } else if (accOn) {
                            statusCategory = 'acc_on';
                            statusColor = 'status-acc-on';
                            countAccOn++;
                        } else {
                            countStop++;
                        }
                        
                        listHtml += `
                            <div data-search="${(unit.name + ' ' + unit.plate_number).toLowerCase()}"
                                data-status="${statusCategory}"
                                onclick="focusUnit('${unit.imei}', ${lat}, ${lng})" 
                                class="unit-card mb-3 p-4 border-2 rounded-2xl transition-all cursor-pointer active:scale-[0.98] ${selectedImei === unit.imei ? 'border-blue-500 bg-blue-50 shadow-md' : 'border-slate-50 bg-white hover:border-blue-200'}">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="min-w-0 flex-1 pr-2">
                                        <h4 class="font-black text-slate-800 uppercase text-[11px] leading-tight truncate">${unit.name}</h4>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase font-mono">${unit.plate_number}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <a href="/device/${unit.imei}/history" onclick="event.stopPropagation()" class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-blue-100 text-slate-400 hover:text-blue-600 flex items-center justify-center text-[10px] transition" title="Riwayat">
                                            <i class="fa-solid fa-route"></i>
                                        </a>
                                        <div class="w-2.5 h-2.5 rounded-full ${statusColor}"></div>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center mt-3">
                                    <span class="text-[8px] font-black px-2 py-1 rounded-lg ${speed >= 5 ? 'bg-green-100 text-green-700' : (accOn ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600')}">
                                        ${speed >= 5 ? speed + ' KM/H' : (accOn ? 'IDLE' : 'PARKIR')}
                                    </span>
                                    <span class="text-[8px] font-mono text-slate-400">${formatWita(unit.gps_time || unit.last_online)}</span>
                                </div>
                            </div>
                        `;

                        if (lat && lng && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                            const iconHtml = `<div class="marker-label-container"><div class="marker-label"><i class="fa-solid fa-key" style="color:${accOn ? '#3b82f6' : '#cbd5e1'}"></i>${unit.name}</div><div class="marker-dot ${statusColor} relative flex items-center justify-center">${speed >= 5 ? '<div class="absolute inset-0 bg-green-500 rounded-full pulse"></div>' : ''}</div></div>`;
                            const customIcon = L.divIcon({ className: 'custom-icon', html: iconHtml, iconSize: [120, 50], iconAnchor: [60, 45] });
                            
                            const popupHtml = `
                                <div class="p-2 min-w-[150px] font-sans">
                                    <div class="font-black text-slate-800 text-xs uppercase">${unit.name}</div>
                                    <div class="text-[10px] text-slate-400 font-mono font-bold uppercase mb-1">${unit.plate_number}</div>
                                    <div class="text-[11px] text-slate-600">Kecepatan: <b>${speed} km/h</b></div>
                                    <div class="text-[11px] text-slate-600">Kontak: <b>${accOn ? 'HIDUP' : 'MATI'}</b></div>
                                    <div class="text-[10px] text-slate-400 mt-1">Waktu: ${formatWita(unit.gps_time || unit.last_online)}</div>
                                </div>
                            `;

                            if (markers[unit.imei]) { 
                                markers[unit.imei].setLatLng([lat, lng]).setIcon(customIcon).setPopupContent(popupHtml); 
                            } else { 
                                markers[unit.imei] = L.marker([lat, lng], {icon: customIcon})
                                    .bindPopup(popupHtml)
                                    .on('click', () => focusUnit(unit.imei, lat, lng)); 
                                clusterGroup.addLayer(markers[unit.imei]);
                            }
                        }
                        if (selectedImei === unit.imei) updateDetail(unit);
                    });

                    // Update UI Counters
                    document.getElementById('count-all').innerText = data.length;
                    document.getElementById('count-moving').innerText = countMoving;
                    document.getElementById('count-acc_on').innerText = countAccOn;
                    document.getElementById('count-stop').innerText = countStop;
                    document.getElementById('mobile-fleet-count').innerText = `${data.length} Unit (${countMoving} Jalan)`;

                    document.getElementById('unit-list').innerHTML = listHtml;
                    filterUnits();
                });
        }

        function focusUnit(imei, lat, lng) {
            selectedImei = imei;
            if (lat && lng) {
                map.setView([lat, lng], 17);
                setTimeout(() => {
                    if (markers[imei]) markers[imei].openPopup();
                }, 400);
            }
            document.getElementById('detail-panel').classList.remove('translate-y-[120%]');
            if (window.innerWidth < 768 && !sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            
            const unit = latestFleetData.find(u => u.imei === imei);
            if (unit) updateDetail(unit);
        }

        function updateDetail(unit) {
            document.getElementById('det-name').innerText = unit.name;
            document.getElementById('det-plate').innerText = unit.plate_number;
            document.getElementById('det-speed').innerText = Math.round(unit.speed || 0);
            document.getElementById('det-time').innerText = formatWita(unit.gps_time || unit.last_online);
            
            // Link Google Maps
            const gmapsLink = document.getElementById('det-gmaps-link');
            if (unit.latitude && unit.longitude) {
                gmapsLink.href = `https://www.google.com/maps/dir/?api=1&destination=${unit.latitude},${unit.longitude}`;
                gmapsLink.classList.remove('opacity-50', 'pointer-events-none');
            } else {
                gmapsLink.removeAttribute('href');
                gmapsLink.classList.add('opacity-50', 'pointer-events-none');
            }

            const accEl = document.getElementById('det-acc');
            const iconBg = document.getElementById('det-icon-bg');
            if (unit.acc_status == 1) { 
                accEl.className = "flex items-center justify-center gap-1.5 font-black text-xs text-blue-600"; 
                accEl.innerHTML = '<i class="fa-solid fa-key"></i> <span>HIDUP</span>'; 
                iconBg.className = "w-14 h-14 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl shrink-0";
            } else { 
                accEl.className = "flex items-center justify-center gap-1.5 font-black text-xs text-slate-400"; 
                accEl.innerHTML = '<i class="fa-solid fa-key"></i> <span>MATI</span>'; 
                iconBg.className = "w-14 h-14 bg-slate-400 rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl shrink-0";
            }

            const badgeEl = document.getElementById('det-module-badge');
            if (badgeEl) {
                const isGT06N = unit.module_type === 'GT06N';
                badgeEl.innerText = isGT06N ? 'GT06N 4G' : (unit.module_type || 'STANDAR');
                badgeEl.className = isGT06N 
                    ? "text-[8px] font-black px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 border border-purple-200 uppercase italic" 
                    : "text-[8px] font-black px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 uppercase italic";
            }

            const relayBtn = document.getElementById('btn-toggle-relay');
            if (relayBtn) {
                relayBtn.style.display = (unit.module_type === 'GT06N') ? 'flex' : 'none';
            }
        }

        function closeDetail() { document.getElementById('detail-panel').classList.add('translate-y-[120%]'); selectedImei = null; }
        function goToHistory() { if (selectedImei) window.location.href = `/device/${selectedImei}/history`; }
        
        function toggleRelay() { 
            if (!selectedImei) return;
            if (confirm('⚠️ PERINGATAN KRITIS: Apakah Anda yakin ingin mematikan mesin kendaraan ini sekarang?')) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                fetch('/api/send-command', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        imei: selectedImei,
                        command: 'RELAY,1#'
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('✅ Perintah matikan mesin berhasil diteruskan.');
                    } else {
                        alert('⚠️ Gagal: ' + (data.msg || 'Terjadi kesalahan'));
                    }
                })
                .catch(() => alert('Gagal terhubung ke server kontrol.'));
            }
        }

        setInterval(updateUI, 5000);
        updateUI();
    </script>
</body>
</html>