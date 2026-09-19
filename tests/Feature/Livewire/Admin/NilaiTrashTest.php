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

it('bulk deletes live nilai and permanently deletes the ones already deleted', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $krsHidup = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $nilaiHidup = Nilai::factory()->create(['id_krs' => $krsHidup->id]);

    $krsTerhapus = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $nilaiTerhapus = Nilai::factory()->create(['id_krs' => $krsTerhapus->id]);
    $nilaiTerhapus->delete();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->set('selected', [(string) $nilaiHidup->id, (string) $nilaiTerhapus->id])
        ->call('confirmBulkDelete')
        ->assertSet('confirmingBulkDelete', true)
        ->call('bulkDelete')
        ->assertSet('selected', [])
        ->assertSet('confirmingBulkDelete', false)
        ->assertSee('1 nilai dihapus dan 1 nilai dihapus permanen.');

    expect(Nilai::find($nilaiHidup->id))->toBeNull();
    expect(Nilai::withTrashed()->find($nilaiHidup->id)->trashed())->toBeTrue();
    expect(Nilai::withTrashed()->find($nilaiTerhapus->id))->toBeNull();
});

it('skips a blocked row in a bulk delete instead of failing the whole batch', function () {
    [$mahasiswa, , $nilaiDiblokir] = nilaiTerhapusUntukMahasiswa();
    KonversiNilai::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_nilai' => $nilaiDiblokir->id]);

    $krsLain = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $nilaiLain = Nilai::factory()->create(['id_krs' => $krsLain->id]);
    $nilaiLain->delete();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->set('selected', [(string) $nilaiDiblokir->id, (string) $nilaiLain->id])
        ->call('bulkDelete')
        ->assertSee('1 nilai dihapus permanen.')
        ->assertSee('1 nilai tidak bisa dihapus permanen karena masih tercatat di data konversi nilai');

    expect(Nilai::withTrashed()->find($nilaiDiblokir->id))->not->toBeNull();
    expect(Nilai::withTrashed()->find($nilaiLain->id))->toBeNull();
});

it('ignores a bulk-delete selection pointing at another mahasiswa nilai', function () {
    [, , $nilaiMahasiswaLain] = nilaiTerhapusUntukMahasiswa();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $nilaiMahasiswaLain->id])
        ->call('bulkDelete');

    expect(Nilai::withTrashed()->find($nilaiMahasiswaLain->id))->not->toBeNull();
});

it('clears the selection when the filter changes', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $nilai = Nilai::factory()->create(['id_krs' => $krs->id]);

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $nilai->id])
        ->set('search', 'apa pun')
        ->assertSet('selected', []);
});

it('forbids bulk delete without the delete nilai permission', function () {
    config(['access.granular_permissions' => true]);

    [$mahasiswa, , $nilai] = nilaiTerhapusUntukMahasiswa();

    Livewire::actingAs(adminUser('admin_akademik'))
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $nilai->id])
        ->call('bulkDelete')
        ->assertForbidden();

    expect(Nilai::withTrashed()->find($nilai->id))->not->toBeNull();
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

it('binds the bulk-delete checkboxes without .live so ticking costs no request', function () {
    [$mahasiswa] = nilaiTerhapusUntukMahasiswa();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->assertSee('wire:model="selected"', escape: false)
        ->assertDontSee('wire:model.live="selected"', escape: false);
});

it('shows a loading state on the delete button inside the confirmation modals', function () {
    [$mahasiswa, , $nilai] = nilaiTerhapusUntukMahasiswa();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->set('selected', [(string) $nilai->id])
        ->call('confirmBulkDelete')
        ->assertSee('wire:loading wire:target="bulkDelete"', escape: false)
        ->assertSee('Menghapus...')
        ->call('confirmForceDelete', $nilai->id)
        ->assertSee('wire:loading wire:target="forceDeleteNilai"', escape: false);
});

it('gives each row button its own loading target so only that row spins', function () {
    [$mahasiswa, , $nilai] = nilaiTerhapusUntukMahasiswa();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('showTrashed', true)
        ->assertSee('wire:target="restore('.$nilai->id.')"', escape: false)
        ->assertSee('wire:target="confirmForceDelete('.$nilai->id.')"', escape: false)
        ->assertDontSee('wire:target="restore"', escape: false);
});

it('targets the per-row delete button at that row only', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $nilai = Nilai::factory()->create(['id_krs' => $krs->id]);

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSee('wire:target="confirmDelete('.$nilai->id.')"', escape: false);
});
