<?php

use App\Livewire\Admin\DosenWali\Index;
use App\Livewire\Admin\DosenWali\Riwayat;
use App\Livewire\Admin\DosenWali\Show;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\DosenWaliBimbingan;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use Livewire\Livewire;

it('renders index and show as full pages', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create(['nama' => 'Budi Santoso']);
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Citra Lestari']);
    DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id]);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.dosen-wali'))
        ->assertOk()
        ->assertSee('Budi Santoso');

    $this->actingAs($admin)
        ->get(route('admin.administrasi.dosen-wali.show', $dosen->id))
        ->assertOk()
        ->assertSee('Citra Lestari');
});

it('adds and deletes a mahasiswa bimbingan', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->call('save')
        ->assertHasNoErrors();

    $dosenWali = DosenWali::where('id_dosen', $dosen->id)->where('id_mahasiswa', $mahasiswa->id)->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('confirmDelete', $dosenWali->id)
        ->call('delete');

    expect(DosenWali::find($dosenWali->id))->toBeNull();
});

it('keeps the add modal open and reset after a successful save so the next mahasiswa can be added', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();
    $pertama = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Pertama']);
    $kedua = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Kedua']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('openModal')
        ->call('selectMahasiswa', $pertama->id, $pertama->nim.' - '.$pertama->nama)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', true)
        ->assertSet('selectedMahasiswaId', null)
        ->assertSet('mahasiswaSearch', '')
        ->assertSet('lastAddedLabel', $pertama->nim.' - '.$pertama->nama)
        ->assertSee('Mahasiswa Pertama')
        ->call('selectMahasiswa', $kedua->id, $kedua->nim.' - '.$kedua->nama)
        ->assertSet('lastAddedLabel', '')
        ->call('save')
        ->assertSet('showModal', true)
        ->assertSee('Mahasiswa Kedua')
        ->call('closeModal')
        ->assertSet('showModal', false)
        ->assertSet('lastAddedLabel', '');

    expect(DosenWali::where('id_dosen', $dosen->id)->count())->toBe(2);
});

it('blocks adding a mahasiswa bimbingan once the dosen kuota is reached', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create(['kuota_bimbingan_akademik' => 1]);
    $existingMahasiswa = Mahasiswa::factory()->create();
    DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $existingMahasiswa->id, 'status' => 'active']);

    $newMahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('selectMahasiswa', $newMahasiswa->id, $newMahasiswa->nim.' - '.$newMahasiswa->nama)
        ->call('save')
        ->assertHasErrors('selectedMahasiswaId');

    expect(DosenWali::where('id_dosen', $dosen->id)->where('id_mahasiswa', $newMahasiswa->id)->exists())->toBeFalse();
});

it('applies bulk kuota only to dosen without an existing kuota', function () {
    $admin = adminUser();
    $dosenTanpaKuota = Dosen::factory()->create(['kuota_bimbingan_akademik' => 0]);
    $dosenSudahAdaKuota = Dosen::factory()->create(['kuota_bimbingan_akademik' => 5]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('kuotaInput', '20')
        ->call('applyKuota');

    expect($dosenTanpaKuota->fresh()->kuota_bimbingan_akademik)->toBe(20);
    expect($dosenSudahAdaKuota->fresh()->kuota_bimbingan_akademik)->toBe(5);
});

it('admin dengan scope prodi tidak bisa menambah mahasiswa bimbingan di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $dosen = Dosen::factory()->create();
    $mahasiswaLuarScope = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('selectMahasiswa', $mahasiswaLuarScope->id, $mahasiswaLuarScope->nim.' - '.$mahasiswaLuarScope->nama)
        ->call('save')
        ->assertStatus(403);

    expect(DosenWali::where('id_mahasiswa', $mahasiswaLuarScope->id)->exists())->toBeFalse();
});

it('admin dengan scope prodi tidak bisa menghapus bimbingan mahasiswa di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $dosen = Dosen::factory()->create();
    $mahasiswaLuarScope = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $dosenWali = DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswaLuarScope->id]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('confirmDelete', $dosenWali->id)
        ->call('delete')
        ->assertStatus(403);

    expect(DosenWali::find($dosenWali->id))->not->toBeNull();
});

it('renders riwayat bimbingan filtered by semester', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();
    $dosenWali = DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id]);

    $semesterA = Semester::factory()->create();
    $semesterB = Semester::factory()->create();

    DosenWaliBimbingan::create([
        'id_dosen_wali' => $dosenWali->id,
        'id_semester' => $semesterA->id,
        'catatan_dosen' => 'Catatan semester A',
    ]);
    DosenWaliBimbingan::create([
        'id_dosen_wali' => $dosenWali->id,
        'id_semester' => $semesterB->id,
        'catatan_dosen' => 'Catatan semester B',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.dosen-wali.riwayat', ['id' => $dosen->id, 'dosenWaliId' => $dosenWali->id]))
        ->assertOk()
        ->assertSee('Catatan semester A')
        ->assertSee('Catatan semester B');

    Livewire::actingAs($admin)
        ->test(Riwayat::class, ['id' => $dosen->id, 'dosenWaliId' => $dosenWali->id])
        ->set('filterSemester', (string) $semesterA->id)
        ->assertSee('Catatan semester A')
        ->assertDontSee('Catatan semester B');
});

it('carries the current page state from index into the Lihat link', function () {
    $admin = adminUser();
    Dosen::factory()->count(15)->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('perPage', 10)
        ->call('gotoPage', 2)
        ->assertSee('page=2');
});

it('points the Kembali button on the show page to the page/search state carried in the query string', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.administrasi.dosen-wali.show', $dosen->id).'?page=2&search=budi&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.administrasi.dosen-wali').'?page=2&search=budi')
        ->assertDontSee('unexpected=1');
});

it('carries the current mhs_page state from show into the Riwayat link', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();
    Mahasiswa::factory()->count(15)->create()->each(function (Mahasiswa $m) use ($dosen) {
        DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $m->id]);
    });

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('gotoPage', 2, 'mhs_page')
        ->assertSee('mhs_page=2');
});

it('points the Kembali button on the riwayat page to the mhs_page/mhs_search state carried in the query string', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();
    $dosenWali = DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id]);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.dosen-wali.riwayat', ['id' => $dosen->id, 'dosenWaliId' => $dosenWali->id]).'?mhs_page=2&mhs_search=budi&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.administrasi.dosen-wali.show', $dosen->id).'?mhs_page=2&mhs_search=budi')
        ->assertDontSee('unexpected=1');
});

it('keeps the index toolbar and show buttons inside the livewire root so wire:click stays bound', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();

    $indexHtml = $this->actingAs($admin)->get(route('admin.administrasi.dosen-wali'))->getContent();
    $indexRootStart = strpos($indexHtml, 'wire:id=');
    expect($indexRootStart)->not->toBeFalse();
    expect(strpos($indexHtml, 'wire:click="openKuotaModal"'))->toBeGreaterThan($indexRootStart);
    expect(strpos($indexHtml, 'wire:model="importFile"'))->toBeGreaterThan($indexRootStart);

    $showHtml = $this->actingAs($admin)->get(route('admin.administrasi.dosen-wali.show', $dosen->id))->getContent();
    $showRootStart = strpos($showHtml, 'wire:id=');
    expect($showRootStart)->not->toBeFalse();
    expect(strpos($showHtml, 'wire:click="openModal"'))->toBeGreaterThan($showRootStart);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.administrasi.dosen-wali'))->assertRedirect(route('login'));
});
