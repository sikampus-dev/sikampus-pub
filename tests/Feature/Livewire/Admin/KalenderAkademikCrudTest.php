<?php

use App\Livewire\Admin\KalenderAkademik\Form;
use App\Livewire\Admin\KalenderAkademik\Index;
use App\Models\KalenderAkademik;
use App\Models\Semester;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    KalenderAkademik::factory()->create(['nama' => 'Pengisian KRS Ganjil 2026/2027']);

    $this->actingAs($admin)->get(route('admin.akademik.kalender-akademik'))->assertOk()->assertSee('Pengisian KRS Ganjil 2026/2027');
    $this->actingAs($admin)->get(route('admin.akademik.kalender-akademik.create'))->assertOk()->assertSee('Tambah Event Kalender Akademik');
});

it('creates, updates, and deletes a kalender akademik event', function () {
    $admin = adminUser();
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Pengisian KRS Ganjil')
        ->set('kategori', 'krs')
        ->set('id_semester', (string) $semester->id)
        ->set('tanggal_mulai', '2026-09-01T08:00')
        ->set('tanggal_selesai', '2026-09-07T23:59')
        ->call('save')
        ->assertRedirect(route('admin.akademik.kalender-akademik'));

    $event = KalenderAkademik::where('nama', 'Pengisian KRS Ganjil')->firstOrFail();
    expect($event->kategori)->toBe('krs');
    expect($event->id_semester)->toBe($semester->id);
    expect($event->created_by)->toBe((string) $admin->id);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $event->id])
        ->assertSet('nama', 'Pengisian KRS Ganjil')
        ->assertSet('id_semester', (string) $semester->id)
        ->set('nama', 'Pengisian KRS Ganjil (Diubah)')
        ->call('save');

    expect($event->fresh()->nama)->toBe('Pengisian KRS Ganjil (Diubah)');
    expect($event->fresh()->updated_by)->toBe((string) $admin->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $event->id)
        ->call('delete');

    expect(KalenderAkademik::find($event->id))->toBeNull();
});

it('allows a global event with no semester attached', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Libur Nasional')
        ->set('kategori', 'libur')
        ->set('id_semester', '')
        ->set('tanggal_mulai', '2026-12-25T00:00')
        ->set('tanggal_selesai', '2026-12-25T23:59')
        ->call('save')
        ->assertRedirect(route('admin.akademik.kalender-akademik'));

    $event = KalenderAkademik::where('nama', 'Libur Nasional')->firstOrFail();
    expect($event->id_semester)->toBeNull();
});

it('rejects an invalid kategori, a missing nama, and a tanggal_selesai before tanggal_mulai', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Event Salah')
        ->set('kategori', 'bukan_kategori_valid')
        ->set('tanggal_mulai', '2026-09-01T08:00')
        ->set('tanggal_selesai', '2026-09-07T23:59')
        ->call('save')
        ->assertHasErrors('kategori');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kategori', 'krs')
        ->set('tanggal_mulai', '2026-09-01T08:00')
        ->set('tanggal_selesai', '2026-09-07T23:59')
        ->call('save')
        ->assertHasErrors('nama');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Event Salah Tanggal')
        ->set('kategori', 'krs')
        ->set('tanggal_mulai', '2026-09-07T08:00')
        ->set('tanggal_selesai', '2026-09-01T08:00')
        ->call('save')
        ->assertHasErrors('tanggal_selesai');
});

it('filters kalender akademik by search, kategori, semester, and status', function () {
    $admin = adminUser();
    $semesterA = Semester::factory()->create(['kode' => '20261']);
    $semesterB = Semester::factory()->create(['kode' => '20262']);

    KalenderAkademik::factory()->create([
        'nama' => 'Pengisian KRS Ganjil', 'kategori' => 'krs', 'id_semester' => $semesterA->id,
    ]);
    KalenderAkademik::factory()->create([
        'nama' => 'Pengisian Nilai Genap', 'kategori' => 'nilai', 'id_semester' => $semesterB->id,
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'KRS')
        ->assertSee('Pengisian KRS Ganjil')
        ->assertDontSee('Pengisian Nilai Genap');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterKategori', 'nilai')
        ->assertSee('Pengisian Nilai Genap')
        ->assertDontSee('Pengisian KRS Ganjil');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterSemester', (string) $semesterA->id)
        ->assertSee('Pengisian KRS Ganjil')
        ->assertDontSee('Pengisian Nilai Genap');
});

it('carries the forwarded state through the edit form Batal link and the save redirect', function () {
    $admin = adminUser();
    $event = KalenderAkademik::factory()->create();

    $expectedBackUrl = route('admin.akademik.kalender-akademik').'?page=2&search=krs';

    $this->actingAs($admin)
        ->get(route('admin.akademik.kalender-akademik.edit', $event->id).'?page=2&search=krs&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');

    Livewire::withQueryParams(['page' => '2', 'search' => 'krs'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $event->id])
        ->set('nama', 'Event Update')
        ->call('save')
        ->assertRedirect($expectedBackUrl);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.akademik.kalender-akademik'))->assertRedirect(route('login'));
});
