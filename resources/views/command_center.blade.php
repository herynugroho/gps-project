<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SECURED CONFIG - Prima GPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-900 min-h-screen p-6">

    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-red-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl shadow-red-900/20">
                    <i class="fa-solid fa-terminal"></i>
                </div>
                <div>
                    <h1 class="text-white font-black text-xl uppercase tracking-tighter">Command Center</h1>
                    <p class="text-red-400 text-[10px] font-bold uppercase tracking-widest leading-none mt-1">Authorized Access Only</p>
                </div>
            </div>
            <a href="/" class="bg-slate-800 text-slate-400 px-5 py-3 rounded-xl text-[10px] font-black uppercase hover:text-white transition">Dashboard</a>
        </div>

        <!-- Unit Selector -->
        <div class="bg-slate-800 rounded-3xl p-6 border border-slate-700/50 mb-6">
            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 ml-1">Pilih Armada untuk Konfigurasi</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="unit-grid">
                <!-- Unit list populated by JS -->
                <p class="text-slate-600 text-xs italic">Memuat daftar armada...</p>
            </div>
        </div>

        <!-- Command Panel (Hidden until unit selected) -->
        <div id="control-panel" class="hidden animate-in fade-in slide-in-from-bottom-4 duration-500">
            <div class="bg-white rounded-[2.5rem] p-8 shadow-2xl">
                <div class="flex items-center gap-4 mb-8 pb-6 border-b border-slate-100">
                    <div class="w-14 h-14 bg-slate-900 rounded-2xl flex items-center justify-center text-white text-2xl" id="selected-icon">
                        <i class="fa-solid fa-car"></i>
                    </div>
                    <div>
                        <h2 class="text-slate-900 font-black text-2xl uppercase leading-none" id="selected-name">-</h2>
                        <p class="text-slate-400 text-xs font-mono font-bold mt-1" id="selected-imei">IMEI: -</p>
                    </div>
                </div>

                <div class="space-y-8">
                    <!-- Quick Actions -->
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Perintah Cepat (GPRS)</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <button onclick="sendRemoteCmd('STATUS#')" class="bg-slate-50 hover:bg-blue-50 text-slate-600 hover:text-blue-600 border border-slate-100 p-4 rounded-2xl text-[11px] font-black uppercase transition-all active:scale-95">Cek Status</button>
                            <button onclick="sendRemoteCmd('VERSION#')" class="bg-slate-50 hover:bg-blue-50 text-slate-600 hover:text-blue-600 border border-slate-100 p-4 rounded-2xl text-[11px] font-black uppercase transition-all active:scale-95">Versi Alat</button>
                            <button onclick="sendRemoteCmd('PARAM#')" class="bg-slate-50 hover:bg-blue-50 text-slate-600 hover:text-blue-600 border border-slate-100 p-4 rounded-2xl text-[11px] font-black uppercase transition-all active:scale-95">Parameter</button>
                            <button onclick="sendRemoteCmd('RESET#')" class="bg-red-50 hover:bg-red-600 text-red-600 hover:text-white border border-red-100 p-4 rounded-2xl text-[11px] font-black uppercase transition-all active:scale-95">Reboot</button>
                        </div>
                    </div>

                    <!-- Custom Console -->
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Konsol Kustom (Manual Command)</p>
                        <div class="flex gap-2">
                            <input type="text" id="custom-cmd" placeholder="Ketik perintah... (Contoh: TIMER,10,10#)" class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-red-500 outline-none uppercase">
                            <button onclick="sendCustomCmd()" class="bg-slate-900 text-white px-8 rounded-2xl active:scale-95 transition shadow-xl">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                        <p class="text-[9px] text-slate-400 mt-3 italic">*Gunakan tanda pagar (#) di akhir perintah sesuai standar Concox.</p>
                    </div>

                    <!-- Security Action -->
                    <div class="pt-6 border-t border-slate-100">
                         <p class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-4">Critical Control</p>
                         <div class="grid grid-cols-2 gap-3">
                            <button onclick="sendRemoteCmd('RELAY,1#')" class="bg-red-600 text-white py-5 rounded-2xl text-[11px] font-black uppercase shadow-xl active:scale-95 transition-all flex items-center justify-center gap-3">
                                <i class="fa-solid fa-power-off"></i> MATIKAN MESIN
                            </button>
                            <button onclick="sendRemoteCmd('RELAY,0#')" class="bg-green-600 text-white py-5 rounded-2xl text-[11px] font-black uppercase shadow-xl active:scale-95 transition-all flex items-center justify-center gap-3">
                                <i class="fa-solid fa-key"></i> HIDUPKAN MESIN
                            </button>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Toast -->
    <div id="toast" class="fixed bottom-10 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-8 py-4 rounded-full text-xs font-bold shadow-2xl opacity-0 transition-opacity pointer-events-none z-50">
        Perintah terkirim...
    </div>

    <script>
        let currentImei = null;

        // Load Units
        function loadUnits() {
            fetch('/api/gps-data')
                .then(res => res.json())
                .then(data => {
                    const grid = document.getElementById('unit-grid');
                    grid.innerHTML = data.map(u => `
                        <div onclick="selectUnit('${u.imei}', '${u.name}')" 
                             class="p-4 rounded-2xl border border-slate-700 bg-slate-800/50 hover:bg-slate-700 hover:border-slate-500 transition-all cursor-pointer group">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="text-slate-200 font-black text-xs uppercase">${u.name}</h4>
                                    <p class="text-slate-500 text-[9px] font-mono">${u.imei}</p>
                                </div>
                                <i class="fa-solid fa-chevron-right text-slate-600 group-hover:text-red-500 transition"></i>
                            </div>
                        </div>
                    `).join('');
                });
        }

        function selectUnit(imei, name) {
            currentImei = imei;
            document.getElementById('selected-name').innerText = name;
            document.getElementById('selected-imei').innerText = "IMEI: " + imei;
            document.getElementById('control-panel').classList.remove('hidden');
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.classList.remove('opacity-0');
            setTimeout(() => t.classList.add('opacity-0'), 3000);
        }

        function sendRemoteCmd(cmd) {
            if(!currentImei) return;
            
            fetch(`/api/send-command?imei=${currentImei}&command=${encodeURIComponent(cmd)}`)
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        showToast(`SUCCESS: Perintah '${cmd}' terkirim!`);
                    } else {
                        alert("ERROR: " + data.msg);
                    }
                })
                .catch(err => alert("Koneksi ke server kontrol gagal."));
        }

        function sendCustomCmd() {
            const input = document.getElementById('custom-cmd');
            if(input.value) {
                sendRemoteCmd(input.value);
                input.value = '';
            }
        }

        loadUnits();
    </script>
</body>
</html>