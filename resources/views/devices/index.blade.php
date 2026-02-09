<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Armada - Prima GPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">

    <!-- Simple Navbar -->
    <nav class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center shadow-lg">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center font-bold">P</div>
            <span class="font-bold text-lg tracking-tight uppercase">Manajemen Armada</span>
        </div>
        <div class="flex items-center gap-6">
            <a href="/" class="text-blue-300 hover:text-white transition text-sm font-bold flex items-center gap-2">
                <i class="fa-solid fa-map"></i> Dashboard Peta
            </a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-red-400 hover:text-red-300 text-sm font-bold">
                    <i class="fa-solid fa-power-off"></i> Logout
                </button>
            </form>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">Daftar Kendaraan</h2>
                <p class="text-slate-500 text-sm">Kelola informasi unit dan IMEI perangkat GPS Anda.</p>
            </div>
            <a href="{{ route('devices.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl shadow-lg shadow-blue-200 transition flex items-center justify-center gap-2 font-bold">
                <i class="fa-solid fa-plus"></i> Tambah Unit Baru
            </a>
        </div>

        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 shadow-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-check"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Nama & Plat</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">IMEI / ID Alat</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-50">
                    @forelse ($devices as $device)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800 uppercase">{{ $device->name }}</div>
                            <div class="text-xs text-slate-400 font-mono tracking-tighter">{{ $device->plate_number }}</div>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm text-slate-600">
                            {{ $device->imei }}
                        </td>
                        <td class="px-6 py-4">
                            @if($device->last_online)
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                    <span class="text-[10px] font-bold text-slate-500 uppercase">Terakhir: {{ \Carbon\Carbon::parse($device->last_online)->diffForHumans() }}</span>
                                </div>
                            @else
                                <span class="text-[10px] font-bold text-slate-300 uppercase italic">Belum Pernah Aktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form action="{{ route('devices.destroy', $device->id) }}" method="POST" onsubmit="return confirm('Hapus kendaraan ini dan semua riwayatnya?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600 p-2 hover:bg-red-50 rounded-lg transition">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="text-slate-300 mb-2 text-4xl"><i class="fa-solid fa-car-rear"></i></div>
                            <p class="text-slate-400 text-sm italic">Belum ada kendaraan terdaftar.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100">
                {{ $devices->links() }}
            </div>
        </div>
    </div>

</body>
</html>