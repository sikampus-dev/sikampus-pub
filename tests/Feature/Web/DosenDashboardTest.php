<?php

use App\Models\User;

it('redirects a guest to the login page', function () {
    $this->get(route('dosen.dashboard'))->assertRedirect(route('login'));
});

it('shows the dashboard to a dosen', function () {
    $dosen = dosenUser();

    $this->actingAs($dosen)
        ->get(route('dosen.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)
        ->get(route('dosen.dashboard'))
        ->assertForbidden();
});

it('does not show a standalone Kehadiran sidebar item — it lives under the Jadwal Mengajar detail tab instead', function () {
    $dosen = dosenUser();

    $html = $this->actingAs($dosen)->get(route('dosen.dashboard'))->getContent();

    expect($html)->not->toContain(route('dosen.kehadiran'));
});

it('shows gelar depan and gelar belakang alongside the dosen name in the sidebar and the dashboard subtitle', function () {
    $dosen = dosenUser([], ['nama' => 'Budi Santoso', 'gelar_depan' => 'Dr.', 'gelar_belakang' => 'M.Kom.']);

    $html = $this->actingAs($dosen)->get(route('dosen.dashboard'))->getContent();

    expect($html)->toContain('Dr. Budi Santoso, M.Kom.');
});

it('falls back to the plain name when the dosen has no gelar depan or gelar belakang', function () {
    $dosen = dosenUser([], ['nama' => 'Citra Lestari', 'gelar_depan' => null, 'gelar_belakang' => null]);

    $html = $this->actingAs($dosen)->get(route('dosen.dashboard'))->getContent();

    expect($html)->toContain('Citra Lestari')
        ->not->toContain('Citra Lestari,');
});
