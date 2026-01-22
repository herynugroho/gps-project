<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Prima GPS - Dashboard Monitoring</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        #map { height: 100%; width: 100%; z-index: 0; }
        
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
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-100 h-screen flex overflow-hidden relative">

    <!-- MOBILE OVERLAY -->
    <div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden transition-opacity"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white shadow-2xl z-40 transform -translate-x-full md:translate-x-0 md:relative md:shadow-xl transition-transform duration-300 ease-in-out flex flex-col h-full">
        <div class="p-5 border-b border-gray-100 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center font-bold shadow-lg">P</div>
                <div>
                    <h1 class="font-bold tracking-wide">PRIMA GPS</h1>
                    <p class="text-xs text-blue-200">Tracking System</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <a href="{{ route('devices.create') }}" class="hidden md:flex bg-blue-600 hover:bg-blue-500 text-white p-2 rounded-full w-8 h-8 items-center justify-center transition shadow-lg" title="Tambah Armada">
                <i class="fa-solid fa-plus text-sm"></i>
            </a>
        </div>

        <div class="p-3 bg-gray-50 border-b shrink-0">
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <div class="bg-white p-2 rounded shadow-sm border border-gray-100">
                    <span class="block font-bold text-lg text-blue-600" id="stat-total">0</span> Total
                </div>
                <div class="bg-white p-2 rounded shadow-sm border border-gray-100">
                    <span class="block font-bold text-lg text-green-600" id="stat-online">0</span> Online
                </div>
                <div class="bg-white p-2 rounded shadow-sm border border-gray-100">
                    <span class="block font-bold text-lg text-gray-500" id="stat-offline">0</span> Offline
                </div>
            </div>
            <a href="{{ route('devices.create') }}" class="mt-3 flex md:hidden items-center justify-center gap-2 w-full bg-blue-600 text-white py-2 rounded text-sm font-bold shadow">
                <i class="fa-solid fa-plus"></i> Tambah Armada Baru
            </a>
        </div>

        <div class="flex-1 overflow-y-auto no-scrollbar" id="vehicle-list">
            <div class="p-10 text-center text-gray-400 text-sm flex flex-col items-center">
                <i class="fa-solid fa-circle-notch fa-spin text-blue-500 text-2xl mb-2"></i> 
                <span>Memuat data...</span>
            </div>
        </div>
        
        <div class="p-2 text-center text-[10px] text-gray-400 border-t shrink-0">
            System Live &bull; v1.0
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative w-full h-full">
        
        <!-- MOBILE HEADER -->
        <div class="absolute top-4 left-4 z-10 md:hidden flex items-center gap-2">
            <button onclick="toggleSidebar()" class="bg-white text-slate-900 p-2.5 rounded-lg shadow-lg active:scale-95 transition border border-gray-200">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <div class="bg-slate-900/90 backdrop-blur text-white px-3 py-2 rounded-lg shadow-lg text-sm font-bold border border-slate-700">
                PRIMA GPS
            </div>
        </div>

        <!-- PETA -->
        <div id="map" class="w-full h-full bg-gray-200"></div>

        <!-- DETAIL PANEL (BOTTOM SHEET STYLE) -->
        <!-- Perubahan: bottom-0 di mobile, rounded-t-xl, pb-8 -->
        <div id="detail-panel" class="absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-96 md:rounded-xl rounded-t-2xl bg-white shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-20 p-5 transform translate-y-[110%] transition-transform duration-300 border-t border-gray-200 md:border md:border-gray-100 pb-10 md:pb-5">
            
            <!-- Handle Grip -->
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-4 md:hidden"></div>

            <button onclick="closeDetail()" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 p-2"><i class="fa-solid fa-xmark text-xl"></i></button>
            
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 text-xl shrink-0">
                    <i class="fa-solid fa-car-side" id="detail-icon"></i>
                </div>
                <div class="overflow-hidden">
                    <h2 class="font-bold text-gray-800 text-lg truncate" id="detail-name">-</h2>
                    <p class="text-sm text-gray-500 font-mono" id="detail-plate">-</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100">
                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Speed</p>
                    <p class="font-bold text-lg text-gray-800"><span id="detail-speed">0</span> <span class="text-xs font-normal text-gray-500">km/h</span></p>
                </div>
                <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100">
                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Status</p>
                    <p class="font-bold text-sm" id="detail-status">Online</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs text-gray-400 mb-5 bg-gray-50 p-2 rounded">
                <i class="fa-regular fa-clock text-blue-400"></i> Update: <span id="detail-time" class="text-gray-600 font-medium">-</span>
            </div>

            <!-- Tombol Action -->
            <div class="grid grid-cols-2 gap-3">
                <button onclick="goToHistory()" class="flex items-center justify-center gap-2 bg-blue-600 text-white py-3 rounded-lg text-sm font-semibold hover:bg-blue-700 active:bg-blue-800 transition shadow-sm">
                    <i class="fa-solid fa-route"></i> History
                </button>
                
                <button class="flex items-center justify-center gap-2 bg-white text-red-600 border border-red-200 py-3 rounded-lg text-sm font-semibold hover:bg-red-50 active:bg-red-100 transition shadow-sm">
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
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }

        var map = L.map('map', { zoomControl: false }).setView([-5.147665, 119.432731], 12);
        L.control.zoom({ position: 'topright' }).addTo(map);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '' }).addTo(map);

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
                if (!unit.latitude) return;
                const lat = parseFloat(unit.latitude);
                const lng = parseFloat(unit.longitude);
                const isActive = unit.speed > 0;
                
                const iconHtml = `
                    <div style="transform: rotate(${unit.course || 0}deg);" class="relative w-9 h-9 rounded-full bg-white border-2 ${isActive ? 'border-green-500' : 'border-red-500'} shadow-lg flex items-center justify-center transition-all duration-500">
                        <i class="fa-solid fa-location-arrow text-[12px] ${isActive ? 'text-green-600' : 'text-red-500'}"></i>
                        ${isActive ? '<div class="pulse-ring"></div>' : ''}
                    </div>
                `;

                const customIcon = L.divIcon({
                    className: 'custom-pin',
                    html: iconHtml,
                    iconSize: [36, 36],
                    iconAnchor: [18, 18]
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
            
            if (data.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center text-gray-400 text-sm flex flex-col items-center justify-center h-full">
                        <i class="fa-solid fa-car mb-3 text-2xl text-gray-300"></i>
                        <p>Belum ada kendaraan.</p>
                        <p class="text-xs mt-1">Klik tombol (+) untuk menambah.</p>
                    </div>`;
                return;
            }

            data.forEach(unit => {
                const hasPosition = unit.latitude != null;
                let speedText, timeText, statusColor, onClickAction;

                if (hasPosition) {
                    const isOnline = unit.speed > 0;
                    statusColor = isOnline ? 'text-green-600' : 'text-red-500';
                    speedText = `${unit.speed} km/h`;
                    timeText = formatTime(unit.gps_time);
                    const lat = parseFloat(unit.latitude);
                    const lng = parseFloat(unit.longitude);
                    onClickAction = `focusUnit('${unit.imei}', ${lat}, ${lng})`;
                } else {
                    statusColor = 'text-gray-400';
                    speedText = 'Menunggu Sinyal';
                    timeText = '-';
                    onClickAction = "alert('Perangkat ini belum aktif.')";
                }

                const bgClass = selectedImei == unit.imei ? 'bg-blue-50 border-l-4 border-blue-500' : 'hover:bg-gray-50 border-l-4 border-transparent';

                html += `
                <div onclick="${onClickAction}" class="p-4 border-b border-gray-100 cursor-pointer transition ${bgClass} active:bg-gray-100">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full ${hasPosition && unit.speed > 0 ? 'bg-green-500' : 'bg-red-400'}"></div>
                            <div>
                                <h4 class="font-bold text-gray-700 text-sm leading-tight">${unit.name || unit.imei}</h4>
                                <p class="text-xs text-gray-500 mt-0.5 font-mono">${unit.plate_number || '-'}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-xs ${statusColor}">${speedText}</p>
                            <p class="text-[10px] text-gray-400 mt-1">${timeText}</p>
                        </div>
                    </div>
                </div>
                `;
            });
            container.innerHTML = html;
        }

        function updateStats(data) {
            document.getElementById('stat-total').innerText = data.length;
            document.getElementById('stat-online').innerText = data.filter(d => d.latitude && d.speed > 0).length;
            document.getElementById('stat-offline').innerText = data.filter(d => d.latitude && d.speed == 0).length;
        }

        function focusUnit(imei, lat, lng) {
            selectedImei = imei;
            if (window.innerWidth < 768) toggleSidebar();
            map.flyTo([lat, lng], 16);
            fetch('/api/gps-data').then(r => r.json()).then(data => {
                const unit = data.find(d => d.imei == imei);
                if(unit) openDetail(unit);
            });
        }

        function openDetail(unit) {
            selectedImei = unit.imei;
            renderDetail(unit);
            document.getElementById('detail-panel').classList.remove('translate-y-[150%]', 'translate-y-[110%]');
        }

        function renderDetail(unit) {
            document.getElementById('detail-name').innerText = unit.name || unit.imei;
            document.getElementById('detail-plate').innerText = unit.plate_number || '-';
            
            if (unit.latitude) {
                document.getElementById('detail-speed').innerText = unit.speed;
                document.getElementById('detail-time').innerText = formatTime(unit.gps_time);
                
                const statusEl = document.getElementById('detail-status');
                if (unit.speed > 0) {
                    statusEl.innerText = "Bergerak";
                    statusEl.className = "font-bold text-green-600";
                } else {
                    statusEl.innerText = "Parkir / Diam";
                    statusEl.className = "font-bold text-red-500";
                }
            } else {
                document.getElementById('detail-status').innerText = "Menunggu Sinyal";
            }
        }

        function closeDetail() {
            // Gunakan translate yang sesuai mobile/desktop
            const panel = document.getElementById('detail-panel');
            if (window.innerWidth < 768) {
                panel.classList.add('translate-y-[110%]');
            } else {
                panel.classList.add('translate-y-[150%]');
            }
            selectedImei = null;
        }

        function goToHistory() {
            if (selectedImei) window.location.href = '/device/' + selectedImei + '/history';
        }

        function formatTime(dateString) {
            if(!dateString) return '-';
            // TIMEZONE FIX: Paksa format UTC 'Z' di akhir agar browser konversi ke lokal (WITA)
            const safeDateString = dateString.replace(' ', 'T') + 'Z';
            const date = new Date(safeDateString);
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute:'2-digit', hour12: false });
        }

        updateData();
        setInterval(updateData, 3000);
    </script>
</body>
</html>