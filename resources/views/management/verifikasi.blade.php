<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Verifikasi Titik Parkir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Filter Verifikasi Titik Parkir</h5>
        </div>
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Pilih Kendaraan / Device</label>
                    <select id="device_id" class="form-select" required>
                        <option value="">-- Pilih Kendaraan --</option>
                        @foreach($devices as $device)
                            <option value="{{ $device->id }}">{{ $device->plate_number }} - {{ $device->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Pilih Tanggal</label>
                    <input type="date" id="date" class="form-select" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Muat Rekap</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Rekap & Verifikasi Lokasi</h5>
            <span class="badge bg-secondary" id="totalPoints">0 Titik Parkir</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="20%">Mulai Parkir</th>
                            <th width="15%">Durasi</th>
                            <th width="15%">Koordinat GPS</th>
                            <th width="20%">Lat Long Pengerjaan (Kolom Tambahan)</th>
                            <th width="20%">Keterangan Lapangan (Kolom Tambahan)</th>
                            <th width="5%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Silakan pilih kendaraan dan tanggal terlebih dahulu.</td>
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

    // Ambil Data
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        let deviceId = $('#device_id').val();
        let date = $('#date').val();

        $('#tableBody').html('<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div> Memuat data koordinat...</td></tr>');

        $.ajax({
            url: "{{ route('verifikasi.data') }}",
            type: "GET",
            data: { device_id: deviceId, date: date },
            success: function(data) {
                $('#totalPoints').text(data.length + ' Titik Parkir');
                let html = '';
                
                if(data.length === 0) {
                    html = '<tr><td colspan="7" class="text-center text-danger py-4">Tidak ada data parkir (> 5 mnt) pada tanggal ini.</td></tr>';
                } else {
                    data.forEach((item, index) => {
                        let statusBtn = item.is_verified ? 'btn-primary' : 'btn-outline-primary';
                        let labelBtn = item.is_verified ? 'Update' : 'Simpan';
                        
                        html += `
                            <tr>
                                <td class="text-center">${index + 1}</td>
                                <td><span class="fw-bold">${item.waktu_mulai}</span></td>
                                <td><span class="badge bg-warning text-dark">${item.durasi}</span></td>
                                <td>
                                    <small class="text-muted d-block">${item.koordinat_gps}</small>
                                    <a href="https://maps.google.com/?q=${item.koordinat_gps}" target="_blank" class="btn btn-sm btn-light py-0 px-1 border" style="font-size:10px;">Buka Map</a>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm input-latlong" value="${item.lat_long_pengerjaan || ''}" placeholder="Contoh: -5.148, 119.432">
                                </td>
                                <td>
                                    <textarea class="form-control form-control-sm input-keterangan" rows="1" placeholder="Isi keterangan pengerjaan...">${item.keterangan || ''}</textarea>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm ${statusBtn} btn-simpan" 
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

    // Simpan Data Verifikasi per Baris
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
                    $btn.removeClass('btn-outline-primary btn-primary').addClass('btn-success').text('Tersimpan');
                    setTimeout(() => {
                        $btn.prop('disabled', false).removeClass('btn-success').addClass('btn-primary').text('Update');
                    }, 1200);
                }
            },
            error: function() {
                alert('Gagal menyimpan data.');
                $btn.prop('disabled', false).text('Simpan');
            }
        });
    });
});
</script>
</body>
</html>