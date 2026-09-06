<?php

use App\Models\Setting;

// Banner peringatan masa tenggang lisensi (partials.subscription-grace-banner), disuntik ke
// layouts.web lewat View Composer di App\Providers\AppServiceProvider::boot() -- lihat
// App\Services\SubscriptionStatus.

it('menampilkan pesan grace di panel admin ketika status lisensi grace', function () {
    Setting::create(['key' => 'app_license_status', 'value' => 'grace']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Masa tenggang uji coba, segera perpanjang.']);

    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Masa tenggang uji coba, segera perpanjang.');
});

it('tidak menampilkan banner apa pun ketika status lisensi active', function () {
    Setting::create(['key' => 'app_license_status', 'value' => 'active']);

    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('tenggang');
});

it('tidak menampilkan banner apa pun ketika belum ada status lisensi sama sekali', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('tenggang');
});
