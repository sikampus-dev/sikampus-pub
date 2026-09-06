<?php

use App\Models\Setting;

// App\Http\Middleware\EnsureSubscriptionActive -- blokir SEMUA rute grup 'web' (termasuk
// login itu sendiri) dan grup auth:sanctum di routes/api.php ketika langganan Sikampus Cloud
// tenant ini berstatus 'suspended'. Grup partner.api.key dikecualikan (integrasi
// sistem-ke-sistem, bukan "pengguna").

function markSuspended(): void
{
    // config('sikampus.managed') true = mensimulasikan tenant Cloud -- default lingkungan
    // test (dan default instalasi self-hosted sungguhan) adalah false, lihat grup test
    // "instalasi self-hosted" di bawah untuk kasus sebaliknya.
    config(['sikampus.managed' => true]);
    Setting::create(['key' => 'app_license_status', 'value' => 'suspended']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Layanan Sikampus Cloud sedang dihentikan sementara (uji coba).']);
}

it('memblokir halaman login itu sendiri (sebelum login) ketika langganan suspended', function () {
    markSuspended();

    $this->get(route('login'))
        ->assertStatus(402)
        ->assertSee('Layanan Sikampus Cloud sedang dihentikan sementara (uji coba).');
});

it('tidak memblokir apa pun ketika belum ada status lisensi sama sekali', function () {
    $this->get(route('login'))->assertOk();
});

it('tidak memblokir ketika status masih active', function () {
    Setting::create(['key' => 'app_license_status', 'value' => 'active']);

    $this->get(route('login'))->assertOk();
});

it('tidak memblokir ketika status masih grace (aplikasi tetap normal, cuma banner)', function () {
    Setting::create(['key' => 'app_license_status', 'value' => 'grace']);

    $this->get(route('login'))->assertOk();
});

it('memblokir halaman panel admin ketika langganan suspended, walau sudah login', function () {
    $admin = adminUser();
    markSuspended();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertStatus(402);
});

it('memblokir rute grup auth:sanctum di routes/api.php dengan JSON ketika suspended', function () {
    $admin = adminUser();
    markSuspended();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/user')
        ->assertStatus(402)
        ->assertJsonFragment(['message' => 'Layanan Sikampus Cloud sedang dihentikan sementara (uji coba).']);
});

it('tidak memblokir endpoint partner.api.key walau langganan suspended', function () {
    config(['partner_api.api_keys' => ['test-key']]);
    markSuspended();

    $this->withHeader('X-API-Key', 'test-key')
        ->get('/api/partner/ping')
        ->assertOk();
});

// Instalasi self-hosted (berbayar maupun gratis): notifikasi/blokir subscription tidak
// pernah relevan sama sekali, terlepas isi tabel settings -- lihat docblock
// App\Services\SubscriptionStatus.

it('tidak memblokir instalasi self-hosted sama sekali walau tabel settings-nya bilang suspended', function () {
    // SENGAJA TANPA config(['sikampus.managed' => true]) -- default false persis seperti
    // instalasi self-hosted sungguhan, walau baris settings-nya (mis. sisa migrasi/restore
    // dari Cloud) tetap bilang 'suspended'.
    Setting::create(['key' => 'app_license_status', 'value' => 'suspended']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Layanan Sikampus Cloud sedang dihentikan sementara (uji coba).']);

    $admin = adminUser();

    $this->get(route('login'))->assertOk();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
});
