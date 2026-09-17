<?php

use App\Livewire\Admin\Kelas\Form;
use App\Livewire\Admin\Kelas\Index;
use App\Livewire\Admin\Kelas\Show;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Jenjang;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\KelompokKelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\MateriPerkuliahan;
use App\Models\Matkul;
use App\Models\Perkuliahan;
use App\Models\Prodi;
use App\Models\Rps;
use App\Models\RpsCpl;
use App\Models\RpsCpmk;
use App\Models\RpsPembelajaran;
use App\Models\RpsSubcpmk;
use App\Models\Ruangan;
use App\Models\Semester;
use App\Models\Tugas;
use App\Models\TugasMahasiswa;
use App\Models\Ujian;
use Livewire\Livewire;

it('renders index, create form, and show page', function () {
    $admin = adminUser();
    $matkul = Matkul::factory()->create(['nama' => 'Pemrograman Web', 'kode' => 'IF101']);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id]);

    $this->actingAs($admin)->get(route('admin.akademik.kelas'))->assertOk()->assertSee('Pemrograman Web');
    $this->actingAs($admin)->get(route('admin.akademik.kelas.create'))->assertOk()->assertSee('Tambah Kelas');
    $this->actingAs($admin)->get(route('admin.akademik.kelas.show', $kelas->id))->assertOk()->assertSee('Pemrograman Web');
});

it('creates, updates, and deletes a kelas', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $semester1 = Semester::factory()->create(['kode' => '20231']);
    $semester2 = Semester::factory()->create(['kode' => '20241']);
    $dosen = Dosen::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodi->id)
        ->set('id_kurikulum_matkul', $kurikulumMatkul->id)
        ->set('id_semester', $semester2->id)
        ->set('id_angkatan', $semester1->id)
        ->set('id_dosen_pic', $dosen->id)
        ->set('kode', 'A')
        ->call('save')
        ->assertRedirect(route('admin.akademik.kelas', ['id_prodi' => $prodi->id, 'id_semester' => $semester2->id]));

    $kelas = Kelas::where('kode', 'A')->firstOrFail();
    expect($kelas->id_dosen_pic)->toBe($dosen->id);
    expect(KelasDosen::where('id_kelas', $kelas->id)->where('id_dosen', $dosen->id)->where('is_pic', true)->exists())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $kelas->id])
        ->assertSet('kode', 'A')
        ->set('kuota', '40')
        ->call('save');

    expect($kelas->fresh()->kuota)->toBe(40);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kelas->id)
        ->call('delete');

    expect(Kelas::find($kelas->id))->toBeNull();
});

it('toggles select-all for jadwal rows on the show page, flipping based on current state', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwalIds = Jadwal::factory()->count(3)->create(['id_kelas' => $kelas->id])->pluck('id')->all();

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelas->id])
        ->assertSet('selectedJadwalIds', [])
        ->call('toggleAllJadwal');

    expect($component->get('selectedJadwalIds'))->toEqualCanonicalizing($jadwalIds);

    // Sudah semua tercentang — toggle lagi berarti mengosongkan, bukan menambah lagi.
    $component->call('toggleAllJadwal')->assertSet('selectedJadwalIds', []);
});

it('bulk deletes only the checked jadwal rows, leaving the rest untouched', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal1 = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jadwal2 = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jadwal3 = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelas->id])
        ->set('selectedJadwalIds', [$jadwal1->id, $jadwal2->id])
        ->call('confirmBulkDelete')
        ->assertSet('confirmingBulkDelete', true)
        ->call('bulkDeleteJadwal')
        ->assertSet('confirmingBulkDelete', false)
        ->assertSet('selectedJadwalIds', []);

    expect(Jadwal::find($jadwal1->id))->toBeNull();
    expect(Jadwal::find($jadwal2->id))->toBeNull();
    expect(Jadwal::find($jadwal3->id))->not->toBeNull();
});

// selectedJadwalIds properti publik Livewire — bisa dimanipulasi lewat request langsung, jadi
// harus tetap disaring ke id_kelas milik halaman ini, bukan dipercaya begitu saja.
it('does not delete a jadwal belonging to a different kelas even if its id is smuggled into selectedJadwalIds', function () {
    $admin = adminUser();
    $kelasA = Kelas::factory()->create();
    $kelasB = Kelas::factory()->create();
    $jadwalA = Jadwal::factory()->create(['id_kelas' => $kelasA->id]);
    $jadwalB = Jadwal::factory()->create(['id_kelas' => $kelasB->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelasA->id])
        ->set('selectedJadwalIds', [$jadwalA->id, $jadwalB->id])
        ->call('bulkDeleteJadwal');

    expect(Jadwal::find($jadwalA->id))->toBeNull();
    expect(Jadwal::find($jadwalB->id))->not->toBeNull();
});

it('does not open the bulk delete confirmation when nothing is checked', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelas->id])
        ->call('confirmBulkDelete')
        ->assertSet('confirmingBulkDelete', false);
});

it('does not create any jadwal when buatJadwalOtomatis is left off', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodi->id)
        ->set('id_kurikulum_matkul', $kurikulumMatkul->id)
        ->set('id_semester', $semester->id)
        ->set('id_angkatan', $semester->id)
        ->call('save')
        ->assertRedirect(route('admin.akademik.kelas', ['id_prodi' => $prodi->id, 'id_semester' => $semester->id]));

    $kelas = Kelas::where('id_kurikulum_matkul', $kurikulumMatkul->id)->firstOrFail();
    expect(Jadwal::where('id_kelas', $kelas->id)->count())->toBe(0);
});

it('creates N jadwal slots with the kelas team as dosen when buatJadwalOtomatis is on at create time', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $semester = Semester::factory()->create();
    $ruangan = Ruangan::factory()->create();
    $pic = Dosen::factory()->create();
    $tim = Dosen::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodi->id)
        ->set('id_kurikulum_matkul', $kurikulumMatkul->id)
        ->set('id_semester', $semester->id)
        ->set('id_angkatan', $semester->id)
        ->set('id_dosen_pic', $pic->id)
        ->call('addDosenTim', $tim->id)
        ->set('jml_pertemuan', '4')
        ->set('buatJadwalOtomatis', true)
        ->set('jadwalHari', 'senin')
        ->set('jadwalJamMulai', '08:00')
        ->set('jadwalJamSelesai', '10:00')
        ->set('jadwalIdRuangan', $ruangan->id)
        ->call('save')
        ->assertRedirect(route('admin.akademik.kelas', ['id_prodi' => $prodi->id, 'id_semester' => $semester->id]));

    $kelas = Kelas::where('id_kurikulum_matkul', $kurikulumMatkul->id)->firstOrFail();
    $jadwalRows = Jadwal::where('id_kelas', $kelas->id)->orderBy('urutan_pertemuan')->get();

    expect($jadwalRows)->toHaveCount(4);
    expect($jadwalRows->pluck('urutan_pertemuan')->all())->toBe([1, 2, 3, 4]);
    foreach ($jadwalRows as $jadwal) {
        expect($jadwal->hari)->toBe('senin');
        expect(substr((string) $jadwal->jam_mulai, 0, 5))->toBe('08:00');
        expect($jadwal->id_ruangan)->toBe($ruangan->id);
        // Dosen jadwal ikut tim dosen kelas (PIC + tim) — bukan dipilih terpisah.
        expect($jadwal->dosen->pluck('id_dosen')->sort()->values()->all())->toBe(collect([$pic->id, $tim->id])->sort()->values()->all());
    }
});

it('rejects saving the kelas when buatJadwalOtomatis fields are invalid, without partially saving anything', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodi->id)
        ->set('id_kurikulum_matkul', $kurikulumMatkul->id)
        ->set('id_semester', $semester->id)
        ->set('id_angkatan', $semester->id)
        ->set('buatJadwalOtomatis', true)
        ->set('jadwalJamMulai', '10:00')
        ->set('jadwalJamSelesai', '08:00')
        ->call('save')
        ->assertHasErrors(['jadwalJamSelesai']);

    expect(Kelas::where('id_kurikulum_matkul', $kurikulumMatkul->id)->exists())->toBeFalse();
});

it('lets an existing kelas generate jadwal on edit, but rejects it when a slot is already taken', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create(['jml_pertemuan' => 2]);
    $ruangan = Ruangan::factory()->create();

    // Slot pertemuan ke-1 untuk kombinasi kelas+ruangan ini sudah ada.
    Jadwal::factory()->create(['id_kelas' => $kelas->id, 'urutan_pertemuan' => 1, 'id_ruangan' => $ruangan->id]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $kelas->id])
        ->set('buatJadwalOtomatis', true)
        ->set('jadwalIdRuangan', $ruangan->id)
        ->call('save')
        ->assertHasErrors(['jadwalIdRuangan']);

    // Kelas tanpa ruangan yang sama tidak bentrok — berhasil membuat slot 1 & 2.
    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $kelas->id])
        ->set('buatJadwalOtomatis', true)
        ->call('save')
        ->assertRedirect(route('admin.akademik.kelas', ['id_prodi' => $kelas->id_prodi, 'id_semester' => $kelas->id_semester]));

    expect(Jadwal::where('id_kelas', $kelas->id)->whereNull('id_ruangan')->count())->toBe(2);
});

it('shows the jumlah pertemuan column counting actual jadwal rows, not the jml_pertemuan target field', function () {
    $admin = adminUser();
    // jml_pertemuan (target rencana) sengaja dibuat BEDA dari jumlah Jadwal sungguhan — kolom
    // index harus mengikuti yang sungguhan (2), bukan angka rencana ini (16).
    $kelas = Kelas::factory()->create(['jml_pertemuan' => 16]);
    Jadwal::factory()->count(2)->create(['id_kelas' => $kelas->id]);

    // Jadwal soft-deleted tidak boleh ikut terhitung.
    $trashedJadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $trashedJadwal->delete();

    $kelasList = Livewire::actingAs($admin)
        ->test(Index::class)
        ->viewData('kelasList');

    expect($kelasList->firstWhere('id', $kelas->id)->jadwal_count)->toBe(2);
});

it('rejects a duplicate kombinasi kurikulum matkul, semester, dan angkatan', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $semester = Semester::factory()->create();

    Kelas::factory()->create([
        'id_prodi' => $prodi->id,
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_semester' => $semester->id,
        'id_angkatan' => $semester->id,
        'id_kelompok_kelas' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodi->id)
        ->set('id_kurikulum_matkul', $kurikulumMatkul->id)
        ->set('id_semester', $semester->id)
        ->set('id_angkatan', $semester->id)
        ->call('save')
        ->assertHasErrors(['id_kurikulum_matkul']);
});

it('hides soft-deleted kelas by default and shows them with a restore/hapus-permanen action when toggled on', function () {
    $admin = adminUser();
    $matkul = Matkul::factory()->create(['nama' => 'Kelas Terhapus']);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id]);
    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertDontSee('Kelas Terhapus');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->assertSee('Kelas Terhapus')
        ->assertSee('Dihapus');
});

it('restores a soft-deleted kelas instead of trying to recreate it', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $kelas->delete();
    expect(Kelas::find($kelas->id))->toBeNull();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('restore', $kelas->id);

    expect(Kelas::find($kelas->id))->not->toBeNull();
    expect(Kelas::find($kelas->id)->deleted_at)->toBeNull();
});

// kelas_unique (id_kelompok_kelas + id_kurikulum_matkul + id_semester + id_angkatan) hanya punya
// celah nyata saat id_kelompok_kelas NULL (MySQL tidak menegakkan uniqueness antar NULL di
// composite index) — sama seperti kasus id_prodi NULL di modul Matkul. Untuk kombinasi dengan
// id_kelompok_kelas terisi, dua baris aktif dengan kombinasi sama tidak akan pernah bisa dibuat
// sejak awal (lihat test "rejects a duplicate kombinasi..." di atas).
it('refuses to restore a kelas whose kombinasi is already taken by an active kelas', function () {
    $admin = adminUser();
    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $semester = Semester::factory()->create();
    $angkatan = Semester::factory()->create();

    $trashed = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_semester' => $semester->id,
        'id_angkatan' => $angkatan->id,
        'id_kelompok_kelas' => null,
    ]);
    $trashed->delete();

    Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_semester' => $semester->id,
        'id_angkatan' => $angkatan->id,
        'id_kelompok_kelas' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('restore', $trashed->id);

    expect(Kelas::withTrashed()->find($trashed->id)->trashed())->toBeTrue();
});

it('permanently deletes a soft-deleted kelas that has no related records', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id))->toBeNull();
});

// krs dan kelas_dosen constrained('kelas')->restrictOnDelete() — restrict itu tetap berlaku walau
// baris perujuknya sendiri sudah soft-deleted, jadi harus ditolak lebih dulu dengan pesan jelas.
it('refuses to permanently delete a kelas still referenced by krs or dosen pengampu', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $krs = Krs::factory()->create(['id_kelas' => $kelas->id]);
    $krs->delete();

    KelasDosen::create(['id_kelas' => $kelas->id, 'id_dosen' => Dosen::factory()->create()->id, 'is_pic' => true]);

    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id)->trashed())->toBeTrue();
});

it('refuses to permanently delete a kelas that still has an active (not soft-deleted) jadwal', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    // Kelas::$hapusBerantai men-cascade soft-delete ke jadwal begitu $kelas->delete() dipanggil,
    // jadi untuk benar-benar mensimulasikan "kelas sudah soft-deleted tapi jadwalnya masih aktif"
    // (mis. kelas dihapus lewat query builder yang melewati event model dan cascade-nya) dipakai
    // Kelas::where(...)->delete() di sini, bukan $kelas->delete().
    Kelas::where('id', $kelas->id)->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id)->trashed())->toBeTrue();
});

// Use case inti: jadwal yang sudah di-soft-delete (dan bersih dari turunan aktif) ikut dihapus
// permanen otomatis begitu kelasnya dihapus permanen — bukan lagi diblokir mentah-mentah seperti
// tabel lain di FORCE_DELETE_BLOCKERS.
it('force-deletes an already soft-deleted jadwal (and its own already-trashed descendants) together with the kelas', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jadwal->delete();

    // Turunan jadwal ini juga sudah di-soft-delete semua — jadi bersih untuk ikut dihapus permanen.
    $dosen = Dosen::factory()->create();
    $jadwalDosen = JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    $jadwalDosen->delete();

    $materi = MateriPerkuliahan::create(['id_jadwal' => $jadwal->id, 'nama' => 'Slide', 'file' => 'materi/slide.pdf']);
    $materi->delete();

    $perkuliahan = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id]);
    $kehadiran = Kehadiran::factory()->create(['id_perkuliahan' => $perkuliahan->id]);
    $kehadiran->delete();
    $perkuliahan->delete();

    $tugas = Tugas::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'nama' => 'Tugas 1']);
    $tugasMahasiswa = TugasMahasiswa::create(['id_tugas' => $tugas->id, 'id_mahasiswa' => Mahasiswa::factory()->create()->id]);
    $tugasMahasiswa->delete();
    $tugas->delete();

    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id))->toBeNull();
    expect(Jadwal::withTrashed()->find($jadwal->id))->toBeNull();
    expect(JadwalDosen::withTrashed()->find($jadwalDosen->id))->toBeNull();
    expect(MateriPerkuliahan::withTrashed()->find($materi->id))->toBeNull();
    expect(Perkuliahan::withTrashed()->find($perkuliahan->id))->toBeNull();
    expect(Kehadiran::withTrashed()->find($kehadiran->id))->toBeNull();
    expect(Tugas::withTrashed()->find($tugas->id))->toBeNull();
    expect(TugasMahasiswa::withTrashed()->find($tugasMahasiswa->id))->toBeNull();
});

it('refuses to permanently delete the kelas when a soft-deleted jadwal has a trashed tugas with an active pengumpulan tugas mahasiswa underneath', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jadwal->delete();

    $tugas = Tugas::create(['id_jadwal' => $jadwal->id, 'id_dosen' => Dosen::factory()->create()->id, 'nama' => 'Tugas 1']);
    TugasMahasiswa::create(['id_tugas' => $tugas->id, 'id_mahasiswa' => Mahasiswa::factory()->create()->id]);

    // Tugas::$hapusDiblokirOleh menolak $tugas->delete() selama tugasMahasiswa masih aktif — dipakai
    // query builder di sini (sama seperti test jadwal/rps aktif di atas) supaya benar-benar bisa
    // mensimulasikan "tugas sudah soft-deleted tapi pengumpulannya masih aktif".
    Tugas::where('id', $tugas->id)->delete();

    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id)->trashed())->toBeTrue();
});

it('force-deletes a soft-deleted kelas_dosen and ujian together with the kelas (both are leaves)', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $kelasDosen = KelasDosen::create(['id_kelas' => $kelas->id, 'id_dosen' => Dosen::factory()->create()->id, 'is_pic' => true]);
    $kelasDosen->delete();

    $ujian = Ujian::factory()->create(['id_kelas' => $kelas->id]);
    $ujian->delete();

    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id))->toBeNull();
    expect(KelasDosen::withTrashed()->find($kelasDosen->id))->toBeNull();
    expect(Ujian::withTrashed()->find($ujian->id))->toBeNull();
});

it('force-deletes a soft-deleted rps and its entire tree (cpl, cpmk, subcpmk, pembelajaran) together with the kelas', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $rps = Rps::create(['id_kelas' => $kelas->id]);
    $cpl = RpsCpl::create(['id_rps' => $rps->id, 'cpl' => 'CPL 1']);
    $cpmk = RpsCpmk::create(['id_rps' => $rps->id, 'cpmk' => 'CPMK 1']);
    $subcpmk = RpsSubcpmk::create(['id_cpmk' => $cpmk->id, 'subcpmk' => 'Subcpmk 1']);
    $pembelajaran = RpsPembelajaran::create(['id_rps' => $rps->id, 'urutan_pertemuan' => 1]);

    // Sesuai AturanHapusBerantai::hapusAnakBerantai() sungguhan — $rps->delete() akan men-cascade
    // seluruh pohon ini otomatis. Dipanggil manual di sini biar tesnya tidak bergantung urutan
    // cascade nyata trait itu, cukup pastikan hasil akhirnya semua sudah soft-deleted.
    $subcpmk->delete();
    $cpmk->delete();
    $cpl->delete();
    $pembelajaran->delete();
    $rps->delete();

    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id))->toBeNull();
    expect(Rps::withTrashed()->find($rps->id))->toBeNull();
    expect(RpsCpl::withTrashed()->find($cpl->id))->toBeNull();
    expect(RpsCpmk::withTrashed()->find($cpmk->id))->toBeNull();
    expect(RpsSubcpmk::withTrashed()->find($subcpmk->id))->toBeNull();
    expect(RpsPembelajaran::withTrashed()->find($pembelajaran->id))->toBeNull();
});

it('refuses to permanently delete a kelas that still has an active (not soft-deleted) rps', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Rps::create(['id_kelas' => $kelas->id]);

    // Sama seperti test jadwal aktif di atas — Kelas::where(...)->delete() melewati cascade
    // AturanHapusBerantai supaya rps-nya benar-benar tetap aktif walau kelasnya sudah soft-deleted.
    Kelas::where('id', $kelas->id)->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id)->trashed())->toBeTrue();
});

it('refuses to permanently delete the kelas when a soft-deleted jadwal still has an active dosen pengampu', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jadwal->delete();

    // id_dosen masih aktif (belum dihapus) — jadwal ini tidak boleh ikut dihapus permanen.
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => Dosen::factory()->create()->id, 'status' => 'active']);

    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id)->trashed())->toBeTrue();
    expect(Jadwal::withTrashed()->find($jadwal->id))->not->toBeNull();
});

it('refuses to permanently delete the kelas when a soft-deleted jadwal has a trashed perkuliahan with an active kehadiran underneath', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jadwal->delete();

    // Perkuliahan-nya sudah dihapus, tapi kehadiran mahasiswa di baliknya masih aktif — rantai
    // turunan ini harus tetap memblokir, bukan cuma cek satu level di bawah jadwal.
    $perkuliahan = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id]);
    Kehadiran::factory()->create(['id_perkuliahan' => $perkuliahan->id]);
    $perkuliahan->delete();

    $kelas->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelas->id)
        ->call('forceDeleteKelas');

    expect(Kelas::withTrashed()->find($kelas->id)->trashed())->toBeTrue();
});

it('shows all kelas mahasiswa filter options when no prodi is selected, and scopes them once one is', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    KelompokKelas::factory()->create(['nama' => 'Kelompok A', 'id_prodi' => $prodiA->id]);
    KelompokKelas::factory()->create(['nama' => 'Kelompok B', 'id_prodi' => $prodiB->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Kelompok A')
        ->assertSee('Kelompok B');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterProdi', (string) $prodiA->id)
        ->assertSee('Kelompok A')
        ->assertDontSee('Kelompok B');
});

it('defaults semester berjalan to the active semester when creating a kelas, but not when editing one', function () {
    $semesterAktif = Semester::factory()->create(['is_active' => true]);
    $semesterLain = Semester::factory()->create(['is_active' => false]);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->assertSet('id_semester', $semesterAktif->id);

    // Kelas yang sudah ada tetap menampilkan semester_berjalan miliknya sendiri, bukan semester aktif.
    $kelas = Kelas::factory()->create(['id_semester' => $semesterLain->id]);
    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $kelas->id])
        ->assertSet('id_semester', $semesterLain->id);
});

it('shows all kelas mahasiswa options in the form when no prodi is picked, and scopes them once one is', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    KelompokKelas::factory()->create(['nama' => 'Kelompok A', 'id_prodi' => $prodiA->id]);
    KelompokKelas::factory()->create(['nama' => 'Kelompok B', 'id_prodi' => $prodiB->id]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->assertSee('Kelompok A')
        ->assertSee('Kelompok B')
        ->set('id_prodi', $prodiA->id)
        ->assertSee('Kelompok A')
        ->assertDontSee('Kelompok B');
});

it('resets the picked kelas mahasiswa when the prodi changes, since it may no longer belong to the new prodi', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelompokA = KelompokKelas::factory()->create(['id_prodi' => $prodiA->id]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_prodi', $prodiA->id)
        ->set('id_kelompok_kelas', $kelompokA->id)
        ->assertSet('id_kelompok_kelas', $kelompokA->id)
        ->set('id_prodi', $prodiB->id)
        ->assertSet('id_kelompok_kelas', null);
});

it('displays prodi filter options with the jenjang code in parentheses', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create(['kode' => 'D3']);
    Prodi::factory()->create(['nama' => 'Kebidanan', 'id_jenjang' => $jenjang->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Kebidanan (D3)');
});

it('displays semester filter options as name with the code in parentheses', function () {
    $admin = adminUser();
    Semester::factory()->create(['nama' => '2025 Ganjil', 'kode' => '20251']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('2025 Ganjil (20251)');
});

it('admin dengan scope prodi hanya melihat kelas miliknya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $matkulA = Matkul::factory()->create(['nama' => 'Kelas Prodi A']);
    $matkulB = Matkul::factory()->create(['nama' => 'Kelas Prodi B']);
    Kelas::factory()->create([
        'id_prodi' => $prodiA->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id]),
    ]);
    Kelas::factory()->create([
        'id_prodi' => $prodiB->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id]),
    ]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Kelas Prodi A')
        ->assertDontSee('Kelas Prodi B');
});

it('admin dengan scope prodi tidak bisa menghapus kelas di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kelasB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(Kelas::find($kelasB->id))->not->toBeNull();
});

it('admin dengan scope prodi tidak bisa memulihkan atau menghapus permanen kelas di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);
    $kelasB->delete();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('restore', $kelasB->id)
        ->assertStatus(403);

    expect(Kelas::withTrashed()->find($kelasB->id)->trashed())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $kelasB->id)
        ->call('forceDeleteKelas')
        ->assertStatus(403);

    expect(Kelas::withTrashed()->find($kelasB->id)->trashed())->toBeTrue();
});

it('admin dengan scope prodi tidak bisa membuka detail kelas di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelasB->id])
        ->assertStatus(403);
});

it('carries the current page/filter state from index into the Lihat and Ubah links', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    Kelas::factory()->count(15)->create(['id_prodi' => $prodi->id]);

    $expectedQuery = 'id_prodi='.$prodi->id.'&page=2';

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterProdi', (string) $prodi->id)
        ->set('filterSemester', '')
        ->set('perPage', 10)
        ->call('gotoPage', 2)
        ->assertSee($expectedQuery);
});

it('points the Kembali button on the detail page to the page/filter state carried in the query string', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.show', $kelas->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.akademik.kelas').'?page=2&search=algoritma')
        ->assertDontSee('unexpected=1');
});

it('carries the forwarded state into the Ubah link on the detail page too', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.show', $kelas->id).'?page=2&search=algoritma')
        ->assertOk()
        ->assertSee(route('admin.akademik.kelas.edit', $kelas->id).'?page=2&search=algoritma');
});

it('carries the forwarded state into the edit form Batal link', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $expectedBackUrl = route('admin.akademik.kelas').'?page=2&search=algoritma';

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.edit', $kelas->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');
});

// Beda dari tombol Batal di atas: begitu simpan BERHASIL, redirect sengaja tidak memakai backUrl
// (filter dari sebelum form dibuka) — diarahkan ke filter prodi & semester milik kelas yang baru
// saja disimpan, supaya langsung kelihatan di daftar tanpa admin mengatur ulang filter manual.
it('redirects to the index filtered by the saved kelas own prodi and semester after a successful save, not the backUrl', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $semester = Semester::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_semester' => $semester->id]);

    $expectedRedirect = route('admin.akademik.kelas', ['id_prodi' => $prodi->id, 'id_semester' => $semester->id]);

    // Datang dari halaman/filter yang sama sekali berbeda (page 2, search "algoritma") — redirect
    // setelah simpan tetap harus mengikuti prodi/semester kelas, bukan filter asal ini.
    Livewire::withQueryParams(['page' => '2', 'search' => 'algoritma'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $kelas->id])
        ->set('kuota', '30')
        ->call('save')
        ->assertRedirect($expectedRedirect);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.akademik.kelas'))->assertRedirect(route('login'));
});

// Regression: layouts.web me-render @section('page_actions') di luar root <div> komponen, jadi
// tombol wire:click yang diletakkan di sana tidak pernah terikat Livewire dan diam saja saat diklik.
it('keeps the delete button inside the livewire root so wire:click stays bound', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.akademik.kelas.show', $kelas->id))->getContent();

    $rootStart = strpos($html, 'wire:id=');
    expect($rootStart)->not->toBeFalse();
    expect(strpos($html, 'wire:click="confirmDelete"'))->toBeGreaterThan($rootStart);
});
