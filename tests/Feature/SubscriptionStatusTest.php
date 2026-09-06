<?php

use App\Models\Setting;
use App\Services\SubscriptionStatus;

// App\Services\SubscriptionStatus -- sisi pembaca kontrak yang ditulis Sikampus Platform
// (repo sikampus-web, App\Services\TenantLicenseStatusWriter) ke tabel `settings` tenant ini.
//
// Gerbang config('sikampus.managed') SENGAJA diuji terpisah dari isi tabel settings -- lihat
// grup "gerbang instalasi self-hosted" di bawah. Test lain di file ini men-set
// config(['sikampus.managed' => true]) secara eksplisit untuk mensimulasikan tenant Cloud,
// karena default lingkungan test (dan default instalasi self-hosted sungguhan) adalah false.

it('menganggap status active kalau belum ada baris settings sama sekali (tenant Cloud lama)', function () {
    config(['sikampus.managed' => true]);

    $subscription = SubscriptionStatus::load();

    expect($subscription->isGrace())->toBeFalse();
    expect($subscription->isSuspended())->toBeFalse();
    expect($subscription->message())->toBeNull();
    expect($subscription->graceEndsAt())->toBeNull();
});

it('membaca status grace dengan benar', function () {
    config(['sikampus.managed' => true]);
    Setting::create(['key' => 'app_license_status', 'value' => 'grace']);
    Setting::create(['key' => 'app_license_grace_ends_at', 'value' => '2026-10-05']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Masa tenggang hingga 5 Oktober.']);

    $subscription = SubscriptionStatus::load();

    expect($subscription->isGrace())->toBeTrue();
    expect($subscription->isSuspended())->toBeFalse();
    expect($subscription->message())->toBe('Masa tenggang hingga 5 Oktober.');
    expect($subscription->graceEndsAt())->toBe('2026-10-05');
});

it('membaca status suspended dengan benar', function () {
    config(['sikampus.managed' => true]);
    Setting::create(['key' => 'app_license_status', 'value' => 'suspended']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Layanan dihentikan sementara.']);

    $subscription = SubscriptionStatus::load();

    expect($subscription->isSuspended())->toBeTrue();
    expect($subscription->isGrace())->toBeFalse();
    expect($subscription->message())->toBe('Layanan dihentikan sementara.');
});

it('menganggap status active/lifetime sebagai tidak grace dan tidak suspended', function () {
    config(['sikampus.managed' => true]);
    Setting::create(['key' => 'app_license_status', 'value' => 'active']);

    expect(SubscriptionStatus::load()->isGrace())->toBeFalse();
    expect(SubscriptionStatus::load()->isSuspended())->toBeFalse();

    Setting::where('key', 'app_license_status')->update(['value' => 'lifetime']);

    expect(SubscriptionStatus::load()->isGrace())->toBeFalse();
    expect(SubscriptionStatus::load()->isSuspended())->toBeFalse();
});

// Gerbang instalasi self-hosted -- lihat docblock kelas App\Services\SubscriptionStatus.
// Notifikasi/blokir subscription HANYA untuk Sikampus Cloud (config('sikampus.managed')).

it('mengabaikan status settings apa pun ketika bukan instalasi Cloud (default self-hosted)', function () {
    config(['sikampus.managed' => false]);
    Setting::create(['key' => 'app_license_status', 'value' => 'suspended']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Layanan dihentikan sementara.']);

    $subscription = SubscriptionStatus::load();

    expect($subscription->isSuspended())->toBeFalse();
    expect($subscription->isGrace())->toBeFalse();
    expect($subscription->message())->toBeNull();
});

it('tetap active pada instalasi self-hosted walau ada baris settings sisa dari Cloud (mis. hasil restore/migrasi)', function () {
    config(['sikampus.managed' => false]);
    Setting::create(['key' => 'app_license_status', 'value' => 'grace']);
    Setting::create(['key' => 'app_license_grace_ends_at', 'value' => '2026-10-05']);

    $subscription = SubscriptionStatus::load();

    expect($subscription->isGrace())->toBeFalse();
    expect($subscription->graceEndsAt())->toBeNull();
});
