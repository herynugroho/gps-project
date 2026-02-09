<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Prima GPS - Monitoring</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        #map { height: 100vh; width: 100%; z-index: 0; }
        .marker-label-container { display: flex; flex-direction: column; align-items: center; position: relative; bottom: 20px; }
        .marker-label { background: white; border: 2px solid #0f172a; border-radius: 6px; padding: 2px 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); white-space: nowrap; font-weight: 800; font-size: 11px; color: #0f172a; text-transform: uppercase; margin-bottom: 4px; }
        .marker-dot { width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
        .bg-moving { background-color: #22c55e; box-shadow: 0 0 15px rgba(34, 197, 94, 0.6); }
        .bg-stop { background-color: #ef4444; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden">

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white border-r border-slate-200 flex flex-col z-40 transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300 shadow-xl">
        <div class="p-6 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center font-bold text-xl">P</div>
                <h1 class="font-bold uppercase tracking-wider">PRIMA GPS</h1>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400"><i class="fa-solid fa-bars"></i></button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3 no-scrollbar" id="unit-list">
            <p class="text-center text-slate-300 py-10">Memuat data...</p>
        </div>

        <div class="p-4 border-t bg-slate-50">
            <a href="{{ route('devices.index') }}" class="flex items-center justify-center gap-2 bg-white border border-slate-200 py-3 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-100 transition shadow-sm">
                <i class="fa-solid fa-list-check"></i> KELOLA SEMUA ARMADA
            </a>
        </div>
    </aside>

    <main class="flex-1 relative">
        <button onclick="toggleSidebar()" class="absolute top-4 left-4 z-10 md:hidden bg-white p-3 rounded-xl shadow-lg border border-slate-100"><i class="fa-solid fa-bars text-lg"></i></button>
        <div id="map"></div>

        <!-- Detail Panel -->
        <div id="detail-panel" class="absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-80 bg-white shadow-2xl z-20 p-5 transform translate-y-[120%] transition-transform duration-300 rounded-t-3xl md:rounded-2xl border border-slate-100">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-white text-xl"><i class="fa-solid fa-car-side"></i></div>
                <div class="flex-1">
                    <h2 class="font-black text-slate-800 uppercase leading-none text-lg" id="det-name">-</h2>
                    <p class="text-[10px] text-slate-400 font-mono mt-1" id="det-plate">-</p>
                </div>
                <button onclick="closeDetail()" class="text-slate-300 hover:text-red-500"><i class="fa-solid fa-circle-xmark text-2xl"></i></button>
            </div>
            <div class="grid grid-cols-2 gap-2 mb-4">
                <div class="bg-slate-50 p-3 rounded-2xl text-center"><p class="text-[9px] text-slate-400 uppercase font-black">Kecepatan</p><p class="font-black text-xl"><span id="det-speed">0</span> <span class="text-[10px] font-normal">km/h</span></p></div>
                <div class="bg-slate-50 p-3 rounded-2xl text-center flex flex-col justify-center items-center"><p class="text-[9px] text-slate-400 uppercase font-black mb-1">Status</p><span id="det-status" class="text-[9px] font-black px-2 py-0.5 rounded-full bg-slate-200 uppercase">Offline</span></div>
            </div>
            <button onclick="goToHistory()" class="w-full bg-slate-900 text-white py-4 rounded-2xl text-sm font-black flex items-center justify-center gap-2 transition hover:bg-slate-800"><i class="fa-solid fa-route"></i> HISTORY PERJALANAN</button>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        function toggleSidebar() { sidebar.classList.toggle('-translate-x-full'); }

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

                    listHtml += `<div onclick="focusUnit('${unit.imei}', ${lat}, ${lng})" class="p-4 border-2 rounded-2xl shadow-sm cursor-pointer ${selectedImei === unit.imei ? 'border-blue-500 bg-blue-50' : 'border-slate-100 bg-white'}">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-black text-slate-800 uppercase text-xs">${unit.name}</h4>
                            <span class="text-[9px] font-black px-2 py-0.5 rounded-full ${isMoving ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-500'}">${isMoving ? Math.round(unit.speed) + ' KM/H' : 'BERHENTI'}</span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-mono font-bold">${unit.plate_number}</p>
                    </div>`;

                    const iconHtml = `<div class="marker-label-container"><div class="marker-label">${unit.name}</div><div class="marker-dot ${isMoving ? 'bg-moving' : 'bg-stop'}"></div></div>`;
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
            map.flyTo([lat, lng], 17);
            if (window.innerWidth < 768) sidebar.classList.add('-translate-x-full');
            updateData();
            document.getElementById('detail-panel').classList.remove('translate-y-[120%]');
        }

        function updateDetailPanel(unit) {
            document.getElementById('det-name').innerText = unit.name;
            document.getElementById('det-plate').innerText = unit.plate_number;
            document.getElementById('det-speed').innerText = Math.round(unit.speed || 0);
            const statusEl = document.getElementById('det-status');
            if (unit.speed > 0.5) { statusEl.innerText = "Bergerak"; statusEl.className = "text-[9px] font-black px-2 py-0.5 rounded-full bg-green-100 text-green-600 uppercase"; }
            else { statusEl.innerText = "Berhenti"; statusEl.className = "text-[9px] font-black px-2 py-0.5 rounded-full bg-slate-100 text-slate-400 uppercase"; }
        }

        function closeDetail() { document.getElementById('detail-panel').classList.add('translate-y-[120%]'); selectedImei = null; }
        function goToHistory() { if (selectedImei) window.location.href = `/device/${selectedImei}/history`; }

        setInterval(updateData, 5000);
        updateData();
    </script>
</body>
</html>