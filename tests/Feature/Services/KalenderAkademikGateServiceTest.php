<?php

use App\Models\KalenderAkademik;
use App\Models\Semester;
use App\Services\KalenderAkademikGateService;
use Illuminate\Support\Carbon;

it('mengizinkan akses secara default kalau belum ada event kalender untuk kategori tersebut', function () {
    $result = KalenderAkademikGateService::isPeriodeAktif('krs');

    expect($result['allowed'])->toBeTrue();
    expect($result['alasan'])->toBeNull();
    expect($result['event'])->toBeNull();
});

it('mengizinkan akses kalau waktu sekarang berada di dalam rentang event', function () {
    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => null,
        'tanggal_mulai' => now()->subDay(),
        'tanggal_selesai' => now()->addDay(),
    ]);

    $result = KalenderAkademikGateService::isPeriodeAktif('krs');

    expect($result['allowed'])->toBeTrue();
    expect($result['event'])->not->toBeNull();
});

it('menolak akses dengan alasan "belum dibuka" kalau event masih di masa depan', function () {
    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => null,
        'tanggal_mulai' => now()->addDays(3),
        'tanggal_selesai' => now()->addDays(10),
    ]);

    $result = KalenderAkademikGateService::isPeriodeAktif('krs');

    expect($result['allowed'])->toBeFalse();
    expect($result['alasan'])->toContain('belum dibuka');
});

it('menolak akses dengan alasan "sudah berakhir" kalau event sudah lewat', function () {
    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => null,
        'tanggal_mulai' => now()->subDays(10),
        'tanggal_selesai' => now()->subDays(3),
    ]);

    $result = KalenderAkademikGateService::isPeriodeAktif('krs');

    expect($result['allowed'])->toBeFalse();
    expect($result['alasan'])->toContain('sudah berakhir');
});

it('mendahulukan event khusus semester di atas event global yang sama-sama aktif', function () {
    $semester = Semester::factory()->create();

    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => null,
        'tanggal_mulai' => now()->subDay(),
        'tanggal_selesai' => now()->addDay(),
    ]);
    $khusus = KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => $semester->id,
        'tanggal_mulai' => now()->subDay(),
        'tanggal_selesai' => now()->addDay(),
    ]);

    $result = KalenderAkademikGateService::isPeriodeAktif('krs', $semester->id);

    expect($result['allowed'])->toBeTrue();
    expect($result['event']->id)->toBe($khusus->id);
});

it('tidak ikut mempertimbangkan event semester lain', function () {
    $semesterLain = Semester::factory()->create();

    KalenderAkademik::factory()->create([
        'kategori' => 'krs',
        'id_semester' => $semesterLain->id,
        'tanggal_mulai' => now()->subDay(),
        'tanggal_selesai' => now()->addDay(),
    ]);

    $result = KalenderAkademikGateService::isPeriodeAktif('krs', 999999);

    // Tidak ada event global maupun event untuk semester 999999 -> fail-open.
    expect($result['allowed'])->toBeTrue();
    expect($result['event'])->toBeNull();
});

it('menerima waktu eksplisit lewat parameter $pada', function () {
    KalenderAkademik::factory()->create([
        'kategori' => 'nilai',
        'id_semester' => null,
        'tanggal_mulai' => '2026-01-01 00:00:00',
        'tanggal_selesai' => '2026-01-31 23:59:59',
    ]);

    $result = KalenderAkademikGateService::isPeriodeAktif('nilai', null, Carbon::parse('2026-01-15'));

    expect($result['allowed'])->toBeTrue();
});

it('mendukung KRS dua tahap (reguler + perbaikan) sebagai dua event kategori krs terpisah', function () {
    $semester = Semester::factory()->create();

    $tahap1 = KalenderAkademik::factory()->create([
        'nama' => 'Pengisian KRS Reguler', 'kategori' => 'krs', 'id_semester' => $semester->id,
        'tanggal_mulai' => Carbon::parse('2026-09-01 00:00:00'),
        'tanggal_selesai' => Carbon::parse('2026-09-07 23:59:59'),
    ]);
    $tahap2 = KalenderAkademik::factory()->create([
        'nama' => 'Perbaikan KRS', 'kategori' => 'krs', 'id_semester' => $semester->id,
        'tanggal_mulai' => Carbon::parse('2026-09-15 00:00:00'),
        'tanggal_selesai' => Carbon::parse('2026-09-20 23:59:59'),
    ]);

    // Sebelum tahap 1 dibuka -> menunjuk ke tahap 1 (yang paling dekat).
    $sebelum = KalenderAkademikGateService::isPeriodeAktif('krs', $semester->id, Carbon::parse('2026-08-25'));
    expect($sebelum['allowed'])->toBeFalse();
    expect($sebelum['event']->id)->toBe($tahap1->id);

    // Di tengah tahap 1 -> terbuka.
    $tengahTahap1 = KalenderAkademikGateService::isPeriodeAktif('krs', $semester->id, Carbon::parse('2026-09-03'));
    expect($tengahTahap1['allowed'])->toBeTrue();
    expect($tengahTahap1['event']->id)->toBe($tahap1->id);

    // Jeda di antara dua tahap -> tertutup, tapi alasannya menunjuk ke tahap 2 (yang akan datang),
    // bukan seolah-olah periode KRS sudah selesai total.
    $jeda = KalenderAkademikGateService::isPeriodeAktif('krs', $semester->id, Carbon::parse('2026-09-10'));
    expect($jeda['allowed'])->toBeFalse();
    expect($jeda['alasan'])->toContain('belum dibuka');
    expect($jeda['event']->id)->toBe($tahap2->id);

    // Di tengah tahap 2 -> terbuka lagi.
    $tengahTahap2 = KalenderAkademikGateService::isPeriodeAktif('krs', $semester->id, Carbon::parse('2026-09-17'));
    expect($tengahTahap2['allowed'])->toBeTrue();
    expect($tengahTahap2['event']->id)->toBe($tahap2->id);

    // Setelah tahap 2 berakhir -> tertutup, alasan menunjuk tanggal berakhirnya tahap 2.
    $setelah = KalenderAkademikGateService::isPeriodeAktif('krs', $semester->id, Carbon::parse('2026-09-25'));
    expect($setelah['allowed'])->toBeFalse();
    expect($setelah['alasan'])->toContain('sudah berakhir');
    expect($setelah['event']->id)->toBe($tahap2->id);
});

it('relevantEventsFor mengembalikan event global dan event semester diminta saja, terurut aktif-akan_datang-selesai', function () {
    $semester = Semester::factory()->create();
    $semesterLain = Semester::factory()->create();

    $lewat = KalenderAkademik::factory()->create([
        'kategori' => 'libur', 'id_semester' => null,
        'tanggal_mulai' => now()->subDays(10), 'tanggal_selesai' => now()->subDays(5),
    ]);
    $aktif = KalenderAkademik::factory()->create([
        'kategori' => 'krs', 'id_semester' => $semester->id,
        'tanggal_mulai' => now()->subDay(), 'tanggal_selesai' => now()->addDay(),
    ]);
    $mendatang = KalenderAkademik::factory()->create([
        'kategori' => 'nilai', 'id_semester' => null,
        'tanggal_mulai' => now()->addDays(5), 'tanggal_selesai' => now()->addDays(10),
    ]);
    KalenderAkademik::factory()->create(['id_semester' => $semesterLain->id]);

    $events = KalenderAkademikGateService::relevantEventsFor($semester->id);

    expect($events->pluck('id')->all())->toBe([$aktif->id, $mendatang->id, $lewat->id]);
    expect($events->pluck('status')->all())->toBe(['aktif', 'akan_datang', 'selesai']);
});
