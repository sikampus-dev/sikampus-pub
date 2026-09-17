<?php

use App\Livewire\Admin\Nilai\Show;
use App\Models\KonversiNilai;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\NilaiRevisi;
use Livewire\Livewire;

function nilaiTerhapusUntukMahasiswa(): array
{
    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $nilai = Nilai::factory()->create(['id_krs' => $krs->id, 'huruf_mutu' => 'ZQ']);
    $revisi = NilaiRevisi::create(['id_krs' => $krs->id, 'huruf_mutu' => 'ZQ', 'angka_mutu' => 3]);

    $nilai->delete();

    return [$mahasiswa, $krs, $nilai, $revisi];
}

it('hides deleted nilai until the trashed toggle is switched on', function () {
    [$mahasiswa] = nilaiTerhapusUntukMahasiswa();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertDontSee('Pulihkan nilai')
        ->set('showTrashed', true)
        ->assertSee('Dihapus')
        ->assertSee('ZQ')
        ->assertSee('Pulihkan nilai');
});

it('restores a deleted nilai together with the revisi deleted alongside it', function () {
    [$mahasiswa, , $nilai, $revisi] = nilaiTerhapusUntukMahasiswa();

    expect(NilaiRevisi::find($revisi->id))->toBeNull();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->call('restore', $nilai->id)
        ->assertSee('Nilai berhasil dipulihkan.');

    $nilai = Nilai::find($nilai->id);
    expect($nilai)->not->toBeNull();
    expect($nilai->deleted_by)->toBeNull();
    expect(NilaiRevisi::find($revisi->id))->not->toBeNull();
});

it('permanently deletes a deleted nilai and the revisi deleted alongside it', function () {
    [$mahasiswa, $krs, $nilai, $revisi] = nilaiTerhapusUntukMahasiswa();

    // Revisi yang dihapus sendiri lebih dulu (deleted_at berbeda) bukan milik penghapusan nilai ini.
    $revisiLama = NilaiRevisi::create(['id_krs' => $krs->id, 'huruf_mutu' => 'ZR', 'angka_mutu' => 2]);
    $revisiLama->deleted_at = now()->subDay();
    $revisiLama->save();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $nilai->id)
        ->call('forceDeleteNilai')
        ->assertSet('confirmForceDeleteId', null)
        ->assertSee('Nilai berhasil dihapus permanen.');

    expect(Nilai::withTrashed()->find($nilai->id))->toBeNull();
    expect(NilaiRevisi::withTrashed()->find($revisi->id))->toBeNull();
    expect(NilaiRevisi::withTrashed()->find($revisiLama->id))->not->toBeNull();
});

it('refuses to permanently delete a nilai still referenced by konversi nilai', function () {
    [$mahasiswa, , $nilai] = nilaiTerhapusUntukMahasiswa();
    KonversiNilai::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_nilai' => $nilai->id]);

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmForceDelete', $nilai->id)
        ->call('forceDeleteNilai')
        ->assertSee('Tidak bisa menghapus permanen nilai ini: masih tercatat di data konversi nilai.');

    expect(Nilai::withTrashed()->find($nilai->id))->not->toBeNull();
});

it('does not touch a deleted nilai belonging to another mahasiswa', function () {
    [, , $nilai] = nilaiTerhapusUntukMahasiswa();
    $mahasiswaLain = Mahasiswa::factory()->create();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswaLain->id])
        ->call('restore', $nilai->id)
        ->assertNotFound();

    expect(Nilai::find($nilai->id))->toBeNull();
});

it('forbids restore and permanent delete without the delete nilai permission', function () {
    // Tanpa granular permissions, PanelAccess mengizinkan seluruh aksi untuk role Akademik.
    config(['access.granular_permissions' => true]);

    [$mahasiswa, , $nilai] = nilaiTerhapusUntukMahasiswa();
    $admin = adminUser('admin_akademik');

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertDontSee('Tampilkan nilai yang sudah dihapus')
        ->call('restore', $nilai->id)
        ->assertForbidden();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmForceDelete', $nilai->id)
        ->assertForbidden();

    expect(Nilai::withTrashed()->find($nilai->id)->trashed())->toBeTrue();
});
