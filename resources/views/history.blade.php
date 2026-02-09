<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History: {{ $device->name }} - Prima GPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 h-screen flex flex-col">

    <nav class="bg-slate-900 text-white p-4 flex justify-between items-center shadow-lg">
        <div class="flex items-center gap-3">
            <a href="/" class="hover:text-blue-400"><i class="fa-solid fa-arrow-left text-xl"></i></a>
            <div>
                <h1 class="font-bold text-sm uppercase">{{ $device->name }}</h1>
                <p class="text-[10px] text-slate-400">Riwayat Perjalanan Hari Ini</p>
            </div>
        </div>
        <div class="text-right">
            <span class="text-xs font-mono bg-slate-800 px-3 py-1 rounded-full">{{ $device->plate_number }}</span>
        </div>
    </nav>

    <div id="map" class="flex-1"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        fetch('/api/history/{{ $device->imei }}')
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    alert('Tidak ada data riwayat untuk hari ini.');
                    return;
                }

                var latlngs = data.map(pos => [parseFloat(pos.latitude), parseFloat(pos.longitude)]);
                
                // Buat Garis Jalur (Polyline)
                var polyline = L.polyline(latlngs, {color: '#3b82f6', weight: 5, opacity: 0.7}).addTo(map);
                
                // Tandai Start & Finish
                L.marker(latlngs[0]).addTo(map).bindPopup("Mulai");
                L.marker(latlngs[latlngs.length - 1]).addTo(map).bindPopup("Posisi Terakhir");

                // Zoom otomatis ke jalur
                map.fitBounds(polyline.getBounds());
            });
    </script>
</body>
</html>