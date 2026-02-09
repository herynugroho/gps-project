<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Armada - Prima GPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">

    <nav class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center shadow-lg">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center font-bold">P</div>
            <span class="font-bold tracking-tight uppercase">Manajemen Armada</span>
        </div>
        <a href="/" class="text-blue-300 hover:text-white transition text-xs font-bold uppercase flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Dashboard Peta
        </a>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-2xl font-black text-slate-800">DAFTAR KENDARAAN</h2>
            <a href="{{ route('devices.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl shadow-lg transition font-bold text-sm flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> TAMBAH UNIT
            </a>
        </div>

        @if (session('success'))
            <div class="bg-green-500 text-white p-4 rounded-xl mb-6 font-bold shadow-md">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Nama & Plat</th>
                        <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">IMEI Perangkat</th>
                        <th class="px-6 py-4 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-50">
                    @forelse ($devices as $device)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-6 py-4">
                            <div class="font-black text-slate-800 uppercase text-sm">{{ $device->name }}</div>
                            <div class="text-[10px] text-slate-400 font-mono font-bold">{{ $device->plate_number }}</div>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm text-slate-600">
                            {{ $device->imei }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form action="{{ route('devices.destroy', $device->id) }}" method="POST" onsubmit="return confirm('Hapus unit ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600 p-2 rounded-lg transition"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-6 py-12 text-center text-slate-300 italic">Belum ada kendaraan.</td></tr>
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