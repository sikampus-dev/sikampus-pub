<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Semester;
use Illuminate\Support\Collection;

/**
 * Aturan pendaftaran KRS yang tidak bisa dijaga database.
 *
 * Unique `krs (id_mahasiswa, id_kelas)` hanya mencegah mahasiswa terdaftar dua kali di kelas yang
 * SAMA. Ia tidak mencegah mahasiswa terdaftar di dua KELAS PARALEL untuk mata kuliah dan semester
 * yang sama (mis. kelas kelompok A dan kelas kelompok B) — dan itulah yang terjadi: impor mencari
 * kelas dengan ->first() tanpa melihat kelompok kelas/angkatan, memilih kelas paralel yang salah,
 * cek "sudah terdaftar?" hanya melihat kelas tebakan itu, lalu membuat KRS kembar. Lebih dari tiga
 * ribu KRS kembar lahir dari sana, dan hasil tebakannya berubah-ubah mengikuti urutan baris di
 * database, bukan mengikuti mahasiswanya.
 *
 * Dua aturan di sini:
 * - Kelas untuk sebuah impor ditentukan dari kelompok kelas & angkatan mahasiswa — sama seperti
 *   filter halaman pengajuan KRS. Kalau tetap tidak bisa dibedakan, DITOLAK, tidak ditebak.
 * - Satu mahasiswa hanya boleh punya satu KRS hidup per mata kuliah per semester, di kelas mana
 *   pun. "Mata kuliah yang sama" dibandingkan lewat matkul, bukan kurikulum_matkul, supaya dua
 *   versi kurikulum dari mata kuliah yang sama tetap terhitung sama.
 */
class PendaftaranKrs
{
    /**
     * Kelas yang tepat untuk mahasiswa pada satu mata kuliah & semester, dari kelas prodinya.
     *
     * Satu kandidat dipakai langsung. Beberapa kandidat (kelas paralel) dipersempit dengan
     * kelompok kelas mahasiswa, lalu angkatannya; baru dipakai kalau tersisa tepat satu.
     *
     * @param  iterable<int>  $idKurikulumMatkul  seluruh versi kurikulum dari mata kuliah itu
     * @return array{0: Kelas|null, 1: string|null} [kelas, pesan kenapa tidak bisa ditentukan]
     */
    public static function tentukanKelas(Mahasiswa $mahasiswa, iterable $idKurikulumMatkul, Semester $semester, string $kodeMatkul): array
    {
        $ids = collect($idKurikulumMatkul)->map(fn ($id) => (int) $id)->all();

        $kandidat = Kelas::with('kelompokKelas')
            ->whereIn('id_kurikulum_matkul', $ids)
            ->where('id_semester', $semester->id)
            ->where('id_prodi', $mahasiswa->id_prodi)
            ->orderBy('id')
            ->get();

        if ($kandidat->isEmpty()) {
            // Kalau kelasnya ternyata ada di prodi lain, sebutkan — supaya admin tahu ini soal
            // ketidakcocokan prodi, bukan kelas yang belum dibuat.
            $prodiKelasLain = Kelas::with('prodi')
                ->whereIn('id_kurikulum_matkul', $ids)
                ->where('id_semester', $semester->id)
                ->first()?->prodi?->nama;

            return [null, "Kelas dengan semester '{$semester->kode}' dan mata kuliah '{$kodeMatkul}' tidak ditemukan pada prodi mahasiswa."
                .($prodiKelasLain ? " Kelas mata kuliah ini adanya di prodi '{$prodiKelasLain}', dan mahasiswa tidak bisa didaftarkan ke kelas prodi lain." : '')];
        }

        if ($kandidat->count() === 1) {
            return [$kandidat->first(), null];
        }

        $sesuaiKelompok = $mahasiswa->id_kelompok_kelas
            ? $kandidat->filter(fn (Kelas $k) => (int) $k->id_kelompok_kelas === (int) $mahasiswa->id_kelompok_kelas)
            : collect();
        if ($sesuaiKelompok->count() === 1) {
            return [$sesuaiKelompok->first(), null];
        }

        $dasar = $sesuaiKelompok->isNotEmpty() ? $sesuaiKelompok : $kandidat;
        $sesuaiAngkatan = $mahasiswa->id_semester_masuk
            ? $dasar->filter(fn (Kelas $k) => (int) $k->id_angkatan === (int) $mahasiswa->id_semester_masuk)
            : collect();
        if ($sesuaiAngkatan->count() === 1) {
            return [$sesuaiAngkatan->first(), null];
        }

        $daftar = $kandidat->map(fn (Kelas $k) => self::labelKelas($k))->implode(', ');

        return [null, "Ada {$kandidat->count()} kelas paralel untuk mata kuliah '{$kodeMatkul}' semester '{$semester->kode}' ({$daftar}), "
            .'dan tidak bisa ditentukan dari kelompok kelas maupun angkatan mahasiswa. Lengkapi kelompok kelas mahasiswa, '
            .'atau daftarkan lewat form Tambah KRS dengan memilih kelasnya langsung.'];
    }

    /**
     * KRS hidup mahasiswa untuk mata kuliah yang sama pada semester yang sama, di kelas mana pun.
     */
    public static function krsMataKuliahSama(int $idMahasiswa, int $idMatkul, int $idSemester, ?int $kecualiKrsId = null): ?Krs
    {
        return self::queryMataKuliahSama($idMahasiswa, $idMatkul, $idSemester, $kecualiKrsId)->first();
    }

    /** Sama dengan krsMataKuliahSama(), dengan mata kuliah & semester diambil dari sebuah kelas. */
    public static function krsMataKuliahSamaDenganKelas(int $idMahasiswa, Kelas $kelas, ?int $kecualiKrsId = null): ?Krs
    {
        $kelas->loadMissing('kurikulumMatkul');
        if (! $kelas->kurikulumMatkul) {
            return null;
        }

        return self::krsMataKuliahSama($idMahasiswa, (int) $kelas->kurikulumMatkul->id_matkul, (int) $kelas->id_semester, $kecualiKrsId);
    }

    /**
     * Seluruh KRS hidup mahasiswa untuk satu mata kuliah & semester di kelas prodinya — dipakai impor
     * nilai, yang harus menempel ke pendaftaran yang SUDAH ada, bukan menebak kelas lalu mencari KRS.
     *
     * @return Collection<int, Krs>
     */
    public static function krsUntukMataKuliah(Mahasiswa $mahasiswa, int $idMatkul, int $idSemester): Collection
    {
        return self::queryMataKuliahSama((int) $mahasiswa->id, $idMatkul, $idSemester)
            ->whereHas('kelas', fn ($q) => $q->where('id_prodi', $mahasiswa->id_prodi))
            ->get();
    }

    /**
     * Pelanggaran "sekali per semester" untuk sekumpulan kelas yang diajukan sekaligus: bentrok
     * dengan KRS hidup di kelas paralel lain, atau dua kelas dalam pengajuan yang sama untuk mata
     * kuliah yang sama. Kelas yang sudah menjadi KRS hidup mahasiswa itu sendiri dilewati, karena
     * mengajukan ulang kelas yang sama memang idempoten.
     *
     * @param  iterable<int>  $idKelasList
     * @return array<int, string>
     */
    public static function pelanggaranPengajuan(int $idMahasiswa, iterable $idKelasList): array
    {
        $pesan = [];
        $dipilih = [];

        foreach ($idKelasList as $idKelas) {
            $kelas = Kelas::with('kurikulumMatkul.matkul', 'kelompokKelas')->find($idKelas);
            if (! $kelas || ! $kelas->kurikulumMatkul) {
                continue;
            }

            if (Krs::where('id_mahasiswa', $idMahasiswa)->where('id_kelas', $kelas->id)->exists()) {
                continue;
            }

            $idMatkul = (int) $kelas->kurikulumMatkul->id_matkul;
            $lain = self::krsMataKuliahSama($idMahasiswa, $idMatkul, (int) $kelas->id_semester);
            if ($lain) {
                $pesan[] = self::pesanSudahTerdaftar($lain);

                continue;
            }

            $kunci = $idMatkul.':'.$kelas->id_semester;
            if (isset($dipilih[$kunci])) {
                $matkul = $kelas->kurikulumMatkul->matkul;
                $nama = $matkul ? trim(($matkul->kode ? $matkul->kode.' - ' : '').$matkul->nama) : 'Mata kuliah';
                $pesan[] = "{$nama} dipilih dua kali ({$dipilih[$kunci]} dan ".self::labelKelas($kelas).'). Pilih salah satu kelas saja.';

                continue;
            }

            $dipilih[$kunci] = self::labelKelas($kelas);
        }

        return array_values(array_unique($pesan));
    }

    public static function pesanSudahTerdaftar(Krs $krs): string
    {
        $krs->loadMissing('kelas.kurikulumMatkul.matkul', 'kelas.kelompokKelas');
        $matkul = $krs->kelas?->kurikulumMatkul?->matkul;
        $namaMatkul = $matkul ? trim(($matkul->kode ? $matkul->kode.' - ' : '').$matkul->nama) : 'mata kuliah ini';
        $kelas = $krs->kelas ? self::labelKelas($krs->kelas) : 'kelas lain';

        return "Mahasiswa sudah terdaftar pada {$namaMatkul} di semester yang sama ({$kelas}). "
            .'Satu mata kuliah hanya boleh diambil sekali per semester.';
    }

    /** "kelas BID24 · Bidan 2024" — kode dan kelompok, karena kode kelas sendiri tidak unik. */
    public static function labelKelas(Kelas $kelas): string
    {
        $kelas->loadMissing('kelompokKelas');
        $bagian = array_filter([
            filled($kelas->kode) ? "kelas {$kelas->kode}" : "kelas #{$kelas->id}",
            $kelas->kelompokKelas?->nama,
        ]);

        return implode(' · ', $bagian);
    }

    private static function queryMataKuliahSama(int $idMahasiswa, int $idMatkul, int $idSemester, ?int $kecualiKrsId = null)
    {
        return Krs::query()
            ->with('kelas.kelompokKelas')
            ->where('id_mahasiswa', $idMahasiswa)
            ->when($kecualiKrsId, fn ($q) => $q->where('id', '!=', $kecualiKrsId))
            ->whereHas('kelas', fn ($q) => $q
                ->where('id_semester', $idSemester)
                ->whereHas('kurikulumMatkul', fn ($km) => $km->where('id_matkul', $idMatkul)))
            ->orderBy('id');
    }
}
