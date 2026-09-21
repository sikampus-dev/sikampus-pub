<?php

use App\Exceptions\PenghapusanDiblokir;
use App\Livewire\Admin\Krs\Show as KrsShow;
use App\Livewire\Admin\Nilai\Show as NilaiShow;
use App\Livewire\Mahasiswa\Krs\Pengajuan;
use App\Models\JenisPenilaian;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\NilaiKomponen;
use App\Models\NilaiRevisi;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * KRS -> nilai. Nilai final adalah catatan akademik resmi: menahan penghapusan KRS. Nilai yang
 * belum final, komponen (UTS/UAS/...), dan revisi tidak berarti tanpa KRS-nya: ikut terhapus
 * dan ikut dipulihkan. Menghapus nilai sendiri ikut menghapus komponen & revisinya.
 */
function krsBernilai(?bool $final, array $krs = []): array
{
    $krs = Krs::factory()->create($krs);

    return [
        'krs' => $krs,
        'nilai' => Nilai::factory()->create(['id_krs' => $krs->id, 'is_final' => $final]),
        'komponen' => NilaiKomponen::create([
            'id_krs' => $krs->id,
            'id_jenis_penilaian' => JenisPenilaian::factory()->create()->id,
            'nilai' => 80,
        ]),
        'revisi' => NilaiRevisi::create(['id_krs' => $krs->id, 'angka_mutu' => 3.5, 'huruf_mutu' => 'B+']),
    ];
}

function terhapus(string $kelas, int $id): bool
{
    return (bool) $kelas::withTrashed()->find($id)?->trashed();
}

it('menolak menghapus KRS yang sudah punya nilai final, tanpa menghapus apa pun', function () {
    $d = krsBernilai(true);

    expect(fn () => $d['krs']->delete())->toThrow(PenghapusanDiblokir::class, '1 nilai final');

    expect(terhapus(Krs::class, $d['krs']->id))->toBeFalse()
        ->and(terhapus(NilaiKomponen::class, $d['komponen']->id))->toBeFalse()
        ->and(terhapus(NilaiRevisi::class, $d['revisi']->id))->toBeFalse();
});

it('ikut menghapus nilai belum final, komponen, dan revisi bersama KRS dengan cap waktu yang sama', function () {
    $d = krsBernilai(false);

    $d['krs']->delete();

    $waktu = Krs::withTrashed()->find($d['krs']->id)->deleted_at->format('Y-m-d H:i:s');
    foreach ([[Nilai::class, 'nilai'], [NilaiKomponen::class, 'komponen'], [NilaiRevisi::class, 'revisi']] as [$kelas, $kunci]) {
        $baris = $kelas::withTrashed()->find($d[$kunci]->id);
        expect($baris->trashed())->toBeTrue("{$kunci} seharusnya ikut terhapus")
            ->and($baris->deleted_at->format('Y-m-d H:i:s'))->toBe($waktu);
    }
});

it('memperlakukan is_final NULL sebagai belum final', function () {
    $d = krsBernilai(null);
    // Pastikan benar-benar NULL di database, bukan dikonversi diam-diam jadi 0.
    expect(Nilai::whereKey($d['nilai']->id)->whereNull('is_final')->exists())->toBeTrue();

    $d['krs']->delete();

    expect(terhapus(Nilai::class, $d['nilai']->id))->toBeTrue();
});

it('ikut menghapus komponen yang sudah diinput walau nilai akhirnya belum dihitung', function () {
    $krs = Krs::factory()->create();
    $komponen = NilaiKomponen::create([
        'id_krs' => $krs->id,
        'id_jenis_penilaian' => JenisPenilaian::factory()->create()->id,
        'nilai' => 75,
    ]);

    $krs->delete();

    expect(terhapus(NilaiKomponen::class, $komponen->id))->toBeTrue();
});

it('memulihkan KRS beserta nilainya, tapi bukan nilai yang sudah dihapus sendiri sebelumnya', function () {
    $d = krsBernilai(false);

    // Nilai dihapus sendiri lebih dulu (alur "hapus nilai"), lalu KRS-nya dihapus belakangan.
    Carbon::setTestNow('2026-09-01 08:00:00');
    $d['nilai']->delete();
    Carbon::setTestNow('2026-09-10 08:00:00');
    $komponenBaru = NilaiKomponen::create([
        'id_krs' => $d['krs']->id,
        'id_jenis_penilaian' => JenisPenilaian::factory()->create()->id,
        'nilai' => 90,
    ]);
    $d['krs']->delete();
    Carbon::setTestNow();

    Krs::withTrashed()->find($d['krs']->id)->restore();

    expect(terhapus(NilaiKomponen::class, $komponenBaru->id))->toBeFalse()
        ->and(terhapus(Nilai::class, $d['nilai']->id))->toBeTrue('nilai yang dihapus sendiri tidak boleh ikut pulih')
        ->and(terhapus(NilaiKomponen::class, $d['komponen']->id))->toBeTrue('komponen milik nilai itu juga tidak');
});

it('menghapus nilai di panel admin ikut menghapus komponen dan revisi, dan bisa dipulihkan utuh', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $d = krsBernilai(true, ['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(NilaiShow::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $d['nilai']->id)
        ->call('delete')
        ->assertHasNoErrors();

    $komponen = NilaiKomponen::withTrashed()->find($d['komponen']->id);
    expect(terhapus(Nilai::class, $d['nilai']->id))->toBeTrue()
        ->and($komponen->trashed())->toBeTrue()
        // deleted_by dulu diisi manual lewat DB::table; kini lewat MencatatPelaku.
        ->and($komponen->deleted_by)->not->toBeNull()
        ->and(terhapus(NilaiRevisi::class, $d['revisi']->id))->toBeTrue();

    Nilai::withTrashed()->find($d['nilai']->id)->restore();

    expect(terhapus(NilaiKomponen::class, $d['komponen']->id))->toBeFalse()
        ->and(terhapus(NilaiRevisi::class, $d['revisi']->id))->toBeFalse();
});

it('menghapus nilai lewat API ikut menghapus komponen dan revisi', function () {
    $admin = adminUser();
    $d = krsBernilai(true);

    $this->actingAs($admin)->deleteJson("/api/nilai/{$d['nilai']->id}")->assertOk();

    expect(terhapus(NilaiKomponen::class, $d['komponen']->id))->toBeTrue()
        ->and(terhapus(NilaiRevisi::class, $d['revisi']->id))->toBeTrue();
});

it('memunculkan peringatan saat admin menghapus KRS bernilai final dari halaman detail', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $d = krsBernilai(true, ['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(KrsShow::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $d['krs']->id)
        ->call('delete')
        ->assertDispatched('hapus-diblokir')
        ->assertSet('confirmDeleteId', null);

    expect(terhapus(Krs::class, $d['krs']->id))->toBeFalse();
});

it('menjawab 422 lewat API saat menghapus KRS bernilai final', function () {
    $admin = adminUser();
    $d = krsBernilai(true);

    $this->actingAs($admin)->deleteJson("/api/krs/{$d['krs']->id}")
        ->assertStatus(422)
        ->assertJsonPath('dipakai_oleh.nilai final', 1);
});

it('memberi mahasiswa pesan yang ditujukan kepadanya saat membatalkan KRS pending bernilai final', function () {
    // Finalisasi nilai dosen tidak menyaring approved_at, jadi KRS pending pun bisa bernilai final.
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);
    $d = krsBernilai(true, ['id_mahasiswa' => $mahasiswa->id, 'approved_at' => null]);

    Livewire::actingAs($user)->test(Pengajuan::class)
        ->set('confirmingCancelId', $d['krs']->id)
        ->call('cancelKrs')
        ->assertStatus(422);

    $this->actingAs($user)->deleteJson("/api/krs/pengajuan/{$d['krs']->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'KRS ini sudah memiliki nilai final dan tidak dapat dibatalkan. Silakan hubungi bagian akademik.');

    expect(terhapus(Krs::class, $d['krs']->id))->toBeFalse();
});

it('tetap mengizinkan mahasiswa membatalkan KRS pending yang hanya punya nilai belum final', function () {
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);
    $d = krsBernilai(false, ['id_mahasiswa' => $mahasiswa->id, 'approved_at' => null]);

    $this->actingAs($user)->deleteJson("/api/krs/pengajuan/{$d['krs']->id}")->assertOk();

    expect(terhapus(Krs::class, $d['krs']->id))->toBeTrue()
        ->and(terhapus(NilaiKomponen::class, $d['komponen']->id))->toBeTrue();
});

it('menghapus KRS bernilai final beserta nilainya ketika opsi hapusNilaiTerkait dicentang di halaman detail', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $d = krsBernilai(true, ['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(KrsShow::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $d['krs']->id)
        ->set('hapusNilaiTerkait', true)
        ->call('delete')
        ->assertNotDispatched('hapus-diblokir')
        ->assertSet('confirmDeleteId', null)
        ->assertSet('hapusNilaiTerkait', false);

    $waktu = Krs::withTrashed()->find($d['krs']->id)->deleted_at->format('Y-m-d H:i:s');
    foreach ([[Krs::class, 'krs'], [Nilai::class, 'nilai'], [NilaiKomponen::class, 'komponen'], [NilaiRevisi::class, 'revisi']] as [$kelas, $kunci]) {
        $baris = $kelas::withTrashed()->find($d[$kunci]->id);
        expect($baris->trashed())->toBeTrue("{$kunci} seharusnya ikut terhapus")
            ->and($baris->deleted_at->format('Y-m-d H:i:s'))->toBe($waktu);
    }
});

it('mengabaikan hapusNilaiTerkait yang dipalsukan dari admin tanpa hak hapus nilai, tetap menolak hapus KRS bernilai final', function () {
    config(['access.granular_permissions' => true]);

    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();
    $d = krsBernilai(true, ['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(KrsShow::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $d['krs']->id)
        ->set('hapusNilaiTerkait', true)
        ->call('delete')
        ->assertDispatched('hapus-diblokir')
        ->assertSet('confirmDeleteId', null);

    expect(terhapus(Krs::class, $d['krs']->id))->toBeFalse()
        ->and(terhapus(Nilai::class, $d['nilai']->id))->toBeFalse();
});

it('menyembunyikan opsi hapus nilai terkait dari admin tanpa hak hapus nilai', function () {
    config(['access.granular_permissions' => true]);

    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(KrsShow::class, ['id' => $mahasiswa->id])
        ->call('confirmDelete', $krs->id)
        ->assertDontSee('Hapus juga nilai yang terkait');
});

it('melewati KRS bernilai final pada hapus massal ketika opsi hapusNilaiTerkait tidak dicentang', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $final = krsBernilai(true, ['id_mahasiswa' => $mahasiswa->id]);
    $belumFinal = krsBernilai(false, ['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(KrsShow::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $final['krs']->id, (string) $belumFinal['krs']->id])
        ->call('confirmBulkDelete')
        ->call('bulkDelete');

    expect(terhapus(Krs::class, $final['krs']->id))->toBeFalse()
        ->and(terhapus(Nilai::class, $final['nilai']->id))->toBeFalse()
        ->and(terhapus(Krs::class, $belumFinal['krs']->id))->toBeTrue()
        ->and(terhapus(Nilai::class, $belumFinal['nilai']->id))->toBeTrue();
});

it('ikut menghapus KRS bernilai final beserta nilainya pada hapus massal ketika opsi hapusNilaiTerkait dicentang', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    $final = krsBernilai(true, ['id_mahasiswa' => $mahasiswa->id]);
    $belumFinal = krsBernilai(false, ['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(KrsShow::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $final['krs']->id, (string) $belumFinal['krs']->id])
        ->call('confirmBulkDelete')
        ->set('hapusNilaiTerkait', true)
        ->call('bulkDelete')
        ->assertSet('hapusNilaiTerkait', false);

    foreach ([$final, $belumFinal] as $d) {
        $waktu = Krs::withTrashed()->find($d['krs']->id)->deleted_at->format('Y-m-d H:i:s');
        foreach ([[Krs::class, 'krs'], [Nilai::class, 'nilai'], [NilaiKomponen::class, 'komponen'], [NilaiRevisi::class, 'revisi']] as [$kelas, $kunci]) {
            $baris = $kelas::withTrashed()->find($d[$kunci]->id);
            expect($baris->trashed())->toBeTrue("{$kunci} seharusnya ikut terhapus")
                ->and($baris->deleted_at->format('Y-m-d H:i:s'))->toBe($waktu);
        }
    }
});

it('mengabaikan hapusNilaiTerkait yang dipalsukan pada hapus massal untuk admin tanpa hak hapus nilai', function () {
    config(['access.granular_permissions' => true]);

    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();
    $final = krsBernilai(true, ['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(KrsShow::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $final['krs']->id])
        ->call('confirmBulkDelete')
        ->set('hapusNilaiTerkait', true)
        ->call('bulkDelete');

    expect(terhapus(Krs::class, $final['krs']->id))->toBeFalse()
        ->and(terhapus(Nilai::class, $final['nilai']->id))->toBeFalse();
});

it('menyembunyikan opsi hapus nilai terkait dari modal hapus massal untuk admin tanpa hak hapus nilai', function () {
    config(['access.granular_permissions' => true]);

    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)->test(KrsShow::class, ['id' => $mahasiswa->id])
        ->set('selected', [(string) $krs->id])
        ->call('confirmBulkDelete')
        ->assertDontSee('Hapus juga nilai yang terkait');
});
