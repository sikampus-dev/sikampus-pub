<?php

use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Fakultas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\Role;
use App\Models\User;
use App\Models\Wisuda;
use App\Models\WisudaMahasiswa;
use App\Models\Yudisium;

it('admin dengan scope prodi hanya melihat mahasiswa layak wisuda dari prodinya sendiri', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id]);
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    Yudisium::factory()->create(['id_mahasiswa' => $mahasiswaA->id]);
    Yudisium::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);
    $wisuda = Wisuda::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $ids = collect(
        $this->actingAs($admin)->getJson("/api/wisuda/{$wisuda->id}/calon-peserta")->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($mahasiswaA->id);
    expect($ids)->not->toContain($mahasiswaB->id);
});

it('admin dengan scope prodi tidak bisa mendaftarkan mahasiswa prodi lain sebagai peserta wisuda', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    Yudisium::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);
    $wisuda = Wisuda::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)
        ->postJson("/api/wisuda/{$wisuda->id}/peserta", ['id_mahasiswa' => $mahasiswaB->id])
        ->assertForbidden();
});

it('admin dengan scope prodi tidak bisa menghapus peserta wisuda dari prodi lain', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $wisuda = Wisuda::factory()->create();
    $peserta = WisudaMahasiswa::factory()->create(['id_wisuda' => $wisuda->id, 'id_mahasiswa' => $mahasiswaB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)
        ->deleteJson("/api/wisuda/{$wisuda->id}/peserta/{$peserta->id}")
        ->assertForbidden();
});

it('admin dengan scope prodi hanya melihat dosen wali dari mahasiswa prodinya sendiri', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id]);
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $dosen = Dosen::factory()->create();
    $dosenWaliA = DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswaA->id]);
    DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswaB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $ids = collect(
        $this->actingAs($admin)->getJson('/api/dosen-wali')->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($dosenWaliA->id);
    expect($ids)->toHaveCount(1);
});

it('admin dengan scope prodi tidak bisa menugaskan dosen wali untuk mahasiswa prodi lain', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $dosen = Dosen::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)
        ->postJson('/api/dosen-wali', ['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswaB->id])
        ->assertForbidden();
});

it('menolak assignment scope fakultas dan prodi sekaligus untuk role yang sama', function () {
    $fakultas = Fakultas::factory()->create();
    $prodi = Prodi::factory()->create(['id_fakultas' => $fakultas->id]);
    $role = Role::firstOrCreate(['name' => 'Akademik', 'guard_name' => 'web'], ['code' => 'akademik']);

    $superadmin = adminUser();
    $target = User::factory()->create(['role' => 'admin']);

    $this->actingAs($superadmin)
        ->postJson("/api/users/{$target->id}/roles-scopes", [
            'roles' => [$role->id],
            'scopes' => ['fakultas' => [$fakultas->id], 'prodi' => [$prodi->id]],
        ])
        ->assertStatus(422);
});

it('mengizinkan assignment scope prodi saja tanpa fakultas', function () {
    $fakultas = Fakultas::factory()->create();
    $prodi = Prodi::factory()->create(['id_fakultas' => $fakultas->id]);
    $role = Role::firstOrCreate(['name' => 'Akademik', 'guard_name' => 'web'], ['code' => 'akademik']);

    $superadmin = adminUser();
    $target = User::factory()->create(['role' => 'admin']);

    $this->actingAs($superadmin)
        ->postJson("/api/users/{$target->id}/roles-scopes", [
            'roles' => [$role->id],
            'scopes' => ['prodi' => [$prodi->id]],
        ])
        ->assertCreated();

    expect($target->fresh()->getAllowedProdiIds())->toBe([$prodi->id]);
});

it('admin dengan scope prodi tidak memulihkan nilai terhapus milik prodi lain lewat update', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);
    $nilai = Nilai::factory()->create(['id_krs' => $krs->id]);
    $nilai->delete();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)
        ->putJson("/api/nilai/{$nilai->id}", ['huruf_mutu' => 'B'])
        ->assertForbidden();

    expect(Nilai::withTrashed()->find($nilai->id)->trashed())->toBeTrue();
});

it('admin dengan scope prodi tetap bisa memulihkan nilai terhapus milik prodinya lewat update', function () {
    $prodiA = Prodi::factory()->create();
    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswaA->id]);
    $nilai = Nilai::factory()->create(['id_krs' => $krs->id]);
    $nilai->delete();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)
        ->putJson("/api/nilai/{$nilai->id}", ['huruf_mutu' => 'B'])
        ->assertOk();

    $nilai = Nilai::withTrashed()->find($nilai->id);
    expect($nilai->trashed())->toBeFalse()
        ->and($nilai->huruf_mutu)->toBe('B');
});
