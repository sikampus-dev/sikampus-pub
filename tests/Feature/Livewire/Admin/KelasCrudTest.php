<?php

use App\Livewire\Admin\Kelas\Form;
use App\Livewire\Admin\Kelas\Index;
use App\Livewire\Admin\Kelas\Show;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\Jenjang;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\KelompokKelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\Semester;
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
        ->assertRedirect(route('admin.akademik.kelas'));

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
        ->assertRedirect(route('admin.akademik.kelas'));

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
        ->assertRedirect(route('admin.akademik.kelas'));

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
        ->assertRedirect(route('admin.akademik.kelas'));

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

it('carries the forwarded state through the edit form Batal link and the save redirect', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    $expectedBackUrl = route('admin.akademik.kelas').'?page=2&search=algoritma';

    $this->actingAs($admin)
        ->get(route('admin.akademik.kelas.edit', $kelas->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');

    Livewire::withQueryParams(['page' => '2', 'search' => 'algoritma'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $kelas->id])
        ->set('kuota', '30')
        ->call('save')
        ->assertRedirect($expectedBackUrl);
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
