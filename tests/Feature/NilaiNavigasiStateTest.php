<?php

use App\Livewire\Admin\Nilai\Form;
use App\Livewire\Admin\Nilai\Index;
use App\Livewire\Admin\Nilai\Show;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use Livewire\Livewire;

/**
 * Navigasi Index -> Detail -> Ubah di modul Nilai harus membawa state Index (halaman, prodi,
 * semester masuk, pencarian) bolak-balik. Pola sama dengan KrsNavigasiStateTest.
 */
function nilaiMahasiswa(): array
{
    $prodi = Prodi::factory()->create();
    $semesterMasuk = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'id_semester_masuk' => $semesterMasuk->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    return compact('prodi', 'semesterMasuk', 'mahasiswa', 'krs');
}

it('meneruskan halaman, prodi, dan semester masuk ke link detail di index', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'prodi' => $prodi, 'semesterMasuk' => $semesterMasuk] = nilaiMahasiswa();

    Livewire::withQueryParams(['id_prodi' => (string) $prodi->id, 'id_semester_masuk' => (string) $semesterMasuk->id])
        ->actingAs($admin)
        ->test(Index::class)
        ->assertSee(route('admin.akademik.nilai.show', $mahasiswa->id)
            .'?id_prodi='.$prodi->id.'&amp;id_semester_masuk='.$semesterMasuk->id, false);
});

it('memulihkan filter prodi dan semester masuk dari query string, bukan hanya nomor halaman', function () {
    $admin = adminUser();

    // Regresi: `page` sudah dipulihkan WithPagination, tapi filter tidak — kembali ke ?page=3
    // menampilkan halaman 3 dari daftar TANPA filter.
    Livewire::withQueryParams(['search' => 'budi', 'id_prodi' => '7', 'id_semester_masuk' => '9', 'page' => 3])
        ->actingAs($admin)
        ->test(Index::class)
        ->assertSet('search', 'budi')
        ->assertSet('filterProdi', '7')
        ->assertSet('filterSemesterMasuk', '9')
        ->tap(fn ($c) => expect($c->instance()->getPage())->toBe(3));
});

it('tombol kembali di detail membawa pulang ke halaman dan filter index yang sama', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa] = nilaiMahasiswa();

    Livewire::withQueryParams(['page' => 3, 'id_prodi' => '7', 'id_semester_masuk' => '9'])
        ->actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSet('backUrl', route('admin.akademik.nilai').'?page=3&id_prodi=7&id_semester_masuk=9');
});

it('detail tanpa state index kembali ke index polos', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa] = nilaiMahasiswa();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSet('backUrl', route('admin.akademik.nilai'));
});

it('meneruskan state index ke link ubah nilai di halaman detail', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = nilaiMahasiswa();

    Livewire::withQueryParams(['page' => 3])
        ->actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSee(route('admin.akademik.nilai.edit', [$mahasiswa->id, $krs->id]).'?page=3', false);
});

it('batal dan breadcrumb di form ubah nilai kembali ke detail dengan state index terbawa', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = nilaiMahasiswa();

    Livewire::withQueryParams(['page' => 3, 'id_prodi' => '7'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $mahasiswa->id, 'idKrs' => $krs->id])
        ->assertSet('detailUrl', route('admin.akademik.nilai.show', $mahasiswa->id).'?page=3&id_prodi=7')
        ->assertSet('backUrl', route('admin.akademik.nilai').'?page=3&id_prodi=7');
});

it('simpan di form ubah nilai kembali ke detail dengan state index tetap terbawa', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = nilaiMahasiswa();

    Livewire::withQueryParams(['page' => 3])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $mahasiswa->id, 'idKrs' => $krs->id])
        ->set('huruf_mutu', 'A')
        ->set('angka_mutu', '4')
        ->call('save')
        ->assertRedirect(route('admin.akademik.nilai.show', $mahasiswa->id).'?page=3');
});

it('tidak meneruskan parameter asing, termasuk id_semester milik link ekspor detail', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa] = nilaiMahasiswa();

    Livewire::withQueryParams(['page' => 2, 'id_semester' => '5', 'redirect' => 'https://jahat.example'])
        ->actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSet('backUrl', route('admin.akademik.nilai').'?page=2');
});
