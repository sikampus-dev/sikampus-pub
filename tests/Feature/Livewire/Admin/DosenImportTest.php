<?php

use App\Livewire\Admin\Dosen\Import;
use App\Models\Dosen;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Susun file xlsx sungguhan (bukan UploadedFile::fake() biasa — isinya harus bisa
 * diparse PhpSpreadsheet) dengan urutan kolom persis DosenController::import.
 * Dibungkus lewat UploadedFile::fake()->createWithContent supaya hasilnya instance
 * Illuminate\Http\Testing\File — Livewire test harness butuh properti publik ->name.
 */
function makeDosenImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['header'], null, 'A1');
    $sheet->fromArray($rows, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'dosen_import_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return UploadedFile::fake()->createWithContent('import.xlsx', file_get_contents($path));
}

it('renders the import page with a link to download the template', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.administrasi.dosen.import'))
        ->assertOk()
        ->assertSee(route('admin.administrasi.dosen.template'));
});

it('imports a new dosen row and reports the success count', function () {
    $admin = adminUser();
    $file = makeDosenImportFile([
        ['Budi Santoso'],
    ]);

    Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertSet('result.success_count', 1);

    expect(Dosen::where('nama', 'Budi Santoso')->exists())->toBeTrue();
});

it('shows the underlying exception message instead of a generic one when the import fails', function () {
    $admin = adminUser();

    // 'no_hp' adalah string(255) di migration — nilai yang jauh melebihi itu memicu
    // QueryException sungguhan (data too long) dari MySQL, tanpa perlu mock apa pun.
    $row = array_fill(0, 16, null);
    $row[0] = 'Nama Gagal';
    $row[14] = str_repeat('9', 300);
    $file = makeDosenImportFile([$row]);

    $component = Livewire::actingAs($admin)
        ->test(Import::class)
        ->set('file', $file)
        ->call('import')
        ->assertHasErrors('file');

    expect(Dosen::where('nama', 'Nama Gagal')->exists())->toBeFalse();

    $errorMessage = $component->errors()->first('file');
    expect($errorMessage)->toStartWith('Terjadi kesalahan saat mengimport data: ');
    expect($errorMessage)->not->toBe('Terjadi kesalahan saat mengimport data! Harap periksa kembali data yang diimport.');
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.administrasi.dosen.import'))
        ->assertRedirect(route('login'));
});
