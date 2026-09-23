<?php

use App\Livewire\Mahasiswa\KalenderAkademik\Index;
use App\Models\KalenderAkademik;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

function krsMahasiswaUserForKalender(): User
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    Mahasiswa::factory()->create(['id_user' => $user->id]);

    return $user;
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.kalender-akademik'))->assertRedirect(route('login'));
});

it('shows global events and events for the active semester, not other semesters', function () {
    $user = krsMahasiswaUserForKalender();
    $activeSemester = Semester::factory()->active()->create();
    $semesterLain = Semester::factory()->create();

    KalenderAkademik::factory()->create(['nama' => 'Libur Nasional', 'kategori' => 'libur', 'id_semester' => null]);
    KalenderAkademik::factory()->create(['nama' => 'Pengisian KRS Aktif', 'kategori' => 'krs', 'id_semester' => $activeSemester->id]);
    KalenderAkademik::factory()->create(['nama' => 'Event Semester Lain', 'kategori' => 'krs', 'id_semester' => $semesterLain->id]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Libur Nasional')
        ->assertSee('Pengisian KRS Aktif')
        ->assertDontSee('Event Semester Lain');
});

it('orders events as aktif first, then akan datang, then selesai', function () {
    $user = krsMahasiswaUserForKalender();

    KalenderAkademik::factory()->create([
        'nama' => 'Event Sudah Lewat', 'kategori' => 'libur',
        'tanggal_mulai' => now()->subDays(10), 'tanggal_selesai' => now()->subDays(5),
    ]);
    KalenderAkademik::factory()->create([
        'nama' => 'Event Sedang Berlangsung', 'kategori' => 'libur',
        'tanggal_mulai' => now()->subDay(), 'tanggal_selesai' => now()->addDay(),
    ]);
    KalenderAkademik::factory()->create([
        'nama' => 'Event Mendatang', 'kategori' => 'libur',
        'tanggal_mulai' => now()->addDays(5), 'tanggal_selesai' => now()->addDays(10),
    ]);

    $html = $this->actingAs($user)->get(route('mahasiswa.kalender-akademik'))->getContent();

    $posAktif = strpos($html, 'Event Sedang Berlangsung');
    $posAkanDatang = strpos($html, 'Event Mendatang');
    $posSelesai = strpos($html, 'Event Sudah Lewat');

    expect($posAktif)->not->toBeFalse();
    expect($posAkanDatang)->toBeGreaterThan($posAktif);
    expect($posSelesai)->toBeGreaterThan($posAkanDatang);
});

it('shows an empty state when there are no relevant events', function () {
    $user = krsMahasiswaUserForKalender();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Belum ada kalender akademik');
});
