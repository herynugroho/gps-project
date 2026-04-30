<!DOCTYPE html>
<html lang="id">
<head>
    <!-- PWA Setup -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="/icon.png">
    
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Login - PrimaTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            -webkit-tap-highlight-color: transparent;
        }
        
        @keyframes radar-ripple {
            0% { transform: scale(0.5); opacity: 0.8; border-width: 2px; }
            100% { transform: scale(3.5); opacity: 0; border-width: 1px; }
        }
        .radar-ring {
            position: absolute;
            border: solid rgba(59, 130, 246, 0.4);
            border-radius: 50%;
            width: 80px;
            height: 80px;
            animation: radar-ripple 4s infinite cubic-bezier(0.1, 0.4, 0.8, 1);
        }
        @media (min-width: 1024px) {
            .radar-ring { width: 120px; height: 120px; }
        }
        .radar-ring:nth-child(1) { animation-delay: 0s; }
        .radar-ring:nth-child(2) { animation-delay: 1.33s; }
        .radar-ring:nth-child(3) { animation-delay: 2.66s; }

        .clean-input:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
        }

        /* Glassmorphism effect for mobile logo container */
        .glass-box {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row text-slate-900 antialiased selection:bg-blue-200 selection:text-blue-900 overflow-x-hidden">

    <!-- BRANDING SECTION (Top on Mobile, Left on Desktop) -->
    <div class="w-full lg:w-1/2 h-[35vh] lg:h-screen bg-[#0B1120] relative flex flex-col justify-center items-center lg:items-start lg:p-16 overflow-hidden shrink-0">
        <!-- Background Decor -->
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_var(--tw-gradient-stops))] from-slate-800/40 via-[#0B1120] to-[#0B1120]"></div>
        
        <!-- Radar Animation -->
        <div class="absolute inset-0 flex items-center justify-center opacity-40 lg:opacity-100">
            <div class="relative flex items-center justify-center w-48 h-48 lg:w-64 lg:h-64">
                <div class="radar-ring"></div>
                <div class="radar-ring"></div>
                <div class="radar-ring"></div>
                <div class="relative z-20 w-12 h-12 lg:w-16 lg:h-16 bg-blue-600 rounded-full flex items-center justify-center text-white text-xl lg:text-2xl shadow-[0_0_40px_rgba(37,99,235,0.6)] border-2 border-white/20">
                    <i class="fa-solid fa-location-crosshairs"></i>
                </div>
            </div>
        </div>

        <!-- Branding Text -->
        <div class="relative z-30 text-center lg:text-left mt-32 lg:mt-0">
            <h1 class="text-4xl lg:text-7xl font-black text-white tracking-tighter">
                PrimaTrack<span class="text-blue-500">.</span>
            </h1>
            <p class="mt-2 lg:mt-4 text-blue-400/80 lg:text-slate-400 font-bold text-[10px] lg:text-base uppercase tracking-widest lg:normal-case lg:tracking-normal">
                Enterprise Fleet Monitoring
            </p>
        </div>

        <!-- Desktop Footer Info -->
        <div class="hidden lg:block relative z-10 mt-auto">
            <p class="text-slate-500 text-xs font-medium">© 2024 PrimaTrack System. Makassar, Indonesia.</p>
        </div>
    </div>

    <!-- LOGIN FORM SECTION -->
    <div class="flex-1 bg-white flex items-start lg:items-center justify-center p-8 lg:p-20 relative z-40 -mt-8 lg:mt-0 rounded-t-[2.5rem] lg:rounded-none shadow-[0_-20px_40px_rgba(0,0,0,0.2)] lg:shadow-none">
        
        <div class="w-full max-w-[380px] pt-4 lg:pt-0">
            
            <div class="mb-10 text-center lg:text-left">
                <h2 class="text-2xl lg:text-3xl font-black text-slate-900 tracking-tight mb-2 uppercase lg:normal-case">Masuk Sistem</h2>
                <p class="text-slate-500 text-sm font-medium">Gunakan kredensial akses Anda untuk memantau.</p>
            </div>

            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-2xl flex items-start gap-3 animate-pulse">
                    <i class="fa-solid fa-circle-exclamation text-red-600 mt-0.5"></i>
                    <span class="text-sm font-bold text-red-800 leading-snug">{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ url('/login') }}" class="space-y-5">
                @csrf
                
                <!-- Email Input -->
                <div class="space-y-2">
                    <label for="email" class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Email Akses</label>
                    <div class="relative clean-input border border-slate-200 rounded-2xl bg-slate-50 transition-all overflow-hidden flex items-center group">
                        <div class="pl-5 pr-2 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                            <i class="fa-solid fa-envelope text-sm"></i>
                        </div>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="w-full py-4 pr-4 bg-transparent outline-none text-sm text-slate-900 placeholder-slate-300 font-bold" 
                            placeholder="admin@primatrack.com">
                    </div>
                </div>

                <!-- Password Input -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Kata Sandi</label>
                    </div>
                    <div class="relative clean-input border border-slate-200 rounded-2xl bg-slate-50 transition-all overflow-hidden flex items-center group">
                        <div class="pl-5 pr-2 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input type="password" name="password" id="password" required
                            class="w-full py-4 pr-4 bg-transparent outline-none text-sm text-slate-900 placeholder-slate-300 font-bold" 
                            placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" class="w-4 h-4 text-blue-600 bg-slate-50 border-slate-200 rounded focus:ring-blue-500 cursor-pointer transition-colors">
                        <label for="remember" class="ml-2.5 text-xs font-bold text-slate-500 cursor-pointer select-none">
                            Ingat Saya
                        </label>
                    </div>
                    <a href="#" class="text-xs font-black text-blue-600 hover:text-blue-700 transition-colors uppercase tracking-tight">Lupa Sandi?</a>
                </div>

                <button type="submit" class="w-full mt-6 py-4 px-4 bg-slate-900 hover:bg-slate-800 text-white rounded-2xl font-black text-xs uppercase tracking-widest transition-all shadow-xl shadow-slate-200 active:scale-[0.98] flex items-center justify-center gap-2">
                    Masuk Sekarang <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </button>
            </form>

            <div class="mt-12 text-center">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Powered by PrimaTrack Enterprise</p>
            </div>

        </div>
    </div>

</body>
</html>