<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History: {{ $device->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; } #map { flex: 1; }</style>
</head>
<body class="bg-slate-50 h-screen flex flex-col">

    <nav class="bg-slate-900 text-white p-4 flex justify-between items-center shadow-lg">
        <div class="flex items-center gap-3">
            <a href="/" class="hover:text-blue-400 transition"><i class="fa-solid fa-arrow-left text-xl"></i></a>
            <div><h1 class="font-black text-sm uppercase tracking-tight">{{ $device->name }}</h1><p class="text-[9px] text-slate-400 uppercase tracking-widest">Riwayat Perjalanan Hari Ini</p></div>
        </div>
        <div class="text-right font-mono text-xs bg-slate-800 px-3 py-1 rounded-full border border-slate-700 font-bold uppercase tracking-tighter">{{ $device->plate_number }}</div>
    </nav>

    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([-5.147, 119.432], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        fetch('/api/history/{{ $device->imei }}')
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    alert('Tidak ada data pergerakan untuk hari ini.');
                    return;
                }
                var latlngs = data.map(pos => [parseFloat(pos.latitude), parseFloat(pos.longitude)]);
                var polyline = L.polyline(latlngs, {color: '#3b82f6', weight: 5, opacity: 0.7}).addTo(map);
                L.marker(latlngs[0]).addTo(map).bindPopup("Mulai");
                L.marker(latlngs[latlngs.length - 1]).addTo(map).bindPopup("Posisi Terakhir");
                map.fitBounds(polyline.getBounds());
            });
    </script>
</body>
</html>