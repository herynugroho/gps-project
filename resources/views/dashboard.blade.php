<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Prima GPS - Monitoring</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; overflow: hidden; }
        #map { height: 100vh; width: 100%; z-index: 0; }
        
        /* Ikon Label Nama Melayang */
        .marker-label-container { display: flex; flex-direction: column; align-items: center; position: relative; bottom: 20px; }
        .marker-label { background: white; border: 2px solid #0f172a; border-radius: 6px; padding: 2px 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); white-space: nowrap; font-weight: 800; font-size: 11px; color: #0f172a; text-transform: uppercase; margin-bottom: 4px; }
        .marker-dot { width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
        .bg-moving { background-color: #22c55e; box-shadow: 0 0 15px rgba(34, 197, 94, 0.6); }
        .bg-stop { background-color: #ef4444; box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); }

        /* Detail Panel - Bottom Sheet Fix for Mobile */
        #detail-panel {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Menangani notch/bar bawah pada HP modern */
        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .safe-pb {
                padding-bottom: calc(2rem + env(safe-area-inset-bottom));
            }
        }

        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white border-r border-slate-200 flex flex-col z-40 transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300 shadow-xl">
        <div class="p-6 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center font-bold text-xl shadow-lg">P</div>
                <h1 class="font-bold uppercase tracking-wider leading-none">PRIMA GPS</h1>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-bars-staggered text-xl"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3 no-scrollbar" id="unit-list">
            <div class="flex flex-col items-center justify-center h-40 text-slate-300">
                <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2"></i>
                <p class="text-xs font-bold uppercase tracking-widest">Sinkronisasi...</p>
            </div>
        </div>

        <div class="p-4 border-t bg-slate-50">
            <a href="{{ route('devices.index') }}" class="flex items-center justify-center gap-2 bg-white border border-slate-200 py-3 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-100 transition shadow-sm">
                <i class="fa-solid fa-gears"></i> KELOLA ARMADA
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 relative overflow-hidden">
        <!-- Floating Menu Button (Mobile) -->
        <button onclick="toggleSidebar()" class="absolute top-4 left-4 z-10 md:hidden bg-white p-3 rounded-xl shadow-xl text-slate-800 border border-slate-100 active:scale-95 transition">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>

        <div id="map"></div>

        <!-- DETAIL PANEL (BOTTOM SHEET) -->
        <div id="detail-panel" class="fixed md:absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-80 bg-white shadow-[0_-10px_40px_rgba(0,0,0,0.15)] md:shadow-2xl z-[1001] p-6 transform translate-y-[110%] rounded-t-[2.5rem] md:rounded-3xl border-t md:border border-slate-100 safe-pb pb-12 md:pb-6">
            
            <!-- Handle untuk Swipe Down di Mobile -->
            <div class="w-16 h-1.5 bg-slate-100 rounded-full mx-auto mb-6 md:hidden"></div>
            
            <div class="flex items-center gap-4 mb-6 relative">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shadow-blue-100">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div class="flex-1">
                    <h2 class="font-black text-slate-800 uppercase leading-none text-lg truncate w-40" id="det-name">Memuat...</h2>
                    <p class="text-[10px] text-slate-400 font-mono mt-1 font-bold" id="det-plate">-</p>
                </div>
                <button onclick="closeDetail()" class="text-slate-200 hover:text-red-500 transition-colors p-1">
                    <i class="fa-solid fa-circle-xmark text-2xl"></i>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-6">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                    <p class="text-[9px] text-slate-400 uppercase font-black tracking-widest mb-1">Kecepatan</p>
                    <p class="font-black text-2xl text-slate-800"><span id="det-speed">0</span> <span class="text-[10px] font-normal italic">km/h</span></p>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center flex flex-col justify-center items-center">
                    <p class="text-[9px] text-slate-400 uppercase font-black mb-1">Status</p>
                    <span id="det-status" class="text-[10px] font-black px-3 py-1 rounded-full bg-slate-200 text-slate-500 uppercase italic">Offline</span>
                </div>
            </div>

            <div class="flex items-center justify-between gap-2 text-[10px] text-slate-400 mb-6 bg-slate-50 p-3 rounded-xl border border-slate-100">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Pembaruan Terakhir:</span>
                </div>
                <b id="det-time" class="text-slate-600 font-black">-</b>
            </div>

            <!-- Tombol Aksi Utama -->
            <div class="flex flex-col gap-3">
                <button onclick="goToHistory()" class="w-full bg-slate-900 hover:bg-slate-800 text-white py-4 rounded-2xl text-sm font-black transition shadow-xl active:scale-95 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-map-location-dot text-blue-400"></i> RIWAYAT PERJALANAN
                </button>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
        }

        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var markers = {};
        var selectedImei = null;

        function updateData() {
            fetch('/api/gps-data').then(res => res.json()).then(data => {
                let listHtml = '';
                data.forEach(unit => {
                    if (!unit.latitude || !unit.longitude) return;
                    const lat = parseFloat(unit.latitude), lng = parseFloat(unit.longitude);
                    const isMoving = unit.speed > 0.5;

                    const activeClass = selectedImei === unit.imei ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-100 bg-white';
                    listHtml += `
                        <div onclick="focusUnit('${unit.imei}', ${lat}, ${lng})" class="p-4 border-2 rounded-2xl shadow-sm transition-all duration-300 cursor-pointer ${activeClass}">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-black text-slate-800 uppercase text-xs truncate w-32">${unit.name || 'Unit ' + unit.imei}</h4>
                                <span class="text-[9px] font-black px-2 py-0.5 rounded-full ${isMoving ? 'bg-green-100 text-green-600 border border-green-200' : 'bg-slate-100 text-slate-500 border border-slate-200'}">
                                    ${isMoving ? Math.round(unit.speed) + ' KM/H' : 'STATIONARY'}
                                </span>
                            </div>
                            <div class="flex justify-between items-end">
                                <p class="text-[10px] text-slate-400 font-mono font-bold tracking-tighter">${unit.plate_number || 'N/A'}</p>
                                <div class="flex items-center gap-1 text-[9px] text-blue-500 font-bold uppercase">View <i class="fa-solid fa-chevron-right text-[8px]"></i></div>
                            </div>
                        </div>
                    `;

                    const iconHtml = `<div class="marker-label-container"><div class="marker-label">${unit.name || unit.imei}</div><div class="marker-dot ${isMoving ? 'bg-moving' : 'bg-stop'}"></div></div>`;
                    const customIcon = L.divIcon({ className: 'custom-div-icon', html: iconHtml, iconSize: [120, 50], iconAnchor: [60, 45] });

                    if (markers[unit.imei]) {
                        markers[unit.imei].setLatLng([lat, lng]);
                        markers[unit.imei].setIcon(customIcon);
                    } else {
                        markers[unit.imei] = L.marker([lat, lng], {icon: customIcon}).addTo(map);
                        markers[unit.imei].on('click', () => focusUnit(unit.imei, lat, lng));
                    }
                });
                document.getElementById('unit-list').innerHTML = listHtml;
                if (selectedImei) {
                    const activeUnit = data.find(u => u.imei === selectedImei);
                    if (activeUnit) updateDetailPanel(activeUnit);
                }
            });
        }

        function focusUnit(imei, lat, lng) {
            selectedImei = imei;
            map.flyTo([lat, lng], 17, { duration: 1 });
            if (window.innerWidth < 768) sidebar.classList.add('-translate-x-full');
            document.getElementById('detail-panel').classList.remove('translate-y-[110%]');
            updateData();
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
                statusEl.className = "text-[10px] font-black px-3 py-1 rounded-full bg-green-100 text-green-600 uppercase border border-green-200 italic";
            } else {
                statusEl.innerText = "Berhenti";
                statusEl.className = "text-[10px] font-black px-3 py-1 rounded-full bg-red-50 text-red-500 uppercase border border-red-100 italic";
            }
        }

        function closeDetail() { document.getElementById('detail-panel').classList.add('translate-y-[110%]'); selectedImei = null; }
        function goToHistory() { if (selectedImei) window.location.href = `/device/${selectedImei}/history`; }

        setInterval(updateData, 5000);
        updateData();
    </script>
</body>
</html>