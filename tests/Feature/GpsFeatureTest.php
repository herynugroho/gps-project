<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Device;
use App\Models\Position;
use App\Models\VerifikasiParkir;
use Carbon\Carbon;

class GpsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'admin@primatrack.com',
            'role' => 'super_admin',
        ]);

        $this->device = Device::create([
            'imei' => '123456789012345',
            'name' => 'Avanza Unit 01',
            'plate_number' => 'DD 1234 AB',
            'module_type' => 'GT06N',
            'fuel_ratio' => 10.00,
            'acc_status' => 1,
            'fuel_status' => 1,
            'last_latitude' => -5.147665,
            'last_longitude' => 119.432731,
            'last_speed' => 35.5,
            'last_gps_time' => Carbon::now('Asia/Makassar'),
            'last_online' => Carbon::now('Asia/Makassar'),
        ]);

        Position::create([
            'imei' => '123456789012345',
            'latitude' => -5.147665,
            'longitude' => 119.432731,
            'speed' => 35.5,
            'course' => 90,
            'gps_time' => Carbon::now('Asia/Makassar'),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('PRIMA TRACK');
    }

    public function test_gps_api_data_returns_denormalized_telemetry(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/gps-data');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'imei' => '123456789012345',
            'name' => 'Avanza Unit 01',
            'plate_number' => 'DD 1234 AB',
            'module_type' => 'GT06N',
            'latitude' => -5.147665,
            'longitude' => 119.432731,
            'acc_status' => 1,
        ]);
    }

    public function test_user_can_store_and_destroy_device(): void
    {
        $response = $this->actingAs($this->user)->post('/devices', [
            'imei' => '999999999999999',
            'name' => 'Truk Hino 02',
            'plate_number' => 'DD 9999 ZZ',
            'module_type' => 'GT06N',
            'fuel_ratio' => 8.5,
        ]);

        $response->assertRedirect(route('devices.index'));
        $this->assertDatabaseHas('devices', ['imei' => '999999999999999']);

        $newDevice = Device::where('imei', '999999999999999')->first();
        $delResponse = $this->actingAs($this->user)->delete("/devices/{$newDevice->id}");
        $delResponse->assertRedirect(route('devices.index'));
        $this->assertDatabaseMissing('devices', ['id' => $newDevice->id]);
    }

    public function test_history_api_returns_positions(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/history/{$this->device->imei}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'imei' => '123456789012345',
            'latitude' => -5.147665,
            'longitude' => 119.432731,
        ]);
    }

    public function test_send_command_validates_and_rejects_unauthorized_commands(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/send-command', [
            'imei' => '123456789012345',
            'command' => 'MALICIOUS_CMD#',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['status' => 'error']);
    }

    public function test_verifikasi_parkir_save_and_retrieve(): void
    {
        $waktu = '2026-09-25 10:00:00';

        $saveResponse = $this->actingAs($this->user)->postJson('/management/verifikasi/simpan', [
            'device_id' => $this->device->id,
            'waktu_mulai' => $waktu,
            'koordinat_gps' => '-5.147665,119.432731',
            'lat_long_pengerjaan' => '-5.147700,119.432800',
            'keterangan' => 'Pemasangan pipa baru',
            'nama_driver' => 'Budi Santoso',
        ]);

        $saveResponse->assertStatus(200);
        $saveResponse->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('verifikasi_parkir', [
            'vehicle_id' => $this->device->id,
            'keterangan' => 'Pemasangan pipa baru',
            'nama_driver' => 'Budi Santoso',
        ]);
    }

    public function test_proxy_wa_blocks_ssrf_internal_ips(): void
    {
        $response = $this->postJson('/api/proxy-wa', [
            'domain' => 'http://127.0.0.1:5023',
            'token' => 'dummy_token',
            'phone' => '08123456789',
            'action' => 'check',
        ]);

        $response->assertStatus(403);
    }

    public function test_history_and_verifikasi_ignore_corrupt_coordinates(): void
    {
        // Masukkan data posisi anomali (misal akibat byte shift protokol)
        Position::create([
            'imei' => $this->device->imei,
            'latitude' => -1319.46,
            'longitude' => 1942.92,
            'speed' => 0,
            'course' => 0,
            'gps_time' => Carbon::now('Asia/Makassar')->subMinutes(10),
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/history/{$this->device->imei}");
        $response->assertStatus(200);

        $json = $response->json();
        foreach ($json as $item) {
            $this->assertGreaterThanOrEqual(-90, (float)$item['latitude']);
            $this->assertLessThanOrEqual(90, (float)$item['latitude']);
            $this->assertGreaterThanOrEqual(-180, (float)$item['longitude']);
            $this->assertLessThanOrEqual(180, (float)$item['longitude']);
        }

        // Verifikasi parkir juga mengabaikan data anomali
        $date = Carbon::today('Asia/Makassar')->toDateString();
        $verifResponse = $this->actingAs($this->user)->getJson("/management/verifikasi/data?device_id={$this->device->id}&date={$date}");
        $verifResponse->assertStatus(200);
        foreach ($verifResponse->json() as $point) {
            $coords = explode(',', $point['koordinat_gps']);
            $this->assertGreaterThanOrEqual(-90, (float)$coords[0]);
            $this->assertLessThanOrEqual(90, (float)$coords[0]);
            $this->assertGreaterThanOrEqual(-180, (float)$coords[1]);
            $this->assertLessThanOrEqual(180, (float)$coords[1]);
        }
    }

    public function test_verifikasi_merges_contiguous_parking_and_ignores_moving_blank_spots(): void
    {
        // Bersihkan posisi sebelumnya untuk pengujian terkontrol
        Position::where('imei', $this->device->imei)->delete();
        $date = '2026-09-25';

        // 1. Sesi Parkir Berkelanjutan: Tiba jam 10:00, heartbeat jam 10:15 di titik yang sama, berangkat jam 10:30
        Position::create([
            'imei' => $this->device->imei,
            'latitude' => -5.147665,
            'longitude' => 119.432731,
            'speed' => 0,
            'course' => 0,
            'gps_time' => "{$date} 10:00:00",
        ]);
        Position::create([
            'imei' => $this->device->imei,
            'latitude' => -5.147665,
            'longitude' => 119.432731,
            'speed' => 0,
            'course' => 0,
            'gps_time' => "{$date} 10:15:00",
        ]);
        Position::create([
            'imei' => $this->device->imei,
            'latitude' => -5.147700,
            'longitude' => 119.432750,
            'speed' => 20,
            'course' => 90,
            'gps_time' => "{$date} 10:30:00",
        ]);

        // 2. Blank Spot saat Melaju: Kecepatan 50 km/h, terputus 10 menit, tersambung 3 km berikutnya di kecepatan 45 km/h
        Position::create([
            'imei' => $this->device->imei,
            'latitude' => -5.150000,
            'longitude' => 119.440000,
            'speed' => 50,
            'course' => 90,
            'gps_time' => "{$date} 11:00:00",
        ]);
        Position::create([
            'imei' => $this->device->imei,
            'latitude' => -5.170000,
            'longitude' => 119.460000,
            'speed' => 45,
            'course' => 90,
            'gps_time' => "{$date} 11:10:00",
        ]);

        $response = $this->actingAs($this->user)->getJson("/management/verifikasi/data?device_id={$this->device->id}&date={$date}");
        $response->assertStatus(200);

        $data = $response->json();

        // Harus menghasilkan tepat 1 sesi parkir (bukan 2 sesi bertumpuk, dan blank spot tidak dihitung parkir)
        $this->assertCount(1, $data);
        $this->assertEquals('30 mnt', $data[0]['durasi']);
        $this->assertStringContainsString('-5.147665,119.432731', $data[0]['koordinat_gps']);
    }
}


