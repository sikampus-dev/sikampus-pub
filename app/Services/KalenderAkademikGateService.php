<?php

namespace App\Services;

use App\Models\KalenderAkademik;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gerbang periode dari kalender akademik, dipakai KRS/Nilai (Fase 3, lihat isPeriodeAktif()) dan
 * tampilan kalender read-only mahasiswa/dosen (Fase 4, lihat relevantEventsFor()).
 *
 * Kalau untuk sebuah kategori+semester belum ada event kalender sama sekali, akses dianggap
 * TERBUKA (fail-open) — sama seperti App\Services\KeuanganAksesMahasiswaService::canAccessByKode()
 * yang mengizinkan akses kalau belum ada AturanAksesKeuangan untuk kode tsb. Ini supaya kampus yang
 * belum sempat mengisi kalender akademiknya tidak mendadak terkunci begitu fitur ini dirilis.
 */
class KalenderAkademikGateService
{
    /**
     * @return array{allowed: bool, alasan: ?string, event: ?KalenderAkademik}
     */
    public static function isPeriodeAktif(string $kategori, ?int $idSemester = null, ?Carbon $pada = null): array
    {
        $pada ??= Carbon::now();

        $events = KalenderAkademik::query()
            ->where('kategori', $kategori)
            ->where(function ($q) use ($idSemester) {
                $q->whereNull('id_semester');
                if ($idSemester !== null) {
                    $q->orWhere('id_semester', $idSemester);
                }
            })
            ->get();

        if ($events->isEmpty()) {
            return ['allowed' => true, 'alasan' => null, 'event' => null];
        }

        // Event spesifik semester didahulukan atas event global (id_semester null) kalau
        // keduanya sama-sama aktif pada waktu yang sama.
        $aktif = $events
            ->filter(fn (KalenderAkademik $e) => $pada->betweenIncluded($e->tanggal_mulai, $e->tanggal_selesai))
            ->sortByDesc(fn (KalenderAkademik $e) => $e->id_semester !== null)
            ->first();

        if ($aktif !== null) {
            return ['allowed' => true, 'alasan' => null, 'event' => $aktif];
        }

        $berikutnya = $events
            ->filter(fn (KalenderAkademik $e) => $e->tanggal_mulai->greaterThan($pada))
            ->sortBy(fn (KalenderAkademik $e) => $e->tanggal_mulai)
            ->first();

        if ($berikutnya !== null) {
            return [
                'allowed' => false,
                'alasan' => sprintf('Periode belum dibuka. Dibuka mulai %s.', $berikutnya->tanggal_mulai->translatedFormat('d M Y, H:i')),
                'event' => $berikutnya,
            ];
        }

        $terakhir = $events->sortByDesc(fn (KalenderAkademik $e) => $e->tanggal_selesai)->first();

        return [
            'allowed' => false,
            'alasan' => sprintf('Periode sudah berakhir sejak %s.', $terakhir->tanggal_selesai->translatedFormat('d M Y, H:i')),
            'event' => $terakhir,
        ];
    }

    /**
     * Event kalender (semua kategori) yang relevan untuk satu semester: global (id_semester null)
     * + khusus semester tsb. Tiap event dapat atribut runtime `status` ('aktif'/'akan_datang'/
     * 'selesai'), lalu diurutkan supaya yang paling relevan tampil duluan — sedang aktif, baru akan
     * datang, baru yang sudah lewat (terbaru dulu). Dipakai halaman kalender mahasiswa/dosen
     * (read-only, tidak menggerbang apa pun).
     *
     * @return Collection<int, KalenderAkademik>
     */
    public static function relevantEventsFor(?int $idSemester, ?Carbon $pada = null): Collection
    {
        $pada ??= Carbon::now();

        $events = KalenderAkademik::query()
            ->with('semester')
            ->where(function ($q) use ($idSemester) {
                $q->whereNull('id_semester');
                if ($idSemester !== null) {
                    $q->orWhere('id_semester', $idSemester);
                }
            })
            ->get()
            ->each(function (KalenderAkademik $event) use ($pada) {
                $event->status = match (true) {
                    $pada->betweenIncluded($event->tanggal_mulai, $event->tanggal_selesai) => 'aktif',
                    $event->tanggal_mulai->greaterThan($pada) => 'akan_datang',
                    default => 'selesai',
                };
            })
            ->groupBy('status');

        $aktif = ($events->get('aktif') ?? collect())->sortBy('tanggal_mulai');
        $akanDatang = ($events->get('akan_datang') ?? collect())->sortBy('tanggal_mulai');
        $selesai = ($events->get('selesai') ?? collect())->sortByDesc('tanggal_selesai');

        return $aktif->concat($akanDatang)->concat($selesai)->values();
    }
}
