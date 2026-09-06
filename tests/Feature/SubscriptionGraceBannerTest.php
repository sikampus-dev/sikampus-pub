<?php

use App\Models\Setting;

// Banner peringatan masa tenggang lisensi (partials.subscription-grace-banner), disuntik ke
// layouts.web lewat View Composer di App\Providers\AppServiceProvider::boot() -- lihat
// App\Services\SubscriptionStatus.

it('menampilkan pesan grace di panel admin ketika status lisensi grace', function () {
    config(['sikampus.managed' => true]);
    Setting::create(['key' => 'app_license_status', 'value' => 'grace']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Masa tenggang uji coba, segera perpanjang.']);

    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Masa tenggang uji coba, segera perpanjang.');
});

it('tidak menampilkan banner apa pun ketika status lisensi active', function () {
    config(['sikampus.managed' => true]);
    Setting::create(['key' => 'app_license_status', 'value' => 'active']);

    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('tenggang');
});

it('tidak menampilkan banner apa pun ketika belum ada status lisensi sama sekali', function () {
    config(['sikampus.managed' => true]);
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('tenggang');
});

// Instalasi self-hosted: banner tidak pernah relevan sama sekali -- lihat docblock
// App\Services\SubscriptionStatus.

it('tidak menampilkan banner apa pun di instalasi self-hosted walau tabel settings-nya bilang grace', function () {
    // SENGAJA TANPA config(['sikampus.managed' => true]).
    Setting::create(['key' => 'app_license_status', 'value' => 'grace']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Masa tenggang uji coba, segera perpanjang.']);

    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Masa tenggang uji coba, segera perpanjang.')
        ->assertDontSee('tenggang');
});
