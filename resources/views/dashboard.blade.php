<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>PRIMA GPS - Makassar</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; overflow: hidden; height: 100vh; height: 100dvh; }
        #map { height: 100%; width: 100%; z-index: 0; }
        
        /* Label Nama Kendaraan Melayang */
        .marker-label-container { display: flex; flex-direction: column; align-items: center; position: relative; bottom: 20px; }
        .marker-label { background: white; border: 2px solid #0f172a; border-radius: 6px; padding: 2px 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); white-space: nowrap; font-weight: 800; font-size: 11px; color: #0f172a; text-transform: uppercase; margin-bottom: 4px; }
        .marker-dot { width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3); transition: background-color 0.5s ease; }
        .bg-moving { background-color: #22c55e; box-shadow: 0 0 15px rgba(34, 197, 94, 0.6); }
        .bg-stop { background-color: #ef4444; box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); }

        .pulse { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation { 0% { box-shadow: 0 0 0 0px rgba(34, 197, 94, 0.7); } 100% { box-shadow: 0 0 0 15px rgba(34, 197, 94, 0); } }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        
        /* Animasi Bottom Sheet */
        #detail-panel { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden relative">

    <!-- OVERLAY UNTUK MOBILE SIDEBAR -->
    <div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[45] hidden md:hidden transition-opacity"></div>

    <!-- SIDEBAR (Menu Kelola & List) -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white border-r border-slate-200 flex flex-col z-50 transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300 ease-in-out shadow-2xl md:shadow-none">
        <div class="p-6 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-xl shadow-lg shadow-blue-500/20">P</div>
                <div>
                    <h1 class="font-bold uppercase tracking-wider leading-none text-sm">PRIMA GPS</h1>
                    <p class="text-[9px] text-blue-300 mt-1 uppercase tracking-widest font-semibold">Monitoring System</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <div class="p-3 bg-slate-50 border-b flex gap-2 shrink-0">
            <div class="flex-1 bg-white p-2 rounded-xl border border-slate-100 text-center shadow-sm">
                <span class="block text-[9px] text-slate-400 font-bold uppercase">Total</span>
                <span class="text-lg font-black text-slate-800" id="stat-total">0</span>
            </div>
            <div class="flex-1 bg-white p-2 rounded-xl border border-slate-100 text-center shadow-sm">
                <span class="block text-[9px] text-slate-400 font-bold uppercase text-green-500">Live</span>
                <span class="text-lg font-black text-green-500" id="stat-online">0</span>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3 no-scrollbar" id="unit-list">
            <div class="flex flex-col items-center justify-center h-40 text-slate-300">
                <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2"></i>
                <p class="text-[10px] font-bold uppercase italic">Memuat Armada...</p>
            </div>
        </div>

        <div class="p-4 border-t bg-slate-50 shrink-0">
            <a href="{{ route('devices.index') }}" class="flex items-center justify-center gap-2 bg-white border border-slate-200 py-3.5 rounded-2xl text-xs font-black text-slate-700 hover:bg-slate-100 transition shadow-sm uppercase tracking-tighter">
                <i class="fa-solid fa-list-check text-blue-500"></i> Kelola Semua Armada
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT (MAP) -->
    <main class="flex-1 relative flex flex-col h-full">
        
        <!-- MOBILE HEADER BRANDING (DIPERBARUI) -->
        <div class="absolute top-4 left-4 right-4 z-10 md:hidden flex items-center justify-between pointer-events-none">
            <div class="flex items-center gap-2 bg-white/90 backdrop-blur p-1.5 pr-4 rounded-2xl shadow-xl border border-white pointer-events-auto">
                <button onclick="toggleSidebar()" class="bg-slate-900 text-white w-10 h-10 rounded-xl flex items-center justify-center active:scale-95 transition shadow-lg">
                    <i class="fa-solid fa-bars-staggered"></i>
                </button>
                <div class="flex flex-col">
                    <span class="text-xs font-black text-slate-900 leading-none uppercase tracking-tighter">PRIMA GPS</span>
                    <span class="text-[8px] text-blue-600 font-bold uppercase tracking-widest">Makassar</span>
                </div>
            </div>
            
            <div class="bg-white/90 backdrop-blur w-10 h-10 rounded-xl shadow-xl flex items-center justify-center border border-white pointer-events-auto">
                <i class="fa-solid fa-signal text-green-500 text-xs"></i>
            </div>
        </div>

        <div id="map"></div>

        <!-- DETAIL PANEL (BOTTOM SHEET) -->
        <div id="detail-panel" class="absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-80 bg-white shadow-2xl z-20 p-6 transform translate-y-[120%] transition-transform duration-300 rounded-t-[2.5rem] md:rounded-3xl border-t md:border border-slate-100 pb-12 md:pb-6">
            <div class="w-12 h-1.5 bg-slate-100 rounded-full mx-auto mb-6 md:hidden"></div>
            
            <div class="flex items-center gap-4 mb-6">
                <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl shadow-blue-500/20">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div class="flex-1">
                    <h2 class="font-black text-slate-800 uppercase leading-none text-lg truncate w-40" id="det-name">-</h2>
                    <p class="text-[10px] text-slate-400 font-mono mt-1 font-bold" id="det-plate">-</p>
                </div>
                <button onclick="closeDetail()" class="text-slate-300 hover:text-red-500 transition p-2">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-6">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center shadow-sm">
                    <p class="text-[9px] text-slate-400 uppercase font-black tracking-widest mb-1">Kecepatan</p>
                    <p class="font-black text-2xl text-slate-800"><span id="det-speed">0</span> <span class="text-[10px] font-normal italic">km/h</span></p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center flex flex-col justify-center items-center shadow-sm">
                    <p class="text-[9px] text-slate-400 uppercase font-black mb-1">Status Unit</p>
                    <span id="det-status" class="text-[10px] font-black px-3 py-1 rounded-full bg-slate-200 text-slate-500 uppercase italic">Offline</span>
                </div>
            </div>

            <div class="flex items-center justify-between gap-2 text-[10px] text-slate-400 mb-6 bg-slate-50 p-3 rounded-xl border border-slate-100">
                <div class="flex items-center gap-2 font-bold uppercase tracking-tighter">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Update:</span>
                </div>
                <b id="det-time" class="text-slate-600 font-black">-</b>
            </div>

            <button onclick="goToHistory()" class="w-full bg-slate-900 hover:bg-slate-800 text-white py-4 rounded-2xl text-sm font-black transition shadow-xl flex items-center justify-center gap-2 active:scale-95">
                <i class="fa-solid fa-route text-blue-400"></i> TRACKING HISTORY
            </button>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var markers = {};
        var selectedImei = null;

        function updateData() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    let listHtml = '';
                    let onlineCount = 0;

                    data.forEach(unit => {
                        if (!unit.latitude || !unit.longitude) return;
                        
                        const lat = parseFloat(unit.latitude);
                        const lng = parseFloat(unit.longitude);
                        const isMoving = unit.speed > 0.5;
                        if(isMoving) onlineCount++;

                        const activeClass = selectedImei === unit.imei ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-100 bg-white hover:border-blue-200';
                        listHtml += `
                            <div onclick="focusUnit('${unit.imei}', ${lat}, ${lng})" class="p-4 border-2 rounded-2xl shadow-sm transition-all duration-300 cursor-pointer ${activeClass}">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-black text-slate-800 uppercase text-xs truncate w-32">${unit.name || 'Unit ' + unit.imei}</h4>
                                    <span class="text-[9px] font-black px-2 py-0.5 rounded-full ${isMoving ? 'bg-green-100 text-green-600 border border-green-200' : 'bg-slate-100 text-slate-500 border border-slate-200'}">
                                        ${isMoving ? Math.round(unit.speed) + ' KM/H' : 'BERHENTI'}
                                    </span>
                                </div>
                                <div class="flex justify-between items-end">
                                    <p class="text-[10px] text-slate-400 font-mono font-bold tracking-tighter">${unit.plate_number || 'N/A'}</p>
                                    <div class="flex items-center gap-1 text-[9px] text-blue-500 font-bold uppercase tracking-tighter">
                                        View <i class="fa-solid fa-chevron-right text-[8px]"></i>
                                    </div>
                                </div>
                            </div>
                        `;

                        const iconHtml = `
                            <div class="marker-label-container">
                                <div class="marker-label">${unit.name || unit.imei}</div>
                                <div class="marker-dot ${isMoving ? 'bg-moving pulse' : 'bg-stop'}"></div>
                            </div>
                        `;

                        const customIcon = L.divIcon({
                            className: 'custom-div-icon',
                            html: iconHtml,
                            iconSize: [120, 50],
                            iconAnchor: [60, 45]
                        });

                        if (markers[unit.imei]) {
                            markers[unit.imei].setLatLng([lat, lng]);
                            markers[unit.imei].setIcon(customIcon);
                        } else {
                            markers[unit.imei] = L.marker([lat, lng], {icon: customIcon}).addTo(map);
                            markers[unit.imei].on('click', () => focusUnit(unit.imei, lat, lng));
                        }
                    });

                    document.getElementById('unit-list').innerHTML = listHtml || '<p class="text-center text-slate-400 text-xs py-10">Belum ada armada.</p>';
                    document.getElementById('stat-total').innerText = data.length;
                    document.getElementById('stat-online').innerText = onlineCount;

                    if (selectedImei) {
                        const activeUnit = data.find(u => u.imei === selectedImei);
                        if (activeUnit) updateDetailPanel(activeUnit);
                    }
                })
                .catch(err => console.error("Error fetching GPS data:", err));
        }

        function focusUnit(imei, lat, lng) {
            selectedImei = imei;
            map.flyTo([lat, lng], 17, { duration: 1 });
            
            if (window.innerWidth < 768) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
            
            fetch('/api/gps-data').then(res => res.json()).then(data => {
                const unit = data.find(u => u.imei === imei);
                if(unit) {
                    updateDetailPanel(unit);
                    document.getElementById('detail-panel').classList.remove('translate-y-[120%]');
                }
            });
        }

        function updateDetailPanel(unit) {
            document.getElementById('det-name').innerText = unit.name || 'Unit ' + unit.imei;
            document.getElementById('det-plate').innerText = unit.plate_number || 'N/A';
            document.getElementById('det-speed').innerText = Math.round(unit.speed || 0);
            
            const time = unit.gps_time ? new Date(unit.gps_time.replace(' ', 'T') + 'Z').toLocaleTimeString('id-ID') : 'N/A';
            document.getElementById('det-time').innerText = time;

            const statusEl = document.getElementById('det-status');
            if (unit.speed > 0.5) {
                statusEl.innerText = "Bergerak";
                statusEl.className = "text-[10px] font-black px-3 py-1 rounded-full bg-green-100 text-green-600 uppercase italic border border-green-200";
            } else {
                statusEl.innerText = "Berhenti";
                statusEl.className = "text-[10px] font-black px-3 py-1 rounded-full bg-red-100 text-red-600 uppercase italic border border-red-200";
            }
        }

        function closeDetail() {
            document.getElementById('detail-panel').classList.add('translate-y-[120%]');
            selectedImei = null;
        }

        function goToHistory() {
            if (selectedImei) window.location.href = `/device/${selectedImei}/history`;
        }

        setInterval(updateData, 5000);
        updateData();
    </script>
</body>
</html>