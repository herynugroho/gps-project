<!DOCTYPE html>
<html lang="id">
<head>
    <!-- PWA Setup -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e5/GPS_Icon.svg/512px-GPS_Icon.svg.png">
    
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - PrimaTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', sans-serif; /* Menggunakan Inter untuk kesan SaaS yang lebih kaku & profesional */
            background-color: #ffffff;
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Animasi Radar GPS yang elegan dan mulus */
        @keyframes radar-ripple {
            0% { transform: scale(0.5); opacity: 0.8; border-width: 2px; }
            100% { transform: scale(3.5); opacity: 0; border-width: 1px; }
        }
        .radar-ring {
            position: absolute;
            border: solid rgba(59, 130, 246, 0.6); /* Warna Biru */
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: radar-ripple 4s infinite cubic-bezier(0.1, 0.4, 0.8, 1);
        }
        .radar-ring:nth-child(1) { animation-delay: 0s; }
        .radar-ring:nth-child(2) { animation-delay: 1.33s; }
        .radar-ring:nth-child(3) { animation-delay: 2.66s; }

        /* Custom input style for cleaner look */
        .clean-input:focus-within {
            border-color: #0f172a;
            box-shadow: 0 0 0 1px #0f172a;
        }
    </style>
</head>
<body class="min-h-screen flex text-slate-900 antialiased selection:bg-blue-200 selection:text-blue-900">

    <!-- LEFT SIDE: Branding & Radar Animation (Hidden on Mobile) -->
    <div class="hidden lg:flex lg:w-1/2 bg-[#0B1120] p-16 flex-col justify-between relative overflow-hidden">
        
        <!-- Background aksen gelap agar tidak terlalu flat -->
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_var(--tw-gradient-stops))] from-slate-800/40 via-[#0B1120] to-[#0B1120]"></div>

        <!-- Branding Text Besar -->
        <div class="relative z-10">
            <h1 class="text-6xl font-black text-white tracking-tighter">
                PrimaTrack<span class="text-blue-500">.</span>
            </h1>
            <p class="mt-4 text-slate-400 font-medium tracking-wide">Enterprise Fleet Monitoring</p>
        </div>

        <!-- Visualisasi Radar Sederhana -->
        <div class="relative z-10 flex-1 flex items-center justify-center my-10">
            <div class="relative flex items-center justify-center w-64 h-64">
                <!-- Rings -->
                <div class="radar-ring"></div>
                <div class="radar-ring"></div>
                <div class="radar-ring"></div>
                
                <!-- Center Pin / Vehicle -->
                <div class="relative z-20 w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center text-white text-2xl shadow-[0_0_40px_rgba(37,99,235,0.6)]">
                    <i class="fa-solid fa-location-crosshairs"></i>
                </div>
            </div>
        </div>

        <!-- Space filler untuk mendorong radar ke tengah -->
        <div class="relative z-10 h-16"></div>
    </div>

    <!-- RIGHT SIDE: Clean Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 lg:p-20 bg-white">
        
        <div class="w-full max-w-[380px]">
            
            <!-- Mobile Logo (Shows only on small screens) -->
            <div class="lg:hidden mb-12">
                <h1 class="text-4xl font-black text-slate-900 tracking-tighter">
                    PrimaTrack<span class="text-blue-600">.</span>
                </h1>
            </div>
            
            <!-- Form Header -->
            <div class="mb-10">
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight mb-2">Masuk ke Sistem</h2>
                <p class="text-slate-500 text-sm">Silakan gunakan kredensial akses Anda.</p>
            </div>

            <!-- Error Alert -->
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-lg flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation text-red-600 mt-0.5"></i>
                    <span class="text-sm font-medium text-red-800 leading-snug">{{ $errors->first() }}</span>
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ url('/login') }}" class="space-y-6">
                @csrf
                
                <!-- Email Input -->
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-bold text-slate-700">Email Akses</label>
                    <div class="relative clean-input border border-slate-300 rounded-lg bg-white transition-all overflow-hidden flex items-center">
                        <div class="pl-4 pr-2 text-slate-400">
                            <i class="fa-regular fa-envelope text-sm"></i>
                        </div>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="w-full py-3.5 pr-4 bg-transparent outline-none text-sm text-slate-900 placeholder-slate-400 font-medium" 
                            placeholder="admin@sistem.com">
                    </div>
                </div>

                <!-- Password Input -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm font-bold text-slate-700">Kata Sandi</label>
                    </div>
                    <div class="relative clean-input border border-slate-300 rounded-lg bg-white transition-all overflow-hidden flex items-center">
                        <div class="pl-4 pr-2 text-slate-400">
                            <i class="fa-regular fa-lock text-sm"></i>
                        </div>
                        <input type="password" name="password" id="password" required
                            class="w-full py-3.5 pr-4 bg-transparent outline-none text-sm text-slate-900 placeholder-slate-400 font-medium" 
                            placeholder="••••••••">
                    </div>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" class="w-4 h-4 text-slate-900 bg-white border-slate-300 rounded focus:ring-slate-900 cursor-pointer transition-colors">
                        <label for="remember" class="ml-2.5 text-sm font-medium text-slate-600 cursor-pointer select-none">
                            Ingat saya
                        </label>
                    </div>
                    <a href="#" class="text-sm font-bold text-slate-600 hover:text-slate-900 transition-colors">Lupa sandi?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full mt-6 py-3.5 px-4 bg-slate-900 hover:bg-slate-800 text-white rounded-lg font-bold text-sm tracking-wide transition-colors active:scale-[0.99] flex items-center justify-center gap-2">
                    Login
                </button>
            </form>

        </div>
    </div>

</body>
</html>