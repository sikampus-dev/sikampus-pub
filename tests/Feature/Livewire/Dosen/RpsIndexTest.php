<?php

use App\Livewire\Dosen\Rps\Index;
use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.rps'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.rps'))->assertForbidden();
});

it('only lists kelas where the dosen is pic, not kelas from the non-pic team', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $kelasPic = Kelas::factory()->create();
    $kelasBukanPic = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasPic->id, 'is_pic' => true]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasBukanPic->id, 'is_pic' => false]);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasPic->id);
});

it('filters by the selected semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterA = Semester::factory()->active()->create();
    $semesterB = Semester::factory()->create();
    $kelasA = Kelas::factory()->create(['id_semester' => $semesterA->id]);
    $kelasB = Kelas::factory()->create(['id_semester' => $semesterB->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasA->id, 'is_pic' => true]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasB->id, 'is_pic' => true]);

    $component = Livewire::actingAs($dosenUser)->test(Index::class);
    expect($component->instance()->rows())->toHaveCount(1);

    $component->set('filterSemester', '');
    expect($component->instance()->rows())->toHaveCount(2);
});

it('links each row to the pdf export', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    $this->actingAs($dosenUser)
        ->get(route('dosen.rps'))
        ->assertOk()
        ->assertSee(route('dosen.rps.pdf', $kelas->id), false);
});
