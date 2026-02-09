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

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-white shadow-2xl z-40 transform -translate-x-full md:translate-x-0 md:relative md:shadow-xl transition-transform duration-300 ease-in-out flex flex-col h-full">
        <div class="p-5 border-b border-gray-100 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center font-bold">P</div>
                <div>
                    <h1 class="font-bold tracking-wide uppercase">Prima GPS</h1>
                    <p class="text-[10px] text-blue-200">Fleet Management</p>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto no-scrollbar px-2 py-3 space-y-2" id="vehicle-list">
            <!-- List unit akan muncul di sini via JS -->
        </div>
        
        <div class="p-4 border-t">
            <a href="{{ route('devices.index') }}" class="w-full flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-xl font-bold text-sm transition">
                <i class="fa-solid fa-gears"></i> Kelola Armada
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 relative">
        <div id="map"></div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map', { zoomControl: false }).setView([-5.147665, 119.432731], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        var markers = {};

        function updateData() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    data.forEach(unit => {
                        if (!unit.latitude || !unit.longitude) return;
                        const lat = parseFloat(unit.latitude);
                        const lng = parseFloat(unit.longitude);
                        
                        const iconHtml = `<div class="marker-label"><b>${unit.name}</b></div>`;
                        const customIcon = L.divIcon({ html: iconHtml, className: 'custom-pin' });

                        if (markers[unit.imei]) {
                            markers[unit.imei].setLatLng([lat, lng]);
                        } else {
                            markers[unit.imei] = L.marker([lat, lng], {icon: customIcon}).addTo(map);
                        }
                    });
                });
        }

        setInterval(updateData, 5000);
        updateData();
    </script>
</body>
</html>