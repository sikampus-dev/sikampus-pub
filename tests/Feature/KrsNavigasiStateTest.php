<?php

use App\Livewire\Admin\Krs\Form;
use App\Livewire\Admin\Krs\Index;
use App\Livewire\Admin\Krs\Show;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use Livewire\Livewire;

/**
 * Navigasi Index -> Detail -> Ubah di modul KRS harus membawa state Index (halaman + filter)
 * bolak-balik, dan Batal di form ubah kembali ke Detail — bukan ke Index halaman 1.
 */
function krsMahasiswa(): array
{
    $prodi = Prodi::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);

    return compact('prodi', 'mahasiswa', 'kelas', 'krs');
}

it('meneruskan halaman dan filter aktif ke link detail di index', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'prodi' => $prodi] = krsMahasiswa();

    Livewire::withQueryParams(['id_prodi' => (string) $prodi->id, 'search' => $mahasiswa->nim])
        ->actingAs($admin)
        ->test(Index::class)
        ->assertSee(route('admin.akademik.krs.show', $mahasiswa->id)
            .'?search='.$mahasiswa->nim.'&amp;id_prodi='.$prodi->id, false);
});

it('memulihkan filter index dari query string, bukan hanya nomor halaman', function () {
    $admin = adminUser();

    // Regresi: `page` sudah dipulihkan WithPagination, tapi filter tidak — sehingga kembali ke
    // ?page=3 menampilkan halaman 3 dari daftar TANPA filter.
    Livewire::withQueryParams(['search' => 'budi', 'id_prodi' => '7', 'status' => 'ada_belum_acc', 'page' => 3])
        ->actingAs($admin)
        ->test(Index::class)
        ->assertSet('search', 'budi')
        ->assertSet('filterProdi', '7')
        ->assertSet('filterStatusPengajuan', 'ada_belum_acc')
        ->tap(fn ($c) => expect($c->instance()->getPage())->toBe(3));
});

it('tombol kembali di detail membawa pulang ke halaman dan filter index yang sama', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa] = krsMahasiswa();

    Livewire::withQueryParams(['page' => 3, 'search' => 'budi'])
        ->actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSet('backUrl', route('admin.akademik.krs').'?page=3&search=budi');
});

it('detail tanpa state index kembali ke index polos', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa] = krsMahasiswa();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSet('backUrl', route('admin.akademik.krs'));
});

it('meneruskan state index ke link ubah di halaman detail', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = krsMahasiswa();

    Livewire::withQueryParams(['page' => 3])
        ->actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSee(route('admin.akademik.krs.edit', $krs->id).'?page=3', false);
});

it('batal di form ubah kembali ke detail mahasiswa, bukan ke index', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = krsMahasiswa();

    Livewire::withQueryParams(['page' => 3, 'search' => 'budi'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $krs->id])
        ->assertSet('cancelUrl', route('admin.akademik.krs.show', $mahasiswa->id).'?page=3&search=budi');
});

it('simpan di form ubah kembali ke detail dengan state index tetap terbawa', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = krsMahasiswa();

    Livewire::withQueryParams(['page' => 3])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $krs->id])
        ->call('save')
        ->assertRedirect(route('admin.akademik.krs.show', $mahasiswa->id).'?page=3');
});

it('batal di form tambah tetap kembali ke index dengan state yang sama', function () {
    $admin = adminUser();

    Livewire::withQueryParams(['page' => 2])
        ->actingAs($admin)
        ->test(Form::class)
        ->assertSet('cancelUrl', route('admin.akademik.krs').'?page=2');
});

it('tidak meneruskan parameter asing dari query string', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa] = krsMahasiswa();

    Livewire::withQueryParams(['page' => 2, 'redirect' => 'https://jahat.example'])
        ->actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSet('backUrl', route('admin.akademik.krs').'?page=2');
});

/**
 * Filter semester di halaman detail KRS (App\Livewire\Admin\Krs\Show::filterSemester) BEDA dari
 * filter Index yang dibawa lewat $returnQuery di atas — ini filter mahasiswa satuan itu sendiri,
 * dikirim lewat query id_semester_detail (bukan id_semester, supaya tidak tertukar dengan filter
 * Index yang kebetulan memakai nama query yang sama).
 */
it('meneruskan filter semester halaman detail ke link ubah', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs, 'kelas' => $kelas] = krsMahasiswa();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->set('filterSemester', (string) $kelas->id_semester)
        ->assertSee(route('admin.akademik.krs.edit', $krs->id).'?id_semester_detail='.$kelas->id_semester, false);
});

it('memulihkan filter semester halaman detail dari query string id_semester_detail', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa] = krsMahasiswa();

    Livewire::withQueryParams(['id_semester_detail' => '42'])
        ->actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->assertSet('filterSemester', '42');
});

it('membawa filter semester halaman detail ke cancelUrl form ubah', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = krsMahasiswa();

    Livewire::withQueryParams(['id_semester_detail' => '42'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $krs->id])
        ->assertSet('cancelUrl', route('admin.akademik.krs.show', $mahasiswa->id).'?id_semester_detail=42');
});

it('mengembalikan filter semester halaman detail setelah simpan di form ubah', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = krsMahasiswa();

    Livewire::withQueryParams(['id_semester_detail' => '42'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $krs->id])
        ->call('save')
        ->assertRedirect(route('admin.akademik.krs.show', $mahasiswa->id).'?id_semester_detail=42');
});

it('menggabungkan filter Index dan filter semester halaman detail sekaligus tanpa saling menimpa', function () {
    $admin = adminUser();
    ['mahasiswa' => $mahasiswa, 'krs' => $krs] = krsMahasiswa();

    Livewire::withQueryParams(['page' => 3, 'id_semester_detail' => '42'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $krs->id])
        ->call('save')
        ->assertRedirect(route('admin.akademik.krs.show', $mahasiswa->id).'?page=3&id_semester_detail=42');
});
