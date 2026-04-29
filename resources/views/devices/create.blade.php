<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Armada - PRIMA TRACK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl p-8 border border-slate-100">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">REGISTRASI ARMADA</h2>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Pilih Jenis Modul Yang Sesuai</p>
        </div>

        <form action="{{ route('devices.store') }}" method="POST" class="space-y-5">
            @csrf
            
            <!-- DROPDOWN JENIS MODUL -->
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Jenis Modul GPS</label>
                <select name="module_type" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none transition font-bold text-slate-700 cursor-pointer">
                    <option value="STANDARD">MODUL STANDAR (LAMA / GT02A)</option>
                    <option value="GT06N">CONCOX GT06N 4G (ACC & RELAY)</option>
                </select>
                <p class="text-[9px] text-blue-500 mt-2 italic font-medium">*GT06N mendukung fitur deteksi mesin & matikan mesin jarak jauh.</p>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Nomor IMEI</label>
                <input type="text" name="imei" placeholder="15 Digit IMEI" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none transition font-mono text-sm" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Nama Kendaraan</label>
                <input type="text" name="name" placeholder="Contoh: Truck Hino BTP" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none transition font-bold" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Nomor Plat</label>
                <input type="text" name="plate_number" placeholder="Contoh: DD 8888 XX" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none transition font-bold uppercase" required>
            </div>

            <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-95 text-xs uppercase tracking-widest">
                SIMPAN PERANGKAT
            </button>
            
            <a href="{{ route('devices.index') }}" class="block text-center text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-4">Kembali ke Daftar</a>
        </form>
    </div>

</body>
</html>