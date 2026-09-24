<?php

use App\Livewire\Mahasiswa\Krs\Index as KrsIndex;
use App\Livewire\Mahasiswa\Krs\Pengajuan;
use App\Models\AturanAksesKeuangan;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\MatkulPrasyarat;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Models\User;
use Livewire\Livewire;

function krsMahasiswaUser(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    return [$user, $mahasiswa];
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.krs'))->assertRedirect(route('login'));
    $this->get(route('mahasiswa.krs.pengajuan'))->assertRedirect(route('login'));
});

it('lists krs grouped by semester with total sks and approval status', function () {
    [$user, $mahasiswa] = krsMahasiswaUser();
    $semester = Semester::factory()->create(['nama' => 'Ganjil 2025/2026']);
    $matkul = Matkul::factory()->create(['kode' => 'IF301', 'nama' => 'Basis Data', 'sks' => 4]);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id, 'id_semester' => $semester->id]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id, 'approved_at' => now()]);

    $this->actingAs($user)->get(route('mahasiswa.krs'))
        ->assertOk()
        ->assertSee('Ganjil 2025/2026')
        ->assertSee('IF301')
        ->assertSee('Basis Data')
        ->assertSee('Total SKS: 4 / 4', false)
        ->assertSee('Disetujui');
});

it('orders semester groups by kode, newest first, even when an older semester was created later', function () {
    [$user, $mahasiswa] = krsMahasiswaUser();

    // Urutan pembuatan sengaja dibuat tidak kronologis: semester paling lama ('20232') dibuat
    // paling akhir sehingga id-nya paling besar. Sort per id akan menaruhnya di puncak; sort per
    // kode harus tetap menempatkannya di dasar.
    $semesterTengah = Semester::factory()->create(['kode' => '20241', 'nama' => 'Ganjil 2024']);
    $semesterBaru = Semester::factory()->create(['kode' => '20252', 'nama' => 'Genap 2025']);
    $semesterLama = Semester::factory()->create(['kode' => '20232', 'nama' => 'Genap 2023']);

    expect($semesterLama->id)->toBeGreaterThan($semesterBaru->id);

    foreach ([$semesterLama, $semesterTengah, $semesterBaru] as $semester) {
        $kelas = Kelas::factory()->create(['id_semester' => $semester->id]);
        Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    }

    $this->actingAs($user)->get(route('mahasiswa.krs'))
        ->assertOk()
        ->assertSeeInOrder(['Genap 2025', 'Ganjil 2024', 'Genap 2023']);
});

it('streams a pdf download when exporting krs', function () {
    [$user, $mahasiswa] = krsMahasiswaUser();
    $semester = Semester::factory()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semester->id]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    $response = Livewire::actingAs($user)->test(KrsIndex::class)->call('exportPdf');

    $response->assertFileDownloaded();
});

it('lists kelas available for pengajuan on the active semester matching prodi and angkatan', function () {
    [$user, $mahasiswa] = krsMahasiswaUser();
    $prodi = Prodi::factory()->create();
    $angkatan = Semester::factory()->create();
    $activeSemester = Semester::factory()->active()->create();
    $mahasiswa->update(['id_prodi' => $prodi->id, 'id_semester_masuk' => $angkatan->id]);

    $matkul = Matkul::factory()->create(['kode' => 'IF101', 'nama' => 'Algoritma', 'sks' => 3]);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_angkatan' => $angkatan->id,
        'id_semester' => $activeSemester->id,
        'is_active' => true,
    ]);

    // Kelas prodi lain tidak boleh muncul.
    Kelas::factory()->create(['id_semester' => $activeSemester->id, 'is_active' => true]);

    $this->actingAs($user)->get(route('mahasiswa.krs.pengajuan'))
        ->assertOk()
        ->assertSee('IF101')
        ->assertSee('Algoritma');
});

it('submits a new krs pengajuan and marks it pending', function () {
    [$user, $mahasiswa] = krsMahasiswaUser();
    $prodi = Prodi::factory()->create();
    $angkatan = Semester::factory()->create();
    $activeSemester = Semester::factory()->active()->create();
    $mahasiswa->update(['id_prodi' => $prodi->id, 'id_semester_masuk' => $angkatan->id]);

    $matkul = Matkul::factory()->create(['sks' => 3]);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_angkatan' => $angkatan->id,
        'id_semester' => $activeSemester->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->call('toggleKelas', $kelas->id)
        ->assertSet('selectedKelas', [$kelas->id])
        ->call('submit')
        ->assertHasNoErrors();

    $krs = Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->firstOrFail();
    expect($krs->approved_at)->toBeNull();
});

it('blocks submission when a prerequisite course has not been passed', function () {
    [$user, $mahasiswa] = krsMahasiswaUser();
    $prodi = Prodi::factory()->create();
    $angkatan = Semester::factory()->create();
    $activeSemester = Semester::factory()->active()->create();
    $mahasiswa->update(['id_prodi' => $prodi->id, 'id_semester_masuk' => $angkatan->id]);

    $matkulPrasyarat = Matkul::factory()->create(['nama' => 'Algoritma Dasar']);
    $matkul = Matkul::factory()->create(['nama' => 'Struktur Data']);
    MatkulPrasyarat::create(['id_matkul' => $matkul->id, 'id_matkul_prasyarat' => $matkulPrasyarat->id]);

    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_angkatan' => $angkatan->id,
        'id_semester' => $activeSemester->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->call('toggleKelas', $kelas->id)
        ->call('submit')
        ->assertHasErrors('selectedKelas');

    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->exists())->toBeFalse();
});

it('blocks submission when payment percentage is below the required minimum', function () {
    [$user, $mahasiswa] = krsMahasiswaUser();
    $prodi = Prodi::factory()->create();
    $angkatan = Semester::factory()->create();
    $activeSemester = Semester::factory()->active()->create();
    $mahasiswa->update(['id_prodi' => $prodi->id, 'id_semester_masuk' => $angkatan->id]);
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'persentase_minimum' => 75]);
    Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'tanggal_tagihan' => now()->subDay(),
        'total' => 1000000,
    ]);

    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $kelas = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_angkatan' => $angkatan->id,
        'id_semester' => $activeSemester->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->assertSet('canSubmitNewKrs', false)
        ->call('toggleKelas', $kelas->id)
        ->assertSet('selectedKelas', []);
});

it('lets a mahasiswa cancel their own pending krs but not an approved one', function () {
    [$user, $mahasiswa] = krsMahasiswaUser();
    $kelasPending = Kelas::factory()->create();
    $kelasApproved = Kelas::factory()->create();
    $krsPending = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasPending->id]);
    $krsApproved = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasApproved->id, 'approved_at' => now()]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->call('confirmCancel', $krsPending->id)
        ->call('cancelKrs');

    expect(Krs::find($krsPending->id))->toBeNull();

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->call('confirmCancel', $krsApproved->id)
        ->call('cancelKrs')
        ->assertStatus(422);
});
