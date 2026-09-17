<?php

use App\Models\Jenjang;
use App\Models\KonversiNilai;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\RentangNilai;

it('restores the soft-deleted nilai of the konversi instead of hitting the unique id_konversi_nilai constraint', function () {
    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'B', 'nilai_angka' => 3]);
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhs->id, 'is_approved' => true, 'nilai_baru' => 'B', 'sks_baru' => 2]);
    $lama = Nilai::factory()->create([
        'id_krs' => null,
        'id_konversi_nilai' => $konversi->id,
        'sks' => 4,
        'angka_mutu' => 1,
        'huruf_mutu' => 'D',
        'revisi' => 5,
    ]);
    $konversi->update(['id_nilai' => $lama->id]);
    $lama->delete();
    $lama->forceFill(['deleted_by' => 'penghapus'])->saveQuietly();
    $kaprodi = kaprodiUser($prodi);

    $this->actingAs($kaprodi)
        ->postJson("/api/prodi/konversi-nilai/{$konversi->id}/transfer-nilai")
        ->assertCreated()
        ->assertJsonPath('nilai.id', $lama->id)
        ->assertJsonPath('nilai.huruf_mutu', 'B')
        ->assertJsonPath('nilai.sks', 2);

    expect(Nilai::withTrashed()->where('id_konversi_nilai', $konversi->id)->count())->toBe(1);
    $nilai = Nilai::find($lama->id);
    expect($nilai)->not->toBeNull()
        ->and($nilai->deleted_by)->toBeNull()
        ->and($nilai->revisi)->toBe(0)
        ->and($nilai->is_final)->toBeTrue()
        ->and($konversi->fresh()->id_nilai)->toBe($lama->id);
});
