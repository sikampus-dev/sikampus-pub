<?php

use App\Livewire\Dosen\Nilai\Input as DosenNilaiInput;
use App\Livewire\Mahasiswa\Krs\Pengajuan;
use App\Models\Dosen;
use App\Models\JenisPenilaian;
use App\Models\KalenderAkademik;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

function krsMahasiswaFixture(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);
    $prodi = Prodi::factory()->create();
    $angkatan = Semester::factory()->create();
    $activeSemester = Semester::factory()->active()->create();
    $mahasiswa->update(['id_prodi' => $prodi->id, 'id_semester_masuk' => $angkatan->id]);

    $kurikulumMatkul = KurikulumMatkul::factory()->create();
    $kelas = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_angkatan' => $angkatan->id,
        'id_semester' => $activeSemester->id,
        'is_active' => true,
    ]);

    return [$user, $mahasiswa, $activeSemester, $kelas];
}

it('blocks a new krs pengajuan (Livewire) when the krs period for the semester is closed', function () {
    [$user, $mahasiswa, $activeSemester, $kelas] = krsMahasiswaFixture();
    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => $activeSemester->id,
        'tanggal_mulai' => now()->subDays(10),
        'tanggal_selesai' => now()->subDays(3),
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->assertSet('canSubmitNewKrs', false)
        ->assertSee('Pengajuan KRS sedang tidak dibuka')
        ->call('toggleKelas', $kelas->id)
        ->assertSet('selectedKelas', []);

    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->exists())->toBeFalse();
});

it('allows a new krs pengajuan (Livewire) when the krs period for the semester is open', function () {
    [$user, $mahasiswa, $activeSemester, $kelas] = krsMahasiswaFixture();
    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => $activeSemester->id,
        'tanggal_mulai' => now()->subDay(),
        'tanggal_selesai' => now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->call('toggleKelas', $kelas->id)
        ->call('submit')
        ->assertHasNoErrors();

    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->exists())->toBeTrue();
});

it('blocks a new krs pengajuan via the API when the krs period for the semester is closed', function () {
    [$user, $mahasiswa, $activeSemester, $kelas] = krsMahasiswaFixture();
    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => $activeSemester->id,
        'tanggal_mulai' => now()->addDays(3),
        'tanggal_selesai' => now()->addDays(10),
    ]);

    $this->actingAs($user)
        ->postJson('/api/krs/pengajuan', ['krs' => [['id_kelas' => $kelas->id]]])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'belum dibuka'));

    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelas->id)->exists())->toBeFalse();
});

it('still allows cancelling an already-submitted krs even when the krs period is closed', function () {
    [$user, $mahasiswa, $activeSemester, $kelas] = krsMahasiswaFixture();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => $activeSemester->id,
        'tanggal_mulai' => now()->subDays(10),
        'tanggal_selesai' => now()->subDays(3),
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->call('confirmCancel', $krs->id)
        ->call('cancelKrs');

    expect(Krs::find($krs->id))->toBeNull();
});

function dosenNilaiFixture(): array
{
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id, 'id_semester' => $semesterAktif->id]);
    $jenisPenilaian = JenisPenilaian::factory()->create(['status' => 'manual']);
    $mhs = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);

    return [$dosenUser, $dosen, $semesterAktif, $kelas, $jenisPenilaian, $krs];
}

it('blocks saving nilai (Livewire) when the nilai period for the semester is closed', function () {
    [$dosenUser, , $semesterAktif, $kelas, $jenisPenilaian, $krs] = dosenNilaiFixture();
    KalenderAkademik::factory()->create([
        'kategori' => 'nilai',
        'id_semester' => $semesterAktif->id,
        'tanggal_mulai' => now()->subDays(10),
        'tanggal_selesai' => now()->subDays(3),
    ]);

    Livewire::actingAs($dosenUser)
        ->test(DosenNilaiInput::class, ['kelasId' => $kelas->id])
        ->assertSee('Pengisian nilai sedang tidak dibuka')
        ->set('selectedJenisPenilaianId', (string) $jenisPenilaian->id)
        ->set("nilaiInputs.{$krs->id}", '80')
        ->call('save')
        ->assertHasErrors('periode');

    $this->assertDatabaseMissing('nilai_komponen', ['id_krs' => $krs->id]);
});

it('allows saving nilai (Livewire) when the nilai period for the semester is open', function () {
    [$dosenUser, , $semesterAktif, $kelas, $jenisPenilaian, $krs] = dosenNilaiFixture();
    KalenderAkademik::factory()->create([
        'kategori' => 'nilai',
        'id_semester' => $semesterAktif->id,
        'tanggal_mulai' => now()->subDay(),
        'tanggal_selesai' => now()->addDay(),
    ]);

    Livewire::actingAs($dosenUser)
        ->test(DosenNilaiInput::class, ['kelasId' => $kelas->id])
        ->set('selectedJenisPenilaianId', (string) $jenisPenilaian->id)
        ->set("nilaiInputs.{$krs->id}", '80')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('nilai_komponen', ['id_krs' => $krs->id, 'nilai' => 80]);
});

it('blocks storing nilai komponen via the API when the nilai period for the semester is closed', function () {
    [$dosenUser, , $semesterAktif, $kelas, $jenisPenilaian, $krs] = dosenNilaiFixture();
    KalenderAkademik::factory()->create([
        'kategori' => 'nilai',
        'id_semester' => $semesterAktif->id,
        'tanggal_mulai' => now()->subDays(10),
        'tanggal_selesai' => now()->subDays(3),
    ]);

    $this->actingAs($dosenUser)
        ->postJson('/api/nilai/komponen', [
            'id_krs' => $krs->id,
            'id_jenis_penilaian' => $jenisPenilaian->id,
            'nilai' => 80,
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('nilai_komponen', ['id_krs' => $krs->id]);
});

it('does not gate the admin panel: a global kalender akademik event scoped to no semester still lets the KRS period stay open elsewhere', function () {
    [$user, $mahasiswa, $activeSemester, $kelas] = krsMahasiswaFixture();
    // Event untuk semester LAIN tidak boleh ikut menutup semester aktif mahasiswa ini.
    $semesterLain = Semester::factory()->create();
    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => $semesterLain->id,
        'tanggal_mulai' => now()->subDays(10),
        'tanggal_selesai' => now()->subDays(3),
    ]);

    Livewire::actingAs($user)
        ->test(Pengajuan::class)
        ->assertSet('canSubmitNewKrs', true);
});
