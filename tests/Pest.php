<?php

use App\Models\Dosen;
use App\Models\Prodi;
use App\Models\Role;
use App\Models\User;
use App\Support\Installer\InstallationState;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Seluruh suite menguji aplikasi yang SUDAH terpasang. Tanpa penanda ini,
    // App\Http\Middleware\EnsureAppIsInstalled membaca database kosong milik RefreshDatabase
    // sebagai "belum terpasang" dan mengalihkan setiap request ke wizard pemasangan — yang akan
    // menggagalkan hampir semua test dengan sebab yang tidak ada hubungannya dengan yang diuji.
    // Test installer sendiri menghapus penanda ini di beforeEach-nya masing-masing.
    ->beforeEach(fn () => InstallationState::markInstalled())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Buat user admin panel untuk test, lengkap dengan role Spatie yang sesuai
 * (dibuat otomatis kalau belum ada). Sejak Spatie menjadi satu-satunya sumber
 * kebenaran untuk role.admin/role.admin.keuangan, users.role legacy saja tidak lagi cukup.
 */
function adminUser(string $legacyRole = 'admin'): User
{
    $spatieRoleName = match ($legacyRole) {
        'admin_akademik' => 'Akademik',
        'admin_keuangan' => 'Keuangan',
        default => 'Superadmin',
    };

    // Seed permission + pemetaan role→permission yang sebenarnya, supaya test menguji batas
    // modul yang benar-benar berlaku di produksi (middleware panel.permission + navbar).
    (new PermissionSeeder)->run();

    $role = Role::firstOrCreate(
        ['name' => $spatieRoleName, 'guard_name' => 'web'],
        ['code' => strtolower($spatieRoleName)]
    );

    $user = User::factory()->create(['role' => $legacyRole]);
    $user->syncRoles([$role]);

    return $user;
}

/**
 * Beri user scope prodi langsung (user_role_scopes, scope_type=prodi) untuk role Spatie-nya.
 */
function scopeAdminToProdi(User $user, int $prodiId): void
{
    $role = $user->roles()->first();

    DB::table('user_role_scopes')->insert([
        'id_user' => $user->id,
        'id_role' => $role->id,
        'id_scope' => $prodiId,
        'scope_type' => 'prodi',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Beri user scope fakultas langsung (user_role_scopes, scope_type=fakultas) untuk role Spatie-nya.
 */
function scopeAdminToFakultas(User $user, int $fakultasId): void
{
    $role = $user->roles()->first();

    DB::table('user_role_scopes')->insert([
        'id_user' => $user->id,
        'id_role' => $role->id,
        'id_scope' => $fakultasId,
        'scope_type' => 'fakultas',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Buat user dosen untuk test, lengkap dengan baris `dosen` yang ditautkan lewat id_user — area
 * dosen (dashboard, profil/akun) selalu mengambil data lewat Dosen::where('id_user', ...), jadi
 * user dosen "telanjang" tanpa baris ini akan 404 (ModelNotFoundException) di komponen tersebut.
 */
function dosenUser(array $userAttributes = [], array $dosenAttributes = []): User
{
    $user = User::factory()->create(array_merge(['role' => 'dosen'], $userAttributes));
    Dosen::factory()->create(array_merge(['id_user' => $user->id], $dosenAttributes));

    return $user;
}

/**
 * Buat user dosen lalu tetapkan sebagai Kepala Prodi ('kaprodi', default) atau Sekretaris Prodi
 * ('sekprodi') untuk $prodi (prodi.id_kaprodi / id_sekprodi mereferensi dosen.id) — dipakai
 * portal Administrasi Prodi (User::hasProdiScope(), middleware role.admin.prodi/.web).
 */
function kaprodiUser(Prodi $prodi, string $peran = 'kaprodi'): User
{
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $prodi->update($peran === 'sekprodi' ? ['id_sekprodi' => $dosen->id] : ['id_kaprodi' => $dosen->id]);

    return $dosenUser;
}
