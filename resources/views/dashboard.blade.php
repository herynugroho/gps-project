<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prima GPS - Dashboard Monitoring</title>
    
    <!-- Tailwind CSS (Warning di console wajar untuk mode development/CDN) -->
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
        
        /* Custom Marker Pulse */
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
    </style>
</head>
<body class="bg-gray-100 h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-80 bg-white shadow-xl flex flex-col z-20 border-r border-gray-200 hidden md:flex">
        <div class="p-5 border-b border-gray-100 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center font-bold">P</div>
                <div>
                    <h1 class="font-bold tracking-wide">PRIMA GPS</h1>
                    <p class="text-xs text-blue-200">Tracking System</p>
                </div>
            </div>
            <!-- Tombol Tambah -->
            <a href="{{ route('devices.create') }}" class="bg-blue-600 hover:bg-blue-500 text-white p-2 rounded-full w-8 h-8 flex items-center justify-center transition shadow-lg" title="Tambah Armada">
                <i class="fa-solid fa-plus text-sm"></i>
            </a>
        </div>

        <div class="p-3 bg-gray-50 border-b">
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <div class="bg-white p-2 rounded shadow-sm">
                    <span class="block font-bold text-lg text-blue-600" id="stat-total">0</span> Total
                </div>
                <div class="bg-white p-2 rounded shadow-sm">
                    <span class="block font-bold text-lg text-green-600" id="stat-online">0</span> Online
                </div>
                <div class="bg-white p-2 rounded shadow-sm">
                    <span class="block font-bold text-lg text-gray-500" id="stat-offline">0</span> Offline
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto" id="vehicle-list">
            <!-- List Kendaraan akan dimuat via JS -->
            <div class="p-4 text-center text-gray-400 text-sm">
                <i class="fa-solid fa-circle-notch fa-spin text-blue-500 mr-2"></i> Memuat data...
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative">
        <!-- Floating Header Mobile -->
        <div class="absolute top-4 left-4 z-10 md:hidden pointer-events-none">
            <div class="bg-slate-900 text-white p-2 rounded shadow flex items-center gap-2 pointer-events-auto">
                <span class="font-bold px-2">PRIMA GPS</span>
            </div>
        </div>

        <!-- MAP -->
        <div id="map"></div>

        <!-- DETAIL PANEL (Slide Up) -->
        <div id="detail-panel" class="absolute bottom-6 left-6 right-6 md:left-auto md:right-6 md:w-96 bg-white rounded-xl shadow-2xl z-10 p-5 transform translate-y-[150%] transition-transform duration-300 border border-gray-100">
            <button onclick="closeDetail()" class="absolute top-3 right-3 text-gray-400 hover:text-red-500"><i class="fa-solid fa-xmark text-xl"></i></button>
            
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-xl">
                    <i class="fa-solid fa-car-side" id="detail-icon"></i>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800 text-lg" id="detail-name">-</h2>
                    <p class="text-sm text-gray-500 font-mono" id="detail-plate">-</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-gray-50 p-2 rounded">
                    <p class="text-xs text-gray-400 uppercase">Speed</p>
                    <p class="font-bold text-xl text-gray-800"><span id="detail-speed">0</span> <span class="text-xs">km/h</span></p>
                </div>
                <div class="bg-gray-50 p-2 rounded">
                    <p class="text-xs text-gray-400 uppercase">Status</p>
                    <p class="font-bold text-sm text-green-600" id="detail-status">Online</p>
                </div>
            </div>

            <div class="text-xs text-gray-400 mb-4">
                <i class="fa-regular fa-clock"></i> Update: <span id="detail-time">-</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <!-- Tombol History -->
                <button onclick="goToHistory()" class="bg-blue-600 text-white py-2 rounded text-sm font-medium hover:bg-blue-700 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-route"></i> Lihat History
                </button>
                
                <button class="bg-red-50 text-red-600 border border-red-200 py-2 rounded text-sm font-medium hover:bg-red-100 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-power-off"></i> Matikan Mesin
                </button>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // === KONFIGURASI PETA ===
        var map = L.map('map', { zoomControl: false }).setView([-5.147665, 119.432731], 12);
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© Prima GPS'
        }).addTo(map);

        var markers = {};
        var selectedImei = null;

        // === FUNGSI UTAMA (UPDATE DATA) ===
        function updateData() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    // Update tampilan jika data berhasil diambil
                    renderSidebar(data);
                    renderMap(data);
                    updateStats(data);
                    
                    // Update panel detail jika sedang terbuka
                    if (selectedImei) {
                        const unit = data.find(d => d.imei === selectedImei);
                        if(unit) renderDetail(unit);
                    }
                })
                .catch(err => console.error("Gagal mengambil data:", err));
        }

        // === RENDER MARKER DI PETA ===
        function renderMap(data) {
            data.forEach(unit => {
                // SKIP Rendering Marker jika tidak ada latitude (Device Baru / Menunggu Sinyal)
                if (!unit.latitude) return;

                const lat = parseFloat(unit.latitude);
                const lng = parseFloat(unit.longitude);
                const isActive = unit.speed > 0;
                
                // Icon HTML Custom
                const iconHtml = `
                    <div style="transform: rotate(${unit.course || 0}deg);" class="relative w-8 h-8 rounded-full bg-white border-2 ${isActive ? 'border-green-500' : 'border-red-500'} shadow-md flex items-center justify-center transition-all duration-500">
                        <i class="fa-solid fa-location-arrow text-[10px] ${isActive ? 'text-green-600' : 'text-red-500'}"></i>
                        ${isActive ? '<div class="pulse-ring"></div>' : ''}
                    </div>
                `;

                const customIcon = L.divIcon({
                    className: 'custom-pin',
                    html: iconHtml,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });

                if (markers[unit.imei]) {
                    // Update posisi marker (Smooth)
                    markers[unit.imei].setLatLng([lat, lng]);
                    markers[unit.imei].setIcon(customIcon);
                } else {
                    // Buat marker baru
                    const marker = L.marker([lat, lng], {icon: customIcon}).addTo(map);
                    marker.on('click', () => {
                        focusUnit(unit.imei, lat, lng);
                    });
                    markers[unit.imei] = marker;
                }
            });
        }

        // === RENDER LIST SIDEBAR ===
        function renderSidebar(data) {
            const container = document.getElementById('vehicle-list');
            let html = '';
            
            if (data.length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-gray-400 text-sm">Belum ada kendaraan aktif.</div>';
                return;
            }

            data.forEach(unit => {
                // Cek apakah device memiliki data posisi
                const hasPosition = unit.latitude != null;
                
                // Status dan Warna
                let speedText, timeText, statusColor, onClickAction;

                if (hasPosition) {
                    const isOnline = unit.speed > 0;
                    statusColor = isOnline ? 'text-green-600' : 'text-red-500';
                    speedText = `${unit.speed} km/h`;
                    timeText = formatTime(unit.gps_time);
                    
                    // Siapkan lat/lng untuk fungsi click
                    const lat = parseFloat(unit.latitude);
                    const lng = parseFloat(unit.longitude);
                    onClickAction = `focusUnit('${unit.imei}', ${lat}, ${lng})`;
                } else {
                    // Jika device baru (belum ada sinyal)
                    statusColor = 'text-gray-400';
                    speedText = 'Menunggu Sinyal';
                    timeText = '-';
                    onClickAction = "alert('Perangkat ini belum mengirim data lokasi GPS.')";
                }

                const bgClass = selectedImei == unit.imei ? 'bg-blue-50 border-l-4 border-blue-500' : 'hover:bg-gray-50 border-l-4 border-transparent';

                html += `
                <div onclick="${onClickAction}" class="p-3 border-b border-gray-100 cursor-pointer transition ${bgClass}">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-bold text-gray-700 text-sm">${unit.name || unit.imei}</h4>
                            <p class="text-xs text-gray-500">${unit.plate_number || 'No Plate'}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-xs ${statusColor}">${speedText}</p>
                            <p class="text-[10px] text-gray-400 mt-1">${timeText}</p>
                        </div>
                    </div>
                </div>
                `;
            });
            
            // Render ulang isi sidebar
            container.innerHTML = html;
        }

        // === UPDATE STATISTIK ===
        function updateStats(data) {
            document.getElementById('stat-total').innerText = data.length;
            // Filter hanya yang punya latitude (sudah aktif)
            document.getElementById('stat-online').innerText = data.filter(d => d.latitude && d.speed > 0).length;
            document.getElementById('stat-offline').innerText = data.filter(d => d.latitude && d.speed == 0).length;
        }

        // === INTERAKSI UI ===
        function focusUnit(imei, lat, lng) {
            selectedImei = imei; // Set IMEI terpilih
            
            // Zoom ke lokasi mobil
            map.flyTo([lat, lng], 16);
            
            // Ambil data terbaru untuk ditampilkan di detail panel
            fetch('/api/gps-data')
                .then(r => r.json())
                .then(data => {
                    const unit = data.find(d => d.imei == imei);
                    if(unit) {
                        openDetail(unit);
                    }
                });
        }

        function openDetail(unit) {
            selectedImei = unit.imei;
            renderDetail(unit);
            document.getElementById('detail-panel').classList.remove('translate-y-[150%]');
        }

        function renderDetail(unit) {
            document.getElementById('detail-name').innerText = unit.name || unit.imei;
            document.getElementById('detail-plate').innerText = unit.plate_number || '-';
            
            // Cek ketersediaan data posisi
            if (unit.latitude) {
                document.getElementById('detail-speed').innerText = unit.speed;
                document.getElementById('detail-time').innerText = formatTime(unit.gps_time);
                
                const statusEl = document.getElementById('detail-status');
                if (unit.speed > 0) {
                    statusEl.innerText = "Bergerak";
                    statusEl.className = "font-bold text-sm text-green-600";
                } else {
                    statusEl.innerText = "Parkir / Diam";
                    statusEl.className = "font-bold text-sm text-red-500";
                }
            } else {
                // Tampilan untuk device baru
                document.getElementById('detail-speed').innerText = "0";
                document.getElementById('detail-time').innerText = "-";
                const statusEl = document.getElementById('detail-status');
                statusEl.innerText = "Menunggu Sinyal GPS...";
                statusEl.className = "font-bold text-sm text-gray-400";
            }
        }

        function closeDetail() {
            document.getElementById('detail-panel').classList.add('translate-y-[150%]');
            selectedImei = null;
        }

        function goToHistory() {
            if (selectedImei) {
                window.location.href = '/device/' + selectedImei + '/history';
            }
        }

        function formatTime(dateString) {
            if(!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
        }

        // === JALANKAN LOOPING ===
        // Update pertama kali
        updateData();
        
        // Update setiap 2 detik
        setInterval(updateData, 2000);

    </script>
</body>
</html>