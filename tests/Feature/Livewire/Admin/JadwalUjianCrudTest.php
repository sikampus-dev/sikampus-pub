<?php

use App\Livewire\Admin\JadwalUjian\Form;
use App\Livewire\Admin\JadwalUjian\Index;
use App\Livewire\Admin\JadwalUjian\Show;
use App\Models\Kelas;
use App\Models\KelompokKelas;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\Semester;
use App\Models\Ujian;
use Livewire\Livewire;

it('renders index, create form, and show page', function () {
    $admin = adminUser();
    $matkul = Matkul::factory()->create(['nama' => 'Pemrograman Web', 'kode' => 'IF101']);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id]);
    $ujian = Ujian::factory()->create(['id_kelas' => $kelas->id, 'id_semester' => $kelas->id_semester]);

    $this->actingAs($admin)->get(route('admin.akademik.jadwal-ujian'))->assertOk()->assertSee('Pemrograman Web');
    $this->actingAs($admin)->get(route('admin.akademik.jadwal-ujian.create'))->assertOk()->assertSee('Tambah Jadwal Ujian');
    $this->actingAs($admin)->get(route('admin.akademik.jadwal-ujian.show', $ujian->id))->assertOk()->assertSee('Pemrograman Web');
});

it('marks the filter prodi and filter semester selects as live so picking them actually filters the kelas list', function () {
    // Regression: x-searchable-select cuma mengirim nilai ke server saat :live="true" — tanpa itu,
    // updatedFilterProdi()/updatedFilterSemester() dan wire:key milik dropdown kelas tidak pernah
    // ter-trigger dari memilih prodi/semester saja, jadi kelas yang tampil selalu daftar tak
    // tersaring. Livewire::test()->set() tidak menangkap bug ini karena melewati Alpine/entangle,
    // makanya diperiksa lewat markup HTML yang benar-benar dirender.
    $admin = adminUser();

    $response = $this->actingAs($admin)->get(route('admin.akademik.jadwal-ujian.create'));

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

it('creates, updates, and deletes a jadwal ujian', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $ruangan = Ruangan::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jenis_ujian', 'UAS')
        ->set('id_ruangan', $ruangan->id)
        ->set('tanggal_mulai', '2026-01-10T08:00')
        ->set('tanggal_selesai', '2026-01-10T10:00')
        ->call('save')
        ->assertRedirect(route('admin.akademik.jadwal-ujian'));

    $ujian = Ujian::where('id_kelas', $kelas->id)->firstOrFail();
    expect($ujian->jenis_ujian)->toBe('UAS');
    expect($ujian->id_ruangan)->toBe($ruangan->id);
    expect($ujian->id_semester)->toBe($kelas->id_semester);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $ujian->id])
        ->assertSet('jenis_ujian', 'UAS')
        ->set('jenis_ujian', 'PRAKTIKUM')
        ->call('save');

    expect($ujian->fresh()->jenis_ujian)->toBe('PRAKTIKUM');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $ujian->id)
        ->call('delete');

    expect(Ujian::find($ujian->id))->toBeNull();
});

it('rejects a duplicate kombinasi kelas, semester, dan jenis ujian', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Ujian::factory()->create(['id_kelas' => $kelas->id, 'id_semester' => $kelas->id_semester, 'jenis_ujian' => 'UTS']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jenis_ujian', 'UTS')
        ->call('save')
        ->assertHasErrors(['id_kelas']);
});

it('rejects tanggal selesai sebelum tanggal mulai', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_kelas', $kelas->id)
        ->set('jenis_ujian', 'UTS')
        ->set('tanggal_mulai', '2026-01-10T10:00')
        ->set('tanggal_selesai', '2026-01-10T08:00')
        ->call('save')
        ->assertHasErrors(['tanggal_selesai']);
});

it('admin dengan scope prodi hanya melihat jadwal ujian miliknya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $matkulA = Matkul::factory()->create(['nama' => 'Ujian Prodi A']);
    $matkulB = Matkul::factory()->create(['nama' => 'Ujian Prodi B']);
    $kelasA = Kelas::factory()->create([
        'id_prodi' => $prodiA->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id]),
    ]);
    $kelasB = Kelas::factory()->create([
        'id_prodi' => $prodiB->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id]),
    ]);
    Ujian::factory()->create(['id_kelas' => $kelasA->id, 'id_semester' => $kelasA->id_semester]);
    Ujian::factory()->create(['id_kelas' => $kelasB->id, 'id_semester' => $kelasB->id_semester]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Ujian Prodi A')
        ->assertDontSee('Ujian Prodi B');
});

it('admin dengan scope prodi tidak bisa menghapus jadwal ujian di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);
    $ujianB = Ujian::factory()->create(['id_kelas' => $kelasB->id, 'id_semester' => $kelasB->id_semester]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $ujianB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(Ujian::find($ujianB->id))->not->toBeNull();
});

it('admin dengan scope prodi tidak bisa membuka detail jadwal ujian di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);
    $ujianB = Ujian::factory()->create(['id_kelas' => $kelasB->id, 'id_semester' => $kelasB->id_semester]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $ujianB->id])
        ->assertStatus(403);
});

it('carries the current page/filter state from index into the Lihat and Ubah links', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    Kelas::factory()->count(15)->create(['id_prodi' => $prodi->id])->each(
        fn (Kelas $k) => Ujian::factory()->create(['id_kelas' => $k->id, 'id_semester' => $k->id_semester])
    );

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
    $ujian = Ujian::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.akademik.jadwal-ujian.show', $ujian->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.akademik.jadwal-ujian').'?page=2&search=algoritma')
        ->assertDontSee('unexpected=1');
});

it('carries the forwarded state into the Ubah link on the detail page too', function () {
    $admin = adminUser();
    $ujian = Ujian::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.akademik.jadwal-ujian.show', $ujian->id).'?page=2&search=algoritma')
        ->assertOk()
        ->assertSee(route('admin.akademik.jadwal-ujian.edit', $ujian->id).'?page=2&search=algoritma');
});

it('carries the forwarded state through the edit form Batal link and the save redirect', function () {
    $admin = adminUser();
    $ujian = Ujian::factory()->create();

    $expectedBackUrl = route('admin.akademik.jadwal-ujian').'?page=2&search=algoritma';

    $this->actingAs($admin)
        ->get(route('admin.akademik.jadwal-ujian.edit', $ujian->id).'?page=2&search=algoritma&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');

    Livewire::withQueryParams(['page' => '2', 'search' => 'algoritma'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $ujian->id])
        ->set('id_ruangan', Ruangan::factory()->create()->id)
        ->call('save')
        ->assertRedirect($expectedBackUrl);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.akademik.jadwal-ujian'))->assertRedirect(route('login'));
});
