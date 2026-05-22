<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Titik Parkir - Prima Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #334155;
        }
        
        /* BAR NAVBAR UTAMA (DIKUNCI AGAR PRESISI) */
        .enterprise-header {
            background-color: #0b1329; /* Navy premium sesuai dashboard */
            border-bottom: 1px solid #1e293b;
        }
        .btn-back-home {
            background-color: #1e293b;
            border: 1px solid #334155;
            color: #94a3b8;
            border-radius: 10px;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .btn-back-home:hover {
            background-color: #334155;
            color: #60a5fa;
        }

        /* Styling Konten */
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background-color: #ffffff;
        }
        .form-select, .form-control {
            border-color: #cbd5e1;
            border-radius: 8px;
            padding: 0.6rem 0.8rem;
            font-size: 0.9rem;
        }
        .form-select:focus, .form-control:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            border-color: #3b82f6;
        }
        .btn-action {
            border-radius: 8px;
            padding: 0.55rem 1.2rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .table {
            font-size: 0.875rem;
        }
        .table th {
            font-weight: 600;
            background-color: #f1f5f9 !important;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
        .text-monospace {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 600;
        }
        .badge-parking {
            background-color: #fef3c7;
            color: #d97706;
            font-weight: 700;
        }
        .print-header-doc {
            display: none;
        }

        /* --- MANAJEMEN MODE CETAK (PRINT) --- */
        @media print {
            body {
                background-color: #fff;
                color: #000;
                font-size: 11px;
            }
            .no-print, #filterForm, .btn-simpan, .btn-action-group, .card-header, header {
                display: none !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
            .table th {
                background-color: #f8fafc !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            tr {
                page-break-inside: avoid !important;
            }
            .print-header-doc {
                display: block !important;
                margin-bottom: 20px;
                border-bottom: 3px double #334155;
                padding-bottom: 10px;
            }
            .input-latlong, .input-keterangan {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                resize: none;
            }
        }
    </style>
</head>
<body>

<header class="enterprise-header py-2 px-4 no-print shadow-sm mb-4">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="/" class="btn btn-back-home p-0" title="Kembali ke Dashboard Utama">
                <i class="fa-solid fa-house"></i>
            </a>
            
            <div style="width: 1px; height: 28px; background-color: #232d42;"></div>

            <div class="d-flex align-items-center gap-2.5">
                <svg viewBox="0 0 512 512" style="width: 38px; height: 38px; border-radius: 10px; box-shadow: 0 0 15px rgba(59,130,246,0.25); flex-shrink: 0;">
                    <rect width="512" height="512" fill="#0B1120" />
                    <g transform="translate(-19, -29)">
                        <path d="M120 110 h 120 c 71.8 0 130 58.2 130 130 v 0 c 0 71.8 -58.2 130 -130 130 h -50 v 90 c 0 11.05 -8.95 20 -20 20 h -40 c -11.05 0 -20 -8.95 -20 -20 V 130 c 0 -11.05 8.95 -20 20 -20 z M 190 190 v 100 h 50 c 27.6 0 50 -22.4 50 -50 v 0 c 0 -27.6 -22.4 -50 -50 -50 h -50 z" fill="#FFFFFF" />
                        <circle cx="410" cy="440" r="40" fill="#3B82F6" />
                    </g>
                </svg>
                <div class="d-flex flex-column justify-content-center">
                    <h1 class="mb-0 text-uppercase tracking-wider" style="font-size: 13px; font-weight: 900; color: #ffffff; line-height: 1.1;">
                        PRIMA TRACK<span style="color: #3b82f6;">.</span>
                    </h1>
                    <p class="mb-0 text-uppercase font-bold italic" style="font-size: 9px; font-weight: 700; color: #60a5fa; margin-top: 1px; letter-spacing: 0.5px;">
                        Enterprise Edition
                    </p>
                </div>
            </div>
        </div>
        
        <div class="d-none d-sm-block">
            <span class="badge bg-dark border border-secondary px-3 py-2 text-uppercase font-monospace" style="font-size: 10px; letter-spacing: 0.5px; color: #94a3b8; background-color: #111827 !important;">
                <i class="fa-solid fa-user-shield text-primary me-1"></i> Management Panel
            </span>
        </div>
    </div>
</header>

<div class="container-fluid px-4 pb-5">
    <div class="print-header-doc text-center">
        <h4 class="mb-1 fw-bold">PRIMA TRACK - MONITORING SYSTEM</h4>
        <p class="mb-0 text-muted small">Laporan Verifikasi Dan Audit Kesesuaian Titik Parkir Lapangan</p>
        <div class="row mt-3 text-start small">
            <div class="col-6"><strong>Kendaraan:</strong> <span id="print-txt-device">-</span></div>
            <div class="col-6 text-end"><strong>Tanggal Rekap:</strong> <span id="print-txt-date">-</span></div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Verifikasi Lokasi Parkir Lapangan</h4>
            <p class="text-muted small mb-0">Validasi kesesuaian titik koordinat singgah GPS dengan data lokasi pengerjaan riil.</p>
        </div>
        <div class="btn-action-group d-flex gap-2" id="actionButtons" style="display: none !important;">
            <button type="button" id="btnPrint" class="btn btn-action btn-outline-secondary d-flex align-items-center gap-2">
                <i class="fa-solid fa-print"></i> Cetak Dokumen
            </button>
            <button type="button" id="btnExport" class="btn btn-action btn-success d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-excel"></i> Export Ke Excel
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body p-4">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-uppercase text-muted">Pilih Kendaraan / Perangkat</label>
                    <select id="device_id" class="form-select" required>
                        <option value="">-- Silakan Tentukan Armada --</option>
                        @foreach($devices as $device)
                            <option value="{{ $device->id }}" data-info="{{ $device->plate_number }} - {{ $device->name }}">{{ $device->plate_number }} - {{ $device->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-uppercase text-muted">Tanggal Audit</label>
                    <input type="date" id="date" class="form-select" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-action btn-dark w-100 py-2.5 d-flex align-items-center justify-content-center gap-2">
                        <i class="fa-solid fa-magnifying-glass"></i> Muat Rekap
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th width="4%" class="text-center py-3">No</th>
                            <th width="18%">Mulai Parkir (WITA)</th>
                            <th width="12%">Durasi Singgah</th>
                            <th width="20%">Koordinat GPS Asli</th>
                            <th width="22%">Lat Long Pengerjaan Riil (Kolom Tambahan)</th>
                            <th width="24%">Keterangan Lapangan (Kolom Tambahan)</th>
                            <th width="5%" class="text-center no-print">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fa-solid fa-route d-block fs-2 mb-3 text-secondary" style="opacity: 0.3;"></i>
                                Silakan tentukan filter armada kendaraan dan tanggal audit terlebih dahulu.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // Proses AJAX Load Data Rekap Parkir
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        let deviceId = $('#device_id').val();
        let date = $('#date').val();
        let deviceText = $('#device_id option:selected').data('info');

        // Pemetaan data teks ke kop surat cetak
        $('#print-txt-device').text(deviceText);
        $('#print-txt-date').text(date);

        $('#tableBody').html('<tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-secondary spinner-border-sm mb-2 d-block mx-auto"></div> Menghitung titik singgah armada...</td></tr>');
        $('#actionButtons').attr('style', 'display: none !important;');

        $.ajax({
            url: "{{ route('verifikasi.data') }}",
            type: "GET",
            data: { device_id: deviceId, date: date },
            success: function(data) {
                let html = '';
                
                if(data.length === 0) {
                    html = '<tr><td colspan="7" class="text-center text-danger py-5"><i class="fa-solid fa-triangle-exclamation d-block fs-3 mb-2"></i> Tidak ada data log parkir (> 5 mnt) pada tanggal pilihan.</td></tr>';
                    $('#actionButtons').attr('style', 'display: none !important;');
                } else {
                    $('#actionButtons').removeAttr('style');

                    data.forEach((item, index) => {
                        let statusBtn = item.is_verified ? 'btn-primary' : 'btn-outline-primary';
                        let labelBtn = item.is_verified ? '<i class="fa-solid fa-arrows-rotate"></i> Update' : '<i class="fa-solid fa-floppy-disk"></i> Simpan';
                        
                        html += `
                            <tr>
                                <td class="text-center text-muted">${index + 1}</td>
                                <td><span class="text-monospace text-secondary">${item.waktu_mulai}</span></td>
                                <td><span class="badge badge-parking px-2.5 py-1.5">${item.durasi}</span></td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <small class="text-muted text-monospace">${item.koordinat_gps}</small>
                                        <a href="https://maps.google.com/?q=${item.koordinat_gps}" target="_blank" class="btn btn-light border py-1 px-2 btn-sm ms-2 no-print" title="Validasi Posisi di Google Maps" style="font-size: 11px;">
                                            <i class="fa-solid fa-map-location-dot text-danger"></i> Map
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm input-latlong text-monospace" value="${item.lat_long_pengerjaan || ''}" placeholder="-5.xxxx, 119.xxxx">
                                </td>
                                <td>
                                    <textarea class="form-control form-control-sm input-keterangan" rows="1" placeholder="cth: Galian pipa simpang tiga">${item.keterangan || ''}</textarea>
                                </td>
                                <td class="text-center no-print">
                                    <button type="button" class="btn btn-sm ${statusBtn} btn-simpan px-3" 
                                            data-waktu="${item.waktu_mulai}" 
                                            data-gps="${item.koordinat_gps}">
                                        ${labelBtn}
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                }
                $('#tableBody').html(html);
            }
        });
    });

    // Proses AJAX Simpan atau Update Verifikasi per Baris Data
    $(document).on('click', '.btn-simpan', function() {
        let $btn = $(this);
        let $row = $btn.closest('tr');
        let deviceId = $('#device_id').val();
        
        let waktuMulai = $btn.data('waktu');
        let koordinatGps = $btn.data('gps');
        let latLongPengerjaan = $row.find('.input-latlong').val();
        let keterangan = $row.find('.input-keterangan').val();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: "{{ route('verifikasi.simpan') }}",
            type: "POST",
            data: {
                device_id: deviceId,
                waktu_mulai: waktuMulai,
                koordinat_gps: koordinatGps,
                lat_long_pengerjaan: latLongPengerjaan,
                keterangan: keterangan
            },
            success: function(response) {
                if(response.status === 'success') {
                    $btn.removeClass('btn-outline-primary btn-primary').addClass('btn-success').html('<i class="fa-solid fa-circle-check"></i> Tersimpan');
                    setTimeout(() => {
                        $btn.prop('disabled', false).removeClass('btn-success').addClass('btn-primary').html('<i class="fa-solid fa-arrows-rotate"></i> Update');
                    }, 1200);
                }
            },
            error: function() {
                alert('Sistem gagal memproses penyimpanan. Periksa kembali inputan koordinat.');
                $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Simpan');
            }
        });
    });

    // Event Handler Cetak/Print Preview
    $('#btnPrint').on('click', function() {
        window.print();
    });

    // Event Handler Ekspor File Excel Stream
    $('#btnExport').on('click', function() {
        let deviceId = $('#device_id').val();
        let date = $('#date').val();
        window.location.href = `{{ route('verifikasi.export') }}?device_id=${deviceId}&date=${date}`;
    });
});
</script>
</body>
</html>