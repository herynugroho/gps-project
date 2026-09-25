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
}
