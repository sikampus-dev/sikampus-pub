<?php

use App\Livewire\Admin\Krs\Form;
use App\Livewire\Admin\Krs\Index;
use App\Livewire\Admin\Krs\Show;
use App\Models\Kelas;
use App\Models\KelompokKelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Semester;
use Livewire\Livewire;

it('renders index and shows the aggregated row for a mahasiswa with krs', function () {
    $admin = adminUser();

    $prodi = Prodi::factory()->create(['nama' => 'Prodi Uji']);
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Budi Santoso', 'nim' => '2024000001', 'id_prodi' => $prodi->id]);
    $matkul = Matkul::factory()->create(['sks' => 3]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id]);
    $kelas->kurikulumMatkul()->update(['id_matkul' => $matkul->id]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    $this->actingAs($admin)
        ->get(route('admin.akademik.krs'))
        ->assertOk()
        ->assertSee('2024000001')
        ->assertSee('Budi Santoso');
});

it('creates a krs row via the create form', function () {
    $admin = adminUser();

    $mahasiswa = Mahasiswa::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswaOption', $mahasiswa->id)
        ->set('krs.0.id_kelas', $kelas->id)
        ->call('save')
        ->assertRedirect(route('admin.akademik.krs'));

    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->exists())->toBeTrue();
});

it('blocks creating a duplicate krs row for the same mahasiswa and kelas', function () {
    $admin = adminUser();

    $mahasiswa = Mahasiswa::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswaOption', $mahasiswa->id)
        ->set('krs.0.id_kelas', $kelas->id)
        ->call('save');

    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->count())->toBe(1);
});

it('menampilkan kelas mahasiswa di kartu detail mahasiswa pada halaman ubah KRS', function () {
    $admin = adminUser();

    $kelompokKelas = KelompokKelas::factory()->create(['nama' => 'PSCA 24 D']);
    $mahasiswa = Mahasiswa::factory()->create(['id_kelompok_kelas' => $kelompokKelas->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $krs->id])
        ->assertSet('mahasiswaKelompokKelas', 'PSCA 24 D')
        ->assertSee('PSCA 24 D');
});

it('updates a krs row via the edit form', function () {
    $admin = adminUser();

    $mahasiswa = Mahasiswa::factory()->create();
    $kelasLama = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    $kelasBaru = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasLama->id]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $krs->id])
        ->assertSet('editIdKelas', $kelasLama->id)
        ->set('editIdKelas', $kelasBaru->id)
        ->set('editStatus', 'acc')
        ->call('save')
        ->assertRedirect(route('admin.akademik.krs.show', $mahasiswa->id));

    $krs->refresh();
    expect($krs->id_kelas)->toBe($kelasBaru->id);
    expect($krs->approved_at)->not->toBeNull();
});

it('deletes a krs row from the show page', function () {
    $admin = adminUser();

    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $krs->id)
        ->call('delete');

    expect(Krs::find($krs->id))->toBeNull();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.akademik.krs'))->assertRedirect(route('login'));
});

it('shows a loading indicator scoped to the search/filter fields and pagination on the index page', function () {
    Livewire::actingAs(adminUser())
        ->test(Index::class)
        ->assertSee('wire:target="search, filterProdi, filterSemester, filterStatusPengajuan, gotoPage, previousPage, nextPage"', escape: false);
});

it('shows a loading indicator scoped to the filter/toggle fields on the show page', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs(adminUser())
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSee('wire:target="filterSemester, showTrashed"', escape: false);
});

it('admin dengan scope prodi hanya melihat KRS mahasiswa di prodinya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $mahasiswaA = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Prodi A', 'nim' => '2024000011', 'id_prodi' => $prodiA->id]);
    $mahasiswaB = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Prodi B', 'nim' => '2024000022', 'id_prodi' => $prodiB->id]);

    $kelasA = Kelas::factory()->create(['id_prodi' => $prodiA->id]);
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);

    Krs::factory()->create(['id_mahasiswa' => $mahasiswaA->id, 'id_kelas' => $kelasA->id]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswaB->id, 'id_kelas' => $kelasB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Mahasiswa Prodi A')
        ->assertDontSee('Mahasiswa Prodi B');

    $this->actingAs($admin)
        ->get(route('admin.akademik.krs.show', $mahasiswaB->id))
        ->assertForbidden();
});

it('generates an inline pdf for a mahasiswa krs on the active semester', function () {
    $admin = adminUser();

    $semester = Semester::factory()->active()->create();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Citra Wulandari', 'nim' => '2024000099']);
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi, 'id_semester' => $semester->id]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    $response = $this->actingAs($admin)
        ->get(route('admin.akademik.krs.cetak', $mahasiswa->id));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

it('admin dengan scope prodi tidak bisa mencetak KRS mahasiswa di luar prodinya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)
        ->get(route('admin.akademik.krs.cetak', $mahasiswaB->id))
        ->assertForbidden();
});

it('lists mata kuliah sorted by name on the detail krs page', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);

    // Dibuat sengaja tidak berurutan abjad supaya urutan created_at berbeda dari urutan nama.
    foreach (['Zoologi Dasar', 'Anatomi Manusia', 'Biologi Sel'] as $nama) {
        $matkul = Matkul::factory()->create(['nama' => $nama, 'sks' => 2]);
        $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id]);
        $kelas->kurikulumMatkul()->update(['id_matkul' => $matkul->id]);
        Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    }

    $this->actingAs($admin)
        ->get(route('admin.akademik.krs.show', $mahasiswa->id))
        ->assertOk()
        ->assertSeeInOrder(['Anatomi Manusia', 'Biologi Sel', 'Zoologi Dasar']);
});

it('adds a krs row via the tambah krs modal on the show page', function () {
    $admin = adminUser();

    $mahasiswa = Mahasiswa::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('bukaTambahKrsModal')
        ->assertSet('showTambahKrsModal', true)
        ->set('tambahKrs.0.id_kelas', $kelas->id)
        ->call('simpanTambahKrs')
        ->assertSet('showTambahKrsModal', false);

    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->exists())->toBeTrue();
});

it('offers kelas options for the tambah krs modal scoped to the mahasiswa prodi only', function () {
    $admin = adminUser();

    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id]);
    $kelasProdiA = Kelas::factory()->create(['id_prodi' => $prodiA->id]);
    $matkulA = Matkul::factory()->create(['nama' => 'Matkul Prodi A']);
    $kelasProdiA->kurikulumMatkul()->update(['id_matkul' => $matkulA->id]);
    $kelasProdiB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);
    $matkulB = Matkul::factory()->create(['nama' => 'Matkul Prodi B']);
    $kelasProdiB->kurikulumMatkul()->update(['id_matkul' => $matkulB->id]);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('bukaTambahKrsModal');

    $options = $component->instance()->kelasOptionsTambahKrs();

    expect($options)->toHaveKey($kelasProdiA->id);
    expect($options)->not->toHaveKey($kelasProdiB->id);
});

it('blocks adding a duplicate krs row via the tambah krs modal', function () {
    $admin = adminUser();

    $mahasiswa = Mahasiswa::factory()->create();
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('bukaTambahKrsModal')
        ->set('tambahKrs.0.id_kelas', $kelas->id)
        ->call('simpanTambahKrs')
        ->assertSet('showTambahKrsModal', true);

    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->count())->toBe(1);
});

it('menampilkan kelas mahasiswa di tabel index KRS', function () {
    $admin = adminUser();

    $kelompokKelas = KelompokKelas::factory()->create(['nama' => 'PSCA 24 A']);
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Dedi Kurniawan', 'nim' => '2024000033', 'id_kelompok_kelas' => $kelompokKelas->id]);
    $kelas = Kelas::factory()->create(['id_prodi' => $mahasiswa->id_prodi]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    $this->actingAs($admin)
        ->get(route('admin.akademik.krs'))
        ->assertOk()
        ->assertSee('PSCA 24 A');
});

it('menampilkan kelas mahasiswa pada cabang belum mengajukan di tabel index KRS', function () {
    $admin = adminUser();

    $semester = Semester::factory()->create();
    $kelompokKelas = KelompokKelas::factory()->create(['nama' => 'PSCA 24 C']);
    Mahasiswa::factory()->create(['nama' => 'Eka Prasetya', 'nim' => '2024000044', 'id_kelompok_kelas' => $kelompokKelas->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterSemester', (string) $semester->id)
        ->set('filterStatusPengajuan', 'belum_mengajukan')
        ->assertSee('PSCA 24 C');
});

it('menampilkan kelas mahasiswa di halaman detail KRS', function () {
    $admin = adminUser();

    $kelompokKelas = KelompokKelas::factory()->create(['nama' => 'PSCA 24 B']);
    $mahasiswa = Mahasiswa::factory()->create(['id_kelompok_kelas' => $kelompokKelas->id]);

    $this->actingAs($admin)
        ->get(route('admin.akademik.krs.show', $mahasiswa->id))
        ->assertOk()
        ->assertSee('PSCA 24 B');
});

it('menampilkan kelas mahasiswa (bukan kode kelas) di tabel detail KRS', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $kelompokKelas = KelompokKelas::factory()->create(['nama' => 'BIOLOGI 25 A']);
    $kelas = Kelas::factory()->create(['kode' => 'BID24', 'id_kelompok_kelas' => $kelompokKelas->id]);
    $tanpaKelompok = Kelas::factory()->create(['kode' => 'BID25', 'id_kelompok_kelas' => null]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $tanpaKelompok->id]);

    Livewire::actingAs($admin)->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSeeInOrder(['Mata Kuliah', 'Kelas Mahasiswa', 'Semester'])
        ->assertSee('BIOLOGI 25 A')
        ->assertDontSee('BID24');
});
