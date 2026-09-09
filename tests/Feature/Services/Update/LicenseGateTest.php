<?php

use App\Models\Setting;
use App\Services\Update\LicenseGate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'sikampus_server.url' => 'https://app.sikampus.example',
        'sikampus.update.timeout' => 5,
    ]);
});

function setLicenseKey(string $key): void
{
    Setting::updateOrCreate(['key' => 'app_license_key'], ['value' => $key]);
}

it('blocks the update when no license key is stored', function () {
    Http::fake();

    $result = app(LicenseGate::class)->check();

    expect($result['state'])->toBe(LicenseGate::MISSING);
    expect($result['message'])->toContain('membutuhkan license key');
    // Portal tidak dihubungi sama sekali kalau memang tidak ada key untuk diverifikasi.
    Http::assertNothingSent();
});

it('blocks the update when the platform does not recognise the key', function () {
    setLicenseKey('KEY-SALAH');
    Http::fake(['app.sikampus.example/*' => Http::response(['valid' => false], 404)]);

    $result = app(LicenseGate::class)->check();

    expect($result['state'])->toBe(LicenseGate::UNKNOWN);
    expect($result['message'])->toContain('tidak dikenali');
});

it('allows the update when the platform recognises the key', function () {
    setLicenseKey('KEY-BENAR');
    Http::fake(['app.sikampus.example/*' => Http::response(['valid' => true])]);

    expect(app(LicenseGate::class)->allows())->toBeTrue();
    Http::assertSent(fn ($request) => $request['license_key'] === 'KEY-BENAR');
});

// Pembedaan ini yang paling penting: kampus tidak boleh dibuat mengira lisensinya bermasalah
// padahal portal yang sedang mati — itu membuat orang mengganti key yang sebenarnya sudah benar.
it('separates an unreachable platform from an invalid licence', function () {
    setLicenseKey('KEY-BENAR');
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $result = app(LicenseGate::class)->check();

    expect($result['state'])->toBe(LicenseGate::UNREACHABLE);
    expect($result['message'])->toContain('bukan berarti lisensi Anda bermasalah');
});

// 500/502 dari portal juga BUKAN pernyataan bahwa lisensinya salah.
it('treats a platform error response as unreachable, not invalid', function () {
    setLicenseKey('KEY-BENAR');
    Http::fake(['app.sikampus.example/*' => Http::response('', 502)]);

    expect(app(LicenseGate::class)->check()['state'])->toBe(LicenseGate::UNREACHABLE);
});

it('blocks when the platform address is not configured at all', function () {
    setLicenseKey('KEY-BENAR');
    config(['sikampus_server.url' => '']);
    Http::fake();

    $result = app(LicenseGate::class)->check();

    expect($result['state'])->toBe(LicenseGate::UNREACHABLE);
    expect($result['message'])->toContain('SIKAMPUS_SERVER_URL');
    Http::assertNothingSent();
});

it('treats a blank license key the same as none', function () {
    setLicenseKey('   ');
    Http::fake();

    expect(app(LicenseGate::class)->check()['state'])->toBe(LicenseGate::MISSING);
});
