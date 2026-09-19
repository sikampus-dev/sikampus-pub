<?php

use App\Livewire\Admin\Krs\Show;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Nilai;
use App\Models\NilaiRevisi;
use App\Models\Prodi;
use Livewire\Livewire;

function krsTerhapusUntukMahasiswa(): array
{
    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    $krs->delete();

    return [$mahasiswa, $kelas, $krs];
}

it('hides deleted krs until the trashed toggle is switched on', function () {
    [$mahasiswa, $kelas] = krsTerhapusUntukMahasiswa();
    $matkul = Matkul::factory()->create(['nama' => 'Anatomi Manusia']);
    $kelas->kurikulumMatkul()->update(['id_matkul' => $matkul->id]);

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertDontSee('Anatomi Manusia')
        ->set('showTrashed', true)
        ->assertSee('Anatomi Manusia')
        ->assertSee('Dihapus')
        ->assertSee('Pulihkan KRS');
});

it('keeps deleted krs out of the sks summary while they are shown', function () {
    [$mahasiswa, $kelas] = krsTerhapusUntukMahasiswa();
    $matkul = Matkul::factory()->create(['sks' => 3]);
    $kelas->kurikulumMatkul()->update(['id_matkul' => $matkul->id]);

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->assertSet('showTrashed', true)
        ->assertSeeInOrder(['Total KRS', '0']);
});

it('restores a deleted krs together with the nilai deleted alongside it', function () {
    [$mahasiswa, , $krs] = krsTerhapusUntukMahasiswa();

    $krsDenganNilai = Krs::withTrashed()->find($krs->id);
    expect($krsDenganNilai->trashed())->toBeTrue();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->call('restore', $krs->id)
        ->assertSee('KRS berhasil dipulihkan.');

    expect(Krs::find($krs->id))->not->toBeNull();
});

it('refuses to restore a krs whose kelas has been deleted', function () {
    [$mahasiswa, $kelas, $krs] = krsTerhapusUntukMahasiswa();
    $kelas->delete();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->call('restore', $krs->id)
        ->assertSee('kelasnya sudah dihapus');

    expect(Krs::find($krs->id))->toBeNull();
});

it('refuses to restore a krs when the mahasiswa was re-enrolled in a parallel kelas', function () {
    [$mahasiswa, $kelas, $krs] = krsTerhapusUntukMahasiswa();

    // Kelas paralel: mata kuliah dan semester sama, kelasnya berbeda.
    $kelasParalel = Kelas::factory()->create([
        'id_prodi' => $kelas->id_prodi,
        'id_kurikulum_matkul' => $kelas->id_kurikulum_matkul,
        'id_semester' => $kelas->id_semester,
    ]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasParalel->id]);

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->call('restore', $krs->id)
        ->assertSee('sudah terdaftar');

    expect(Krs::find($krs->id))->toBeNull();
});

it('permanently deletes a deleted krs along with its leftover nilai rows', function () {
    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    $nilai = Nilai::factory()->create(['id_krs' => $krs->id, 'is_final' => false]);
    $revisi = NilaiRevisi::create(['id_krs' => $krs->id, 'huruf_mutu' => 'ZQ', 'angka_mutu' => 3]);

    $krs->delete();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $krs->id)
        ->call('forceDeleteKrs')
        ->assertSet('confirmForceDeleteId', null)
        ->assertSee('KRS berhasil dihapus permanen.');

    expect(Krs::withTrashed()->find($krs->id))->toBeNull();
    expect(Nilai::withTrashed()->find($nilai->id))->toBeNull();
    expect(NilaiRevisi::withTrashed()->find($revisi->id))->toBeNull();
});

it('bulk deletes live krs and permanently deletes the ones already deleted', function () {
    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);

    $krsHidup = Krs::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_kelas' => Kelas::factory()->create(['id_prodi' => $prodi->id])->id,
    ]);
    $krsTerhapus = Krs::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_kelas' => Kelas::factory()->create(['id_prodi' => $prodi->id])->id,
    ]);
    $krsTerhapus->delete();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->set('selected', [(string) $krsHidup->id, (string) $krsTerhapus->id])
        ->call('confirmBulkDelete')
        ->assertSet('confirmingBulkDelete', true)
        ->call('bulkDelete')
        ->assertSet('selected', [])
        ->assertSet('confirmingBulkDelete', false)
        ->assertSee('1 KRS dihapus dan 1 KRS dihapus permanen.');

    expect(Krs::find($krsHidup->id))->toBeNull();
    expect(Krs::withTrashed()->find($krsHidup->id)->trashed())->toBeTrue();
    expect(Krs::withTrashed()->find($krsTerhapus->id))->toBeNull();
});

it('skips a krs with a final nilai in a bulk delete instead of failing the whole batch', function () {
    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);

    $matkul = Matkul::factory()->create(['kode' => 'ZZ-900']);
    $kelasFinal = Kelas::factory()->create(['id_prodi' => $prodi->id]);
    $kelasFinal->kurikulumMatkul()->update(['id_matkul' => $matkul->id]);
    $krsFinal = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasFinal->id]);
    Nilai::factory()->create(['id_krs' => $krsFinal->id, 'is_final' => true]);

    $krsBiasa = Krs::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_kelas' => Kelas::factory()->create(['id_prodi' => $prodi->id])->id,
    ]);

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $krsFinal->id, (string) $krsBiasa->id])
        ->call('bulkDelete')
        ->assertSee('1 KRS dihapus.')
        ->assertSee('ZZ-900 (punya nilai final)');

    expect(Krs::find($krsFinal->id))->not->toBeNull();
    expect(Krs::find($krsBiasa->id))->toBeNull();
});

it('ignores a bulk-delete selection pointing at another mahasiswa krs', function () {
    [, , $krsMahasiswaLain] = krsTerhapusUntukMahasiswa();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $krsMahasiswaLain->id])
        ->call('bulkDelete');

    expect(Krs::withTrashed()->find($krsMahasiswaLain->id))->not->toBeNull();
});

it('clears the selection when the semester filter changes', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $krs->id])
        ->set('filterSemester', '1')
        ->assertSet('selected', []);
});

it('does not touch a deleted krs belonging to another mahasiswa', function () {
    [, , $krs] = krsTerhapusUntukMahasiswa();
    $mahasiswaLain = Mahasiswa::factory()->create();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswaLain->id])
        ->call('restore', $krs->id)
        ->assertNotFound();

    expect(Krs::find($krs->id))->toBeNull();
});

it('binds the bulk-delete checkboxes without .live so ticking costs no request', function () {
    [$mahasiswa] = krsTerhapusUntukMahasiswa();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->assertSee('wire:model="selected"', escape: false)
        ->assertDontSee('wire:model.live="selected"', escape: false);
});

it('shows a loading state on the delete button inside the confirmation modals', function () {
    [$mahasiswa, , $krs] = krsTerhapusUntukMahasiswa();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->set('selected', [(string) $krs->id])
        ->call('confirmBulkDelete')
        ->assertSee('wire:loading wire:target="bulkDelete"', escape: false)
        ->assertSee('Menghapus...')
        ->call('confirmForceDelete', $krs->id)
        ->assertSee('wire:loading wire:target="forceDeleteKrs"', escape: false);
});
