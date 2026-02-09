<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PRIMA GPS - Makassar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #map { height: 100vh; width: 100%; z-index: 0; }
        .marker-label {
            background: white; border: 2px solid #0f172a; border-radius: 6px;
            padding: 2px 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            white-space: nowrap; font-weight: bold; font-size: 11px;
            position: relative; bottom: 25px; color: #0f172a;
        }
        .marker-pin {
            width: 14px; height: 14px; border-radius: 50%;
            border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        .bg-moving { background-color: #22c55e; box-shadow: 0 0 10px #22c55e; }
        .bg-stop { background-color: #ef4444; }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-80 bg-white border-r border-slate-200 flex flex-col z-10 shadow-xl">
        <div class="p-6 bg-slate-900 text-white flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center font-bold text-xl">P</div>
            <div>
                <h1 class="font-bold uppercase tracking-wider">PRIMA GPS</h1>
                <p class="text-[10px] text-blue-300">Live Tracking System</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3" id="unit-list">
            <p class="text-center text-slate-400 text-sm py-10">Mencari armada...</p>
        </div>

        <div class="p-4 border-t bg-slate-50">
            <a href="{{ route('devices.index') }}" class="flex items-center justify-center gap-2 bg-white border border-slate-200 py-3 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-100 transition">
                <i class="fa-solid fa-list-check"></i> Kelola Armada
            </a>
        </div>
    </aside>

    <!-- Main Map -->
    <main class="flex-1 relative">
        <div id="map"></div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false }).setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var markers = {};

        function updateData() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    let listHtml = '';
                    data.forEach(unit => {
                        if (!unit.latitude || !unit.longitude) return;
                        
                        const lat = parseFloat(unit.latitude);
                        const lng = parseFloat(unit.longitude);
                        const isMoving = unit.speed > 0.1;
                        const statusColor = isMoving ? 'bg-moving' : 'bg-stop';

                        // Update Sidebar List
                        listHtml += `
                            <div class="p-4 bg-white border border-slate-100 rounded-2xl shadow-sm hover:border-blue-300 transition cursor-pointer">
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="font-bold text-slate-800 uppercase text-sm">${unit.name}</h4>
                                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full ${isMoving ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'}">
                                        ${isMoving ? Math.round(unit.speed) + ' KM/H' : 'BERHENTI'}
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-400 font-mono">${unit.plate_number}</p>
                            </div>
                        `;

                        // Update Map Marker with Label
                        const iconHtml = `
                            <div class="flex flex-col items-center">
                                <div class="marker-label">${unit.name}</div>
                                <div class="marker-pin ${statusColor}"></div>
                            </div>
                        `;

                        const customIcon = L.divIcon({
                            className: 'custom-div-icon',
                            html: iconHtml,
                            iconSize: [100, 40],
                            iconAnchor: [50, 40]
                        });

                        if (markers[unit.imei]) {
                            markers[unit.imei].setLatLng([lat, lng]);
                            markers[unit.imei].setIcon(customIcon);
                        } else {
                            markers[unit.imei] = L.marker([lat, lng], {icon: customIcon}).addTo(map);
                        }
                    });
                    document.getElementById('unit-list').innerHTML = listHtml;
                });
        }

        setInterval(updateData, 5000);
        updateData();
    </script>
</body>
</html>