<?php

use App\Livewire\Prodi\Krs\Index;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\GrupMahasiswa;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Semester;
use Livewire\Livewire;

it('lists only krs within the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mhsA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id, 'nama' => 'Mahasiswa Prodi A']);
    $mhsB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id, 'nama' => 'Mahasiswa Prodi B']);
    $matkulA = Matkul::factory()->create(['id_prodi' => $prodiA->id, 'sks' => 3]);
    $matkulB = Matkul::factory()->create(['id_prodi' => $prodiB->id, 'sks' => 3]);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id, 'sks' => 3]);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id, 'sks' => 3]);
    $kelasA = Kelas::factory()->create(['id_prodi' => $prodiA->id, 'id_kurikulum_matkul' => $kmA->id]);
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id, 'id_kurikulum_matkul' => $kmB->id]);
    Krs::factory()->create(['id_mahasiswa' => $mhsA->id, 'id_kelas' => $kelasA->id]);
    Krs::factory()->create(['id_mahasiswa' => $mhsB->id, 'id_kelas' => $kelasB->id]);

    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Mahasiswa Prodi A')
        ->assertDontSee('Mahasiswa Prodi B');
});

it('shows aggregated sks and dosen wali for a mahasiswa row', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Siti Aminah', 'nim' => '2024001']);
    $dosen = Dosen::factory()->create(['nama' => 'Dr. Wali Amanah']);
    DosenWali::factory()->create(['id_mahasiswa' => $mhs->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    $matkulA = Matkul::factory()->create(['id_prodi' => $prodi->id, 'sks' => 3]);
    $matkulB = Matkul::factory()->create(['id_prodi' => $prodi->id, 'sks' => 2]);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id, 'sks' => 3]);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id, 'sks' => 2]);
    $kelasA = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $kmA->id]);
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $kmB->id]);
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelasA->id, 'approved_at' => now()]);
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelasB->id, 'approved_at' => null]);
    $kaprodi = kaprodiUser($prodi);

    $component = Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Siti Aminah')
        ->assertSee('2024001')
        ->assertSee('Dr. Wali Amanah');

    $row = collect($component->viewData('krsList')->items())->firstWhere('id_mahasiswa', $mhs->id);
    expect($row['sks_diajukan'])->toBe(5);
    expect($row['sks_diacc'])->toBe(3);
    expect($row['total_kelas'])->toBe(2);
});

it('filters by periode semester, angkatan, and grup mahasiswa', function () {
    $prodi = Prodi::factory()->create();
    $semesterA = Semester::factory()->create();
    $semesterB = Semester::factory()->create();
    $angkatanA = Semester::factory()->create();
    $angkatanB = Semester::factory()->create();
    $grupA = GrupMahasiswa::create(['nama' => 'Grup A', 'kode' => 'GA', 'angkatan' => 2024, 'status' => 'active']);
    $grupB = GrupMahasiswa::create(['nama' => 'Grup B', 'kode' => 'GB', 'angkatan' => 2024, 'status' => 'active']);

    $mhsA = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Mahasiswa Satu', 'id_semester_masuk' => $angkatanA->id, 'id_grup_mahasiswa' => $grupA->id]);
    $mhsB = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Mahasiswa Dua', 'id_semester_masuk' => $angkatanB->id, 'id_grup_mahasiswa' => $grupB->id]);

    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'sks' => 3]);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 3]);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 3]);
    $kelasA = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $kmA->id, 'id_semester' => $semesterA->id]);
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $kmB->id, 'id_semester' => $semesterB->id]);
    Krs::factory()->create(['id_mahasiswa' => $mhsA->id, 'id_kelas' => $kelasA->id]);
    Krs::factory()->create(['id_mahasiswa' => $mhsB->id, 'id_kelas' => $kelasB->id]);
    $kaprodi = kaprodiUser($prodi);

    $component = Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->set('filterSemester', (string) $semesterA->id)
        ->assertSee('Mahasiswa Satu')
        ->assertDontSee('Mahasiswa Dua');

    $component
        ->set('filterSemester', '')
        ->set('filterAngkatan', (string) $angkatanB->id)
        ->assertSee('Mahasiswa Dua')
        ->assertDontSee('Mahasiswa Satu');

    $component
        ->set('filterAngkatan', '')
        ->set('filterGrup', (string) $grupA->id)
        ->assertSee('Mahasiswa Satu')
        ->assertDontSee('Mahasiswa Dua');
});

it('does not default to the active semester', function () {
    $prodi = Prodi::factory()->create();
    Semester::factory()->create(['is_active' => true]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSet('filterSemester', '');
});

it('opens the detail modal grouped by semester for a mahasiswa within scope', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Budi Hartono', 'nim' => '2024099']);
    $semester = Semester::factory()->create(['kode' => '20241', 'nama' => '2024 Ganjil']);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'nama' => 'Kalkulus', 'sks' => 3]);
    $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 3]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $km->id, 'id_semester' => $semester->id]);
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id, 'approved_at' => now()]);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $mhs->id)
        ->assertSee('Budi Hartono')
        ->assertSee('2024099')
        ->assertSee('Kalkulus')
        ->assertSee('Disetujui');
});

it('returns a 404 when opening the detail modal for a mahasiswa outside the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mhsB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->call('openDetailModal', $mhsB->id)
        ->assertStatus(404);
});

it('has no add, edit, or delete actions (read-only portal)', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'sks' => 3]);
    $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 3]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $km->id]);
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.krs'))->getContent();

    expect($html)->not->toContain('Tambah KRS');
    expect($html)->not->toContain('wire:click="confirmDelete');

    $component = Livewire::actingAs($kaprodi)->test(Index::class);
    expect(method_exists($component->instance(), 'delete'))->toBeFalse();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('prodi.krs'))->assertRedirect(route('login'));
});

it('orders the detail modal semester groups by kode, newest first', function () {
    $prodi = Prodi::factory()->create();
    $mhs = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);

    // Semester paling lama dibuat terakhir supaya id-nya paling besar; sort per id akan salah.
    $baru = Semester::factory()->create(['kode' => '20252']);
    $tengah = Semester::factory()->create(['kode' => '20241']);
    $lama = Semester::factory()->create(['kode' => '20232']);
    expect($lama->id)->toBeGreaterThan($baru->id);

    foreach ([$lama, $tengah, $baru] as $semester) {
        $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'sks' => 3]);
        $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 3]);
        $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_kurikulum_matkul' => $km->id, 'id_semester' => $semester->id]);
        Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    }

    $groups = Livewire::actingAs(kaprodiUser($prodi))
        ->test(Index::class)
        ->call('openDetailModal', $mhs->id)
        ->instance()
        ->detailKrsBySemester();

    expect(array_column(array_column($groups, 'semester'), 'kode'))->toBe(['20252', '20241', '20232']);
});
