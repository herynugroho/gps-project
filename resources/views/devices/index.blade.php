<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Manajemen Armada - PRIMA TRACK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            padding-bottom: env(safe-area-inset-bottom, 1.5rem);
            -webkit-tap-highlight-color: transparent;
        }
        nav {
            padding-top: max(0.85rem, env(safe-area-inset-top));
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <nav class="bg-[#0b1329] border-b border-slate-800 text-white px-4 sm:px-8 py-3.5 flex justify-between items-center shadow-lg sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <svg viewBox="0 0 512 512" class="w-8 h-8 rounded-lg shadow-md shadow-blue-500/20 shrink-0">
                <rect width="512" height="512" fill="#0B1120" />
                <g transform="translate(-19, -29)">
                    <path d="M120 110 h 120 c 71.8 0 130 58.2 130 130 v 0 c 0 71.8 -58.2 130 -130 130 h -50 v 90 c 0 11.05 -8.95 20 -20 20 h -40 c -11.05 0 -20 -8.95 -20 -20 V 130 c 0 -11.05 8.95 -20 20 -20 z M 190 190 v 100 h 50 c 27.6 0 50 -22.4 50 -50 v 0 c 0 -27.6 -22.4 -50 -50 -50 h -50 z" fill="#FFFFFF" />
                    <circle cx="410" cy="440" r="40" fill="#3B82F6" />
                </g>
            </svg>
            <div>
                <span class="font-black tracking-tight uppercase text-sm leading-none block">Prima Track<span class="text-blue-500">.</span></span>
                <span class="text-[9px] font-bold text-blue-400 uppercase tracking-widest leading-none mt-0.5 block">Manajemen Armada</span>
            </div>
        </div>
        <a href="/" class="bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-3.5 py-2 rounded-xl transition text-xs font-bold uppercase flex items-center gap-2 border border-slate-700 active:scale-95">
            <i class="fa-solid fa-map"></i> <span class="hidden sm:inline">Peta Monitoring</span>
        </a>
    </nav>

    <div class="container mx-auto px-4 py-6 sm:py-8 max-w-5xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 sm:mb-8">
            <div>
                <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">DAFTAR ARMADA KENDARAAN</h2>
                <p class="text-slate-400 text-xs font-medium mt-0.5">Kelola perangkat GPS dan unit operasional lapangan</p>
            </div>
            <a href="{{ route('devices.create') }}" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl shadow-lg shadow-blue-500/25 transition font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 active:scale-95">
                <i class="fa-solid fa-plus"></i> Tambah Unit
            </a>
        </div>

        @if (session('success'))
            <div class="bg-emerald-500 text-white px-4 py-3.5 rounded-2xl mb-6 font-bold text-xs shadow-md flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- MOBILE CARDS VIEW (Visible on small screens) -->
        <div class="block sm:hidden space-y-3 mb-6">
            @forelse ($devices as $device)
            <div class="bg-white p-4 rounded-2xl border border-slate-200/70 shadow-sm flex flex-col gap-3">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-black text-slate-800 uppercase text-sm leading-tight">{{ $device->name }}</h3>
                        <div class="inline-block bg-slate-100 text-slate-600 font-mono font-black text-[10px] px-2 py-0.5 rounded-md mt-1 border border-slate-200 uppercase">
                            {{ $device->plate_number }}
                        </div>
                    </div>
                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full {{ $device->module_type === 'GT06N' ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-500' }}">
                        {{ $device->module_type ?? 'STANDAR' }}
                    </span>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100">
                    <div class="font-mono text-[11px] text-slate-400">
                        <span class="text-[9px] uppercase font-bold text-slate-300 block">IMEI</span>
                        {{ $device->imei }}
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('devices.history', $device->imei) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-600 font-bold text-xs hover:bg-blue-100 transition active:scale-95">
                            <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
                        </a>
                        <form action="{{ route('devices.destroy', $device->id) }}" method="POST" onsubmit="return confirm('Hapus unit ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-8 h-8 inline-flex items-center justify-center rounded-xl bg-red-50 text-red-500 hover:bg-red-100 transition active:scale-95" title="Hapus">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="bg-white p-8 rounded-2xl border border-slate-100 text-center text-slate-300 text-xs italic">
                Belum ada kendaraan yang terdaftar.
            </div>
            @endforelse
        </div>

        <!-- DESKTOP TABLE VIEW (Visible on >= 640px) -->
        <div class="hidden sm:block bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Nama & Plat</th>
                        <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Tipe Modul</th>
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
                        <td class="px-6 py-4">
                            <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full {{ $device->module_type === 'GT06N' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-500' }}">
                                {{ $device->module_type ?? 'STANDAR' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm text-slate-600">
                            {{ $device->imei }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('devices.history', $device->imei) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-600 font-bold text-xs hover:bg-blue-600 hover:text-white transition active:scale-95">
                                    <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
                                </a>
                                <form action="{{ route('devices.destroy', $device->id) }}" method="POST" onsubmit="return confirm('Hapus unit ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-8 h-8 inline-flex items-center justify-center rounded-xl bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition active:scale-95" title="Hapus Unit">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-slate-300 italic">Belum ada kendaraan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $devices->links() }}
        </div>
    </div>
</body>
</html>