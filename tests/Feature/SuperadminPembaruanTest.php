<?php

use App\Models\Setting;
use App\Models\UpdateRun;
use App\Services\Update\InstallationInspector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config([
        'sikampus.version' => '1.0.0',
        'sikampus.update.enabled' => true,
        'sikampus_server.url' => '',
    ]);

    // Sejak pembaruan menuntut license key yang dikenali platform, sebagian besar test di
    // berkas ini perlu instalasi berlisensi. Yang menguji gerbangnya sendiri menimpa ini.
    Setting::updateOrCreate(['key' => 'app_license_key'], ['value' => 'KEY-BENAR']);
    config(['sikampus_server.url' => 'https://app.sikampus.example']);

    Http::fake([
        // Menjawab berdasarkan KEY yang dikirim, bukan sekadar URL: Laravel memakai stub yang
        // cocok PERTAMA, jadi stub per-URL di beforeEach akan selalu menang atas stub yang
        // dipasang di dalam test — dan test "key tidak dikenali" tidak akan pernah jalan.
        'app.sikampus.example/api/licenses/verify' => fn ($request) => $request['license_key'] === 'KEY-BENAR'
            ? Http::response(['valid' => true])
            : Http::response(['valid' => false], 404),
        'api.github.com/*' => Http::response([
            'tag_name' => 'v1.2.0',
            'name' => 'Sikampus v1.2.0',
            'body' => 'Catatan.',
            'assets' => [
                ['name' => 'sikampus-1.2.0.zip', 'browser_download_url' => 'https://example.test/z.zip'],
                ['name' => 'sikampus-1.2.0.zip.sha256', 'browser_download_url' => 'https://example.test/z.sha256'],
            ],
        ]),
    ]);
});

it('is reachable only by superadmin', function () {
    $this->get(route('superadmin.pembaruan'))->assertRedirect(route('login'));
    $this->actingAs(adminUser('admin_akademik'))
        ->get(route('superadmin.pembaruan'))
        ->assertRedirect(route('login'));
});

it('offers the update when a newer release exists', function () {
    $this->actingAs(adminUser())
        ->get(route('superadmin.pembaruan'))
        ->assertOk()
        ->assertSee('Mulai perbarui ke v1.2.0')
        ->assertSee('Backup database Anda sekarang');
});

// Konfirmasi backup adalah gerbang keras, bukan hiasan: isi database tidak bisa dikembalikan
// otomatis oleh rollback mana pun yang kita punya.
it('refuses to start without the backup confirmation', function () {
    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.mulai'), [])
        ->assertSessionHasErrors('confirm');

    expect(UpdateRun::count())->toBe(0);
});

it('refuses a second update while one is still running', function () {
    UpdateRun::create([
        'version_from' => '1.0.0', 'version_to' => '1.1.0',
        'path' => UpdateRun::PATH_ARCHIVE, 'status' => UpdateRun::STATUS_RUNNING, 'step' => 'download',
    ]);

    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.mulai'), ['confirm' => '1'])
        ->assertSessionHas('error');

    expect(UpdateRun::count())->toBe(1);
});

it('creates a run and picks a path when started', function () {
    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.mulai'), ['confirm' => '1'])
        ->assertRedirect(route('superadmin.pembaruan'));

    $run = UpdateRun::sole();
    expect($run->version_to)->toBe('v1.2.0');
    expect($run->status)->toBe(UpdateRun::STATUS_RUNNING);
    expect($run->step)->toBe(UpdateRun::STEPS[$run->path][0]);
    expect($run->path)->toBeIn([UpdateRun::PATH_ARCHIVE, UpdateRun::PATH_GIT]);
});

// Setelah berkas hidup mulai ditukar, "batal" berarti meninggalkan instalasi setengah jadi.
it('refuses to cancel once the swap has begun', function () {
    UpdateRun::create([
        'version_from' => '1.0.0', 'version_to' => '1.2.0',
        'path' => UpdateRun::PATH_ARCHIVE, 'status' => UpdateRun::STATUS_RUNNING, 'step' => 'swap',
    ]);

    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.batal'))
        ->assertSessionHas('error');

    expect(UpdateRun::sole()->status)->toBe(UpdateRun::STATUS_RUNNING);
});

it('allows cancelling before anything has been touched', function () {
    UpdateRun::create([
        'version_from' => '1.0.0', 'version_to' => '1.2.0',
        'path' => UpdateRun::PATH_ARCHIVE, 'status' => UpdateRun::STATUS_RUNNING, 'step' => 'download',
    ]);

    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.batal'))
        ->assertSessionHas('status');

    expect(UpdateRun::sole()->status)->toBe(UpdateRun::STATUS_FAILED);
});

it('refuses to update a cloud-managed installation', function () {
    $this->mock(InstallationInspector::class, function ($mock) {
        $mock->shouldReceive('type')->andReturn(InstallationInspector::TYPE_MANAGED);
        $mock->shouldReceive('typeLabel')->andReturn('Sikampus Cloud (dikelola)');
        $mock->shouldReceive('writablePaths')->andReturn([]);
        $mock->shouldReceive('isFullyWritable')->andReturn(true);
        $mock->shouldReceive('canUseGitPath')->andReturn(false);
    });

    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.mulai'), ['confirm' => '1'])
        ->assertSessionHas('error');

    expect(UpdateRun::count())->toBe(0);
});

it('refuses to update when the application directory is not writable', function () {
    $this->mock(InstallationInspector::class, function ($mock) {
        $mock->shouldReceive('type')->andReturn(InstallationInspector::TYPE_ARCHIVE);
        $mock->shouldReceive('isFullyWritable')->andReturn(false);
    });

    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.mulai'), ['confirm' => '1'])
        ->assertSessionHas('error');

    expect(UpdateRun::count())->toBe(0);
});

// Gerbang lisensi: pembaruan menuntut license key yang DIKENALI Sikampus Platform.
// Pengecekan pembaruan sendiri tetap terbuka — instalasi tanpa lisensi tetap diberi tahu ada
// versi baru, karena menyembunyikannya hanya membuat mereka tidak tahu sedang tertinggal.
it('refuses to start an update when no license key is stored', function () {
    Setting::where('key', 'app_license_key')->delete();

    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.mulai'), ['confirm' => '1'])
        ->assertSessionHas('error');

    expect(UpdateRun::count())->toBe(0);
});

it('refuses to start when the platform does not recognise the license key', function () {
    Setting::updateOrCreate(['key' => 'app_license_key'], ['value' => 'KEY-SALAH']);

    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.mulai'), ['confirm' => '1'])
        ->assertSessionHas('error');

    expect(UpdateRun::count())->toBe(0);
});

it('starts the update once the platform confirms the license key', function () {
    $this->actingAs(adminUser())
        ->post(route('superadmin.pembaruan.mulai'), ['confirm' => '1'])
        ->assertRedirect(route('superadmin.pembaruan'));

    expect(UpdateRun::count())->toBe(1);
});

// Halaman harus memberi tahu syaratnya SEBELUM tombol ditekan, bukan menolak setelahnya.
it('states the licence requirement on the start screen', function () {
    Setting::where('key', 'app_license_key')->delete();

    $this->actingAs(adminUser())
        ->get(route('superadmin.pembaruan'))
        ->assertOk()
        ->assertSee('Pembaruan membutuhkan license key');
});
