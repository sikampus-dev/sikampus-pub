<?php

use App\Livewire\Admin\Matkul\Import;
use App\Models\JenisMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Susun file xlsx sungguhan (bukan UploadedFile::fake() biasa — isinya harus bisa
 * diparse PhpSpreadsheet) dengan urutan kolom persis MatkulController::import.
 * Dibungkus lewat UploadedFile::fake()->createWithContent supaya hasilnya instance
 * Illuminate\Http\Testing\File — Livewire test harness butuh properti publik ->name.
 */
function makeMatkulImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['header'], null, 'A1');
    $sheet->fromArray($rows, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'matkul_import_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return UploadedFile::fake()->createWithContent('import.xlsx', file_get_contents($path));
}

it('renders the import page with a link to download the template', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.akademik.matkul.import'))
        ->assertOk()
        ->assertSee('Proses Import')
        ->assertSee(route('admin.akademik.matkul.template'));
});

it('shows download template and import links on the matkul index page', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.akademik.matkul'))
        ->assertOk()
        ->assertSee(route('admin.akademik.matkul.template'))
        ->assertSee(route('admin.akademik.matkul.import'));
});

it('downloads a template with an xlsx content type', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.akademik.matkul.template'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('imports a new mata kuliah row and resolves prodi and jenis matkul by kode', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create(['kode' => 'TI']);
    $jenis = JenisMatkul::factory()->create(['kode' => 'WAJIB']);

    $file = makeMatkulImportFile([
        ['MK001', 'Algoritma dan Struktur Data', 'Algorithm and Data Structure', 'Deskripsi', '3', '2', 'TI', 'WAJIB', 'active'],
    ]);

    Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 1)
        ->assertSet('result.skip_count', 0);

    $matkul = Matkul::where('kode', 'MK001')->firstOrFail();
    expect($matkul->nama)->toBe('Algoritma dan Struktur Data');
    expect($matkul->id_prodi)->toBe($prodi->id);
    expect($matkul->id_jenis_matkul)->toBe($jenis->id);
    expect($matkul->sks)->toBe(3);
    expect($matkul->semester)->toBe(2);
    expect($matkul->status)->toBe('active');
});

it('allows the same kode across different prodi but skips it within the same prodi', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create(['kode' => 'TI']);
    $prodiB = Prodi::factory()->create(['kode' => 'SI']);
    Matkul::factory()->create(['kode' => 'MK100', 'id_prodi' => $prodiA->id]);

    $file = makeMatkulImportFile([
        ['MK100', 'Mata Kuliah A', '', '', '', '', 'TI', '', ''],
        ['MK100', 'Mata Kuliah B', '', '', '', '', 'SI', '', ''],
    ]);

    Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 1)
        ->assertSet('result.skip_count', 1);

    expect(Matkul::where('kode', 'MK100')->where('id_prodi', $prodiB->id)->exists())->toBeTrue();
    expect(Matkul::where('kode', 'MK100')->where('id_prodi', $prodiA->id)->count())->toBe(1);
});

it('skips duplicate kode within the same prodi inside a single file', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create(['kode' => 'MN']);

    $file = makeMatkulImportFile([
        ['MK200', 'Mata Kuliah Pertama', '', '', '', '', 'MN', '', ''],
        ['MK200', 'Mata Kuliah Duplikat', '', '', '', '', 'MN', '', ''],
    ]);

    Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 1)
        ->assertSet('result.skip_count', 1);

    expect(Matkul::where('kode', 'MK200')->count())->toBe(1);
});

it('records an error when kode prodi cannot be found', function () {
    $admin = adminUser();

    $file = makeMatkulImportFile([
        ['MK300', 'Mata Kuliah Tanpa Prodi', '', '', '', '', 'TIDAK-ADA', '', ''],
    ]);

    $result = Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 0)
        ->get('result');

    expect($result['errors'])->not->toBeEmpty();
    expect(Matkul::where('kode', 'MK300')->exists())->toBeFalse();
});

it('shows the underlying exception message instead of a generic one when the import fails', function () {
    $admin = adminUser();

    // 'nama' adalah string(255) di migration — nilai yang jauh melebihi itu memicu
    // QueryException sungguhan (data too long) dari MySQL, tanpa perlu mock apa pun.
    $file = makeMatkulImportFile([
        ['MK-GAGAL', str_repeat('a', 300)],
    ]);

    $component = Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertHasErrors('file');

    expect(Matkul::where('kode', 'MK-GAGAL')->exists())->toBeFalse();

    $errorMessage = $component->errors()->first('file');
    expect($errorMessage)->toStartWith('Terjadi kesalahan saat mengimport data: ');
    expect($errorMessage)->not->toBe('Terjadi kesalahan saat mengimport data! Harap periksa kembali data yang diimport.');
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.akademik.matkul.import'))
        ->assertRedirect(route('login'));
});
