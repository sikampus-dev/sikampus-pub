<?php

use App\Livewire\Dosen\KalenderAkademik\Index;
use App\Models\KalenderAkademik;
use App\Models\Semester;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.kalender-akademik'))->assertRedirect(route('login'));
});

it('shows global events and events for the active semester, not other semesters', function () {
    $dosenUser = dosenUser();
    $activeSemester = Semester::factory()->active()->create();
    $semesterLain = Semester::factory()->create();

    KalenderAkademik::factory()->create(['nama' => 'Libur Nasional', 'kategori' => 'libur', 'id_semester' => null]);
    KalenderAkademik::factory()->create(['nama' => 'Pengisian Nilai Aktif', 'kategori' => 'nilai', 'id_semester' => $activeSemester->id]);
    KalenderAkademik::factory()->create(['nama' => 'Event Semester Lain', 'kategori' => 'nilai', 'id_semester' => $semesterLain->id]);

    Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->assertSee('Libur Nasional')
        ->assertSee('Pengisian Nilai Aktif')
        ->assertDontSee('Event Semester Lain');
});

it('shows an empty state when there are no relevant events', function () {
    $dosenUser = dosenUser();

    Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->assertSee('Belum ada kalender akademik');
});
