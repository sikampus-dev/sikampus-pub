<?php

use App\Models\User;

it('shows the login form to a guest', function () {
    $this->get(route('login'))->assertOk();
});

it('logs an admin in by email and redirects to the admin panel', function () {
    $admin = adminUser('admin_akademik');

    $this->post(route('login'), [
        'login' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
});

it('logs a dosen in by username and redirects to the dosen dashboard', function () {
    $dosen = User::factory()->create(['role' => 'dosen', 'username' => 'dosen01']);

    $this->post(route('login'), [
        'login' => 'dosen01',
        'password' => 'password',
    ])->assertRedirect(route('dosen.dashboard'));

    $this->assertAuthenticatedAs($dosen);
});

it('logs a mahasiswa in by username and redirects to the mahasiswa dashboard', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'username' => '2024001']);

    $this->post(route('login'), [
        'login' => '2024001',
        'password' => 'password',
    ])->assertRedirect(route('mahasiswa.dashboard'));

    $this->assertAuthenticatedAs($mahasiswa);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['role' => 'mahasiswa']);

    $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('rejects a mahasiswa whose email is not verified yet', function () {
    $mahasiswa = User::factory()->unverified()->create(['role' => 'mahasiswa']);

    $this->post(route('login'), [
        'login' => $mahasiswa->email,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('rejects a deactivated admin even with correct credentials', function () {
    $admin = adminUser('admin_akademik');
    $admin->update(['status' => 'inactive']);

    $this->post(route('login'), [
        'login' => $admin->email,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('rejects a deactivated mahasiswa even with correct credentials', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'username' => '2024099', 'status' => 'inactive']);

    $this->post(route('login'), [
        'login' => '2024099',
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('allows a user with status active to log in as before', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'username' => '2024098', 'status' => 'active']);

    $this->post(route('login'), [
        'login' => '2024098',
        'password' => 'password',
    ])->assertRedirect(route('mahasiswa.dashboard'));

    $this->assertAuthenticatedAs($mahasiswa);
});

it('rejects a user with no matching panel', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('redirects an already-authenticated admin away from the login page', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('login'))
        ->assertRedirect(route('admin.dashboard'));
});

it('logs the user out via the generic logout route', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
