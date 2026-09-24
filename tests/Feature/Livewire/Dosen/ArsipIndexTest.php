<?php

use App\Livewire\Dosen\Arsip\Index;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.arsip'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.arsip'))->assertForbidden();
});

it('lists a unique kelas from active jadwal_dosen rows, filtered by semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $semesterLain = Semester::factory()->create();

    $kelasAktif = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal1 = Jadwal::factory()->create(['id_kelas' => $kelasAktif->id]);
    $jadwal2 = Jadwal::factory()->create(['id_kelas' => $kelasAktif->id]);
    JadwalDosen::create(['id_jadwal' => $jadwal1->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    JadwalDosen::create(['id_jadwal' => $jadwal2->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $kelasLain = Kelas::factory()->create(['id_semester' => $semesterLain->id]);
    $jadwalLain = Jadwal::factory()->create(['id_kelas' => $kelasLain->id]);
    JadwalDosen::create(['id_jadwal' => $jadwalLain->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    // jadwal nonaktif -> tidak muncul
    $kelasNonaktif = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwalNonaktif = Jadwal::factory()->create(['id_kelas' => $kelasNonaktif->id]);
    JadwalDosen::create(['id_jadwal' => $jadwalNonaktif->id, 'id_dosen' => $dosen->id, 'status' => 'inactive']);

    // Semester filter kosong secara eksplisit = semua semester, jadi kelas dari kedua semester
    // ikut terdaftar (default arsip sekarang mengunci ke semester aktif — lihat test lain).
    $rows = Livewire::actingAs($dosenUser)->test(Index::class)
        ->set('filterSemester', '')
        ->instance()->rows();
    expect($rows)->toHaveCount(2);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)
        ->set('filterSemester', (string) $semesterAktif->id)
        ->instance()->rows();

    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasAktif->id);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)
        ->set('filterSemester', (string) $semesterLain->id)
        ->instance()->rows();

    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasLain->id);
});

it('defaults the semester filter to the active semester on first visit', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $semesterLampau = Semester::factory()->create();

    $kelasAktif = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $kelasLampau = Kelas::factory()->create(['id_semester' => $semesterLampau->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasAktif->id, 'is_pic' => true]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasLampau->id, 'is_pic' => true]);

    $component = Livewire::actingAs($dosenUser)->test(Index::class);

    expect($component->get('filterSemester'))->toBe((string) $semesterAktif->id);
    $rows = $component->instance()->rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasAktif->id);

    // Arsip semester lampau tetap terjangkau — cukup ganti filter secara eksplisit, bukan
    // dengan membuka halamannya (itulah kenapa default aktif tidak menyembunyikannya).
    $rows = $component->set('filterSemester', (string) $semesterLampau->id)->instance()->rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasLampau->id);
});

it('leaves the semester filter empty when no semester is currently active', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterLampau = Semester::factory()->create();
    $kelasLampau = Kelas::factory()->create(['id_semester' => $semesterLampau->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelasLampau->id]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $component = Livewire::actingAs($dosenUser)->test(Index::class);

    expect($component->get('filterSemester'))->toBe('');
    expect($component->instance()->rows())->toHaveCount(1);
});

it('also lists kelas the dosen only has a kelas_dosen row for', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semester = Semester::factory()->create();

    // Diampu (kelas_dosen) tapi belum punya slot jadwal sama sekali.
    $kelasTanpaJadwal = Kelas::factory()->create(['id_semester' => $semester->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasTanpaJadwal->id, 'is_pic' => true]);

    // Punya jadwal_dosen saja — tetap harus ikut, tidak tergeser oleh sumber baru.
    $kelasDariJadwal = Kelas::factory()->create(['id_semester' => $semester->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelasDariJadwal->id]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();
    $ids = $rows->pluck('id')->all();

    expect($ids)->toContain($kelasTanpaJadwal->id);
    expect($ids)->toContain($kelasDariJadwal->id);
    expect($rows)->toHaveCount(2);
});

it('does not duplicate a kelas listed in both kelas_dosen and jadwal_dosen', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semester = Semester::factory()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semester->id]);

    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);
    $jadwal1 = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jadwal2 = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    JadwalDosen::create(['id_jadwal' => $jadwal1->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    JadwalDosen::create(['id_jadwal' => $jadwal2->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    expect(Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows())->toHaveCount(1);
});

it('does not list kelas where the dosen has no jadwal', function () {
    $dosenUser = dosenUser();
    Kelas::factory()->create();

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();
    expect($rows)->toHaveCount(0);
});

it('shows the semester of each kelas, newest semester first', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterLama = Semester::factory()->create(['kode' => '20241', 'nama' => '2024 Ganjil']);
    $semesterBaru = Semester::factory()->create(['kode' => '20251', 'nama' => '2025 Ganjil']);

    $kelasLama = Kelas::factory()->create(['id_semester' => $semesterLama->id]);
    $kelasBaru = Kelas::factory()->create(['id_semester' => $semesterBaru->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasLama->id, 'is_pic' => true]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasBaru->id, 'is_pic' => true]);

    $component = Livewire::actingAs($dosenUser)->test(Index::class);

    $component->assertSee('2025 Ganjil')->assertSee('20251')
        ->assertSee('2024 Ganjil')->assertSee('20241')
        ->assertSeeInOrder(['2025 Ganjil', '2024 Ganjil']);

    expect($component->instance()->rows()[0]->id)->toBe($kelasBaru->id);
});

it('offers both export formats only when the arsip has at least one kelas', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    Livewire::actingAs($dosenUser)->test(Index::class)
        ->assertDontSee('Ekspor Excel')
        ->assertDontSee('Ekspor PDF');

    $kelas = Kelas::factory()->create(['id_semester' => Semester::factory()->create()->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    Livewire::actingAs($dosenUser)->test(Index::class)
        ->assertSee('Ekspor Excel')
        ->assertSee('Ekspor PDF');
});

it('streams xlsx and pdf downloads of the arsip', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semester = Semester::factory()->create(['kode' => '20242', 'nama' => '2024 Genap']);
    $kelas = Kelas::factory()->create(['id_semester' => $semester->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    Livewire::actingAs($dosenUser)->test(Index::class)->call('exportExcel')->assertFileDownloaded();
    Livewire::actingAs($dosenUser)->test(Index::class)->call('exportPdf')->assertFileDownloaded();
});

it('names the export file after the selected semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semester = Semester::factory()->create(['kode' => '20242', 'nama' => '2024 Genap']);
    $kelas = Kelas::factory()->create(['id_semester' => $semester->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    // Tanpa filter: nama berkas tidak menyebut semester mana pun.
    $tanpaFilter = Livewire::actingAs($dosenUser)->test(Index::class)
        ->call('exportExcel')->effects['download']['name'];
    expect($tanpaFilter)->toStartWith('Arsip_Perkuliahan_');
    expect($tanpaFilter)->not->toContain('20242');

    $denganFilter = Livewire::actingAs($dosenUser)->test(Index::class)
        ->set('filterSemester', (string) $semester->id)
        ->call('exportExcel')->effects['download']['name'];
    expect($denganFilter)->toContain('20242');
});

it('honours id_semester from the query string, as sent by the arsip nilai back link', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterLampau = Semester::factory()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterLampau->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);
    Semester::factory()->active()->create();

    $component = Livewire::withQueryParams(['id_semester' => (string) $semesterLampau->id])
        ->actingAs($dosenUser)
        ->test(Index::class);

    expect($component->get('filterSemester'))->toBe((string) $semesterLampau->id);
    expect($component->instance()->rows())->toHaveCount(1);

    // Sisi pengirimnya: halaman arsip nilai menautkan balik dengan semester kelas ini.
    $this->actingAs($dosenUser)->get(route('dosen.arsip.nilai', $kelas->id))
        ->assertOk()
        ->assertSee(route('dosen.arsip', ['id_semester' => $semesterLampau->id]), false);
});

it('paginates the arsip list at 10 rows per page', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $semester = Semester::factory()->create();
    // Prodi dibagikan (bukan Kelas::factory()->create() polos per baris) supaya tidak membuat
    // Prodi/Jenjang baru 15x — JenjangFactory::kode cuma punya pool 26 nilai unik (lexify('S?')),
    // dan test lain di file/proses yang sama juga ikut memakainya.
    $prodi = Prodi::factory()->create();

    foreach (range(1, 15) as $i) {
        $kelas = Kelas::factory()->create(['id_semester' => $semester->id, 'id_prodi' => $prodi->id]);
        KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);
    }

    $component = Livewire::actingAs($dosenUser)->test(Index::class);

    $page1 = $component->instance()->rows();
    expect($page1)->toHaveCount(10);
    expect($page1->total())->toBe(15);
    expect($page1->lastPage())->toBe(2);

    $page2 = $component->call('gotoPage', 2)->instance()->rows();
    expect($page2)->toHaveCount(5);
});

it('resets to page 1 when the semester filter or search term changes', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $semesterA = Semester::factory()->create();
    $semesterB = Semester::factory()->create();
    $prodi = Prodi::factory()->create();

    foreach (range(1, 12) as $i) {
        $kelas = Kelas::factory()->create(['id_semester' => $semesterA->id, 'id_prodi' => $prodi->id]);
        KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);
    }

    $component = Livewire::actingAs($dosenUser)->test(Index::class)->call('gotoPage', 2);
    expect($component->instance()->rows()->currentPage())->toBe(2);

    $component->set('filterSemester', (string) $semesterB->id);
    expect($component->instance()->rows()->currentPage())->toBe(1);
});

it('searches the arsip list by kode and nama mata kuliah, via the kurikulum_matkul override or the matkul fallback', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $semester = Semester::factory()->create();

    // kurikulum_matkul punya override kode/nama sendiri.
    $matkulA = Matkul::factory()->create(['kode' => 'IF999', 'nama' => 'Tidak Relevan']);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id, 'kode_matkul' => 'IF101', 'nama_matkul' => 'Algoritma dan Pemrograman']);
    $kelasA = Kelas::factory()->create(['id_semester' => $semester->id, 'id_kurikulum_matkul' => $kmA->id]);

    // kurikulum_matkul tidak override apa pun, jadi jatuh ke kode/nama milik matkul.
    $matkulB = Matkul::factory()->create(['kode' => 'IF202', 'nama' => 'Basis Data']);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id, 'kode_matkul' => null, 'nama_matkul' => null]);
    $kelasB = Kelas::factory()->create(['id_semester' => $semester->id, 'id_kurikulum_matkul' => $kmB->id]);

    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasA->id, 'is_pic' => true]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasB->id, 'is_pic' => true]);

    $component = Livewire::actingAs($dosenUser)->test(Index::class)->set('filterSemester', '');

    $rows = $component->set('search', 'IF101')->instance()->rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasA->id);

    $rows = $component->set('search', 'algoritma')->instance()->rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasA->id);

    $rows = $component->set('search', 'IF202')->instance()->rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasB->id);

    $rows = $component->set('search', 'basis data')->instance()->rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasB->id);

    $rows = $component->set('search', 'tidak ada yang cocok')->instance()->rows();
    expect($rows)->toHaveCount(0);
});

it('includes every matching row in the export, not just the current page', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $semester = Semester::factory()->create();
    $prodi = Prodi::factory()->create();

    foreach (range(1, 12) as $i) {
        $kelas = Kelas::factory()->create(['id_semester' => $semester->id, 'id_prodi' => $prodi->id]);
        KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);
    }

    $component = Livewire::actingAs($dosenUser)->test(Index::class);
    expect($component->instance()->rows())->toHaveCount(10);

    $reflection = new ReflectionMethod($component->instance(), 'exportRows');
    $reflection->setAccessible(true);
    expect($reflection->invoke($component->instance()))->toHaveCount(12);

    $download = $component->call('exportExcel')->effects['download'];
    expect($download['name'])->toStartWith('Arsip_Perkuliahan_');
});
