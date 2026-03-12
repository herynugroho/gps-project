<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>PRIMA GPS - Monitoring System</title>
    
    <!-- Framework & Library (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            overflow: hidden; 
            height: 100vh; 
            height: 100dvh; 
            margin: 0;
        }
        #map { height: 100%; width: 100%; z-index: 0; }
        
        /* Custom Marker Styles */
        .custom-icon { background: none; border: none; }
        .marker-label-container { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            position: relative; 
            bottom: 30px; 
        }
        .marker-label { 
            background: white; 
            border: 2px solid #0f172a; 
            border-radius: 6px; 
            padding: 2px 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
            white-space: nowrap; 
            font-weight: 800; 
            font-size: 11px; 
            color: #0f172a; 
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .marker-dot { 
            width: 16px; 
            height: 16px; 
            border-radius: 50%; 
            border: 3px solid white; 
            box-shadow: 0 0 10px rgba(0,0,0,0.3); 
            transition: all 0.3s ease;
        }

        /* Status Colors */
        .status-moving { background-color: #22c55e; box-shadow: 0 0 15px rgba(34, 197, 94, 0.6); }
        .status-acc-on { background-color: #3b82f6; box-shadow: 0 0 15px rgba(59, 130, 246, 0.5); }
        .status-stop { background-color: #ef4444; box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); }

        .pulse { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation { 
            0% { transform: scale(0.9); opacity: 1; } 
            100% { transform: scale(1.5); opacity: 0; } 
        }

        /* Detail Panel Transition */
        #detail-panel { 
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            z-index: 1001;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden relative">

    <!-- SIDEBAR (Mobile & Desktop) -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white border-r border-slate-200 flex flex-col z-[1002] transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300 shadow-2xl md:shadow-none">
        <div class="p-6 bg-slate-900 text-white shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl shadow-lg shadow-blue-500/20">P</div>
                <div>
                    <h1 class="font-black text-sm uppercase tracking-wider leading-none">PRIMA GPS</h1>
                    <p class="text-[9px] text-blue-300 mt-1 uppercase tracking-widest font-bold italic">Monitoring System</p>
                </div>
            </div>
        </div>

        <!-- Filter/Search Placeholder -->
        <div class="p-4 bg-slate-50 border-b">
             <div class="relative">
                <i class="fa-solid fa-search absolute left-4 top-3 text-slate-300 text-xs"></i>
                <input type="text" placeholder="Cari armada..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold outline-none focus:ring-2 focus:ring-blue-500 transition">
             </div>
        </div>

        <!-- Armada List Container -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3 no-scrollbar" id="unit-list">
            <div class="flex flex-col items-center justify-center h-40 text-slate-300">
                <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2"></i>
                <p class="text-[10px] font-bold uppercase">Sinkronisasi...</p>
            </div>
        </div>

        <div class="p-4 border-t bg-white">
            <a href="{{ route('devices.index') }}" class="flex items-center justify-center gap-2 w-full py-4 bg-slate-900 rounded-2xl text-[10px] font-black text-white hover:bg-slate-800 transition shadow-lg uppercase">
                <i class="fa-solid fa-gear text-blue-400"></i> Kelola Armada
            </a>
        </div>
    </aside>

    <!-- MAIN MAP AREA -->
    <main class="flex-1 relative flex flex-col h-full">
        
        <!-- Mobile Header Branding -->
        <div class="absolute top-4 left-4 right-4 z-[500] md:hidden flex items-center justify-between pointer-events-none">
            <div class="flex items-center gap-2 bg-white/90 backdrop-blur p-1.5 pr-4 rounded-2xl shadow-xl border border-white pointer-events-auto">
                <button onclick="toggleSidebar()" class="bg-slate-900 text-white w-10 h-10 rounded-xl flex items-center justify-center shadow-lg active:scale-90 transition">
                    <i class="fa-solid fa-bars-staggered"></i>
                </button>
                <div class="flex flex-col">
                    <span class="text-xs font-black text-slate-900 leading-none uppercase tracking-tighter">PRIMA GPS</span>
                    <span class="text-[8px] text-blue-600 font-bold uppercase tracking-widest">Makassar Live</span>
                </div>
            </div>
            
            <div class="bg-white/90 backdrop-blur w-10 h-10 rounded-xl shadow-xl flex items-center justify-center border border-white pointer-events-auto">
                <i class="fa-solid fa-signal text-green-500 text-xs"></i>
            </div>
        </div>

        <!-- Map Container -->
        <div id="map"></div>

        <!-- DETAIL PANEL (Bottom Sheet) -->
        <div id="detail-panel" class="absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-85 bg-white shadow-2xl p-7 transform translate-y-[120%] rounded-t-[2.5rem] md:rounded-3xl border-t md:border border-slate-100 pb-12 md:pb-7">
            <div class="w-12 h-1.5 bg-slate-100 rounded-full mx-auto mb-6 md:hidden"></div>
            
            <div class="flex items-center gap-5 mb-6">
                <div id="det-icon-bg" class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center text-white text-3xl shadow-xl shadow-blue-500/20 transition-colors duration-500">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div class="flex-1">
                    <h2 class="font-black text-slate-800 uppercase leading-none text-xl truncate w-40" id="det-name">-</h2>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-[10px] text-slate-400 font-mono font-bold tracking-tighter bg-slate-50 px-2 py-0.5 rounded" id="det-plate">-</span>
                        <span id="det-module-badge" class="text-[8px] font-black px-2 py-0.5 rounded-full bg-slate-900 text-white uppercase italic tracking-widest">STANDARD</span>
                    </div>
                </div>
                <button onclick="closeDetail()" class="text-slate-300 hover:text-red-500 transition active:scale-90 p-2">
                    <i class="fa-solid fa-circle-xmark text-3xl"></i>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <!-- Status ACC (GT06N) -->
                <div class="bg-slate-50 p-5 rounded-[1.5rem] border border-slate-100 text-center shadow-sm">
                    <p class="text-[9px] text-slate-400 uppercase font-black mb-2 tracking-widest">Status Mesin</p>
                    <div id="det-acc" class="flex items-center justify-center gap-2 font-black text-xs">
                        <i class="fa-solid fa-key"></i> <span>-</span>
                    </div>
                </div>
                <!-- Speed -->
                <div class="bg-slate-50 p-5 rounded-[1.5rem] border border-slate-100 text-center shadow-sm">
                    <p class="text-[9px] text-slate-400 uppercase font-black mb-2 tracking-widest">Kecepatan</p>
                    <p class="font-black text-2xl text-slate-800 tracking-tighter"><span id="det-speed">0</span> <small class="text-[10px] font-normal italic">km/h</small></p>
                </div>
            </div>

            <div class="flex items-center justify-between gap-2 text-[10px] text-slate-400 mb-6 bg-slate-50/50 p-4 rounded-2xl border border-dashed border-slate-200">
                <div class="flex items-center gap-2 font-bold uppercase tracking-tighter">
                    <i class="fa-solid fa-clock-rotate-left text-blue-500"></i>
                    <span>Sinyal Terakhir:</span>
                </div>
                <b id="det-time" class="text-slate-600 font-black">-</b>
            </div>

            <!-- Kontrol Relay & History -->
            <div class="flex flex-col gap-3">
                <button id="btn-relay" onclick="toggleRelay()" class="hidden w-full bg-red-600 hover:bg-red-700 text-white py-5 rounded-[1.5rem] text-xs font-black transition shadow-xl shadow-red-500/20 flex items-center justify-center gap-3 active:scale-95">
                    <i class="fa-solid fa-power-off"></i> MATIKAN MESIN (RELAY)
                </button>
                <button onclick="goToHistory()" class="w-full bg-slate-900 hover:bg-slate-800 text-white py-5 rounded-[1.5rem] text-xs font-black transition shadow-xl flex items-center justify-center gap-3 active:scale-95">
                    <i class="fa-solid fa-route text-blue-400"></i> RIWAYAT PERJALANAN
                </button>
            </div>
        </div>
    </main>

    <script>
        // --- CONFIG & STATE ---
        var sidebar = document.getElementById('sidebar');
        var markers = {};
        var selectedImei = null;

        // Initialize Map (Center Makassar)
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; Prima GPS System'
        }).addTo(map);

        // --- FUNCTIONS ---
        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
        }

        function fetchData() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    let listHtml = '';
                    data.forEach(unit => {
                        const lat = parseFloat(unit.latitude);
                        const lng = parseFloat(unit.longitude);
                        if (!lat || !lng) return;

                        const speed = Math.round(unit.speed || 0);
                        const isMoving = speed >= 5;
                        const accOn = unit.acc_status == 1;
                        const isGT06N = unit.module_type === 'GT06N';

                        // Status Color Logic
                        let statusColorClass = 'status-stop';
                        if (isMoving) statusColorClass = 'status-moving';
                        else if (accOn) statusColorClass = 'status-acc-on';

                        // Sidebar List UI
                        listHtml += `
                            <div onclick="focusUnit('${unit.imei}', ${lat}, ${lng})" 
                                class="p-4 border-2 rounded-2xl transition-all cursor-pointer ${selectedImei === unit.imei ? 'border-blue-500 bg-blue-50' : 'border-slate-50 bg-white hover:border-blue-200'}">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex flex-col">
                                        <h4 class="font-black text-slate-800 uppercase text-[11px] leading-tight truncate w-32">${unit.name}</h4>
                                        <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter mt-1">${unit.plate_number}</span>
                                    </div>
                                    <i class="fa-solid fa-key text-[10px] ${accOn ? 'text-blue-500' : 'text-slate-200'}"></i>
                                </div>
                                <div class="flex justify-between items-center mt-3">
                                    <span class="text-[8px] font-black px-2 py-1 rounded-lg uppercase tracking-widest ${isMoving ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-500'}">
                                        ${isMoving ? speed + ' KM/H' : 'BERHENTI'}
                                    </span>
                                    <span class="text-[7px] font-bold text-slate-300 uppercase">${isGT06N ? 'GT06N-4G' : 'STD'}</span>
                                </div>
                            </div>
                        `;

                        // Marker Map UI
                        const iconHtml = `
                            <div class="marker-label-container">
                                <div class="marker-label">
                                    <i class="fa-solid fa-key text-[8px] ${accOn ? 'text-blue-500' : 'text-slate-200'}"></i>
                                    ${unit.name}
                                </div>
                                <div class="marker-dot ${statusColorClass} relative flex items-center justify-center">
                                     ${isMoving ? '<div class="absolute inset-0 bg-green-500 rounded-full pulse"></div>' : ''}
                                </div>
                            </div>
                        `;

                        const customIcon = L.divIcon({
                            className: 'custom-icon',
                            html: iconHtml,
                            iconSize: [120, 50],
                            iconAnchor: [60, 45]
                        });

                        if (markers[unit.imei]) {
                            markers[unit.imei].setLatLng([lat, lng]).setIcon(customIcon);
                        } else {
                            markers[unit.imei] = L.marker([lat, lng], {icon: customIcon}).addTo(map);
                            markers[unit.imei].on('click', () => focusUnit(unit.imei, lat, lng));
                        }

                        if (selectedImei === unit.imei) updateDetailPanel(unit);
                    });

                    document.getElementById('unit-list').innerHTML = listHtml;
                });
        }

        function focusUnit(imei, lat, lng) {
            selectedImei = imei;
            map.flyTo([lat, lng], 17, { duration: 1 });
            document.getElementById('detail-panel').classList.remove('translate-y-[120%]');
            
            if (window.innerWidth < 768) {
                sidebar.classList.add('-translate-x-full');
            }
            fetchData();
        }

        function updateDetailPanel(unit) {
            const speed = Math.round(unit.speed || 0);
            const isMoving = speed >= 5;
            const accOn = unit.acc_status == 1;
            const isGT06N = unit.module_type === 'GT06N';

            document.getElementById('det-name').innerText = unit.name;
            document.getElementById('det-plate').innerText = unit.plate_number;
            document.getElementById('det-speed').innerText = speed;
            document.getElementById('det-module-badge').innerText = isGT06N ? 'GT06N 4G' : 'STANDARD';
            
            const time = unit.gps_time ? new Date(unit.gps_time.replace(' ', 'T') + 'Z').toLocaleTimeString('id-ID') : '-';
            document.getElementById('det-time').innerText = time + ' WITA';

            // ACC Indicator
            const accEl = document.getElementById('det-acc');
            if (accOn) {
                accEl.className = "flex items-center justify-center gap-2 font-black text-xs text-blue-600";
                accEl.innerHTML = '<i class="fa-solid fa-key animate-bounce"></i> <span>MESIN HIDUP</span>';
            } else {
                accEl.className = "flex items-center justify-center gap-2 font-black text-xs text-slate-400";
                accEl.innerHTML = '<i class="fa-solid fa-key"></i> <span>MESIN MATI</span>';
            }

            // Relay Button Visibility
            const relayBtn = document.getElementById('btn-relay');
            if (isGT06N) relayBtn.classList.remove('hidden');
            else relayBtn.classList.add('hidden');

            // Icon Background color change
            const iconBg = document.getElementById('det-icon-bg');
            if (isMoving) iconBg.className = "w-16 h-16 bg-green-500 rounded-2xl flex items-center justify-center text-white text-3xl shadow-xl shadow-green-500/20";
            else if (accOn) iconBg.className = "w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center text-white text-3xl shadow-xl shadow-blue-500/20";
            else iconBg.className = "w-16 h-16 bg-slate-400 rounded-2xl flex items-center justify-center text-white text-3xl shadow-xl";
        }

        function closeDetail() {
            document.getElementById('detail-panel').classList.add('translate-y-[120%]');
            selectedImei = null;
        }

        function toggleRelay() {
            if (confirm("⚠️ PERINGATAN: Apakah Anda yakin ingin mengirim perintah MATIKAN MESIN?")) {
                alert("Perintah Cut-off terkirim ke " + selectedImei);
            }
        }

        function goToHistory() {
            if (selectedImei) window.location.href = `/device/${selectedImei}/history`;
        }

        // --- INIT ---
        setInterval(fetchData, 5000);
        fetchData();
    </script>
</body>
</html>