<?php

use App\Livewire\Prodi\KonversiNilai\Index;
use App\Models\Jenjang;
use App\Models\KonversiNilai;
use App\Models\Kurikulum;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\RentangNilai;
use App\Models\Semester;
use Livewire\Livewire;

it('lists only konversi nilai within the kaprodi/sekprodi scope (scoped by mahasiswa prodi)', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mhsA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id, 'nama' => 'Mahasiswa Prodi A']);
    $mhsB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id, 'nama' => 'Mahasiswa Prodi B']);
    KonversiNilai::factory()->create(['id_mahasiswa' => $mhsA->id]);
    KonversiNilai::factory()->create(['id_mahasiswa' => $mhsB->id]);

    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Mahasiswa Prodi A')
        ->assertDontSee('Mahasiswa Prodi B');
});

it('filters by search, semester (tahun berlaku kurikulum), and angkatan', function () {
    $prodi = Prodi::factory()->create();
    $angkatanA = Semester::factory()->create();
    $angkatanB = Semester::factory()->create();
    $tahunA = Semester::factory()->create();
    $tahunB = Semester::factory()->create();
    $kurA = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'id_tahun_berlaku' => $tahunA->id]);
    $kurB = Kurikulum::factory()->create(['id_prodi' => $prodi->id, 'id_tahun_berlaku' => $tahunB->id]);

    $mhsA = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Mahasiswa Satu', 'id_semester_masuk' => $angkatanA->id]);
    $mhsB = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Mahasiswa Dua', 'id_semester_masuk' => $angkatanB->id]);

    KonversiNilai::factory()->create(['id_mahasiswa' => $mhsA->id, 'id_kurikulum' => $kurA->id, 'kode_mk_lama' => 'FISIKA-01']);
    KonversiNilai::factory()->create(['id_mahasiswa' => $mhsB->id, 'id_kurikulum' => $kurB->id, 'kode_mk_lama' => 'KIMIA-01']);
    $kaprodi = kaprodiUser($prodi);

    $component = Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->set('filterSemester', (string) $tahunA->id)
        ->assertSee('Mahasiswa Satu')
        ->assertDontSee('Mahasiswa Dua');

    $component
        ->set('filterSemester', '')
        ->set('filterAngkatan', (string) $angkatanB->id)
        ->assertSee('Mahasiswa Dua')
        ->assertDontSee('Mahasiswa Satu');

    $component
        ->set('filterAngkatan', '')
        ->set('search', 'KIMIA-01')
        ->assertSee('Mahasiswa Dua')
        ->assertDontSee('Mahasiswa Satu');
});

it('does not default to the active semester', function () {
    $prodi = Prodi::factory()->create();
    Semester::factory()->create(['is_active' => true]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSet('filterSemester', '');
});

it('toggles approval and records who made the change', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhs->id, 'is_approved' => false]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('toggleApproval', $konversi->id, true);

    $konversi->refresh();
    expect($konversi->is_approved)->toBeTrue();
    expect($konversi->updated_by)->toBe($kaprodi->name);
});

it('forbids toggling approval for a konversi outside the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mhsB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhsB->id, 'is_approved' => false]);
    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('toggleApproval', $konversi->id, true)
        ->assertStatus(403);

    expect($konversi->fresh()->is_approved)->toBeFalse();
});

it('opens the detail modal for a konversi within scope', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Rina Marlina', 'nim' => '2024050']);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhs->id, 'nama_mk_lama' => 'Fisika Dasar I']);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $konversi->id)
        ->assertSee('Rina Marlina')
        ->assertSee('2024050')
        ->assertSee('Fisika Dasar I');
});

it('returns a 403 when opening the detail modal for a konversi outside scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mhsB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhsB->id]);
    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $konversi->id)
        ->assertStatus(403);
});

it('transfers an approved konversi to nilai using the matching rentang nilai for the mahasiswa jenjang', function () {
    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'B', 'nilai_angka' => 3]);
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $konversi = KonversiNilai::factory()->create([
        'id_mahasiswa' => $mhs->id,
        'is_approved' => true,
        'nilai_baru' => 'b', // sengaja huruf kecil — pencocokan harus case-insensitive
        'sks_baru' => 3,
        'id_nilai' => null,
    ]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $konversi->id)
        ->call('transferToNilai')
        ->assertSet('transferError', '')
        ->assertSee('berhasil ditransfer');

    $konversi->refresh();
    expect($konversi->id_nilai)->not->toBeNull();
    $nilai = Nilai::find($konversi->id_nilai);
    expect($nilai->huruf_mutu)->toBe('B');
    expect((int) $nilai->angka_mutu)->toBe(3);
    expect($nilai->id_krs)->toBeNull();
    expect($nilai->id_konversi_nilai)->toBe($konversi->id);
});

it('rejects transfer when the konversi is not approved yet', function () {
    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'B']);
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhs->id, 'is_approved' => false, 'nilai_baru' => 'B']);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $konversi->id)
        ->call('transferToNilai')
        ->assertSee('harus disetujui');

    expect($konversi->fresh()->id_nilai)->toBeNull();
});

it('rejects transfer when the mahasiswa prodi has no jenjang', function () {
    $prodi = Prodi::factory()->create(['id_jenjang' => null]);
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhs->id, 'is_approved' => true, 'nilai_baru' => 'B']);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $konversi->id)
        ->call('transferToNilai')
        ->assertSee('belum memiliki jenjang');

    expect($konversi->fresh()->id_nilai)->toBeNull();
});

it('rejects transfer when no rentang nilai matches the huruf', function () {
    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhs->id, 'is_approved' => true, 'nilai_baru' => 'Z']);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $konversi->id)
        ->call('transferToNilai')
        ->assertSee('Tidak ada rentang nilai');

    expect($konversi->fresh()->id_nilai)->toBeNull();
});

it('rejects transfer when the konversi is already linked to an existing nilai', function () {
    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'B']);
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $konversi = KonversiNilai::factory()->create(['id_mahasiswa' => $mhs->id, 'is_approved' => true, 'nilai_baru' => 'B']);
    $nilai = Nilai::factory()->create(['id_krs' => null, 'id_konversi_nilai' => $konversi->id]);
    $konversi->update(['id_nilai' => $nilai->id]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $konversi->id)
        ->call('transferToNilai')
        ->assertSee('sudah terhubung ke data nilai');
});

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

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $konversi->id)
        ->call('transferToNilai')
        ->assertSet('transferError', '')
        ->assertSee('berhasil ditransfer');

    expect(Nilai::withTrashed()->where('id_konversi_nilai', $konversi->id)->count())->toBe(1);
    $nilai = Nilai::find($lama->id);
    expect($nilai)->not->toBeNull()
        ->and($nilai->deleted_by)->toBeNull()
        ->and($nilai->huruf_mutu)->toBe('B')
        ->and((int) $nilai->angka_mutu)->toBe(3)
        ->and($nilai->sks)->toBe(2)
        ->and($nilai->revisi)->toBe(0)
        ->and($nilai->is_final)->toBeTrue()
        ->and($konversi->fresh()->id_nilai)->toBe($lama->id);
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('prodi.konversi-nilai'))->assertRedirect(route('login'));
});
