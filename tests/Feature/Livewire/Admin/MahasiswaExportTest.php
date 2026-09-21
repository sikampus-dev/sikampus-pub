<?php

use App\Livewire\Admin\Mahasiswa\Import;
use App\Models\JalurMasuk;
use App\Models\JenisDaftar;
use App\Models\KelompokKelas;
use App\Models\Kota;
use App\Models\Mahasiswa;
use App\Models\Negara;
use App\Models\Pekerjaan;
use App\Models\Pendidikan;
use App\Models\Penghasilan;
use App\Models\Prodi;
use App\Models\Provinsi;
use App\Models\Semester;
use App\Models\StatusAkademik;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Simpan body streamed response ke file sementara lalu baca isinya sebagai baris teks —
 * dipakai untuk memastikan hasil export benar-benar mencerminkan filter yang dikirim.
 */
function readMahasiswaExportRows($response): array
{
    $path = tempnam(sys_get_temp_dir(), 'mhs_export_').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $rows = IOFactory::load($path)->getActiveSheet()->toArray();

    return array_map(fn ($row) => implode(' | ', array_filter($row, fn ($v) => $v !== null && $v !== '')), $rows);
}

it('shows an export link on the index page that carries the current filters', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Budi Santoso']);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa'))
        ->assertOk()
        ->assertSee('Export Excel')
        ->assertSee(route('admin.administrasi.mahasiswa.export'), false);
});

it('exports as an xlsx file', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.export'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('only exports mahasiswa matching the selected prodi filter', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi B']);
    Mahasiswa::factory()->create(['nama' => 'Mahasiswa Prodi A', 'id_prodi' => $prodiA->id]);
    Mahasiswa::factory()->create(['nama' => 'Mahasiswa Prodi B', 'id_prodi' => $prodiB->id]);

    $response = $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.export', ['id_prodi' => $prodiA->id]));

    $text = implode("\n", readMahasiswaExportRows($response));

    expect($text)->toContain('Mahasiswa Prodi A');
    expect($text)->not->toContain('Mahasiswa Prodi B');
});

it('only exports mahasiswa matching the search filter', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Findable Student', 'nim' => '2024000111']);
    Mahasiswa::factory()->create(['nama' => 'Other Student', 'nim' => '2024000222']);

    $response = $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.export', ['search' => 'Findable']));

    $text = implode("\n", readMahasiswaExportRows($response));

    expect($text)->toContain('Findable Student');
    expect($text)->not->toContain('Other Student');
});

it('admin dengan scope prodi hanya bisa mengexport mahasiswa dari prodinya sendiri', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    Mahasiswa::factory()->create(['nama' => 'Dalam Scope', 'id_prodi' => $prodiA->id]);
    Mahasiswa::factory()->create(['nama' => 'Luar Scope', 'id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $response = $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa.export'));

    $text = implode("\n", readMahasiswaExportRows($response));

    expect($text)->toContain('Dalam Scope');
    expect($text)->not->toContain('Luar Scope');
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.administrasi.mahasiswa.export'))
        ->assertRedirect(route('login'));
});

/**
 * Kolom ekspor harus sama persis dengan template impor, sehingga file ekspor bisa diedit lalu
 * diimpor kembali. Lihat App\Services\KolomExcelMahasiswa.
 */
function bacaEksporMahasiswa($response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'mhs_export_').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    return IOFactory::load($path);
}

it('memakai baris judul yang sama persis dengan template impor', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create();

    $ekspor = bacaEksporMahasiswa($this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.export')));

    $pathTemplate = tempnam(sys_get_temp_dir(), 'mhs_tpl_').'.xlsx';
    file_put_contents($pathTemplate, $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.template'))->streamedContent());
    $template = IOFactory::load($pathTemplate);

    expect($ekspor->getActiveSheet()->toArray()[0])->toBe($template->getActiveSheet()->toArray()[0]);
});

it('menaruh data mulai baris kedua di sheet aktif, dan info filter di sheet terpisah', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create(['nama' => 'Prodi Uji']);
    Mahasiswa::factory()->create(['nama' => 'Budi Santoso', 'id_prodi' => $prodi->id]);

    $ekspor = bacaEksporMahasiswa($this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.export', ['id_prodi' => $prodi->id])));

    // Impor membaca sheet AKTIF dan hanya membuang satu baris judul.
    $data = $ekspor->getActiveSheet();
    expect($data->getTitle())->toBe('Data Mahasiswa')
        ->and($data->getCell('A1')->getValue())->toBe('Nama*')
        ->and($data->getCell('A2')->getValue())->toBe('Budi Santoso');

    $info = implode(' ', array_merge(...$ekspor->getSheetByName('Info Export')->toArray()));
    expect($info)->toContain('Prodi: Prodi Uji');
});

it('hasil ekspor bisa diimpor kembali tanpa ada data yang berubah', function () {
    $admin = adminUser();

    $prodi = Prodi::factory()->create(['kode' => 'BIOUJI']);
    $semester = Semester::factory()->create(['kode' => '20241']);
    $kelompok = KelompokKelas::factory()->create(['nama' => 'BIO 24 A', 'id_prodi' => $prodi->id]);
    $status = StatusAkademik::factory()->create(['nama' => 'Aktif Uji']);
    $jalur = JalurMasuk::factory()->create(['nama' => 'Jalur Prestasi Uji']);
    $jenis = JenisDaftar::factory()->create(['nama' => 'Peserta Didik Baru Uji']);
    $negara = Negara::factory()->create(['nama' => 'Negara Uji']);
    // Impor mencari provinsi di dalam negara mahasiswa, dan kota di dalam provinsinya — jadi
    // wilayahnya harus berjenjang dengan benar, seperti data sungguhan.
    $provinsi = Provinsi::factory()->create(['nama' => 'Provinsi Uji', 'id_negara' => $negara->id]);
    $kota = Kota::factory()->create(['nama' => 'Kota Uji', 'id_provinsi' => $provinsi->id]);
    $pendidikan = Pendidikan::create(['nama' => 'Pendidikan Uji']);
    $pekerjaan = Pekerjaan::create(['nama' => 'Pekerjaan Uji']);
    $penghasilan = Penghasilan::create(['nama' => 'Penghasilan Uji']);

    $mahasiswa = Mahasiswa::factory()->create([
        'nama' => 'Siti Aminah',
        'nim' => '0244110049', // nol di depan
        'email' => 'siti@example.test',
        'no_wa' => '081234567890', // nol di depan
        'handphone' => '081299998888',
        'jenis_kelamin' => 'P',
        'id_tempat_lahir' => '32.01',
        'tanggal_lahir' => '2005-03-17',
        'no_ktp' => '3201234567890123', // 16 digit: melewati presisi 15 digit Excel
        'id_status_akademik' => $status->id,
        'alamat' => 'Jl. Contoh No. 1',
        'rt' => '001',
        'rw' => '002',
        'dusun' => 'Dusun A',
        'kelurahan' => 'Kelurahan B',
        'kode_pos' => '01234',
        'id_kecamatan' => '3201010001',
        'id_negara' => $negara->id,
        'id_provinsi' => $provinsi->id,
        'id_kota' => $kota->id,
        'id_prodi' => $prodi->id,
        'id_kelompok_kelas' => $kelompok->id,
        'id_semester_masuk' => $semester->id,
        'id_jalur_masuk' => $jalur->id,
        'id_jenis_daftar' => $jenis->id,
        'mulai_semester' => '2024/2025 Ganjil',
        'sks_diakui' => 20,
        'sekolah_asal' => 'SMA Negeri 1',
        'nis' => '01234',
        'nisn' => '0012345678',
        'npwp' => '12.345.678.9-000.000',
        'ayah' => 'Ayah Uji', 'nik_ayah' => '3201234567890124', 'tgl_lahir_ayah' => '1970-01-02',
        'id_pddk_ayah' => $pendidikan->id, 'id_pekerjaan_ayah' => $pekerjaan->id, 'id_penghasilan_ayah' => $penghasilan->id,
        'ibu' => 'Ibu Uji', 'nik_ibu' => '3201234567890125', 'tgl_lahir_ibu' => '1972-05-06',
        'id_pddk_ibu' => $pendidikan->id, 'id_pekerjaan_ibu' => $pekerjaan->id, 'id_penghasilan_ibu' => $penghasilan->id,
        'wali' => 'Wali Uji', 'nik_wali' => '3201234567890126', 'tgl_lahir_wali' => '1968-07-08',
        'id_pddk_wali' => $pendidikan->id, 'id_pekerjaan_wali' => $pekerjaan->id, 'id_penghasilan_wali' => $penghasilan->id,
        'jml_biaya_masuk' => 5000000, // bisa terbaca 5,0 kalau diberi format ribuan
        'penerima_kps' => 'Ya',
        'no_kps' => '0987654321',
    ]);

    $kolom = [
        'nama', 'nim', 'email', 'no_wa', 'handphone', 'jenis_kelamin', 'id_tempat_lahir', 'no_ktp',
        'id_status_akademik', 'alamat', 'rt', 'rw', 'dusun', 'kelurahan', 'kode_pos', 'id_kecamatan',
        'id_negara', 'id_provinsi', 'id_kota', 'id_prodi', 'id_kelompok_kelas', 'id_semester_masuk',
        'id_jalur_masuk', 'id_jenis_daftar', 'mulai_semester', 'sks_diakui', 'sekolah_asal', 'nis', 'nisn',
        'npwp', 'ayah', 'nik_ayah', 'id_pddk_ayah', 'id_pekerjaan_ayah', 'id_penghasilan_ayah', 'ibu',
        'nik_ibu', 'id_pddk_ibu', 'id_pekerjaan_ibu', 'id_penghasilan_ibu', 'wali', 'nik_wali',
        'id_pddk_wali', 'id_pekerjaan_wali', 'id_penghasilan_wali', 'penerima_kps', 'no_kps',
    ];
    $tanggal = ['tanggal_lahir', 'tgl_lahir_ayah', 'tgl_lahir_ibu', 'tgl_lahir_wali'];
    $potret = function () use ($mahasiswa, $kolom, $tanggal) {
        $m = $mahasiswa->fresh();
        $hasil = collect($kolom)->mapWithKeys(fn ($k) => [$k => $m->{$k} === null ? null : (string) $m->{$k}])->all();
        foreach ($tanggal as $k) {
            $hasil[$k] = $m->{$k}?->format('Y-m-d');
        }
        $hasil['jml_biaya_masuk'] = $m->jml_biaya_masuk === null ? null : (float) $m->jml_biaya_masuk;

        return $hasil;
    };
    $asli = $potret();

    $respons = $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.export'));
    $path = tempnam(sys_get_temp_dir(), 'mhs_roundtrip_').'.xlsx';
    file_put_contents($path, $respons->streamedContent());

    // Kosongkan semua field kecuali kunci impor (NIM) dan kolom wajib (nama, prodi) — kalau impor
    // gagal membaca satu kolom saja, field itu tidak akan kembali.
    $dikosongkan = array_diff(array_merge($kolom, $tanggal, ['jml_biaya_masuk']), ['nim', 'nama', 'id_prodi']);
    DB::table('mahasiswa')->where('id', $mahasiswa->id)
        ->update(array_fill_keys($dikosongkan, null));
    expect($potret()['no_ktp'])->toBeNull();

    Livewire::actingAs($admin)->test(Import::class)
        ->set('file', UploadedFile::fake()->createWithContent('ekspor.xlsx', file_get_contents($path)))
        ->call('import');

    expect($potret())->toBe($asli);
});
