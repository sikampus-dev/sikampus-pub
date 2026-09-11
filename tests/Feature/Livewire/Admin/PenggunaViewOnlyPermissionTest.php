<?php

use App\Livewire\Admin\Pengguna\Index;
use App\Livewire\Admin\Pengguna\Show;
use App\Models\User;
use Livewire\Livewire;

/**
 * 'view pengguna' adalah satu-satunya bagian dari modul Pengguna yang dipecah dari 'manage
 * pengguna' — sengaja dibatasi ke index/show saja (bukan create/update/delete/assign role/assign
 * permission, yang semuanya tetap 'manage pengguna' murni) karena melihat daftar/detail pengguna
 * tidak membuka jalur privilege-escalation apa pun (lihat catatan di config/panel_access.php).
 */
beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a staff member with only view pengguna see the list and detail page but not create/edit', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo('view pengguna');
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $this->actingAs($admin)->get(route('admin.pengguna.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.pengguna.show', $target->id))->assertOk();

    $this->actingAs($admin)->get(route('admin.pengguna.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.pengguna.edit', $target->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus pengguna buttons from a view-only staff member', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo('view pengguna');
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $this->actingAs($admin)->get(route('admin.pengguna.index'))
        ->assertOk()
        ->assertDontSee('Tambah Pengguna');

    $this->actingAs($admin)->get(route('admin.pengguna.show', $target->id))
        ->assertOk()
        ->assertDontSee('Hapus Pengguna')
        ->assertDontSee(route('admin.pengguna.edit', $target->id));
});

it('blocks a view-only staff member from deleting a user via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo('view pengguna');
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $target->id])
        ->call('confirmDeleteUser')
        ->assertStatus(403);

    expect(User::find($target->id))->not->toBeNull();
});

it('blocks a view-only staff member from restoring or permanently deleting a user via the livewire methods directly', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo('view pengguna');
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $target->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('restore', $target->id)
        ->assertStatus(403);

    expect(User::withTrashed()->find($target->id)->trashed())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $target->id)
        ->assertStatus(403);
});

it('lets a staff member restore and permanently delete a user once granted manage pengguna', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['view pengguna', 'manage pengguna']);
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $target->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('restore', $target->id);

    expect(User::find($target->id))->not->toBeNull();

    $target->refresh()->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $target->id)
        ->call('forceDeleteUser');

    expect(User::withTrashed()->find($target->id))->toBeNull();
});

it('denies access entirely to a staff member with no pengguna permission at all', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.pengguna.index'))->assertStatus(403);
});

it('marks pengguna as a view_plus_manage resource in the permission tab', function () {
    $admin = adminUser();
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $target->id])
        ->call('setTab', 'permission');

    $form = $component->get('permissionForm');
    $index = collect($form)->search(fn ($row) => $row['resource'] === 'pengguna');

    expect($form[$index]['mode'])->toBe('view_plus_manage');
    expect($form[$index]['hasWrite'])->toBeTrue();
    expect($form[$index]['hasDelete'])->toBeFalse();
});

it('assigns only view pengguna when read is checked without write', function () {
    $admin = adminUser();
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $target->id])
        ->call('setTab', 'permission');

    $index = collect($component->get('permissionForm'))->search(fn ($row) => $row['resource'] === 'pengguna');

    $component->set("permissionForm.{$index}.read", true)
        ->call('savePermissions');

    $target = $target->fresh();
    expect($target->can('view pengguna'))->toBeTrue();
    expect($target->can('manage pengguna'))->toBeFalse();
});

it('assigns both manage and view pengguna when write is checked, so full-access staff keep visibility', function () {
    $admin = adminUser();
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $target->id])
        ->call('setTab', 'permission');

    $index = collect($component->get('permissionForm'))->search(fn ($row) => $row['resource'] === 'pengguna');

    $component->set("permissionForm.{$index}.write", true)
        ->call('savePermissions');

    $target = $target->fresh();
    expect($target->can('manage pengguna'))->toBeTrue();
    expect($target->can('view pengguna'))->toBeTrue();
});

it('self-heals a legacy manage-only grant into view+manage once the write checkbox is re-saved', function () {
    $admin = adminUser();
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $target->givePermissionTo('manage pengguna');

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $target->id])
        ->call('setTab', 'permission');

    $form = $component->get('permissionForm');
    $index = collect($form)->search(fn ($row) => $row['resource'] === 'pengguna');

    // 'manage pengguna' tidak lagi mengisi kolom Read (yang sekarang murni 'view pengguna') —
    // hanya kolom Write yang ter-load true.
    expect($form[$index]['read'])->toBeFalse();
    expect($form[$index]['write'])->toBeTrue();

    $component->call('savePermissions');

    $target = $target->fresh();
    expect($target->can('manage pengguna'))->toBeTrue();
    expect($target->can('view pengguna'))->toBeTrue();
});
