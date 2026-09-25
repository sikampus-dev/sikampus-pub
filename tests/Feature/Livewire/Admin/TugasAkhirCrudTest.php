<?php

use App\Livewire\Admin\TugasAkhir\Index;
use App\Livewire\Admin\TugasAkhir\Show;
use App\Livewire\Admin\TugasAkhir\UjianSidangShow;
use App\Models\Dosen;
use App\Models\JenisMatkul;
use App\Models\Kelas;
use App\Models\KelompokKelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\RentangNilai;
use App\Models\Semester;
use App\Models\TugasAkhir;
use App\Models\TugasAkhirPembimbing;
use App\Models\TugasAkhirStatusLog;
use App\Models\UjianSidang;
use App\Models\UjianSidangPenguji;
use Livewire\Livewire;

function buatTugasAkhir(array $overrides = []): TugasAkhir
{
    $mahasiswa = $overrides['mahasiswa'] ?? Mahasiswa::factory()->create();
    $semester = $overrides['semester'] ?? Semester::factory()->active()->create();

    return TugasAkhir::create(array_merge([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'judul' => 'Sistem Informasi Akademik Berbasis Web',
        'status' => 'submitted',
        'is_proposal' => true,
    ], array_diff_key($overrides, array_flip(['mahasiswa', 'semester']))));
}

it('renders index and show pages', function () {
    $admin = adminUser();
    $ta = buatTugasAkhir();

    $this->actingAs($admin)->get(route('admin.akademik.tugas-akhir'))->assertOk()->assertSee($ta->mahasiswa->nama);
    $this->actingAs($admin)->get(route('admin.akademik.tugas-akhir.show', $ta->id))->assertOk()->assertSee($ta->judul);
});

it('filters index by search and status', function () {
    $admin = adminUser();
    $semester = Semester::factory()->active()->create();
    $match = buatTugasAkhir(['semester' => $semester, 'judul' => 'Deteksi Objek dengan YOLO', 'status' => 'approved']);
    buatTugasAkhir(['semester' => $semester, 'judul' => 'Aplikasi Kasir', 'status' => 'draft']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'YOLO')
        ->assertSee($match->judul)
        ->assertDontSee('Aplikasi Kasir');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', '')
        ->set('filterStatus', 'approved')
        ->assertSee($match->judul)
        ->assertDontSee('Aplikasi Kasir');
});

it('updates the pengajuan status and logs the decision', function () {
    $admin = adminUser();
    $ta = buatTugasAkhir(['status' => 'submitted']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $ta->id])
        ->set('keputusan', 'acc')
        ->set('keteranganStatus', 'Judul sudah sesuai.')
        ->call('saveStatus');

    expect($ta->fresh()->status)->toBe('approved');
    expect(TugasAkhirStatusLog::where('id_tugas_akhir', $ta->id)->where('status', 'acc')->exists())->toBeTrue();
});

it('creates, updates, and deletes a pembimbing', function () {
    $admin = adminUser();
    $ta = buatTugasAkhir();
    $dosen = Dosen::factory()->create();
    $dosenLain = Dosen::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $ta->id])
        ->set('pembimbingDosenId', $dosen->id)
        ->set('pembimbingTanggal', '2026-01-10')
        ->call('savePembimbing');

    $pembimbing = TugasAkhirPembimbing::where('id_tugas_akhir', $ta->id)->firstOrFail();
    expect($pembimbing->id_dosen)->toBe($dosen->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $ta->id])
        ->call('openPembimbingModal', $pembimbing->id)
        ->assertSet('pembimbingDosenId', $dosen->id)
        ->set('pembimbingDosenId', $dosenLain->id)
        ->call('savePembimbing');

    expect($pembimbing->fresh()->id_dosen)->toBe($dosenLain->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $ta->id])
        ->call('confirmDeletePembimbing', $pembimbing->id)
        ->call('deletePembimbing');

    expect(TugasAkhirPembimbing::find($pembimbing->id))->toBeNull();
});

it('creates an ujian sidang and prevents duplicate semester', function () {
    $admin = adminUser();
    $ta = buatTugasAkhir();
    $semesterSidang = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $ta->id])
        ->set('sidangSemesterId', (string) $semesterSidang->id)
        ->call('saveSidang');

    expect(UjianSidang::where('id_tugas_akhir', $ta->id)->count())->toBe(1);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $ta->id])
        ->set('sidangSemesterId', (string) $semesterSidang->id)
        ->call('saveSidang')
        ->assertHasErrors('sidangSemesterId');

    expect(UjianSidang::where('id_tugas_akhir', $ta->id)->count())->toBe(1);
});

it('manages jadwal and penguji on the ujian sidang detail page', function () {
    $admin = adminUser();
    $ta = buatTugasAkhir();
    $sidang = UjianSidang::create([
        'id_tugas_akhir' => $ta->id,
        'id_semester' => Semester::factory()->create()->id,
        'tanggal_daftar' => now(),
        'status' => 'draft',
    ]);
    $dosen = Dosen::factory()->create();

    Livewire::actingAs($admin)
        ->test(UjianSidangShow::class, ['id' => $ta->id, 'sidangId' => $sidang->id])
        ->set('tanggalMulai', '2026-08-01T09:00')
        ->set('tanggalSelesai', '2026-08-01T10:00')
        ->call('saveJadwal')
        ->set('pengujiDosenId', $dosen->id)
        ->set('pengujiIsKetua', true)
        ->call('addPenguji');

    $sidang->refresh();
    expect($sidang->tanggal_ujian_mulai)->not->toBeNull();
    $penguji = UjianSidangPenguji::where('id_ujian_sidang', $sidang->id)->firstOrFail();
    expect($penguji->is_ketua)->toBeTrue();

    Livewire::actingAs($admin)
        ->test(UjianSidangShow::class, ['id' => $ta->id, 'sidangId' => $sidang->id])
        ->call('openEditPenguji', $penguji->id)
        ->set('editPengujiNilai', '88')
        ->call('saveEditPenguji');

    expect((float) $penguji->fresh()->nilai)->toBe(88.0);

    Livewire::actingAs($admin)
        ->test(UjianSidangShow::class, ['id' => $ta->id, 'sidangId' => $sidang->id])
        ->call('confirmDeletePenguji', $penguji->id)
        ->call('deletePenguji');

    expect(UjianSidangPenguji::find($penguji->id))->toBeNull();
});

it('finalizes the ujian sidang score into the transkrip', function () {
    $admin = adminUser();

    $jenjang = Prodi::factory()->create()->jenjang;
    RentangNilai::create([
        'id_jenjang' => $jenjang->id,
        'nilai_huruf' => 'A',
        'nilai_angka' => 4,
        'nilai_rendah' => 80,
        'nilai_tinggi' => 100,
        'is_lulus' => true,
    ]);

    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $semesterTa = Semester::factory()->active()->create();

    $jenisTa = JenisMatkul::create(['kode' => 'TA', 'nama' => 'Tugas Akhir']);
    $matkul = Matkul::factory()->create(['id_jenis_matkul' => $jenisTa->id, 'id_prodi' => $prodi->id]);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 6]);
    $kelas = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_semester' => $semesterTa->id,
    ]);
    Krs::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_kelas' => $kelas->id,
        'approved_at' => now(),
    ]);

    $ta = buatTugasAkhir(['mahasiswa' => $mahasiswa, 'semester' => $semesterTa, 'status' => 'approved']);
    $sidang = UjianSidang::create([
        'id_tugas_akhir' => $ta->id,
        'id_semester' => Semester::factory()->create()->id,
        'tanggal_daftar' => now(),
        'status' => 'approved',
    ]);
    $penguji1 = UjianSidangPenguji::create([
        'id_ujian_sidang' => $sidang->id,
        'id_dosen' => Dosen::factory()->create()->id,
        'is_ketua' => true,
        'nilai' => 90,
        'status' => 'approved',
    ]);
    UjianSidangPenguji::create([
        'id_ujian_sidang' => $sidang->id,
        'id_dosen' => Dosen::factory()->create()->id,
        'is_ketua' => false,
        'nilai' => 86,
        'status' => 'approved',
    ]);

    $component = Livewire::actingAs($admin)
        ->test(UjianSidangShow::class, ['id' => $ta->id, 'sidangId' => $sidang->id]);

    expect($component->get('previewFinalisasi')['ok'])->toBeTrue();

    $component->call('openFinalisasiConfirm')->call('finalisasiNilai');

    $krs = Krs::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    $nilai = Nilai::where('id_krs', $krs->id)->firstOrFail();
    expect($nilai->huruf_mutu)->toBe('A');
    expect((bool) $nilai->is_final)->toBeTrue();
});

it('enforces prodi scope on the tugas akhir detail page', function () {
    $admin = adminUser('admin_akademik');
    $allowedProdi = Prodi::factory()->create();
    scopeAdminToProdi($admin, $allowedProdi->id);

    $luarScope = Mahasiswa::factory()->create(['id_prodi' => Prodi::factory()->create()->id]);
    $ta = buatTugasAkhir(['mahasiswa' => $luarScope]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $ta->id])
        ->assertStatus(403);
});

it('redirects unauthenticated users to the login page', function () {
    $ta = buatTugasAkhir();

    $this->get(route('admin.akademik.tugas-akhir'))->assertRedirect(route('login'));
    $this->get(route('admin.akademik.tugas-akhir.show', $ta->id))->assertRedirect(route('login'));
});

it('shows the mahasiswa kelompok kelas on the show page, read from kelompok_kelas', function () {
    // Regresi: halaman ini dulu membaca relasi grup_mahasiswa. Tabel itu sudah tidak dipakai
    // (kosong, tak ada mahasiswa yang punya id_grup_mahasiswa), jadi kolomnya selalu tampil "—".
    $admin = adminUser();
    $kelompok = KelompokKelas::factory()->create(['nama' => 'Kelas Reguler Sore B']);
    $ta = buatTugasAkhir(['mahasiswa' => Mahasiswa::factory()->create(['id_kelompok_kelas' => $kelompok->id])]);

    $this->actingAs($admin)->get(route('admin.akademik.tugas-akhir.show', $ta->id))
        ->assertOk()
        ->assertSee('Kelompok Kelas')
        ->assertSee('Kelas Reguler Sore B');
});
