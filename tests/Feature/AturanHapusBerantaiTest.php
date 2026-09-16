<?php

use App\Exceptions\PenghapusanDiblokir;
use App\Livewire\Admin\Kelas\Index as KelasIndex;
use App\Livewire\Admin\Kelas\Show as KelasShow;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Krs;
use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\MateriPerkuliahan;
use App\Models\Pembayaran;
use App\Models\Perkuliahan;
use App\Models\Prodi;
use App\Models\Rps;
use App\Models\RpsCpmk;
use App\Models\RpsSubcpmk;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Models\TagihanRinci;
use App\Models\Ujian;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Soft delete tidak pernah memicu ON DELETE CASCADE database (soft delete = UPDATE deleted_at),
 * jadi aturannya di AturanHapusBerantai. Anak "milik" induk ikut terhapus & terpulihkan; anak
 * berisi riwayat menahan penghapusan.
 */
function kelasLengkap(): array
{
    $kelas = Kelas::factory()->create();
    $dosen = Dosen::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    return [
        'kelas' => $kelas,
        'jadwal' => $jadwal,
        'kelasDosen' => KelasDosen::create(['id_kelas' => $kelas->id, 'id_dosen' => $dosen->id]),
        'jadwalDosen' => JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id]),
        'materi' => MateriPerkuliahan::create(['id_jadwal' => $jadwal->id, 'file' => 'materi.pdf']),
        'ujian' => Ujian::factory()->create(['id_kelas' => $kelas->id, 'id_semester' => $kelas->id_semester]),
        'rps' => $rps = Rps::create(['id_kelas' => $kelas->id]),
        'cpmk' => $cpmk = RpsCpmk::create(['id_rps' => $rps->id]),
        'subcpmk' => RpsSubcpmk::create(['id_cpmk' => $cpmk->id]),
    ];
}

it('ikut menghapus seluruh anak milik kelas, sampai ke cucu, dengan deleted_at yang sama', function () {
    $d = kelasLengkap();

    $d['kelas']->delete();

    foreach (['jadwal', 'kelasDosen', 'jadwalDosen', 'materi', 'ujian', 'rps', 'cpmk', 'subcpmk'] as $anak) {
        $segar = $d[$anak]::withTrashed()->find($d[$anak]->id);
        expect($segar->trashed())->toBeTrue("{$anak} seharusnya ikut terhapus")
            // Cap waktu identik adalah syarat pemulihan yang simetris.
            ->and($segar->deleted_at->format('Y-m-d H:i:s'))
            ->toBe($d['kelas']->fresh()?->deleted_at?->format('Y-m-d H:i:s') ?? Kelas::withTrashed()->find($d['kelas']->id)->deleted_at->format('Y-m-d H:i:s'));
    }
});

it('memulihkan anak yang terhapus bersama induk, tapi tidak anak yang sudah dihapus sendiri sebelumnya', function () {
    $kelas = Kelas::factory()->create();
    $jadwalLama = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jadwalAktif = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    Carbon::setTestNow('2026-09-01 08:00:00');
    $jadwalLama->delete();

    Carbon::setTestNow('2026-09-10 08:00:00');
    $kelas->delete();
    Carbon::setTestNow();

    Kelas::withTrashed()->find($kelas->id)->restore();

    expect(Jadwal::find($jadwalAktif->id))->not->toBeNull()
        ->and(Jadwal::find($jadwalLama->id))->toBeNull('jadwal yang dihapus sendiri tidak boleh ikut pulih');
});

it('memulihkan pohon bertingkat sampai ke cucu', function () {
    $d = kelasLengkap();

    $d['kelas']->delete();
    Kelas::withTrashed()->find($d['kelas']->id)->restore();

    foreach (['jadwal', 'jadwalDosen', 'rps', 'cpmk', 'subcpmk'] as $anak) {
        expect($d[$anak]::find($d[$anak]->id))->not->toBeNull("{$anak} seharusnya ikut pulih");
    }
});

it('menolak menghapus kelas yang masih punya KRS', function () {
    $d = kelasLengkap();
    Krs::factory()->create(['id_kelas' => $d['kelas']->id]);

    expect(fn () => $d['kelas']->delete())
        ->toThrow(PenghapusanDiblokir::class, '1 KRS mahasiswa');

    expect(Kelas::find($d['kelas']->id))->not->toBeNull()
        ->and(Jadwal::find($d['jadwal']->id))->not->toBeNull();
});

it('menolak dari cucu tanpa sempat menghapus apa pun di pohonnya', function () {
    $d = kelasLengkap();
    Perkuliahan::factory()->create(['id_jadwal' => $d['jadwal']->id]);

    expect(fn () => $d['kelas']->delete())
        ->toThrow(PenghapusanDiblokir::class, 'pertemuan perkuliahan');

    // Pemeriksaan jalan di seluruh pohon SEBELUM ada yang dihapus.
    expect(Kelas::find($d['kelas']->id))->not->toBeNull()
        ->and(Jadwal::find($d['jadwal']->id))->not->toBeNull()
        ->and(RpsSubcpmk::find($d['subcpmk']->id))->not->toBeNull();
});

it('menolak menghapus kurikulum yang mata kuliahnya sudah dipakai kelas', function () {
    $kurikulum = Kurikulum::factory()->create();
    $km = KurikulumMatkul::factory()->create(['id_kurikulum' => $kurikulum->id]);
    Kelas::factory()->create(['id_kurikulum_matkul' => $km->id]);

    expect(fn () => $kurikulum->delete())->toThrow(PenghapusanDiblokir::class, '1 kelas');
    expect(KurikulumMatkul::find($km->id))->not->toBeNull();
});

it('ikut menghapus pengajuan pembayaran yang belum disetujui bersama tagihannya', function () {
    $tagihan = Tagihan::factory()->create();
    $rinci = TagihanRinci::factory()->create(['id_tagihan' => $tagihan->id]);
    $pending = Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'approved_at' => null]);

    $tagihan->delete();

    expect(Pembayaran::withTrashed()->find($pending->id)->trashed())->toBeTrue()
        ->and(TagihanRinci::withTrashed()->find($rinci->id)->trashed())->toBeTrue();
});

it('menolak menghapus tagihan yang punya pembayaran disetujui, tanpa mengotori deleted_by', function () {
    $tagihan = Tagihan::factory()->create();
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'approved_at' => now()]);

    expect(fn () => $tagihan->delete())
        ->toThrow(PenghapusanDiblokir::class, '1 pembayaran yang sudah disetujui');

    // Penolakan harus terjadi sebelum MencatatPelaku menulis deleted_by.
    expect(Tagihan::find($tagihan->id)->deleted_by)->toBeNull();
});

it('menolak menghapus data master yang masih dipakai', function () {
    $fakultas = Fakultas::factory()->create();
    $prodi = Prodi::factory()->create(['id_fakultas' => $fakultas->id]);
    $semester = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id, 'id_semester_masuk' => $semester->id]);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_semester' => $semester->id]);

    expect(fn () => $fakultas->delete())->toThrow(PenghapusanDiblokir::class, 'program studi');
    expect(fn () => $prodi->delete())->toThrow(PenghapusanDiblokir::class, 'mahasiswa');
    expect(fn () => $semester->delete())->toThrow(PenghapusanDiblokir::class, 'tagihan');
    expect(fn () => $mahasiswa->delete())->toThrow(PenghapusanDiblokir::class, 'tagihan');
});

it('mengembalikan jam uji semula setelah cascade', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');
    $d = kelasLengkap();

    $d['kelas']->delete();

    expect(Carbon::now()->toDateTimeString())->toBe('2026-01-01 00:00:00');
    Carbon::setTestNow();
});

it('menjawab 422 berisi rincian pemakai lewat API, bukan 500', function () {
    $admin = adminUser();
    $d = kelasLengkap();
    Krs::factory()->count(2)->create(['id_kelas' => $d['kelas']->id]);

    $this->actingAs($admin)->deleteJson("/api/kelas/{$d['kelas']->id}")
        ->assertStatus(422)
        ->assertJsonPath('dipakai_oleh.KRS mahasiswa', 2);

    expect(Kelas::find($d['kelas']->id))->not->toBeNull();
});

it('memunculkan peringatan di panel admin dan menutup modal konfirmasi, bukan error', function () {
    $admin = adminUser();
    $d = kelasLengkap();
    Krs::factory()->create(['id_kelas' => $d['kelas']->id]);

    Livewire::actingAs($admin)->test(KelasIndex::class)
        ->call('confirmDelete', $d['kelas']->id)
        ->call('delete')
        ->assertDispatched('hapus-diblokir')
        ->assertSet('confirmingDeleteId', null);

    expect(Kelas::find($d['kelas']->id))->not->toBeNull();
});

it('hapus massal jadwal tidak menghapus satu pun kalau salah satunya masih punya pertemuan', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $bebas = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $terpakai = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    Perkuliahan::factory()->create(['id_jadwal' => $terpakai->id]);

    Livewire::actingAs($admin)->test(KelasShow::class, ['id' => $kelas->id])
        ->set('selectedJadwalIds', [$bebas->id, $terpakai->id])
        ->set('confirmingBulkDelete', true)
        ->call('bulkDeleteJadwal')
        ->assertDispatched('hapus-diblokir')
        ->assertSet('confirmingBulkDelete', false);

    expect(Jadwal::find($bebas->id))->not->toBeNull('pilihan yang lolos tidak boleh terhapus setengah jalan')
        ->and(Jadwal::find($terpakai->id))->not->toBeNull();
});

it('hapus massal jadwal ikut menghapus anak milik jadwal', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $jd = JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => Dosen::factory()->create()->id]);

    Livewire::actingAs($admin)->test(KelasShow::class, ['id' => $kelas->id])
        ->set('selectedJadwalIds', [$jadwal->id])
        ->call('bulkDeleteJadwal');

    expect(JadwalDosen::withTrashed()->find($jd->id)->trashed())->toBeTrue();
});

it('layout admin memasang peringatan hapus-diblokir yang tersembunyi sampai dipicu', function () {
    $admin = adminUser();

    $html = $this->actingAs($admin)->get(route('admin.akademik.kelas'))->assertOk()->getContent();

    expect($html)->toContain('x-on:hapus-diblokir.window="pesan = $event.detail.pesan"')
        ->and($html)->toContain('Tidak bisa dihapus')
        // Tersembunyi sebelum Alpine siap — proyek ini tidak punya aturan CSS [x-cloak], jadi
        // tanpa ini lapisan gelapnya berkedip di setiap halaman admin.
        ->and($html)->toMatch('/x-on:hapus-diblokir\.window="[^"]*"\s+x-show="pesan !== \'\'"\s+style="display: none"/');
});
