<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Prima GPS - Dashboard Monitoring</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        #map { height: 100%; width: 100%; z-index: 0; }
        
        .marker-label {
            background: white;
            border: 2px solid #1e293b;
            border-radius: 4px;
            padding: 2px 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            white-space: nowrap;
            display: flex;
            flex-direction: column;
            align-items: center;
            bottom: 20px;
            position: relative;
        }

        .pulse-ring {
            border: 3px solid #10B981;
            border-radius: 30px;
            height: 18px; width: 18px;
            position: absolute; left: -9px; top: -9px;
            animation: pulsate 1s ease-out infinite;
            opacity: 0.0;
        }
        @keyframes pulsate {
            0% {transform: scale(0.1, 0.1); opacity: 0.0;}
            50% {opacity: 1.0;}
            100% {transform: scale(1.2, 1.2); opacity: 0.0;}
        }

        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-gray-100 h-screen flex overflow-hidden relative">

    <div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden transition-opacity"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white shadow-2xl z-40 transform -translate-x-full md:translate-x-0 md:relative md:shadow-xl transition-transform duration-300 ease-in-out flex flex-col h-full">
        <div class="p-5 border-b border-gray-100 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center font-bold shadow-lg">P</div>
                <div>
                    <h1 class="font-bold tracking-wide uppercase">Prima GPS</h1>
                    <p class="text-[10px] text-blue-200 uppercase tracking-tighter">Fleet Management Makassar</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <div class="p-3 bg-gray-50 border-b shrink-0">
            <div class="grid grid-cols-3 gap-2 text-center text-[10px] font-bold">
                <div class="bg-white p-2 rounded shadow-sm border border-gray-100">
                    <span class="block font-bold text-lg text-blue-600" id="stat-total">0</span> TOTAL
                </div>
                <div class="bg-white p-2 rounded shadow-sm border border-gray-100">
                    <span class="block font-bold text-lg text-green-600" id="stat-online">0</span> ONLINE
                </div>
                <div class="bg-white p-2 rounded shadow-sm border border-gray-100">
                    <span class="block font-bold text-lg text-gray-400" id="stat-offline">0</span> OFF
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto no-scrollbar px-2 py-3 space-y-2" id="vehicle-list">
            <div class="p-10 text-center text-gray-400 text-sm">
                <i class="fa-solid fa-circle-notch fa-spin text-blue-500 text-2xl mb-2"></i> 
            </div>
        </div>
        
        <div class="p-4 border-t shrink-0">
            <a href="{{ route('devices.index') }}" class="w-full flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-xl font-bold text-sm transition shadow-sm">
                <i class="fa-solid fa-gears"></i> Kelola Armada
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative w-full h-full">
        
        <div class="absolute top-4 left-4 z-10 md:hidden flex items-center gap-2">
            <button onclick="toggleSidebar()" class="bg-white text-slate-900 p-2.5 rounded-lg shadow-lg active:scale-95 transition border border-gray-200">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
        </div>

        <div id="map" class="w-full h-full bg-gray-200"></div>

        <div id="detail-panel" class="absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-80 md:rounded-2xl rounded-t-2xl bg-white shadow-2xl z-20 p-5 transform translate-y-[120%] transition-transform duration-300 border-t border-gray-200 md:border md:border-gray-100 pb-12 md:pb-5">
            <div class="w-12 h-1 bg-gray-200 rounded-full mx-auto mb-4 md:hidden"></div>
            <button onclick="closeDetail()" class="absolute top-4 right-4 text-gray-300 hover:text-red-500"><i class="fa-solid fa-circle-xmark text-2xl"></i></button>
            
            <div class="flex items-center gap-4 mb-5">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl shadow-lg shadow-blue-200">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800 text-lg uppercase leading-tight" id="detail-name">-</h2>
                    <p class="text-xs text-slate-400 font-mono tracking-widest" id="detail-plate">-</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-4">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-center">
                    <p class="text-[10px] text-slate-400 uppercase font-bold">Kecepatan</p>
                    <p class="font-black text-xl text-slate-800"><span id="detail-speed">0</span> <span class="text-[10px] font-normal">km/h</span></p>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-center flex flex-col justify-center">
                    <p class="text-[10px] text-slate-400 uppercase font-bold">Status</p>
                    <p class="font-bold text-xs mt-1" id="detail-status">-</p>
                </div>
            </div>

            <div class="text-[10px] text-slate-400 mb-5 flex items-center gap-2 bg-slate-50 p-2 rounded-lg">
                <i class="fa-solid fa-clock"></i> Update: <span id="detail-time" class="font-bold text-slate-600">-</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button onclick="goToHistory()" class="bg-blue-600 text-white py-3 rounded-xl text-sm font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-100 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-route"></i> History
                </button>
                <button class="bg-white text-red-500 border border-red-100 py-3 rounded-xl text-sm font-bold hover:bg-red-50 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-power-off"></i> Matikan
                </button>
            </div>
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

        var map = L.map('map', { zoomControl: false }).setView([-5.147665, 119.432731], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; Prima GPS' }).addTo(map);

        var markers = {};
        var selectedImei = null;

        function updateData() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    renderSidebar(data);
                    renderMap(data);
                    updateStats(data);
                    if (selectedImei) {
                        const unit = data.find(d => d.imei === selectedImei);
                        if(unit) renderDetail(unit);
                    }
                })
                .catch(err => console.error(err));
        }

        function renderMap(data) {
            data.forEach(unit => {
                if (!unit.latitude || !unit.longitude) return;
                const lat = parseFloat(unit.latitude);
                const lng = parseFloat(unit.longitude);
                const isMoving = unit.speed > 0;
                
                const iconHtml = `
                    <div class="flex flex-col items-center">
                        <div class="marker-label">
                            <span class="text-[10px] font-bold text-slate-800 uppercase">${unit.name || 'Unit'}</span>
                            <span class="text-[8px] text-slate-400 font-mono">${unit.plate_number || ''}</span>
                        </div>
                        <div class="relative w-6 h-6 rounded-full bg-white border-2 ${isMoving ? 'border-green-500' : 'border-red-500'} shadow-lg flex items-center justify-center">
                            <div class="w-3 h-3 rounded-full ${isMoving ? 'bg-green-500' : 'bg-red-500'}"></div>
                            ${isMoving ? '<div class="pulse-ring"></div>' : ''}
                        </div>
                    </div>
                `;

                const customIcon = L.divIcon({
                    className: 'custom-pin',
                    html: iconHtml,
                    iconSize: [100, 60],
                    iconAnchor: [50, 55]
                });

                if (markers[unit.imei]) {
                    markers[unit.imei].setLatLng([lat, lng]);
                    markers[unit.imei].setIcon(customIcon);
                } else {
                    const marker = L.marker([lat, lng], {icon: customIcon}).addTo(map);
                    marker.on('click', () => { focusUnit(unit.imei, lat, lng); });
                    markers[unit.imei] = marker;
                }
            });
        }

        function renderSidebar(data) {
            const container = document.getElementById('vehicle-list');
            let html = '';
            
            data.forEach(unit => {
                const isMoving = unit.speed > 0;
                const activeClass = selectedImei == unit.imei ? 'border-blue-500 bg-blue-50' : 'border-transparent bg-white hover:border-slate-200';

                html += `
                <div onclick="focusUnit('${unit.imei}', ${unit.latitude}, ${unit.longitude})" class="p-4 border-2 rounded-2xl cursor-pointer transition-all duration-300 ${activeClass}">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400">
                                <i class="fa-solid fa-car-side"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm uppercase truncate w-32">${unit.name || unit.imei}</h4>
                                <p class="text-[10px] text-slate-400 font-mono">${unit.plate_number || '-'}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] font-bold px-2 py-1 rounded-full ${isMoving ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-500'}">
                                ${isMoving ? Math.round(unit.speed) + ' km/h' : 'PARKIR'}
                            </span>
                        </div>
                    </div>
                </div>`;
            });
            container.innerHTML = html || '<p class="text-center text-slate-400 text-xs mt-10">Belum ada armada.</p>';
        }

        function updateStats(data) {
            document.getElementById('stat-total').innerText = data.length;
            document.getElementById('stat-online').innerText = data.filter(d => d.latitude && d.speed > 0).length;
            document.getElementById('stat-offline').innerText = data.filter(d => d.latitude && d.speed == 0).length;
        }

        function focusUnit(imei, lat, lng) {
            selectedImei = imei;
            if (window.innerWidth < 768) sidebar.classList.add('-translate-x-full');
            if (lat && lng) map.flyTo([lat, lng], 17);
            openDetail();
        }

        function openDetail() {
            document.getElementById('detail-panel').classList.remove('translate-y-[120%]');
        }

        function renderDetail(unit) {
            document.getElementById('detail-name').innerText = unit.name || unit.imei;
            document.getElementById('detail-plate').innerText = unit.plate_number || '-';
            document.getElementById('detail-speed').innerText = Math.round(unit.speed || 0);
            document.getElementById('detail-time').innerText = unit.gps_time ? new Date(unit.gps_time + 'Z').toLocaleTimeString('id-ID') : '-';
            
            const statusEl = document.getElementById('detail-status');
            if (unit.speed > 0) {
                statusEl.innerText = "BERGERAK";
                statusEl.className = "font-bold text-green-500 text-xs";
            } else {
                statusEl.innerText = "PARKIR / DIAM";
                statusEl.className = "font-bold text-slate-400 text-xs";
            }
        }

        function closeDetail() {
            document.getElementById('detail-panel').classList.add('translate-y-[120%]');
            selectedImei = null;
        }

        function goToHistory() {
            if (selectedImei) window.location.href = '/device/' + selectedImei + '/history';
        }

        updateData();
        setInterval(updateData, 5000);
    </script>
</body>
</html>