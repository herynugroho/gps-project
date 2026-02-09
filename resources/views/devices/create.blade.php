<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Armada - Prima GPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white w-full max-w-md rounded-3xl shadow-xl overflow-hidden border border-slate-100">
        <div class="bg-slate-900 p-6 text-white flex items-center justify-between">
            <h2 class="text-xl font-bold">Tambah Kendaraan</h2>
            <a href="{{ route('devices.index') }}" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></a>
        </div>

        <form action="{{ route('devices.store') }}" method="POST" class="p-8 space-y-6">
            @csrf
            
            @if ($errors->any())
                <div class="bg-red-50 text-red-500 p-4 rounded-xl text-xs font-bold border border-red-100">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Nomor IMEI Perangkat</label>
                <input type="text" name="imei" placeholder="Contoh: 3552280..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-mono" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Nama Kendaraan</label>
                <input type="text" name="name" placeholder="Contoh: Avanza Putih" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Nomor Plat (DD)</label>
                <input type="text" name="plate_number" placeholder="Contoh: DD 1234 XX" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition uppercase" required>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-100 transition-all active:scale-95">
                    Simpan Kendaraan
                </button>
            </div>
        </form>
    </div>

</body>
</html>