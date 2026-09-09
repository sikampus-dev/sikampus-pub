<?php

use App\Models\Setting;
use App\Models\UpdateRun;
use App\Services\Update\UpdateReporter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'sikampus_server.url' => 'https://app.sikampus.example',
        'sikampus.update.timeout' => 5,
        'app.url' => 'https://siak.kampus.ac.id',
    ]);

    Setting::updateOrCreate(['key' => 'app_license_key'], ['value' => 'KEY-BENAR']);

    $this->run = UpdateRun::create([
        'version_from' => '1.0.1',
        'version_to' => '1.1.0',
        'path' => UpdateRun::PATH_ARCHIVE,
        'status' => UpdateRun::STATUS_RUNNING,
        'step' => 'finalize',
    ]);
});

it('reports the license key and both versions to the platform', function () {
    Http::fake(['app.sikampus.example/*' => Http::response(['success' => true])]);

    expect(app(UpdateReporter::class)->report($this->run))->toContain('dilaporkan');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/installations/updated')
        && $request['license_key'] === 'KEY-BENAR'
        && $request['from_version'] === '1.0.1'
        && $request['to_version'] === '1.1.0'
        && $request['app_url'] === 'https://siak.kampus.ac.id');
});

// Pada titik ini berkas sudah tertukar dan aplikasi sudah berjalan di versi baru. Melempar
// exception akan menandai pembaruan yang BERHASIL sebagai gagal, lalu membuat orang mengulanginya.
it('never throws when the platform cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $note = app(UpdateReporter::class)->report($this->run);

    expect($note)->toContain('Pembaruan sendiri tetap berhasil');
});

it('never throws when the platform answers with an error', function () {
    Http::fake(['app.sikampus.example/*' => Http::response('', 500)]);

    expect(app(UpdateReporter::class)->report($this->run))->toContain('tetap berhasil');
});

it('skips reporting when there is no license key', function () {
    Setting::where('key', 'app_license_key')->delete();
    Http::fake();

    expect(app(UpdateReporter::class)->report($this->run))->toContain('dilewati');
    Http::assertNothingSent();
});

it('skips reporting when the platform address is not configured', function () {
    config(['sikampus_server.url' => '']);
    Http::fake();

    expect(app(UpdateReporter::class)->report($this->run))->toContain('dilewati');
    Http::assertNothingSent();
});
