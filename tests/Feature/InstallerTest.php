<?php

use App\Models\User;
use App\Services\Installer\EnvWriter;
use App\Support\Installer\InstallationState;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Suite ini menguji aplikasi yang BELUM terpasang, jadi penanda global dari tests/Pest.php
    // harus dilepas dulu.
    File::delete(InstallationState::lockPath());
});

afterEach(function () {
    File::delete(InstallationState::lockPath());
});

it('sends visitors of a normal page to the installer while not installed', function () {
    $this->get('/dashboard')->assertRedirect(route('install.index'));
});

it('opens the requirements page when not installed', function () {
    $this->get(route('install.index'))
        ->assertOk()
        ->assertSee('Persyaratan server');
});

// PENJAGAAN TERPENTING: kalau /install tetap hidup setelah aplikasi terpasang, siapa pun yang
// menemukan URL-nya bisa menimpa .env dan membuat superadmin baru.
it('closes every installer route once the application is installed', function () {
    InstallationState::markInstalled();

    $this->get(route('install.index'))->assertNotFound();
    $this->get(route('install.database'))->assertNotFound();
    $this->post(route('install.database.store'))->assertNotFound();
    $this->post(route('install.execute'))->assertNotFound();
});

// 404, bukan 403: 403 mengonfirmasi bahwa endpoint-nya ada dan hanya sedang tertutup.
it('answers 404 rather than 403 for a closed installer', function () {
    InstallationState::markInstalled();

    $this->get(route('install.index'))->assertStatus(404);
});

// Instalasi yang sudah berjalan sejak sebelum installer ini ada tidak punya berkas kunci. Tanpa
// pengadopsian ini, mereka akan dilempar ke wizard pemasangan begitu memperbarui versi — dan
// wizard itu menawarkan menimpa .env di atas data kampus yang hidup.
it('adopts an existing installation that has users but no lock file', function () {
    User::factory()->create();

    expect(InstallationState::isInstalled())->toBeTrue();
    expect(is_file(InstallationState::lockPath()))->toBeTrue();

    $this->get(route('install.index'))->assertNotFound();
});

it('treats an empty database as not installed', function () {
    expect(InstallationState::isInstalled())->toBeFalse();
});

it('rejects database credentials that do not connect', function () {
    $this->post(route('install.database.store'), [
        'db_host' => '127.0.0.1',
        'db_port' => 3306,
        'db_database' => 'database_yang_tidak_ada_'.uniqid(),
        'db_username' => 'user_yang_tidak_ada',
        'db_password' => 'salah',
    ])->assertSessionHasErrors('db_host');
});

// Password database tidak boleh kembali ke form: itu berarti menyimpannya di sesi lalu
// merendernya lagi ke HTML.
it('never flashes the database password back', function () {
    $this->post(route('install.database.store'), [
        'db_host' => '127.0.0.1',
        'db_port' => 3306,
        'db_database' => 'tidak_ada_'.uniqid(),
        'db_username' => 'salah',
        'db_password' => 'rahasia-db',
    ])->assertSessionMissing('_old_input.db_password');
});

it('requires the database step before the account step', function () {
    $this->get(route('install.account'))->assertRedirect(route('install.database'));
    $this->get(route('install.run'))->assertRedirect(route('install.database'));
});

it('validates the admin account fields', function () {
    $this->withSession(['install.database' => ['db_host' => 'x']])
        ->post(route('install.account.store'), [])
        ->assertSessionHasErrors(['institution_name', 'admin_name', 'admin_email', 'admin_password']);
});

it('rejects a mismatched password confirmation', function () {
    $this->withSession(['install.database' => ['db_host' => 'x']])
        ->post(route('install.account.store'), [
            'institution_name' => 'Universitas Contoh',
            'admin_name' => 'Rektorat',
            'admin_email' => 'admin@kampus.ac.id',
            'admin_password' => 'rahasia-panjang',
            'admin_password_confirmation' => 'beda',
        ])->assertSessionHasErrors('admin_password');
});

it('refuses to run the installation without both earlier steps', function () {
    $this->post(route('install.execute'))->assertRedirect(route('install.database'));
});

// Halaman gagal dan halaman konfirmasi memakai view yang sama. Tanpa status berbeda, pemantauan
// otomatis akan membaca pemasangan yang gagal sebagai berhasil.
//
// Kegagalan dipaksa dari titik PERTAMA (penulisan .env), bukan lewat kredensial database yang
// salah: di dalam transaksi RefreshDatabase, mengalihkan koneksi ke database lain tidak berlaku,
// sehingga migrasi justru berhasil dan test memeriksa jalur yang keliru.
it('answers 422 when the installation itself fails', function () {
    // PrepareInstallerEnvironment memaksa sesi ke driver berkas sebelum StartSession berjalan;
    // tanpa menyamakan driver di sini, sesi yang disiapkan withSession() (driver array bawaan
    // testing) tidak akan pernah ditemukan oleh request-nya.
    config(['session.driver' => 'file']);

    // Path yang mustahil ditulis — dan sekaligus menjamin test ini tidak pernah menyentuh .env
    // repo ini sendiri.
    $this->app->bind(EnvWriter::class,
        fn () => new EnvWriter('/dev/null/tidak-mungkin/.env'));

    $this->withSession([
        'install.database' => [
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'apa_pun',
            'db_username' => 'root',
            'db_password' => '',
        ],
        'install.account' => [
            'institution_name' => 'Universitas Contoh',
            'admin_name' => 'Rektorat',
            'admin_email' => 'admin@kampus.ac.id',
            'admin_password' => 'rahasia-panjang',
        ],
    ])->post(route('install.execute'))
        ->assertStatus(422)
        ->assertSee('Pemasangan gagal');

    // Pemasangan yang gagal tidak boleh menutup installer — kalau ditutup, tidak ada jalan
    // memperbaiki dan mengulang selain menghapus berkas kunci lewat shell.
    expect(is_file(InstallationState::lockPath()))->toBeFalse();
});
