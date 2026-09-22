<?php

use App\Models\User;

it('logs a user in and issues a token', function () {
    $user = User::factory()->create(['role' => 'dosen']);

    $response = $this->postJson('/api/auth/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()->assertJsonStructure(['user', 'token', 'token_type']);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['role' => 'dosen']);

    $this->postJson('/api/auth/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('login');
});

it('rejects a mahasiswa or dosen whose email is not verified yet', function () {
    $mahasiswa = User::factory()->unverified()->create(['role' => 'mahasiswa']);

    $this->postJson('/api/auth/login', [
        'login' => $mahasiswa->email,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('login');
});

it('rejects a deactivated admin even with correct credentials', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'inactive']);

    $this->postJson('/api/auth/login', [
        'login' => $admin->email,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('login');
});

it('rejects a deactivated dosen even with correct credentials', function () {
    $dosen = User::factory()->create(['role' => 'dosen', 'status' => 'inactive']);

    $this->postJson('/api/auth/login', [
        'login' => $dosen->email,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('login');
});

it('rejects a deactivated mahasiswa even with correct credentials', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa', 'status' => 'inactive']);

    $this->postJson('/api/auth/login', [
        'login' => $mahasiswa->email,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('login');
});

it('allows a user with status active to log in as before', function () {
    $dosen = User::factory()->create(['role' => 'dosen', 'status' => 'active']);

    $this->postJson('/api/auth/login', [
        'login' => $dosen->email,
        'password' => 'password',
    ])->assertOk();
});
