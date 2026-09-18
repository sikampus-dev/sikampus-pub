<?php

use App\Livewire\Admin\Mahasiswa\Form;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use Livewire\Livewire;

it('renders the edit form as a full page with an edit button on the detail page', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Nama Lama']);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.show', $mahasiswa->id))
        ->assertOk()
        ->assertSee(route('admin.administrasi.mahasiswa.edit', $mahasiswa->id));

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.edit', $mahasiswa->id))
        ->assertOk()
        ->assertSee('Nama Lama');
});

it('loads and updates an existing mahasiswa', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Nama Lama', 'nim' => '2024111222']);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $mahasiswa->id])
        ->assertSet('nama', 'Nama Lama')
        ->assertSet('nim', '2024111222')
        ->set('nama', 'Nama Baru')
        ->call('save')
        ->assertRedirect(route('admin.administrasi.mahasiswa.show', $mahasiswa->id));

    expect($mahasiswa->fresh()->nama)->toBe('Nama Baru');
});

it('requires nama', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $mahasiswa->id])
        ->set('nama', '')
        ->call('save')
        ->assertHasErrors(['nama' => 'required']);
});

it('rejects a duplicate nim', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nim' => '2024999999']);
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $mahasiswa->id])
        ->set('nim', '2024999999')
        ->call('save')
        ->assertHasErrors(['nim' => 'unique']);
});

it('admin dengan scope prodi tidak bisa membuka form edit mahasiswa di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.edit', $mahasiswaB->id))
        ->assertForbidden();
});

it('admin dengan scope prodi tidak bisa memindahkan mahasiswa ke prodi di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $mahasiswa->id])
        ->set('id_prodi', $prodiB->id)
        ->call('save')
        ->assertStatus(403);
});

it('redirects unauthenticated users to the admin login page', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $this->get(route('admin.administrasi.mahasiswa.edit', $mahasiswa->id))
        ->assertRedirect(route('login'));
});

it('saves the nama ayah and nama ibu typed into the form', function () {
    // Regresi: 'ayah'/'ibu' tidak punya aturan validasi, sehingga validate() tidak pernah
    // mengembalikannya dan nama yang diketik admin hilang diam-diam saat simpan ('wali' lolos
    // karena aturannya ada).
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $mahasiswa->id])
        ->set('ayah', 'Bapak Admin')
        ->set('ibu', 'Ibu Admin')
        ->set('wali', 'Wali Admin')
        ->call('save')
        ->assertHasNoErrors();

    $mahasiswa->refresh();
    expect($mahasiswa->ayah)->toBe('Bapak Admin');
    expect($mahasiswa->ibu)->toBe('Ibu Admin');
    expect($mahasiswa->wali)->toBe('Wali Admin');
});
