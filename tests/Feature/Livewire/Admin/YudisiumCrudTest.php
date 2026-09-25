<?php

use App\Livewire\Admin\Yudisium\Form;
use App\Livewire\Admin\Yudisium\Index;
use App\Livewire\Admin\Yudisium\Show;
use App\Models\JenisKeluar;
use App\Models\KelompokKelas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Yudisium;
use Livewire\Livewire;

it('renders index and show pages', function () {
    $admin = adminUser();
    $yudisium = Yudisium::factory()->create();

    $this->actingAs($admin)->get(route('admin.akademik.yudisium'))->assertOk()->assertSee($yudisium->mahasiswa->nama);
    $this->actingAs($admin)->get(route('admin.akademik.yudisium.show', $yudisium->id))->assertOk()->assertSee($yudisium->mahasiswa->nama);
    $this->actingAs($admin)->get(route('admin.akademik.yudisium.create'))->assertOk()->assertSee('Tambah Yudisium');
});

it('streams a pdf and an excel export', function () {
    $admin = adminUser();
    Yudisium::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('exportExcel')
        ->assertFileDownloaded(null, null, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('exportPdf')
        ->assertFileDownloaded(null, null, 'application/pdf');
});

// Regression: layouts.web me-render @section('page_actions') di luar root <div> komponen, jadi
// tombol wire:click yang diletakkan di sana tidak pernah terikat Livewire dan diam saja saat diklik.
it('keeps the export buttons inside the livewire root so wire:click stays bound', function () {
    $admin = adminUser();
    Yudisium::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.akademik.yudisium'))->getContent();

    $rootStart = strpos($html, 'wire:id=');
    expect($rootStart)->not->toBeFalse();

    foreach (['exportPdf', 'exportExcel'] as $action) {
        expect(strpos($html, 'wire:click="'.$action.'"'))->toBeGreaterThan($rootStart);
    }
});

it('filters index by search and prodi', function () {
    $admin = adminUser();
    $prodiMatch = Prodi::factory()->create();
    $mahasiswaMatch = Mahasiswa::factory()->create(['id_prodi' => $prodiMatch->id, 'nama' => 'Budi Santoso Kusuma']);
    $match = Yudisium::factory()->create(['id_mahasiswa' => $mahasiswaMatch->id]);
    Yudisium::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'Budi Santoso Kusuma')
        ->assertSee($match->mahasiswa->nama);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', '')
        ->set('filterProdi', (string) $prodiMatch->id)
        ->assertSee($match->mahasiswa->nama);
});

it('creates a yudisium and prevents duplicate jenis keluar for the same mahasiswa', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $jenisKeluar = JenisKeluar::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id)
        ->set('id_jenis_keluar', $jenisKeluar->id)
        ->set('ipk', '3.75')
        ->call('save')
        ->assertRedirect(route('admin.akademik.yudisium'));

    $yudisium = Yudisium::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect((float) $yudisium->ipk)->toBe(3.75);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id)
        ->set('id_jenis_keluar', $jenisKeluar->id)
        ->call('save')
        ->assertHasErrors('id_jenis_keluar');

    expect(Yudisium::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(1);
});

it('resets the form when saving and creating a new one', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $jenisKeluar = JenisKeluar::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id)
        ->set('id_jenis_keluar', $jenisKeluar->id)
        ->call('save', true)
        ->assertSet('selectedMahasiswaId', null)
        ->assertSet('id_jenis_keluar', null)
        ->assertNoRedirect();

    expect(Yudisium::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(1);
});

it('enforces prodi scope on the yudisium detail page', function () {
    $admin = adminUser('admin_akademik');
    $allowedProdi = Prodi::factory()->create();
    scopeAdminToProdi($admin, $allowedProdi->id);

    $luarScope = Mahasiswa::factory()->create(['id_prodi' => Prodi::factory()->create()->id]);
    $yudisium = Yudisium::factory()->create(['id_mahasiswa' => $luarScope->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $yudisium->id])
        ->assertStatus(403);
});

it('redirects unauthenticated users to the login page', function () {
    $yudisium = Yudisium::factory()->create();

    $this->get(route('admin.akademik.yudisium'))->assertRedirect(route('login'));
    $this->get(route('admin.akademik.yudisium.show', $yudisium->id))->assertRedirect(route('login'));
});

it('shows the mahasiswa kelompok kelas on the show page and form, read from kelompok_kelas', function () {
    // Regresi: kedua halaman ini dulu membaca relasi grup_mahasiswa, yang tabelnya sudah tidak
    // dipakai (kosong), jadi kolom kelompok kelas selalu tampil "—".
    $admin = adminUser();
    $kelompok = KelompokKelas::factory()->create(['nama' => 'Kelas Reguler Sore B']);
    $mahasiswa = Mahasiswa::factory()->create(['id_kelompok_kelas' => $kelompok->id]);
    $yudisium = Yudisium::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    $this->actingAs($admin)->get(route('admin.akademik.yudisium.show', $yudisium->id))
        ->assertOk()
        ->assertSee('Kelompok Kelas')
        ->assertSee('Kelas Reguler Sore B');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id)
        ->assertSee('Kelas Reguler Sore B');
});
