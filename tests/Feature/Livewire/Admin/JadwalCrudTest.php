<?php

use App\Livewire\Admin\Jadwal\Form;
use App\Livewire\Admin\Jadwal\Index;
use App\Livewire\Admin\Jadwal\Show;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\KelompokKelas;
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
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $this->actingAs($admin)->get(route('admin.akademik.jadwal'))->assertOk()->assertSee('Pemrograman Web');
    $this->actingAs($admin)->get(route('admin.akademik.jadwal.create'))->assertOk()->assertSee('Tambah Jadwal');
    $this->actingAs($admin)->get(route('admin.akademik.jadwal.show', $jadwal->id))->assertOk()->assertSee('Pemrograman Web');
});

it('marks the filter prodi and filter semester selects as live so picking them actually filters the kelas list', function () {
    // Regression: x-searchable-select cuma mengirim nilai ke server saat :live="true" — tanpa itu,
    // updatedFilterProdi()/updatedFilterSemester() dan wire:key milik dropdown kelas tidak pernah
    // ter-trigger dari memilih prodi/semester saja, jadi kelas yang tampil selalu daftar tak
    // tersaring. Livewire::test()->set() tidak menangkap bug ini karena melewati Alpine/entangle,
    // makanya diperiksa lewat markup HTML yang benar-benar dirender.
    $admin = adminUser();

    $response = $this->actingAs($admin)->get(route('admin.akademik.jadwal.create'));

    $response->assertOk();
    $response->assertSee("\$wire.entangle('filterProdi').live", false);
    $response->assertSee("\$wire.entangle('filterSemester').live", false);
});

it('lists every kelas matching the selected prodi and semester, not capped at 200', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $semester = Semester::factory()->create();

    // Satu kurikulum_matkul dipakai ulang di semua baris supaya tidak memicu 205x rantai factory
    // bersarang (Kurikulum -> Prodi -> Jenjang) yang menghabiskan nilai unique() milik Jenjang.
    $kurikulumMatkul = KurikulumMatkul::factory()->create();

    // Regression: kelasOptions() dulu pakai ->limit(200), jadi kombinasi prodi+semester dengan
    // lebih dari 200 kelas kehilangan sisanya begitu saja.
    $kelasIds = Kelas::factory()->count(205)->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_semester' => $semester->id,
    ])->pluck('id');

    // Kelas di prodi/semester lain tidak boleh ikut muncul.
    Kelas::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('filterProdi', $prodi->id)
        ->set('filterSemester', $semester->id);

    $optionIds = collect($component->instance()->kelasOptions())->pluck('id');
    expect($optionIds)->toHaveCount(205);
    expect($optionIds->diff($kelasIds))->toBeEmpty();
});

it('includes the kelompok kelas name in the label so classes sharing a matkul and semester can be told apart', function () {
    $admin = adminUser();
    $semester = Semester::factory()->create();
    $matkul = Matkul::factory()->create(['nama' => 'Pemrograman Web', 'kode' => 'IF101']);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelompokA = KelompokKelas::factory()->create(['nama' => 'Kelas A']);
    $kelompokB = KelompokKelas::factory()->create(['nama' => 'Kelas B']);

    // Regression: kode_matkul + nama_matkul + semester identik untuk kedua baris ini — tanpa nama
    // kelompok kelas di label, keduanya tampil sama persis di dropdown dan tidak bisa dibedakan.
    $kelasA = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_semester' => $semester->id,
        'id_kelompok_kelas' => $kelompokA->id,
    ]);
    $kelasB = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_semester' => $semester->id,
        'id_kelompok_kelas' => $kelompokB->id,
    ]);

    $options = Livewire::actingAs($admin)
        ->test(Form::class)
        ->instance()
        ->kelasOptions();

    $labelById = $options->pluck('label', 'id');

    expect($labelById[$kelasA->id])->toContain('Kelompok: Kelas A');
    expect($labelById[$kelasB->id])->toContain('Kelompok: Kelas B');
    expect($labelById[$kelasA->id])->not->toBe($labelById[$kelasB->id]);
});

it('creates several jadwal slots at once from jumlah_pertemuan', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create(['jml_pertemuan' => 16]);
    $dosen = Dosen::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jumlah_pertemuan', '3')
        ->set('hari', 'senin')
        ->set('jam_mulai', '08:00')
        ->set('jam_selesai', '10:00')
        ->set('is_active', true)
        ->call('addDosen', $dosen->id)
        ->call('save')
        ->assertRedirect(route('admin.akademik.jadwal', [
            'id_prodi' => $kelas->id_prodi,
            'id_semester' => $kelas->id_semester,
            'id_kelas' => $kelas->id,
        ]));

    $rows = Jadwal::where('id_kelas', $kelas->id)->orderBy('urutan_pertemuan')->get();
    expect($rows)->toHaveCount(3);
    expect($rows->pluck('urutan_pertemuan')->all())->toBe([1, 2, 3]);
    expect($rows->every(fn (Jadwal $j) => $j->is_active === true && $j->hari === 'senin'))->toBeTrue();

    foreach ($rows as $row) {
        expect(JadwalDosen::where('id_jadwal', $row->id)->where('id_dosen', $dosen->id)->exists())->toBeTrue();
    }
});

it('rejects create when a slot for the kelas and ruangan is already taken', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $ruangan = Ruangan::factory()->create();
    Jadwal::factory()->create(['id_kelas' => $kelas->id, 'id_ruangan' => $ruangan->id, 'urutan_pertemuan' => 1]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jumlah_pertemuan', '2')
        ->set('id_ruangan', $ruangan->id)
        ->call('save')
        ->assertHasErrors(['jumlah_pertemuan']);

    expect(Jadwal::where('id_kelas', $kelas->id)->count())->toBe(1);
});

it('redirects to the index filtered by the new jadwal prodi, semester and kelas after create', function () {
    // Filter diambil dari kelas yang dipilih, bukan dari filter lama Index (backUrl) — slot yang
    // baru dibuat harus langsung kelihatan tanpa admin menyetel ulang filter.
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    Livewire::withQueryParams(['page' => '3', 'search' => 'algoritma', 'id_semester' => '999'])
        ->actingAs($admin)
        ->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jumlah_pertemuan', '1')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.akademik.jadwal').'?id_prodi='.$kelas->id_prodi
            .'&id_semester='.$kelas->id_semester.'&id_kelas='.$kelas->id);
});

it('restores the prodi, semester and kelas filters on the index from that redirect', function () {
    $admin = adminUser();

    Livewire::withQueryParams(['id_prodi' => '7', 'id_semester' => '8', 'id_kelas' => '9'])
        ->actingAs($admin)
        ->test(Index::class)
        ->assertSet('filterProdi', '7')
        ->assertSet('filterSemester', '8')
        ->assertSet('filterKelas', '9');
});

it('lewatiPengecekanBentrok lets create skip the slot pre-check when id_ruangan is empty', function () {
    // Tanpa ruangan, constraint unique di database membolehkan baris kembar (MySQL memperlakukan
    // NULL sebagai berbeda dari NULL lain pada unique index) — jadi bypass ini benar-benar berhasil
    // menyimpan, bukan cuma melewati pengecekan lalu tetap gagal di database.
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Jadwal::factory()->create(['id_kelas' => $kelas->id, 'id_ruangan' => null, 'urutan_pertemuan' => 1]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jumlah_pertemuan', '2')
        ->set('lewatiPengecekanBentrok', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.akademik.jadwal', [
            'id_prodi' => $kelas->id_prodi,
            'id_semester' => $kelas->id_semester,
            'id_kelas' => $kelas->id,
        ]));

    expect(Jadwal::where('id_kelas', $kelas->id)->count())->toBe(3);
});

it('lewatiPengecekanBentrok does not let create bypass the database unique constraint when id_ruangan is set', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $ruangan = Ruangan::factory()->create();
    Jadwal::factory()->create(['id_kelas' => $kelas->id, 'id_ruangan' => $ruangan->id, 'urutan_pertemuan' => 1]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jumlah_pertemuan', '1')
        ->set('id_ruangan', $ruangan->id)
        ->set('lewatiPengecekanBentrok', true)
        ->call('save')
        ->assertHasErrors(['jumlah_pertemuan']);

    expect(Jadwal::where('id_kelas', $kelas->id)->count())->toBe(1);
});

it('updates a single jadwal row and syncs its dosen list', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'urutan_pertemuan' => 1, 'hari' => 'senin']);
    $dosenLama = Dosen::factory()->create();
    $dosenBaru = Dosen::factory()->create();
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosenLama->id, 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $jadwal->id])
        ->assertSet('hari', 'senin')
        ->set('hari', 'rabu')
        ->call('removeDosen', $dosenLama->id)
        ->call('addDosen', $dosenBaru->id)
        ->call('save')
        ->assertRedirect(route('admin.akademik.jadwal'));

    $jadwal->refresh();
    expect($jadwal->hari)->toBe('rabu');
    expect(JadwalDosen::where('id_jadwal', $jadwal->id)->where('id_dosen', $dosenLama->id)->exists())->toBeFalse();
    expect(JadwalDosen::where('id_jadwal', $jadwal->id)->where('id_dosen', $dosenBaru->id)->exists())->toBeTrue();
});

it('rejects update when the new slot collides with another jadwal row', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Jadwal::factory()->create(['id_kelas' => $kelas->id, 'urutan_pertemuan' => 1]);
    $jadwal2 = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'urutan_pertemuan' => 2]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $jadwal2->id])
        ->set('urutan_pertemuan', '1')
        ->call('save')
        ->assertHasErrors(['urutan_pertemuan']);
});

it('lewatiPengecekanBentrok lets update skip the slot pre-check when id_ruangan is empty', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Jadwal::factory()->create(['id_kelas' => $kelas->id, 'id_ruangan' => null, 'urutan_pertemuan' => 1]);
    $jadwal2 = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'id_ruangan' => null, 'urutan_pertemuan' => 2]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $jadwal2->id])
        ->set('urutan_pertemuan', '1')
        ->set('lewatiPengecekanBentrok', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.akademik.jadwal'));

    expect($jadwal2->fresh()->urutan_pertemuan)->toBe(1);
});

it('lewatiPengecekanBentrok does not let update bypass the database unique constraint when id_ruangan is set', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $ruangan = Ruangan::factory()->create();
    Jadwal::factory()->create(['id_kelas' => $kelas->id, 'id_ruangan' => $ruangan->id, 'urutan_pertemuan' => 1]);
    $jadwal2 = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'id_ruangan' => $ruangan->id, 'urutan_pertemuan' => 2]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $jadwal2->id])
        ->set('urutan_pertemuan', '1')
        ->set('lewatiPengecekanBentrok', true)
        ->call('save')
        ->assertHasErrors(['urutan_pertemuan']);

    expect($jadwal2->fresh()->urutan_pertemuan)->toBe(2);
});

it('deletes a jadwal from the index page', function () {
    $admin = adminUser();
    $jadwal = Jadwal::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jadwal->id)
        ->call('delete');

    expect(Jadwal::find($jadwal->id))->toBeNull();
});

it('admin dengan scope prodi hanya melihat jadwal miliknya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $matkulA = Matkul::factory()->create(['nama' => 'Jadwal Prodi A']);
    $matkulB = Matkul::factory()->create(['nama' => 'Jadwal Prodi B']);
    $kelasA = Kelas::factory()->create([
        'id_prodi' => $prodiA->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id]),
    ]);
    $kelasB = Kelas::factory()->create([
        'id_prodi' => $prodiB->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id]),
    ]);
    Jadwal::factory()->create(['id_kelas' => $kelasA->id]);
    Jadwal::factory()->create(['id_kelas' => $kelasB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Jadwal Prodi A')
        ->assertDontSee('Jadwal Prodi B');
});

it('admin dengan scope prodi tidak bisa menghapus jadwal di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);
    $jadwalB = Jadwal::factory()->create(['id_kelas' => $kelasB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jadwalB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(Jadwal::find($jadwalB->id))->not->toBeNull();
});

it('admin dengan scope prodi tidak bisa membuka detail jadwal di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);
    $jadwalB = Jadwal::factory()->create(['id_kelas' => $kelasB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $jadwalB->id])
        ->assertStatus(403);
});

it('carries the current page/filter state from index into the Lihat and Ubah links', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id]);
    Jadwal::factory()->count(15)->create(['id_kelas' => $kelas->id, 'id_ruangan' => null]);

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
    $jadwal = Jadwal::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.akademik.jadwal.show', $jadwal->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.akademik.jadwal').'?page=2&search=algoritma')
        ->assertDontSee('unexpected=1');
});

it('carries the forwarded state into the Ubah link on the detail page too', function () {
    $admin = adminUser();
    $jadwal = Jadwal::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.akademik.jadwal.show', $jadwal->id).'?page=2&search=algoritma')
        ->assertOk()
        ->assertSee(route('admin.akademik.jadwal.edit', $jadwal->id).'?page=2&search=algoritma');
});

it('carries the forwarded state through the edit form Batal link and the save redirect', function () {
    $admin = adminUser();
    $jadwal = Jadwal::factory()->create();

    $expectedBackUrl = route('admin.akademik.jadwal').'?page=2&search=algoritma';

    $this->actingAs($admin)
        ->get(route('admin.akademik.jadwal.edit', $jadwal->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');

    Livewire::withQueryParams(['page' => '2', 'search' => 'algoritma'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $jadwal->id])
        ->set('hari', 'kamis')
        ->call('save')
        ->assertRedirect($expectedBackUrl);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.akademik.jadwal'))->assertRedirect(route('login'));
});

// Regression: layouts.web me-render @section('page_actions') di luar root <div> komponen, jadi
// tombol wire:click yang diletakkan di sana tidak pernah terikat Livewire dan diam saja saat diklik.
it('keeps the delete button inside the livewire root so wire:click stays bound', function () {
    $admin = adminUser();
    $jadwal = Jadwal::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.akademik.jadwal.show', $jadwal->id))->getContent();

    $rootStart = strpos($html, 'wire:id=');
    expect($rootStart)->not->toBeFalse();
    expect(strpos($html, 'wire:click="confirmDelete"'))->toBeGreaterThan($rootStart);
});
