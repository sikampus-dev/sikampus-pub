<?php

use App\Livewire\Admin\Krs\Form as KrsForm;
use App\Livewire\Admin\Krs\Import as KrsImport;
use App\Livewire\Admin\Nilai\Import as NilaiImport;
use App\Livewire\Mahasiswa\Krs\Pengajuan;
use App\Models\Kelas;
use App\Models\KelompokKelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\User;
use App\Services\PendaftaranKrs;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Unique krs(id_mahasiswa, id_kelas) tidak mencegah pendaftaran ganda di KELAS PARALEL. Impor dulu
 * memilih kelas dengan ->first() tanpa melihat kelompok kelas/angkatan dan melahirkan ribuan KRS
 * kembar. Skenario dasar di sini sengaja menaruh kelas kelompok A (id lebih kecil, jadi yang
 * dipilih ->first()) sebelum kelas kelompok B milik mahasiswa.
 */
function kelasParalel(array $mahasiswa = []): array
{
    $prodi = Prodi::factory()->create();
    $semester = Semester::factory()->active()->create(['kode' => '20251']);
    $angkatan = Semester::factory()->create(['kode' => '20241']);
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id, 'kode' => 'MKD101']);
    $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelompokA = KelompokKelas::factory()->create(['nama' => 'BIO 24 A', 'id_prodi' => $prodi->id]);
    $kelompokB = KelompokKelas::factory()->create(['nama' => 'BIO 24 B', 'id_prodi' => $prodi->id]);

    $kelas = fn (KelompokKelas $kk, string $kode) => Kelas::factory()->create([
        'kode' => $kode, 'id_kurikulum_matkul' => $km->id, 'id_prodi' => $prodi->id,
        'id_semester' => $semester->id, 'id_angkatan' => $angkatan->id, 'id_kelompok_kelas' => $kk->id,
    ]);
    $kelasA = $kelas($kelompokA, 'BIO24A');
    $kelasB = $kelas($kelompokB, 'BIO24B');

    $mhs = Mahasiswa::factory()->create(array_merge([
        'nim' => '244110049', 'id_prodi' => $prodi->id,
        'id_kelompok_kelas' => $kelompokB->id, 'id_semester_masuk' => $angkatan->id,
    ], $mahasiswa));

    return compact('prodi', 'semester', 'angkatan', 'matkul', 'km', 'kelompokA', 'kelompokB', 'kelasA', 'kelasB', 'mhs');
}

function berkasImporPendaftaran(array $baris): UploadedFile
{
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray(['header'], null, 'A1');
    $sheet->getActiveSheet()->fromArray($baris, null, 'A2');
    $path = tempnam(sys_get_temp_dir(), 'pendaftaran_').'.xlsx';
    (new Xlsx($sheet))->save($path);

    return UploadedFile::fake()->createWithContent('import.xlsx', file_get_contents($path));
}

// ---------------------------------------------------------------- penentuan kelas

it('memilih kelas paralel yang sesuai kelompok kelas mahasiswa, bukan kelas ber-id terkecil', function () {
    $d = kelasParalel();

    [$kelas, $gagal] = PendaftaranKrs::tentukanKelas($d['mhs'], [$d['km']->id], $d['semester'], 'MKD101');

    expect($gagal)->toBeNull()->and($kelas->id)->toBe($d['kelasB']->id);
});

it('memakai angkatan saat kelompok kelas tidak bisa membedakan', function () {
    $d = kelasParalel(['id_kelompok_kelas' => null]);
    $d['kelasA']->update(['id_angkatan' => Semester::factory()->create()->id]);

    [$kelas] = PendaftaranKrs::tentukanKelas($d['mhs'], [$d['km']->id], $d['semester'], 'MKD101');

    expect($kelas->id)->toBe($d['kelasB']->id);
});

it('menolak menebak saat kelas paralel tidak bisa dibedakan, dan menyebut kelas-kelasnya', function () {
    $d = kelasParalel(['id_kelompok_kelas' => null]);

    [$kelas, $gagal] = PendaftaranKrs::tentukanKelas($d['mhs'], [$d['km']->id], $d['semester'], 'MKD101');

    expect($kelas)->toBeNull()
        ->and($gagal)->toContain('2 kelas paralel')
        ->and($gagal)->toContain('BIO24A')
        ->and($gagal)->toContain('BIO24B');
});

it('langsung memakai satu-satunya kelas yang ada', function () {
    $d = kelasParalel();
    $d['kelasA']->delete();

    [$kelas] = PendaftaranKrs::tentukanKelas($d['mhs'], [$d['km']->id], $d['semester'], 'MKD101');

    expect($kelas->id)->toBe($d['kelasB']->id);
});

// ---------------------------------------------------------------- impor KRS

it('impor KRS mendaftarkan mahasiswa ke kelas kelompoknya', function () {
    $admin = adminUser();
    $d = kelasParalel();

    Livewire::actingAs($admin)->test(KrsImport::class)
        ->set('file', berkasImporPendaftaran([['244110049', 'MKD101', '20251', 'acc']]))
        ->call('import')
        ->assertSet('result.success_count', 1);

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->pluck('id_kelas')->all())->toBe([$d['kelasB']->id]);
});

it('impor KRS yang dijalankan ulang tidak membuat KRS kembar', function () {
    $admin = adminUser();
    $d = kelasParalel();
    $berkas = fn () => berkasImporPendaftaran([['244110049', 'MKD101', '20251', 'acc']]);

    Livewire::actingAs($admin)->test(KrsImport::class)->set('file', $berkas())->call('import');
    Livewire::actingAs($admin)->test(KrsImport::class)->set('file', $berkas())->call('import')
        ->assertSet('result.success_count', 0)
        ->assertSet('result.skip_count', 1);

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(1);
});

it('impor KRS melaporkan mahasiswa yang sudah terdaftar di kelas paralel yang salah, tanpa mendaftarkan ulang', function () {
    $admin = adminUser();
    $d = kelasParalel();
    // Pola datanya: sudah terdaftar di kelas kelompok A, padahal mahasiswanya kelompok B.
    Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasA']->id]);

    $komponen = Livewire::actingAs($admin)->test(KrsImport::class)
        ->set('file', berkasImporPendaftaran([['244110049', 'MKD101', '20251', 'acc']]))
        ->call('import')
        ->assertSet('result.success_count', 0);

    expect(implode(' ', $komponen->get('result.errors')))->toContain('sudah terdaftar')->toContain('BIO24B');
    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(1);
});

it('impor KRS menolak baris yang kelasnya tidak bisa ditentukan', function () {
    $admin = adminUser();
    $d = kelasParalel(['id_kelompok_kelas' => null]);

    Livewire::actingAs($admin)->test(KrsImport::class)
        ->set('file', berkasImporPendaftaran([['244110049', 'MKD101', '20251', 'acc']]))
        ->call('import')
        ->assertSet('result.success_count', 0);

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->exists())->toBeFalse();
});

it('impor KRS lewat API juga mendaftarkan ke kelas kelompok mahasiswa dan tidak membuat kembar', function () {
    $admin = adminUser();
    $d = kelasParalel();
    Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasB']->id]);

    $this->actingAs($admin)->post('/api/krs/import', [
        'file' => berkasImporPendaftaran([['244110049', 'MKD101', '20251', 'acc']]),
    ], ['Accept' => 'application/json']);

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(1);
});

// ---------------------------------------------------------------- pendaftaran manual

it('form tambah KRS menolak kelas paralel dari mata kuliah yang sudah diambil', function () {
    $admin = adminUser();
    $d = kelasParalel();
    Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasB']->id]);

    Livewire::actingAs($admin)->test(KrsForm::class)
        ->set('selectedMahasiswaId', $d['mhs']->id)
        ->set('krs', [['id_kelas' => $d['kelasA']->id, 'status' => 'pending']])
        ->call('save')
        ->assertSet('submitError', fn ($pesan) => str_contains($pesan, 'sekali per semester'));

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(1);
});

it('form tambah KRS menolak dua kelas paralel mata kuliah yang sama dalam satu simpan', function () {
    $admin = adminUser();
    $d = kelasParalel();

    Livewire::actingAs($admin)->test(KrsForm::class)
        ->set('selectedMahasiswaId', $d['mhs']->id)
        ->set('krs', [
            ['id_kelas' => $d['kelasA']->id, 'status' => 'pending'],
            ['id_kelas' => $d['kelasB']->id, 'status' => 'pending'],
        ])
        ->call('save');

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(0);
});

it('form ubah KRS boleh memindahkan KRS ke kelas paralel dari mata kuliah yang sama', function () {
    $admin = adminUser();
    $d = kelasParalel();
    $krs = Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasA']->id]);

    // Memindahkan dirinya sendiri bukan pendaftaran ganda.
    Livewire::actingAs($admin)->test(KrsForm::class, ['id' => $krs->id])
        ->set('editIdKelas', $d['kelasB']->id)
        ->call('save')
        ->assertSet('submitError', '');

    expect($krs->fresh()->id_kelas)->toBe($d['kelasB']->id);
});

it('API tambah KRS menolak kelas paralel dari mata kuliah yang sudah diambil', function () {
    $admin = adminUser();
    $d = kelasParalel();
    Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasB']->id]);

    $this->actingAs($admin)->postJson('/api/krs', [
        'id_mahasiswa' => $d['mhs']->id,
        'krs' => [['id_kelas' => $d['kelasA']->id]],
    ])->assertStatus(422);

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(1);
});

// ---------------------------------------------------------------- pengajuan mahasiswa

it('pengajuan mahasiswa menolak dua kelas paralel mata kuliah yang sama', function () {
    $d = kelasParalel();
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $d['mhs']->update(['id_user' => $user->id]);

    $this->actingAs($user)->postJson('/api/krs/pengajuan', [
        'krs' => [['id_kelas' => $d['kelasA']->id], ['id_kelas' => $d['kelasB']->id]],
    ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'dipilih dua kali'));

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(0);
});

it('pengajuan mahasiswa menolak kelas yang mata kuliahnya sudah diambil di kelas paralel lain', function () {
    $d = kelasParalel();
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $d['mhs']->update(['id_user' => $user->id]);
    // Terdaftar di kelas A (tidak tampil di halaman pengajuannya), lalu mengajukan kelas B.
    Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasA']->id]);

    Livewire::actingAs($user)->test(Pengajuan::class)
        ->set('selectedKelas', [$d['kelasB']->id])
        ->call('submit')
        ->assertHasErrors('selectedKelas');

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(1);
});

it('pengajuan ulang kelas yang sama tetap idempoten', function () {
    $d = kelasParalel();
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $d['mhs']->update(['id_user' => $user->id]);
    Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasB']->id]);

    $this->actingAs($user)->postJson('/api/krs/pengajuan', [
        'krs' => [['id_kelas' => $d['kelasB']->id]],
    ])->assertSuccessful();

    expect(Krs::where('id_mahasiswa', $d['mhs']->id)->count())->toBe(1);
});

// ---------------------------------------------------------------- impor nilai

it('impor nilai menempel ke KRS mahasiswa di kelas mana pun, bukan ke kelas tebakan', function () {
    $admin = adminUser();
    $d = kelasParalel();
    $krs = Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasB']->id]);

    Livewire::actingAs($admin)->test(NilaiImport::class)
        ->set('file', berkasImporPendaftaran([['244110049', 'MKD101', '20251', '4', 'A', 'true']]))
        ->call('import')
        ->assertSet('result.success_count', 1);

    expect(Nilai::where('id_krs', $krs->id)->value('huruf_mutu'))->toBe('A');
});

it('impor nilai menolak mahasiswa yang terdaftar di dua kelas paralel', function () {
    $admin = adminUser();
    $d = kelasParalel();
    Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasA']->id]);
    Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasB']->id]);

    $komponen = Livewire::actingAs($admin)->test(NilaiImport::class)
        ->set('file', berkasImporPendaftaran([['244110049', 'MKD101', '20251', '4', 'A', 'true']]))
        ->call('import')
        ->assertSet('result.success_count', 0);

    expect(implode(' ', $komponen->get('result.errors')))->toContain('2 kelas paralel');
    expect(Nilai::count())->toBe(0);
});

it('impor nilai lewat API menempel ke KRS yang benar', function () {
    $admin = adminUser();
    $d = kelasParalel();
    $krs = Krs::factory()->create(['id_mahasiswa' => $d['mhs']->id, 'id_kelas' => $d['kelasB']->id]);

    $this->actingAs($admin)->post('/api/nilai/import', [
        'file' => berkasImporPendaftaran([['244110049', 'MKD101', '20251', '4', 'A', 'true']]),
    ], ['Accept' => 'application/json']);

    expect(Nilai::where('id_krs', $krs->id)->exists())->toBeTrue();
});
