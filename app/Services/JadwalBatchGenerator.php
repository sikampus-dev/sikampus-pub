<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use Carbon\Carbon;

/**
 * Pembuatan N slot pertemuan jadwal sekaligus untuk satu kelas — diekstrak dari
 * App\Livewire\Admin\Jadwal\Form::saveCreate() supaya logic yang sama (penghitungan tanggal
 * mingguan, pengecekan slot bentrok, assign dosen) bisa dipakai ulang oleh
 * App\Livewire\Admin\Kelas\Form (opsi "buat jadwal otomatis" saat kelas dibuat/diubah) tanpa
 * menduplikasinya.
 */
class JadwalBatchGenerator
{
    /**
     * Cek slot pertemuan ke-1..N untuk kombinasi (kelas, ruangan) sudah terisi atau belum.
     * Dipanggil SEBELUM transaksi supaya form pemanggil bisa menampilkan error validasi tanpa
     * membuat apa pun — kelas yang baru dibuat tidak perlu cek ini (pasti belum punya jadwal sama
     * sekali), tapi kelas yang sudah ada (edit) bisa saja sudah punya sebagian slot terisi.
     *
     * @return string|null Pesan error pada slot pertama yang bentrok, null kalau semuanya kosong.
     */
    public static function cekSlotTersedia(int $idKelas, int $jumlahPertemuan, ?int $idRuangan): ?string
    {
        for ($u = 1; $u <= $jumlahPertemuan; $u++) {
            $slotQ = Jadwal::where('id_kelas', $idKelas)->where('urutan_pertemuan', $u);
            if ($idRuangan) {
                $slotQ->where('id_ruangan', $idRuangan);
            } else {
                $slotQ->whereNull('id_ruangan');
            }
            if ($slotQ->exists()) {
                return "Slot pertemuan ke-{$u} untuk kelas dan ruangan ini sudah terisi.";
            }
        }

        return null;
    }

    /**
     * Buat $jumlahPertemuan baris Jadwal untuk kelas ber-id $idKelas, plus assign $dosenIds ke
     * setiap baris. WAJIB dipanggil di dalam DB::transaction oleh pemanggil (dibungkus bersama
     * pembuatan/pembaruan Kelas itu sendiri supaya keduanya all-or-nothing) — pengecekan validasi
     * (jam selesai > jam mulai, tanggal wajib kalau tanggal_hari_otomatis, cekSlotTersedia di
     * atas) juga tanggung jawab pemanggil, sama seperti Jadwal\Form::saveCreate().
     *
     * $kelas dipisah dari $idKelas (bukan cukup $kelas->id) supaya perilaku lama Jadwal\Form tetap
     * sama persis: id_kelas hasil validasi tetap dipakai untuk FK Jadwal walau lookup modelnya
     * kembali null (mis. kelas soft-deleted tapi masih lolos rule exists:kelas,id) — hanya
     * penentuan is_mingguan yang null-safe lewat $kelas.
     *
     * @param  array{id_jenis_kuliah: ?int, tanggal: ?string, hari: ?string, jam_mulai: ?string, jam_selesai: ?string, id_ruangan: ?int, is_active: bool, tanggal_hari_otomatis: bool}  $opts
     * @param  array<int>  $dosenIds
     */
    public static function generate(?Kelas $kelas, int $idKelas, int $jumlahPertemuan, array $opts, array $dosenIds): void
    {
        $isMingguan = ($kelas && $kelas->is_mingguan === true) || $opts['tanggal_hari_otomatis'];

        for ($u = 1; $u <= $jumlahPertemuan; $u++) {
            $tanggalSlot = null;
            $hariSlot = $opts['hari'];
            if ($opts['tanggal']) {
                if ($isMingguan) {
                    $dt = Carbon::parse($opts['tanggal'])->startOfDay()->addWeeks($u - 1);
                    $tanggalSlot = $dt->format('Y-m-d');
                    if ($opts['tanggal_hari_otomatis'] || ($kelas && $kelas->is_mingguan === true)) {
                        $hariSlot = self::hariDariTanggal($dt);
                    }
                } else {
                    $tanggalSlot = $u === 1 ? $opts['tanggal'] : null;
                }
            }

            $jadwal = Jadwal::create([
                'id_kelas' => $idKelas,
                'id_jenis_kuliah' => $opts['id_jenis_kuliah'],
                'tanggal' => $tanggalSlot,
                'hari' => $hariSlot,
                'jam_mulai' => $opts['jam_mulai'] ?: null,
                'jam_selesai' => $opts['jam_selesai'] ?: null,
                'id_ruangan' => $opts['id_ruangan'],
                'urutan_pertemuan' => $u,
                'is_active' => $opts['is_active'],
            ]);

            foreach ($dosenIds as $dosenId) {
                JadwalDosen::create([
                    'id_jadwal' => $jadwal->id,
                    'id_dosen' => $dosenId,
                    'status' => 'active',
                ]);
            }
        }
    }

    /**
     * Senin–Minggu dari tanggal — sama persis dengan JadwalController::hariDariTanggal.
     */
    private static function hariDariTanggal(Carbon $dt): string
    {
        $idx = (int) $dt->format('N') - 1;

        return Jadwal::HARI[$idx] ?? 'senin';
    }
}
