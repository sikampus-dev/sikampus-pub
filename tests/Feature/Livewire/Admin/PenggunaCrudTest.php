<?php

use App\Livewire\Admin\Pengguna\Form;
use App\Livewire\Admin\Pengguna\Index;
use App\Livewire\Admin\Pengguna\Show;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    User::factory()->create(['name' => 'Budi Santoso', 'role' => 'admin', 'status' => 'active']);

    $this->actingAs($admin)->get(route('admin.pengguna.index'))->assertOk()->assertSee('Budi Santoso');
    $this->actingAs($admin)->get(route('admin.pengguna.create'))->assertOk()->assertSee('Tambah Pengguna');
});

it('creates and updates a pengguna, then shows the detail page', function () {
    $admin = adminUser();
    $superadminRole = Role::where('name', 'Superadmin')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Citra Dewi')
        ->set('email', 'citra@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
        ->set('spatieRoleId', $superadminRole->id)
        ->call('save')
        ->assertRedirect();

    $pengguna = User::where('email', 'citra@example.com')->firstOrFail();
    expect($pengguna->role)->toBe('admin');
    expect($pengguna->status)->toBe('active');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pengguna->id])
        ->assertSet('name', 'Citra Dewi')
        ->set('name', 'Citra Dewi Lestari')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.show', $pengguna->id));

    expect($pengguna->fresh()->name)->toBe('Citra Dewi Lestari');

    $this->actingAs($admin)->get(route('admin.pengguna.show', $pengguna->id))
        ->assertOk()
        ->assertSee('Citra Dewi Lestari')
        ->assertSee('citra@example.com');
});

it('assigns a role and scope to a pengguna from the show page', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $akademik = Role::firstOrCreate(['name' => 'Akademik', 'guard_name' => 'web'], ['code' => 'akademik']);
    $keuangan = Role::firstOrCreate(['name' => 'Keuangan', 'guard_name' => 'web'], ['code' => 'keuangan']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('openRoleForm')
        ->set('selectedRoleIds', [$akademik->id, $keuangan->id])
        ->call('saveRoleScope');

    expect($pengguna->fresh()->hasRole('Akademik'))->toBeTrue();
    expect($pengguna->fresh()->hasRole('Keuangan'))->toBeTrue();

    // Menghapus satu dari dua role tersisa boleh, tapi menghapus satu-satunya role harus ditolak.
    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('deleteRole', 'akademik');

    expect($pengguna->fresh()->hasRole('Akademik'))->toBeFalse();
    expect($pengguna->fresh()->hasRole('Keuangan'))->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('deleteRole', 'keuangan');

    expect($pengguna->fresh()->hasRole('Keuangan'))->toBeTrue();
});

it('automatically assigns the chosen spatie role — and its permissions — when creating an admin account', function () {
    $admin = adminUser();
    $keuanganRole = Role::where('name', 'Keuangan')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Dedi Keuangan')
        ->set('email', 'dedi.keuangan@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
        ->set('spatieRoleId', $keuanganRole->id)
        ->call('save')
        ->assertRedirect();

    $pengguna = User::where('email', 'dedi.keuangan@example.com')->firstOrFail();

    expect($pengguna->hasRole('Keuangan'))->toBeTrue();
    expect($pengguna->hasRole('Akademik'))->toBeFalse();
    // Permission diwarisi dari role_has_permissions (diseed PermissionSeeder), bukan disalin manual.
    expect($pengguna->can('manage tagihan'))->toBeTrue();
    expect($pengguna->can('manage mata kuliah'))->toBeFalse();
});

it('requires a spatie role when creating an admin-type account', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Tanpa Role')
        ->set('email', 'tanpa.role@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
        ->call('save')
        ->assertHasErrors(['spatieRoleId' => 'required']);

    expect(User::where('email', 'tanpa.role@example.com')->exists())->toBeFalse();
});

it('deletes a pengguna from the show page', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('confirmDeleteUser')
        ->call('deleteUser')
        ->assertRedirect(route('admin.pengguna.index'));

    expect(User::find($pengguna->id))->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.pengguna.index'))->assertRedirect(route('login'));
});

it('hides soft-deleted pengguna by default and shows them with a restore/hapus-permanen action when toggled on', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['name' => 'Pengguna Terhapus', 'role' => 'admin', 'status' => 'active']);
    $pengguna->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertDontSee('Pengguna Terhapus');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->assertSee('Pengguna Terhapus')
        ->assertSee('Dihapus');
});

it('restores a soft-deleted pengguna instead of trying to recreate it', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['email' => 'restore-me@example.com']);
    $pengguna->delete();
    expect(User::find($pengguna->id))->toBeNull();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('restore', $pengguna->id);

    expect(User::find($pengguna->id))->not->toBeNull();
    expect(User::find($pengguna->id)->deleted_at)->toBeNull();
});

it('permanently deletes a soft-deleted pengguna that has no related records', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create();
    $pengguna->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $pengguna->id)
        ->call('forceDeleteUser');

    expect(User::withTrashed()->find($pengguna->id))->toBeNull();
});

// user_roles adalah tabel legacy (lihat catatan di Index.php) tapi tetap constrained('users')
// ->restrictOnDelete() di DB — restrict itu berlaku walau baris perujuknya sendiri sudah
// soft-deleted, jadi harus ditolak lebih dulu dengan pesan jelas kalau ternyata terisi.
it('refuses to permanently delete a pengguna still referenced by legacy user_roles', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Akademik', 'guard_name' => 'web'], ['code' => 'akademik']);
    $userRole = UserRole::create(['id_user' => $pengguna->id, 'id_role' => $role->id]);
    $userRole->delete();

    $pengguna->delete();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showTrashed', true)
        ->call('confirmForceDelete', $pengguna->id)
        ->call('forceDeleteUser');

    expect(User::withTrashed()->find($pengguna->id)->trashed())->toBeTrue();
});
