<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Rekapan Riwayat Seluruh Armada - PRIMA TRACK</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        body { 
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            background-color: #f8fafc;
            -webkit-tap-highlight-color: transparent;
        }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Badge plat nomor khas indonesia */
        .plate-badge {
            background-color: #0f172a;
            color: #ffffff;
            font-family: 'Courier New', Courier, monospace;
            font-weight: 800;
            letter-spacing: 0.05em;
            border-radius: 6px;
            padding: 2px 8px;
            border: 1px solid #334155;
            display: inline-block;
        }

        /* --- STYLING CETAK DOKUMEN RESMI (PRINT) --- */
        @media print {
            .no-print { display: none !important; }
            body { 
                background-color: #ffffff !important; 
                color: #000000 !important; 
                font-size: 10.5pt !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-only { display: block !important; }
            
            .report-container { 
                max-width: 100% !important; 
                padding: 0 !important; 
                margin: 0 !important; 
            }

            .print-summary-box {
                border: 1px solid #94a3b8 !important;
                border-radius: 6px !important;
                padding: 10px !important;
                margin-bottom: 16px !important;
            }

            .print-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 12px !important;
            }
            .print-table th, .print-table td {
                border: 1px solid #94a3b8 !important;
                padding: 6px 8px !important;
                font-size: 9pt !important;
                color: #000000 !important;
            }
            .print-table th {
                background-color: #f1f5f9 !important;
                font-weight: 800 !important;
                text-transform: uppercase !important;
            }
            .print-table tr {
                page-break-inside: avoid !important;
            }

            .print-signatures {
                margin-top: 40px !important;
                page-break-inside: avoid !important;
            }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- TOP NAVBAR (Screen Only) -->
    <header class="bg-[#0b1329] border-b border-slate-800 text-white sticky top-0 z-50 shadow-md no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="/dashboard" class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700 transition active:scale-95" title="Kembali ke Dashboard">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center font-black text-white text-xs shadow-md shadow-blue-500/25">
                        P
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-black text-sm tracking-tight uppercase leading-none">PRIMA TRACK<span class="text-blue-500">.</span></h1>
                            <span class="bg-blue-900/60 text-blue-400 text-[9px] font-black px-1.5 py-0.5 rounded border border-blue-700/50 uppercase tracking-widest">Rekap</span>
                        </div>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5 leading-none">Rekapan Riwayat Armada</p>
                    </div>
                </div>
            </div>

            <!-- Quick Navigation & Print Actions -->
            <div class="flex items-center gap-2">
                <a href="/dashboard" class="hidden md:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800 transition border border-transparent hover:border-slate-700">
                    <i class="fa-solid fa-map text-blue-400"></i> Peta Live
                </a>
                <a href="/management/verifikasi" class="hidden md:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800 transition border border-transparent hover:border-slate-700">
                    <i class="fa-solid fa-square-parking text-amber-400"></i> Verifikasi Parkir
                </a>
                <a href="/devices" class="hidden md:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800 transition border border-transparent hover:border-slate-700">
                    <i class="fa-solid fa-truck text-slate-400"></i> Armada
                </a>

                <div class="h-6 w-px bg-slate-800 mx-1 hidden md:block"></div>

                <a id="btnExport" href="{{ route('rekap.export', ['date' => $date]) }}" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 sm:px-3.5 py-2 rounded-xl text-xs font-black uppercase tracking-wider shadow-md shadow-emerald-600/20 transition active:scale-95">
                    <i class="fa-solid fa-file-excel"></i> <span class="hidden sm:inline">Export Excel</span>
                </a>
                <button onclick="window.print()" class="inline-flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white px-3 sm:px-3.5 py-2 rounded-xl text-xs font-black uppercase tracking-wider border border-slate-700 transition active:scale-95">
                    <i class="fa-solid fa-print"></i> <span class="hidden sm:inline">Cetak</span>
                </button>
            </div>
        </div>
    </header>

    <!-- PRINT HEADER (Hanya Tampil Saat Cetak Kertas / PDF) -->
    <div class="print-only p-6 border-b-2 border-slate-800 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900">PRIMA TRACK TELEMATICS</h1>
                <p class="text-sm font-bold text-slate-600 uppercase tracking-widest mt-0.5">Laporan Rekapan Riwayat & Operasional Seluruh Armada</p>
                <p class="text-xs text-slate-500 mt-1">Sistem Pemantauan GPS Lapangan Realtime - Zona Waktu WITA (UTC+8)</p>
            </div>
            <div class="text-right text-xs">
                <p class="font-bold text-slate-800">Tanggal Rekap: <span class="print-target-date font-black text-sm">{{ $date }}</span></p>
                <p class="text-slate-500 mt-1">Dicetak: {{ \Carbon\Carbon::now('Asia/Makassar')->format('d/m/Y H:i:s') }} WITA</p>
            </div>
        </div>
    </div>

    <!-- MAIN WRAPPER -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-7 report-container">

        <!-- FILTER BAR (Screen Only) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-5 mb-6 no-print">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                
                <!-- Left: Date Picker & Quick Selectors -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <label for="dateSelect" class="text-xs font-black uppercase tracking-wider text-slate-500 whitespace-nowrap">
                            <i class="fa-solid fa-calendar-day text-blue-600 mr-1"></i> Tanggal:
                        </label>
                        <input type="date" id="dateSelect" value="{{ $date }}" class="bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition w-full sm:w-auto">
                    </div>

                    <div class="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto no-scrollbar pt-1 sm:pt-0">
                        <button type="button" onclick="setQuickDate('today')" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition bg-slate-100 hover:bg-slate-200 text-slate-700 active:scale-95">
                            Hari Ini
                        </button>
                        <button type="button" onclick="setQuickDate('yesterday')" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition bg-slate-100 hover:bg-slate-200 text-slate-700 active:scale-95">
                            Kemarin
                        </button>
                        @if ($date !== '2026-09-25')
                        <button type="button" onclick="setSpecificDate('2026-09-25')" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200/60 active:scale-95">
                            <i class="fa-solid fa-database mr-1"></i> Rekap 25 Sep
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Right: Search & Status Filter -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <div class="relative flex-1 sm:w-60">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" id="searchInput" onkeyup="filterVehicleList()" placeholder="Cari unit atau plat..." class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                    </div>
                    <select id="statusFilter" onchange="filterVehicleList()" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        <option value="all">Semua Status</option>
                        <option value="active">Aktif Bergerak</option>
                        <option value="parked">Parkir / Diam</option>
                        <option value="offline">Tidak Ada Sinyal</option>
                    </select>
                </div>

            </div>
        </div>

        <!-- EXECUTIVE SUMMARY KPI CARDS (Serupa Rekap History) -->
        <section class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse"></span>
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Ringkasan Operasional Seluruh Armada</h2>
                </div>
                <span id="date-label" class="text-xs font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-lg">
                    Periode: {{ $date }} (WITA)
                </span>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                
                <!-- Card 1: Sinyal -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 relative overflow-hidden flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Sinyal</span>
                        <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-xs">
                            <i class="fa-solid fa-tower-broadcast"></i>
                        </div>
                    </div>
                    <div>
                        <div class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight leading-none mb-1">
                            <span id="sum-points">{{ number_format($summary['total_points'], 0, ',', '.') }}</span>
                            <span class="text-xs font-bold text-slate-400">titik</span>
                        </div>
                        <p class="text-[10px] font-bold text-blue-600 flex items-center gap-1">
                            <span id="sum-active-devs">{{ $summary['active_vehicles'] }}</span> dari <span id="sum-total-devs">{{ $summary['total_vehicles'] }}</span> armada aktif
                        </p>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-slate-300"></div>
                </div>

                <!-- Card 2: Parkir -->
                <div class="bg-white rounded-2xl border border-amber-200/80 shadow-sm p-4 relative overflow-hidden flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-amber-600">Total Parkir</span>
                        <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                            <i class="fa-solid fa-square-parking"></i>
                        </div>
                    </div>
                    <div>
                        <div class="text-xl sm:text-2xl font-black text-amber-600 tracking-tight leading-none mb-1">
                            <span id="sum-parking">{{ $summary['total_parking_count'] }}</span>
                            <span class="text-xs font-bold text-amber-500">titik singgah</span>
                        </div>
                        <p class="text-[10px] font-bold text-amber-700">
                            Durasi: <span id="sum-parking-time">{{ $summary['total_parking_formatted'] }}</span>
                        </p>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-400"></div>
                </div>

                <!-- Card 3: Jarak -->
                <div class="bg-white rounded-2xl border border-blue-200/80 shadow-sm p-4 relative overflow-hidden flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-600">Total Jarak</span>
                        <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                            <i class="fa-solid fa-route"></i>
                        </div>
                    </div>
                    <div>
                        <div class="text-xl sm:text-2xl font-black text-blue-600 tracking-tight leading-none mb-1">
                            <span id="sum-distance">{{ number_format($summary['total_distance_km'], 2, ',', '.') }}</span>
                            <span class="text-xs font-bold text-blue-400">km</span>
                        </div>
                        <p class="text-[10px] font-bold text-blue-700">
                            Rata-rata: <span id="sum-avg-dist">{{ $summary['active_vehicles'] > 0 ? number_format($summary['total_distance_km'] / $summary['active_vehicles'], 1, ',', '.') : 0 }}</span> km / armada
                        </p>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-blue-500"></div>
                </div>

                <!-- Card 4: Est. BBM -->
                <div class="bg-white rounded-2xl border border-emerald-200/80 shadow-sm p-4 relative overflow-hidden flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Est. BBM Armada</span>
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                            <i class="fa-solid fa-gas-pump"></i>
                        </div>
                    </div>
                    <div>
                        <div class="text-xl sm:text-2xl font-black text-emerald-600 tracking-tight leading-none mb-1">
                            <span id="sum-fuel-liters">{{ number_format($summary['total_fuel_liters'], 2, ',', '.') }}</span>
                            <span class="text-xs font-bold text-emerald-500">Liter</span>
                        </div>
                        <p class="text-[10px] font-bold text-emerald-800">
                            Biaya: <span id="sum-fuel-cost">Rp {{ number_format($summary['total_fuel_cost'], 0, ',', '.') }}</span>
                        </p>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
                </div>

            </div>
        </section>

        <!-- PRINT SUMMARY BLOCK (Khusus Cetak) -->
        <div class="print-only print-summary-box">
            <div style="display: flex; justify-content: space-between; font-size: 9.5pt;">
                <div><strong>Total Armada:</strong> {{ $summary['total_vehicles'] }} Unit ({{ $summary['active_vehicles'] }} Aktif)</div>
                <div><strong>Total Sinyal:</strong> {{ number_format($summary['total_points'], 0, ',', '.') }} Titik</div>
                <div><strong>Total Jarak Tempuh:</strong> {{ number_format($summary['total_distance_km'], 2, ',', '.') }} km</div>
                <div><strong>Total Parkir:</strong> {{ $summary['total_parking_count'] }} Titik ({{ $summary['total_parking_formatted'] }})</div>
                <div><strong>Est. BBM:</strong> {{ number_format($summary['total_fuel_liters'], 2, ',', '.') }} L (Rp {{ number_format($summary['total_fuel_cost'], 0, ',', '.') }})</div>
            </div>
        </div>

        <!-- TABEL REKAPAN SELURUH ARMADA -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            
            <!-- Table Header Bar -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between no-print">
                <div>
                    <h3 class="font-black text-sm text-slate-800 uppercase tracking-tight">Rincian Riwayat Tiap Kendaraan</h3>
                    <p class="text-[11px] text-slate-400 font-medium">Klik pada tombol "Lihat Rute" untuk memeriksa rekaman lintasan di peta interaktif.</p>
                </div>
                <div class="text-xs font-bold text-slate-500">
                    Menampilkan <span id="visible-count" class="text-blue-600 font-black">{{ count($vehicles) }}</span> armada
                </div>
            </div>

            <!-- TABEL VIEW (Desktop & Print) -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse print-table" id="recapTable">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3.5 px-3 text-center" width="4%">No</th>
                            <th class="py-3.5 px-4" width="22%">Armada & Kendaraan</th>
                            <th class="py-3.5 px-4" width="16%">Sinyal GPS</th>
                            <th class="py-3.5 px-4" width="16%">Persinggahan (Parkir)</th>
                            <th class="py-3.5 px-4" width="14%">Jarak Tempuh</th>
                            <th class="py-3.5 px-4" width="18%">Estimasi BBM</th>
                            <th class="py-3.5 px-3 text-center no-print" width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs" id="tableBody">
                        @forelse ($vehicles as $index => $v)
                        <tr class="vehicle-row hover:bg-slate-50/80 transition" data-name="{{ strtolower($v['name']) }}" data-plate="{{ strtolower($v['plate_number']) }}" data-status="{{ $v['status_key'] }}">
                            <td class="py-3 px-3 text-center font-bold text-slate-400">
                                {{ $index + 1 }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-black text-slate-900 uppercase text-xs tracking-tight">{{ $v['name'] }}</div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="plate-badge text-[10px]">{{ $v['plate_number'] }}</span>
                                    <span class="text-[10px] text-slate-400 font-bold">{{ $v['module_type'] }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5">
                                    @if ($v['status_key'] === 'active')
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span class="font-black text-emerald-700 text-xs">{{ number_format($v['points'], 0, ',', '.') }} Titik</span>
                                    @elseif ($v['status_key'] === 'parked')
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        <span class="font-black text-amber-700 text-xs">{{ number_format($v['points'], 0, ',', '.') }} Titik</span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                                        <span class="font-bold text-slate-400 text-xs">0 Titik</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-slate-500 font-medium mt-0.5">
                                    @if ($v['points'] > 0)
                                        Jam: <span class="font-mono font-bold text-slate-700">{{ $v['first_gps_time'] }} - {{ $v['last_gps_time'] }}</span>
                                    @else
                                        <span class="text-slate-400 italic">Offline sepanjang hari</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                @if ($v['parking_count'] > 0)
                                    <div class="font-black text-amber-600 text-xs">
                                        <i class="fa-solid fa-square-parking text-amber-500 mr-0.5"></i> {{ $v['parking_count'] }} Titik
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-bold mt-0.5">
                                        Durasi: {{ $v['parking_formatted'] }}
                                    </div>
                                @else
                                    <span class="text-slate-400 text-xs">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-black text-slate-800 text-sm">
                                    {{ number_format($v['distance_km'], 2, ',', '.') }} <span class="text-[10px] font-bold text-slate-400">km</span>
                                </div>
                                @if ($v['distance_km'] > 0)
                                    <div class="w-24 bg-slate-100 rounded-full h-1.5 mt-1 overflow-hidden no-print">
                                        <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ min(100, ($v['distance_km'] / max(1, $summary['total_distance_km'])) * 100 * 2) }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-black text-emerald-600 text-xs">
                                    {{ number_format($v['fuel_liters'], 2, ',', '.') }} <span class="text-[10px] font-bold text-emerald-500">Liter</span>
                                </div>
                                <div class="text-[10px] font-bold text-slate-600 mt-0.5">
                                    Rp {{ number_format($v['fuel_cost'], 0, ',', '.') }}
                                    <span class="text-slate-400 text-[9px] font-normal">(1:{{ $v['fuel_ratio'] }})</span>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-center no-print">
                                @if ($v['points'] > 0)
                                    <a href="/device/{{ $v['imei'] }}/history?date={{ $date }}" class="inline-flex items-center gap-1 bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase transition active:scale-95 border border-blue-200 hover:border-blue-600 shadow-sm" title="Buka Detail Rute Peta">
                                        <i class="fa-solid fa-route"></i> Rute
                                    </a>
                                @else
                                    <span class="text-[10px] text-slate-300 font-bold">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 font-bold text-xs">
                                Tidak ada data armada yang tercatat pada sistem.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Empty Filter State (Hidden by default) -->
            <div id="no-result" class="hidden py-12 text-center text-slate-400 text-xs font-bold">
                <i class="fa-solid fa-car-tunnel text-3xl text-slate-300 mb-2 block"></i>
                Tidak ada kendaraan yang sesuai dengan filter pencarian atau status.
            </div>

        </div>

        <!-- PRINT SIGNATURE BLOCK (Hanya Tampil Saat Cetak Dokumen) -->
        <div class="print-only print-signatures">
            <div style="display: flex; justify-content: space-between; margin-top: 40px; font-size: 10pt;">
                <div style="width: 40%; text-align: center;">
                    <p>Dibuat Oleh,</p>
                    <p style="font-weight: bold; margin-top: 4px;">Operator Telemetri GPS</p>
                    <div style="height: 60px;"></div>
                    <p style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 4px; font-weight: bold;">( .................................................. )</p>
                </div>
                <div style="width: 40%; text-align: center;">
                    <p>Diketahui Oleh,</p>
                    <p style="font-weight: bold; margin-top: 4px;">Manajer Operasional Armada</p>
                    <div style="height: 60px;"></div>
                    <p style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 4px; font-weight: bold;">( .................................................. )</p>
                </div>
            </div>
            <div style="margin-top: 30px; text-align: right; font-size: 8pt; color: #64748b;">
                Dokumen Resmi Tercetak Otomatis dari Sistem PRIMA TRACK
            </div>
        </div>

    </main>

    <!-- FOOTER (Screen Only) -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400 font-medium no-print mt-8">
        &copy; {{ date('Y') }} PRIMA TRACK. Sistem Monitoring Telemetri GPS & Manajemen Armada Makassar.
    </footer>

    <!-- INTERACTIVE JAVASCRIPT -->
    <script>
        const dateInput = document.getElementById('dateSelect');
        const btnExport = document.getElementById('btnExport');
        let currentRecapDate = "{{ $date }}";

        // Date Change Handler via AJAX for seamless UX
        dateInput.addEventListener('change', () => {
            loadRecapByDate(dateInput.value);
        });

        function setQuickDate(type) {
            const today = new Date();
            let target = new Date();
            if (type === 'yesterday') {
                target.setDate(today.getDate() - 1);
            }
            const yyyy = target.getFullYear();
            const mm = String(target.getMonth() + 1).padStart(2, '0');
            const dd = String(target.getDate()).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;
            
            dateInput.value = dateStr;
            loadRecapByDate(dateStr);
        }

        function setSpecificDate(dateStr) {
            dateInput.value = dateStr;
            loadRecapByDate(dateStr);
        }

        async function loadRecapByDate(dateStr) {
            if (!dateStr) return;
            currentRecapDate = dateStr;
            
            // Update Export Link
            btnExport.href = `/management/rekap-riwayat/export?date=${dateStr}`;
            document.querySelectorAll('.print-target-date').forEach(el => el.innerText = dateStr);
            document.getElementById('date-label').innerText = `Periode: ${dateStr} (WITA)`;

            // Push state into browser URL without reloading
            const newUrl = `${window.location.pathname}?date=${dateStr}`;
            window.history.pushState({ path: newUrl }, '', newUrl);

            // Fetch AJAX data
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = `<tr><td colspan="7" class="py-12 text-center text-slate-400 font-bold text-xs"><i class="fa-solid fa-spinner animate-spin text-blue-600 text-lg mr-2"></i> Mengkalkulasi data seluruh armada...</td></tr>`;

            try {
                const response = await fetch(`/management/rekap-riwayat/data?date=${dateStr}`);
                if (!response.ok) throw new Error("Gagal mengambil data");
                const data = await response.json();

                renderRecapData(data);
            } catch (err) {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="7" class="py-12 text-center text-red-500 font-bold text-xs"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Terjadi kesalahan memuat data rekapan.</td></tr>`;
            }
        }

        function renderRecapData(data) {
            const sum = data.summary;
            const vehicles = data.vehicles;

            // Update Summary Cards
            document.getElementById('sum-points').innerText = Number(sum.total_points).toLocaleString('id-ID');
            document.getElementById('sum-active-devs').innerText = sum.active_vehicles;
            document.getElementById('sum-total-devs').innerText = sum.total_vehicles;
            document.getElementById('sum-parking').innerText = sum.total_parking_count;
            document.getElementById('sum-parking-time').innerText = sum.total_parking_formatted;
            document.getElementById('sum-distance').innerText = Number(sum.total_distance_km).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            const avgDist = sum.active_vehicles > 0 ? (sum.total_distance_km / sum.active_vehicles).toFixed(1) : 0;
            document.getElementById('sum-avg-dist').innerText = Number(avgDist).toLocaleString('id-ID');

            document.getElementById('sum-fuel-liters').innerText = Number(sum.total_fuel_liters).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('sum-fuel-cost').innerText = `Rp ${Number(sum.total_fuel_cost).toLocaleString('id-ID')}`;

            // Render Table Rows
            const tbody = document.getElementById('tableBody');
            if (!vehicles || vehicles.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="py-12 text-center text-slate-400 font-bold text-xs">Tidak ada data armada.</td></tr>`;
                document.getElementById('visible-count').innerText = '0';
                return;
            }

            let html = '';
            vehicles.forEach((v, idx) => {
                const pointsFmt = Number(v.points).toLocaleString('id-ID');
                const distFmt = Number(v.distance_km).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const fuelFmt = Number(v.fuel_liters).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const costFmt = Number(v.fuel_cost).toLocaleString('id-ID');

                let statusBadge = '';
                if (v.status_key === 'active') {
                    statusBadge = `<span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="font-black text-emerald-700 text-xs">${pointsFmt} Titik</span>`;
                } else if (v.status_key === 'parked') {
                    statusBadge = `<span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="font-black text-amber-700 text-xs">${pointsFmt} Titik</span>`;
                } else {
                    statusBadge = `<span class="w-2 h-2 rounded-full bg-slate-300"></span><span class="font-bold text-slate-400 text-xs">0 Titik</span>`;
                }

                let timeInfo = v.points > 0 
                    ? `Jam: <span class="font-mono font-bold text-slate-700">${v.first_gps_time} - ${v.last_gps_time}</span>`
                    : `<span class="text-slate-400 italic">Offline sepanjang hari</span>`;

                let parkingInfo = v.parking_count > 0
                    ? `<div class="font-black text-amber-600 text-xs"><i class="fa-solid fa-square-parking text-amber-500 mr-0.5"></i> ${v.parking_count} Titik</div><div class="text-[10px] text-slate-500 font-bold mt-0.5">Durasi: ${v.parking_formatted}</div>`
                    : `<span class="text-slate-400 text-xs">-</span>`;

                let actionBtn = v.points > 0
                    ? `<a href="/device/${v.imei}/history?date=${currentRecapDate}" class="inline-flex items-center gap-1 bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase transition active:scale-95 border border-blue-200 hover:border-blue-600 shadow-sm" title="Buka Detail Rute Peta"><i class="fa-solid fa-route"></i> Rute</a>`
                    : `<span class="text-[10px] text-slate-300 font-bold">-</span>`;

                const barWidth = sum.total_distance_km > 0 ? Math.min(100, (v.distance_km / sum.total_distance_km) * 100 * 2) : 0;
                let progressBar = v.distance_km > 0 
                    ? `<div class="w-24 bg-slate-100 rounded-full h-1.5 mt-1 overflow-hidden no-print"><div class="bg-blue-600 h-1.5 rounded-full" style="width: ${barWidth}%"></div></div>`
                    : '';

                html += `
                    <tr class="vehicle-row hover:bg-slate-50/80 transition" data-name="${v.name.toLowerCase()}" data-plate="${v.plate_number.toLowerCase()}" data-status="${v.status_key}">
                        <td class="py-3 px-3 text-center font-bold text-slate-400">${idx + 1}</td>
                        <td class="py-3 px-4">
                            <div class="font-black text-slate-900 uppercase text-xs tracking-tight">${v.name}</div>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="plate-badge text-[10px]">${v.plate_number}</span>
                                <span class="text-[10px] text-slate-400 font-bold">${v.module_type}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-1.5">${statusBadge}</div>
                            <div class="text-[10px] text-slate-500 font-medium mt-0.5">${timeInfo}</div>
                        </td>
                        <td class="py-3 px-4">${parkingInfo}</td>
                        <td class="py-3 px-4">
                            <div class="font-black text-slate-800 text-sm">${distFmt} <span class="text-[10px] font-bold text-slate-400">km</span></div>
                            ${progressBar}
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-black text-emerald-600 text-xs">${fuelFmt} <span class="text-[10px] font-bold text-emerald-500">Liter</span></div>
                            <div class="text-[10px] font-bold text-slate-600 mt-0.5">Rp ${costFmt} <span class="text-slate-400 text-[9px] font-normal">(1:${v.fuel_ratio})</span></div>
                        </td>
                        <td class="py-3 px-3 text-center no-print">${actionBtn}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            filterVehicleList();
        }

        // Live Search & Status Filter
        function filterVehicleList() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.vehicle-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const plate = row.getAttribute('data-plate') || '';
                const st = row.getAttribute('data-status') || '';

                const matchesQuery = query === '' || name.includes(query) || plate.includes(query);
                const matchesStatus = status === 'all' || st === status;

                if (matchesQuery && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('visible-count').innerText = visibleCount;
            document.getElementById('no-result').classList.toggle('hidden', visibleCount > 0);
        }
    </script>
</body>
</html>
