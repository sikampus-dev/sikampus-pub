<?php

use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Support\Facades\Hash;

it('creates a superadmin with the given credentials', function () {
    $this->artisan('sikampus:create-admin', [
        '--name' => 'Rektorat',
        '--email' => 'admin@kampus.ac.id',
        '--password' => 'rahasia-panjang',
    ])->assertSuccessful();

    $user = User::where('email', 'admin@kampus.ac.id')->sole();

    expect($user->role)->toBe('admin');
    expect($user->status)->toBe('active');
    expect(Hash::check('rahasia-panjang', $user->password))->toBeTrue();
    // Role Spatie adalah sumber kebenaran akses panel; tanpa ini akun bisa login tapi tidak
    // bisa membuka satu modul pun.
    expect($user->hasRole('Superadmin'))->toBeTrue();
});

it('rejects a password that is too short', function () {
    $this->artisan('sikampus:create-admin', [
        '--name' => 'Rektorat',
        '--email' => 'admin@kampus.ac.id',
        '--password' => 'pendek',
    ])->assertFailed();

    expect(User::where('email', 'admin@kampus.ac.id')->exists())->toBeFalse();
});

it('refuses to overwrite an existing account without --force', function () {
    User::factory()->create(['email' => 'admin@kampus.ac.id']);

    $this->artisan('sikampus:create-admin', [
        '--name' => 'Rektorat',
        '--email' => 'admin@kampus.ac.id',
        '--password' => 'rahasia-panjang',
    ])->assertFailed();
});

it('updates the password when --force is given', function () {
    User::factory()->create(['email' => 'admin@kampus.ac.id']);

    $this->artisan('sikampus:create-admin', [
        '--name' => 'Rektorat',
        '--email' => 'admin@kampus.ac.id',
        '--password' => 'rahasia-panjang',
        '--force' => true,
    ])->assertSuccessful();

    expect(Hash::check('rahasia-panjang', User::where('email', 'admin@kampus.ac.id')->value('password')))->toBeTrue();
});

// Password lazim ikut tercatat di log saat perintah ini dijalankan sistem lain lewat Process.
it('never echoes the password back', function () {
    $this->artisan('sikampus:create-admin', [
        '--name' => 'Rektorat',
        '--email' => 'admin@kampus.ac.id',
        '--password' => 'rahasia-panjang',
    ])->doesntExpectOutputToContain('rahasia-panjang');
});

// Inti perbaikan keamanannya: `migrate --seed` tidak boleh lagi menghasilkan akun berkredensial
// publik. Kalau seseorang mengembalikan UserSeeder ke DatabaseSeeder, test ini gagal.
it('does not create any default account when the database is seeded', function () {
    $this->seed();

    expect(User::where('email', 'admin@gmail.com')->exists())->toBeFalse();
    expect(User::where('email', 'admin@pmb.com')->exists())->toBeFalse();
});

// Penjagaan environment ada di kode, bukan cuma di dokumentasi: perintah seed bisa dijalankan
// di produksi oleh orang yang tidak menyadari akibatnya.
it('refuses to seed demo accounts outside the local environment', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoAccountsSeeder)->run())
        ->toThrow(RuntimeException::class, 'hanya boleh dijalankan di environment "local"');
});

// Argumen proses terbaca semua user lokal lewat /proc, jadi sistem lain yang memanggil perintah
// ini harus punya jalan memberi password tanpa menaruhnya di argv.
it('accepts the password from an environment variable', function () {
    putenv('SIKAMPUS_ADMIN_PASSWORD=rahasia-dari-env');

    $this->artisan('sikampus:create-admin', [
        '--name' => 'Rektorat',
        '--email' => 'admin@kampus.ac.id',
    ])->assertSuccessful();

    putenv('SIKAMPUS_ADMIN_PASSWORD');

    expect(Hash::check('rahasia-dari-env', User::where('email', 'admin@kampus.ac.id')->value('password')))->toBeTrue();
});
