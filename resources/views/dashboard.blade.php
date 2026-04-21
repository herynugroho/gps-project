<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PRIMA GPS - Monitoring System</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; overflow: hidden; height: 100vh; margin: 0; }
        #map { height: 100%; width: 100%; z-index: 0; }
        .custom-icon { background: none; border: none; }
        .marker-label-container { display: flex; flex-direction: column; align-items: center; position: relative; bottom: 30px; }
        .marker-label { background: white; border: 2px solid #0f172a; border-radius: 6px; padding: 2px 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); white-space: nowrap; font-weight: 800; font-size: 11px; color: #0f172a; text-transform: uppercase; display: flex; align-items: center; gap: 4px; }
        .marker-dot { width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
        .status-moving { background-color: #22c55e; }
        .status-acc-on { background-color: #3b82f6; }
        .status-stop { background-color: #ef4444; }
        .pulse { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation { 0% { transform: scale(0.9); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }
        #detail-panel { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1001; }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden relative">

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white border-r border-slate-200 flex flex-col z-[1002] transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300 shadow-2xl md:shadow-none">
        <div class="p-6 bg-slate-900 text-white shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl shadow-lg">P</div>
                <div>
                    <h1 class="font-black text-sm uppercase tracking-wider leading-none">PRIMA GPS</h1>
                    <p class="text-[9px] text-blue-300 mt-1 uppercase font-bold italic">Monitoring System</p>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3" id="unit-list">
             <p class="text-center text-slate-400 text-xs py-10">Mencari armada...</p>
        </div>

        <div class="p-4 border-t">
            <a href="/devices" class="flex items-center justify-center gap-2 w-full py-4 bg-slate-900 rounded-2xl text-[10px] font-black text-white hover:bg-slate-800 transition uppercase">
                <i class="fa-solid fa-gear text-blue-400"></i> Kelola Armada
            </a>
        </div>
    </aside>

    <!-- MAIN MAP -->
    <main class="flex-1 relative flex flex-col h-full">
        <div class="absolute top-4 left-4 z-[500] md:hidden">
            <button onclick="toggleSidebar()" class="bg-white p-3 rounded-xl shadow-xl border border-slate-100">
                <i class="fa-solid fa-bars-staggered text-slate-900"></i>
            </button>
        </div>
        <div id="map"></div>

        <!-- DETAIL PANEL -->
        <div id="detail-panel" class="absolute bottom-0 left-0 right-0 md:left-auto md:right-6 md:bottom-6 md:w-96 bg-white shadow-2xl p-7 transform translate-y-[120%] rounded-t-[2.5rem] md:rounded-3xl border-t md:border border-slate-100 pb-12 md:pb-7">
            <div class="w-12 h-1.5 bg-slate-100 rounded-full mx-auto mb-6 md:hidden"></div>
            
            <div class="flex items-center gap-5 mb-6">
                <div id="det-icon-bg" class="w-16 h-16 bg-green-500 rounded-2xl flex items-center justify-center text-white text-3xl shadow-xl">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div class="flex-1">
                    <h2 class="font-black text-slate-800 uppercase leading-none text-xl truncate" id="det-name">-</h2>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-[10px] text-slate-400 font-mono font-bold bg-slate-50 px-2 py-0.5 rounded" id="det-plate">-</span>
                        <span id="det-module-badge" class="text-[8px] font-black px-2 py-0.5 rounded-full bg-slate-900 text-white uppercase italic">GT06N 4G</span>
                    </div>
                </div>
                <button onclick="closeDetail()" class="bg-slate-100 w-10 h-10 rounded-full flex items-center justify-center text-slate-300 hover:text-red-500 transition">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-slate-50 p-5 rounded-[1.5rem] border border-slate-100 text-center shadow-sm">
                    <p class="text-[9px] text-slate-400 uppercase font-black mb-2 tracking-widest">Status Mesin</p>
                    <div id="det-acc" class="flex items-center justify-center gap-2 font-black text-xs text-blue-600">
                        <i class="fa-solid fa-key"></i> <span>MESIN HIDUP</span>
                    </div>
                </div>
                <div class="bg-slate-50 p-5 rounded-[1.5rem] border border-slate-100 text-center shadow-sm">
                    <p class="text-[9px] text-slate-400 uppercase font-black mb-2 tracking-widest">Kecepatan</p>
                    <p class="font-black text-2xl text-slate-800"><span id="det-speed">0</span> <small class="text-[10px] text-slate-400 font-normal">km/h</small></p>
                </div>
            </div>

            <!-- Box Sinyal Terakhir -->
            <div class="border-2 border-dashed border-blue-50 bg-blue-50/20 rounded-2xl p-4 flex justify-between items-center mb-6">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-clock-rotate-left text-blue-400 text-sm"></i>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sinyal Terakhir:</span>
                </div>
                <span id="det-time" class="text-[11px] font-black text-slate-600">--:--:-- WITA</span>
            </div>

            <div class="flex flex-col gap-3">
                <button onclick="toggleRelay()" class="w-full bg-red-500 hover:bg-red-600 text-white py-5 rounded-[1.5rem] text-[11px] font-black shadow-xl flex items-center justify-center gap-3 uppercase transition active:scale-95">
                    <i class="fa-solid fa-power-off"></i> Matikan Mesin (Relay)
                </button>
                <button onclick="goToHistory()" class="w-full bg-slate-900 text-white py-5 rounded-[1.5rem] text-[11px] font-black shadow-xl flex items-center justify-center gap-3 uppercase transition active:scale-95">
                    <i class="fa-solid fa-route"></i> Riwayat Perjalanan
                </button>
            </div>
        </div>
    </main>

    <script>
        var sidebar = document.getElementById('sidebar');
        var markers = {};
        var selectedImei = null;
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'Prima GPS System' }).addTo(map);

        function toggleSidebar() { sidebar.classList.toggle('-translate-x-full'); }

        // FUNGSI BARU: NATIVE WITA MURNI
        function formatWita(gpsTime) {
            if(!gpsTime) return "--:--:-- WITA";
            
            // Format DB selalu "YYYY-MM-DD HH:MM:SS"
            // Kita potong dari karakter indeks ke-11 sebanyak 8 karakter
            // Contoh: "2026-04-21 16:56:00" -> "16:56:00"
            // Lalu kita ubah tanda ':' jadi '.' biar estetik (16.56.00 WITA)
            try {
                let timeStr = gpsTime.substring(11, 19).replace(/:/g, '.');
                return timeStr + " WITA";
            } catch(e) {
                return "--:--:-- WITA";
            }
        }

        function updateUI() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    let listHtml = '';
                    data.forEach(unit => {
                        const lat = unit.latitude ? parseFloat(unit.latitude) : null;
                        const lng = unit.longitude ? parseFloat(unit.longitude) : null;
                        const speed = Math.round(unit.speed || 0);
                        const accOn = unit.acc_status == 1;
                        const isGT06N = unit.module_type === 'GT06N';

                        let statusColor = (speed >= 5) ? 'status-moving' : (accOn ? 'status-acc-on' : 'status-stop');
                        
                        listHtml += `
                            <div onclick="focusUnit('${unit.imei}', ${lat}, ${lng})" 
                                class="p-4 border-2 rounded-2xl transition-all cursor-pointer ${selectedImei === unit.imei ? 'border-blue-500 bg-blue-50' : 'border-slate-50 bg-white hover:border-blue-200'}">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-black text-slate-800 uppercase text-[11px] leading-tight">${unit.name}</h4>
                                        <span class="text-[8px] font-bold text-slate-400 uppercase">${unit.plate_number}</span>
                                    </div>
                                    <i class="fa-solid fa-key text-[10px] ${accOn ? 'text-blue-500' : 'text-slate-200'}"></i>
                                </div>
                                <div class="flex justify-between items-center mt-3">
                                    ${lat ? `<span class="text-[8px] font-black px-2 py-1 rounded-lg ${speed >= 5 ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-500'}">${speed >= 5 ? speed + ' KM/H' : 'BERHENTI'}</span>` : 
                                    `<span class="text-[7px] font-black px-2 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-100 animate-pulse">MENUNGGU GPS...</span>`}
                                    <span class="text-[7px] font-bold text-slate-300 uppercase">${isGT06N ? 'GT06N-4G' : 'STD'}</span>
                                </div>
                            </div>
                        `;

                        if (lat && lng) {
                            const iconHtml = `<div class="marker-label-container"><div class="marker-label"><i class="fa-solid fa-key" style="color:${accOn ? '#3b82f6' : '#cbd5e1'}"></i>${unit.name}</div><div class="marker-dot ${statusColor} relative flex items-center justify-center">${speed >= 5 ? '<div class="absolute inset-0 bg-green-500 rounded-full pulse"></div>' : ''}</div></div>`;
                            const customIcon = L.divIcon({ className: 'custom-icon', html: iconHtml, iconSize: [120, 50], iconAnchor: [60, 45] });
                            if (markers[unit.imei]) { 
                                markers[unit.imei].setLatLng([lat, lng]).setIcon(customIcon); 
                            } else { 
                                markers[unit.imei] = L.marker([lat, lng], {icon: customIcon}).addTo(map).on('click', () => focusUnit(unit.imei, lat, lng)); 
                            }
                        }
                        if (selectedImei === unit.imei) updateDetail(unit);
                    });
                    document.getElementById('unit-list').innerHTML = listHtml;
                });
        }

        function focusUnit(imei, lat, lng) {
            selectedImei = imei;
            if (lat) map.flyTo([lat, lng], 17);
            document.getElementById('detail-panel').classList.remove('translate-y-[120%]');
            if (window.innerWidth < 768) sidebar.classList.add('-translate-x-full');
            updateUI();
        }

        function updateDetail(unit) {
            document.getElementById('det-name').innerText = unit.name;
            document.getElementById('det-plate').innerText = unit.plate_number;
            document.getElementById('det-speed').innerText = Math.round(unit.speed || 0);
            
            // Mengirim data dari DB langsung untuk di-format murni WITA
            document.getElementById('det-time').innerText = formatWita(unit.gps_time || unit.last_online);
            
            document.getElementById('det-module-badge').innerText = unit.module_type === 'GT06N' ? 'GT06N 4G' : 'STANDARD';
            
            const accEl = document.getElementById('det-acc');
            const iconBg = document.getElementById('det-icon-bg');
            
            if (unit.acc_status == 1) { 
                accEl.className = "flex items-center justify-center gap-2 font-black text-xs text-blue-600"; 
                accEl.innerHTML = '<i class="fa-solid fa-key"></i> <span>MESIN HIDUP</span>'; 
                iconBg.className = "w-16 h-16 bg-green-500 rounded-2xl flex items-center justify-center text-white text-3xl shadow-xl";
            } else { 
                accEl.className = "flex items-center justify-center gap-2 font-black text-xs text-slate-400"; 
                accEl.innerHTML = '<i class="fa-solid fa-key"></i> <span>MESIN MATI</span>'; 
                iconBg.className = "w-16 h-16 bg-slate-300 rounded-2xl flex items-center justify-center text-white text-3xl shadow-xl";
            }
        }

        function closeDetail() { document.getElementById('detail-panel').classList.add('translate-y-[120%]'); selectedImei = null; }
        function goToHistory() { if (selectedImei) window.location.href = `/device/${selectedImei}/history`; }
        function toggleRelay() { if(confirm('⚠️ Matikan mesin sekarang?')) alert('Command dikirim!'); }

        setInterval(updateUI, 5000);
        updateUI();
    </script>
</body>
</html>