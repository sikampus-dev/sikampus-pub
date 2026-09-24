<?php

use App\Livewire\Dosen\Perwalian\Show;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\DosenWaliBimbingan;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Nilai;
use App\Models\Semester;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $this->get(route('dosen.perwalian.show', $mahasiswa->id))->assertRedirect(route('login'));
});

it('returns 404 for a mahasiswa that is not an active bimbingan of this dosen', function () {
    $dosenUser = dosenUser();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($dosenUser)->test(Show::class, ['idMahasiswa' => $mahasiswa->id])->assertStatus(404);
});

it('renders the biodata tab by default', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Bimbingan Uji']);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['idMahasiswa' => $mahasiswa->id])
        ->assertSet('activeTab', 'biodata')
        ->assertSee('Mahasiswa Bimbingan Uji');
});

it('groups krs by semester with sks totals', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $mahasiswa = Mahasiswa::factory()->create();
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);

    $semester = Semester::factory()->create();
    $matkul = Matkul::factory()->create(['sks' => 4]);
    $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 4]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $km->id, 'id_semester' => $semester->id]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id, 'approved_at' => now()]);

    $groups = Livewire::actingAs($dosenUser)->test(Show::class, ['idMahasiswa' => $mahasiswa->id])->instance()->krsBySemester();

    expect($groups)->toHaveCount(1);
    expect($groups[0]['total_sks_diajukan'])->toBe(4);
    expect($groups[0]['total_sks_diacc'])->toBe(4);
});

it('computes ipk only from approved krs with a final nilai', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $mahasiswa = Mahasiswa::factory()->create();
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);

    $semester = Semester::factory()->create();
    $matkulA = Matkul::factory()->create(['sks' => 3]);
    $matkulB = Matkul::factory()->create(['sks' => 2]);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id]);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id]);
    $kelasA = Kelas::factory()->create(['id_kurikulum_matkul' => $kmA->id, 'id_semester' => $semester->id]);
    $kelasB = Kelas::factory()->create(['id_kurikulum_matkul' => $kmB->id, 'id_semester' => $semester->id]);

    $krsA = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasA->id, 'approved_at' => now()]);
    Nilai::factory()->create(['id_krs' => $krsA->id, 'huruf_mutu' => 'A', 'angka_mutu' => 4, 'is_final' => true]);

    // KRS belum disetujui tidak boleh ikut masuk transkrip walau ada nilai final.
    $krsB = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasB->id, 'approved_at' => null]);
    Nilai::factory()->create(['id_krs' => $krsB->id, 'huruf_mutu' => 'B', 'angka_mutu' => 3, 'is_final' => true]);

    $transkrip = Livewire::actingAs($dosenUser)->test(Show::class, ['idMahasiswa' => $mahasiswa->id])->instance()->transkrip();

    expect($transkrip['mata_kuliah'])->toHaveCount(1);
    expect($transkrip['ipk'])->toBe(4.0);
    expect($transkrip['total_sks_dengan_nilai'])->toBe(3);
});

it('creates a bimbingan note with langsung validasi, and edits it afterwards', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $mahasiswa = Mahasiswa::factory()->create();
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);
    $semester = Semester::factory()->create();

    $component = Livewire::actingAs($dosenUser)
        ->test(Show::class, ['idMahasiswa' => $mahasiswa->id])
        ->set('activeTab', 'catatan')
        ->call('openBimbinganModal')
        ->set('form_id_semester', (string) $semester->id)
        ->set('form_catatan_dosen', 'Diskusi rencana studi semester depan')
        ->set('form_langsung_validasi', true)
        ->call('saveBimbingan')
        ->assertHasNoErrors();

    $row = DosenWaliBimbingan::where('catatan_dosen', 'Diskusi rencana studi semester depan')->firstOrFail();
    expect($row->waktu_validasi_dosen)->not->toBeNull();

    $component
        ->call('openBimbinganModal', $row->id)
        ->assertSet('form_catatan_dosen', 'Diskusi rencana studi semester depan')
        ->set('form_catatan_dosen', 'Catatan diperbarui')
        ->call('saveBimbingan')
        ->assertHasNoErrors();

    expect($row->fresh()->catatan_dosen)->toBe('Catatan diperbarui');
});

it('uploads an attachment file with the bimbingan note', function () {
    Storage::fake('public');

    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $mahasiswa = Mahasiswa::factory()->create();
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);
    $semester = Semester::factory()->create();

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['idMahasiswa' => $mahasiswa->id])
        ->call('openBimbinganModal')
        ->set('form_id_semester', (string) $semester->id)
        ->set('form_file', UploadedFile::fake()->create('lampiran.pdf', 100, 'application/pdf'))
        ->call('saveBimbingan')
        ->assertHasNoErrors();

    $row = DosenWaliBimbingan::first();
    expect($row->file)->not->toBeNull();
    Storage::disk('public')->assertExists($row->file);
});

it('rejects opening or editing a bimbingan note that belongs to a different dosen wali', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $mahasiswaSaya = Mahasiswa::factory()->create();
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswaSaya->id, 'status' => 'active']);

    $dosenLain = Dosen::factory()->create();
    $mahasiswaLain = Mahasiswa::factory()->create();
    $dosenWaliLain = DosenWali::create(['id_dosen' => $dosenLain->id, 'id_mahasiswa' => $mahasiswaLain->id, 'status' => 'active']);
    $catatanOrangLain = DosenWaliBimbingan::create(['id_dosen_wali' => $dosenWaliLain->id, 'id_semester' => Semester::factory()->create()->id]);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['idMahasiswa' => $mahasiswaSaya->id])
        ->call('openBimbinganModal', $catatanOrangLain->id)
        ->assertStatus(404);
});

it('orders krs semester groups by kode, newest first, even when an older semester was created later', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $mahasiswa = Mahasiswa::factory()->create();
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);

    // Semester paling lama sengaja dibuat terakhir supaya id-nya paling besar — sort per id akan
    // menaruhnya di puncak, sort per kode harus menempatkannya di dasar.
    $baru = Semester::factory()->create(['kode' => '20252']);
    $tengah = Semester::factory()->create(['kode' => '20241']);
    $lama = Semester::factory()->create(['kode' => '20232']);
    expect($lama->id)->toBeGreaterThan($baru->id);

    foreach ([$lama, $tengah, $baru] as $semester) {
        $kelas = Kelas::factory()->create(['id_semester' => $semester->id]);
        Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    }

    $groups = Livewire::actingAs($dosenUser)->test(Show::class, ['idMahasiswa' => $mahasiswa->id])->instance()->krsBySemester();

    expect(array_column(array_map(fn ($g) => ['kode' => $g['semester']->kode], $groups), 'kode'))
        ->toBe(['20252', '20241', '20232']);
});
