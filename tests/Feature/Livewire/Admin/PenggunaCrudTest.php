<?php

use App\Livewire\Admin\Pengguna\Form;
use App\Livewire\Admin\Pengguna\Index;
use App\Livewire\Admin\Pengguna\Show;
use App\Models\Mahasiswa;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;
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

it('allows removing the last spatie role from a dosen account, unlike an admin account', function () {
    $superadmin = adminUser();
    $dosenUser = User::factory()->create(['role' => 'dosen']);
    $akademik = Role::firstOrCreate(['name' => 'Akademik', 'guard_name' => 'web'], ['code' => 'akademik']);
    $dosenUser->assignRole($akademik);

    Livewire::actingAs($superadmin)
        ->test(Show::class, ['id' => $dosenUser->id])
        ->call('deleteRole', 'akademik');

    expect($dosenUser->fresh()->hasRole('Akademik'))->toBeFalse();
    expect($dosenUser->fresh()->roles)->toHaveCount(0);
});

it('rejects assigning a spatie role to a dosen account through the show page role form', function () {
    $superadmin = adminUser();
    $dosenUser = User::factory()->create(['role' => 'dosen']);
    $akademik = Role::firstOrCreate(['name' => 'Akademik', 'guard_name' => 'web'], ['code' => 'akademik']);

    Livewire::actingAs($superadmin)
        ->test(Show::class, ['id' => $dosenUser->id])
        ->call('openRoleForm')
        ->set('selectedRoleIds', [$akademik->id])
        ->call('saveRoleScope')
        ->assertHasErrors(['selectedRoleIds']);

    expect($dosenUser->fresh()->hasRole('Akademik'))->toBeFalse();
});

it('rejects assigning a spatie role to a mahasiswa account through the show page role form', function () {
    $superadmin = adminUser();
    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $akademik = Role::firstOrCreate(['name' => 'Akademik', 'guard_name' => 'web'], ['code' => 'akademik']);

    Livewire::actingAs($superadmin)
        ->test(Show::class, ['id' => $mahasiswaUser->id])
        ->call('openRoleForm')
        ->set('selectedRoleIds', [$akademik->id])
        ->call('saveRoleScope')
        ->assertHasErrors(['selectedRoleIds']);

    expect($mahasiswaUser->fresh()->hasRole('Akademik'))->toBeFalse();
});

it('finds a mahasiswa without an existing account in the picker search', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Uji Coba Pencarian', 'nim' => '2099001']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('role', 'mahasiswa')
        ->set('mahasiswaSearch', 'Uji Coba Pencarian')
        ->assertSee('2099001')
        ->assertSee('Uji Coba Pencarian');
});

it('excludes a mahasiswa who already has a user account from the picker search', function () {
    $admin = adminUser();
    $existingUser = User::factory()->create(['role' => 'mahasiswa']);
    Mahasiswa::factory()->create([
        'nama' => 'Sudah Punya Akun',
        'nim' => '2099002',
        'id_user' => $existingUser->id,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('role', 'mahasiswa')
        ->set('mahasiswaSearch', 'Sudah Punya Akun')
        ->assertDontSee('2099002')
        ->assertSee('Tidak ada hasil');
});

it('shows a hint to narrow the search when mahasiswa results exceed the picker limit', function () {
    $admin = adminUser();
    foreach (['A', 'B', 'C', 'D', 'E'] as $suffix) {
        Mahasiswa::factory()->create(['nama' => "Banyak Hasil {$suffix}"]);
    }

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('mahasiswaSearchLimit', 3)
        ->set('role', 'mahasiswa')
        ->set('mahasiswaSearch', 'Banyak Hasil')
        ->assertSee('Menampilkan 3 hasil teratas');
});

it('does not show the narrow-search hint when mahasiswa results are within the picker limit', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Hasil Tunggal Saja']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('role', 'mahasiswa')
        ->set('mahasiswaSearch', 'Hasil Tunggal Saja')
        ->assertDontSee('hasil teratas');
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

it('automatically verifies the email when a new pengguna is created with status active', function () {
    $admin = adminUser();
    $superadminRole = Role::where('name', 'Superadmin')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Aktif Baru')
        ->set('email', 'aktif.baru@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
        ->set('spatieRoleId', $superadminRole->id)
        ->set('status', 'active')
        ->call('save')
        ->assertRedirect();

    $pengguna = User::where('email', 'aktif.baru@example.com')->firstOrFail();
    expect($pengguna->email_verified_at)->not->toBeNull();
});

it('does not verify the email when a new pengguna is created with status inactive', function () {
    $admin = adminUser();
    $superadminRole = Role::where('name', 'Superadmin')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Tidak Aktif Baru')
        ->set('email', 'tidak.aktif.baru@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
        ->set('spatieRoleId', $superadminRole->id)
        ->set('status', 'inactive')
        ->call('save')
        ->assertRedirect();

    $pengguna = User::where('email', 'tidak.aktif.baru@example.com')->firstOrFail();
    expect($pengguna->email_verified_at)->toBeNull();
});

it('automatically verifies the email when an existing pengguna is edited to status active', function () {
    $admin = adminUser();
    $pengguna = User::factory()->unverified()->create(['role' => 'admin', 'status' => 'inactive']);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pengguna->id])
        ->set('status', 'active')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.show', $pengguna->id));

    expect($pengguna->fresh()->email_verified_at)->not->toBeNull();
});

it('does not overwrite an already-verified email timestamp when re-saving an active pengguna', function () {
    $admin = adminUser();
    $verifiedAt = now()->subMonth();
    $pengguna = User::factory()->create([
        'role' => 'admin',
        'status' => 'active',
        'email_verified_at' => $verifiedAt,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pengguna->id])
        ->set('status', 'active')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.show', $pengguna->id));

    // Dibandingkan sampai level detik (bukan equalTo) karena kolom datetime di MySQL memangkas
    // microseconds saat disimpan, sedangkan $verifiedAt di memori masih menyimpannya.
    expect($pengguna->fresh()->email_verified_at->format('Y-m-d H:i:s'))->toBe($verifiedAt->format('Y-m-d H:i:s'));
});

it('keeps the email verified when an active pengguna is deactivated', function () {
    $admin = adminUser();
    $verifiedAt = now()->subMonth();
    $pengguna = User::factory()->create([
        'role' => 'admin',
        'status' => 'active',
        'email_verified_at' => $verifiedAt,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pengguna->id])
        ->set('status', 'inactive')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.show', $pengguna->id));

    expect($pengguna->fresh()->email_verified_at)->not->toBeNull();
    expect($pengguna->fresh()->status)->toBe('inactive');
});

it('revokes existing sanctum tokens and sessions when a pengguna is deactivated', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'dosen', 'status' => 'active']);
    $pengguna->createToken('auth_token');
    DB::table('sessions')->insert([
        'id' => 'stale-session-id',
        'user_id' => $pengguna->id,
        'payload' => 'irrelevant',
        'last_activity' => now()->timestamp,
    ]);

    expect($pengguna->tokens()->count())->toBe(1);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pengguna->id])
        ->set('status', 'inactive')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.show', $pengguna->id));

    expect($pengguna->tokens()->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $pengguna->id)->exists())->toBeFalse();
});

it('does not touch tokens or sessions when a pengguna stays active', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'dosen', 'status' => 'active']);
    $pengguna->createToken('auth_token');
    DB::table('sessions')->insert([
        'id' => 'still-valid-session-id',
        'user_id' => $pengguna->id,
        'payload' => 'irrelevant',
        'last_activity' => now()->timestamp,
    ]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pengguna->id])
        ->set('name', $pengguna->name)
        ->set('status', 'active')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.show', $pengguna->id));

    expect($pengguna->tokens()->count())->toBe(1);
    expect(DB::table('sessions')->where('user_id', $pengguna->id)->exists())->toBeTrue();
});

it('shows the email verification badge on the index table', function () {
    $admin = adminUser();
    User::factory()->create(['name' => 'Sudah Verifikasi', 'role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
    User::factory()->unverified()->create(['name' => 'Belum Verifikasi', 'role' => 'admin', 'status' => 'inactive']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Terverifikasi')
        ->assertSee('Belum Terverifikasi');
});

it('includes the current page in the index "Lihat Detail"/"Ubah" links so returning lands on the same pagination page', function () {
    $admin = adminUser();
    User::factory()->count(15)->create(['role' => 'admin', 'status' => 'active']);

    $response = $this->actingAs($admin)->get(route('admin.pengguna.index', ['page' => 2]));

    $secondPageUser = User::orderBy('name')->skip(10)->first();

    $response->assertOk()
        ->assertSee(route('admin.pengguna.show', $secondPageUser->id).'?page=2', false)
        ->assertSee(route('admin.pengguna.edit', $secondPageUser->id).'?page=2', false);
});

it('does not append a page query string when already on the first page', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $this->actingAs($admin)->get(route('admin.pengguna.index'))
        ->assertOk()
        ->assertSee(route('admin.pengguna.show', $pengguna->id).'"', false);
});

it('resolves the detail page "Kembali" link to include the page it was opened from', function () {
    $admin = adminUser();
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $this->actingAs($admin)->get(route('admin.pengguna.show', $target->id).'?page=3')
        ->assertOk()
        ->assertSee(route('admin.pengguna.index').'?page=3', false);
});

it('resolves the detail page "Kembali" link back to a plain index url when opened without a page query', function () {
    $admin = adminUser();
    $target = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    $this->actingAs($admin)->get(route('admin.pengguna.show', $target->id))
        ->assertOk()
        ->assertSee(route('admin.pengguna.index').'"', false);
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
