<?php

use App\Models\Setting;
use App\Services\SubscriptionStatus;

// App\Services\SubscriptionStatus -- sisi pembaca kontrak yang ditulis Sikampus Platform
// (repo sikampus-web, App\Services\TenantLicenseStatusWriter) ke tabel `settings` tenant ini.

it('menganggap status active kalau belum ada baris settings sama sekali (tenant lama/self-hosted)', function () {
    $subscription = SubscriptionStatus::load();

    expect($subscription->isGrace())->toBeFalse();
    expect($subscription->isSuspended())->toBeFalse();
    expect($subscription->message())->toBeNull();
    expect($subscription->graceEndsAt())->toBeNull();
});

it('membaca status grace dengan benar', function () {
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
    Setting::create(['key' => 'app_license_status', 'value' => 'suspended']);
    Setting::create(['key' => 'app_license_message', 'value' => 'Layanan dihentikan sementara.']);

    $subscription = SubscriptionStatus::load();

    expect($subscription->isSuspended())->toBeTrue();
    expect($subscription->isGrace())->toBeFalse();
    expect($subscription->message())->toBe('Layanan dihentikan sementara.');
});

it('menganggap status active/lifetime sebagai tidak grace dan tidak suspended', function () {
    Setting::create(['key' => 'app_license_status', 'value' => 'active']);

    expect(SubscriptionStatus::load()->isGrace())->toBeFalse();
    expect(SubscriptionStatus::load()->isSuspended())->toBeFalse();

    Setting::where('key', 'app_license_status')->update(['value' => 'lifetime']);

    expect(SubscriptionStatus::load()->isGrace())->toBeFalse();
    expect(SubscriptionStatus::load()->isSuspended())->toBeFalse();
});
