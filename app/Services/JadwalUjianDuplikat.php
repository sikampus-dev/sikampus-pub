<?php

namespace App\Services;

use App\Models\Ujian;

/**
 * Pencarian bentrokan pada unique key `ujian_unique` (id_kelas, id_semester, jenis_ujian).
 *
 * Unique itu TIDAK menyertakan deleted_at, sedangkan Ujian memakai SoftDeletes — jadi baris yang
 * sudah dihapus tetap menduduki slot unique-nya. Cek duplikat lewat Ujian::query() biasa tidak
 * melihat baris itu (kena global scope SoftDeletes), lolos validasi, lalu menabrak constraint di
 * database dan berakhir sebagai 500. Karena itu pencarian di sini SELALU withTrashed().
 */
class JadwalUjianDuplikat
{
    /**
     * Baris yang menduduki kombinasi unik ini — hidup maupun sudah terhapus.
     *
     * @param  int|null  $kecualiId  id baris yang sedang diedit, supaya tidak bentrok dengan dirinya sendiri
     */
    public static function cari(int $idKelas, int $idSemester, string $jenisUjian, ?int $kecualiId = null): ?Ujian
    {
        return Ujian::withTrashed()
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->where('id_kelas', $idKelas)
            ->where('id_semester', $idSemester)
            ->where('jenis_ujian', $jenisUjian)
            ->first();
    }

    /** Pesan untuk bentrokan dengan jadwal yang masih hidup. */
    public static function pesanBentrokHidup(): string
    {
        return 'Kombinasi kelas, semester, dan jenis ujian harus unik — jadwal untuk kombinasi ini sudah ada.';
    }

    /**
     * Pesan untuk bentrokan dengan jadwal yang sudah dihapus.
     *
     * Dipakai jalur API yang tidak punya tombol; jalur Livewire memakai modal dengan dua aksi.
     */
    public static function pesanBentrokTerhapus(): string
    {
        return 'Kombinasi ini masih dipakai jadwal ujian yang sudah dihapus. Pulihkan jadwal itu, '
            .'atau hapus permanen lebih dulu sebelum membuat yang baru.';
    }
}
