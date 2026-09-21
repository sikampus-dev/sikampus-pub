<?php

namespace App\Services;

use App\Models\Mahasiswa;

/**
 * Kolom Excel data mahasiswa — satu sumber untuk template impor
 * (MahasiswaController::downloadTemplate) DAN ekspor (Web\MahasiswaExportController).
 *
 * Urutannya adalah kontrak dengan App\Livewire\Admin\Mahasiswa\Import dan
 * MahasiswaController::import, yang membaca kolom menurut POSISI ($row[0] = Nama, ...). Menyisipkan
 * atau memindah kolom di sini tanpa menyesuaikan kedua pembaca itu akan membuat impor menaruh data
 * di field yang salah. MahasiswaExportTest mengunci urutan ini lewat uji ekspor -> impor ulang.
 *
 * baris() menulis setiap nilai dalam bentuk yang dibaca impor: prodi & semester masuk lewat KODE,
 * rujukan lain lewat NAMA, tanggal `Y-m-d`.
 */
class KolomExcelMahasiswa
{
    public const HEADER = [
        'Nama*',
        'NIM',
        'Email',
        'No. HP',
        'Handphone',
        'Jenis Kelamin (L/P)',
        'ID Tempat Lahir (string kode kota/wilayah; sesuai kolom di form mahasiswa)',
        'Tanggal Lahir (YYYY-MM-DD atau tanggal Excel)',
        'No. KTP',
        'Status Akademik (Nama)',
        'Alamat',
        'RT',
        'RW',
        'Dusun',
        'Kelurahan',
        'Kode Pos',
        'ID Kecamatan (string kode wilayah; bukan nama teks)',
        'Negara (Nama Negara)',
        'Provinsi (Nama Provinsi)',
        'Kota (Nama Kota)',
        'Kode Prodi*',
        'Kelas Mahasiswa (Nama)',
        'Kode Semester Masuk',
        'Jalur Masuk (Nama Jalur)',
        'Jenis Daftar (Nama Jenis)',
        'Mulai Semester',
        'SKS Diakui',
        'Sekolah Asal',
        'NIS',
        'NISN',
        'NPWP',
        'Nama Ayah',
        'NIK Ayah',
        'Tanggal Lahir Ayah (YYYY-MM-DD)',
        'Pendidikan Ayah (Nama)',
        'Pekerjaan Ayah (Nama)',
        'Penghasilan Ayah (Nama)',
        'Nama Ibu',
        'NIK Ibu',
        'Tanggal Lahir Ibu (YYYY-MM-DD)',
        'Pendidikan Ibu (Nama)',
        'Pekerjaan Ibu (Nama)',
        'Penghasilan Ibu (Nama)',
        'Nama Wali',
        'NIK Wali',
        'Tanggal Lahir Wali (YYYY-MM-DD)',
        'Pendidikan Wali (Nama)',
        'Pekerjaan Wali (Nama)',
        'Penghasilan Wali (Nama)',
        'Jumlah Biaya Masuk',
        'Penerima KPS',
        'No. KPS',
        'Foto (path relatif di storage disk public; file harus sudah diunggah ke server, contoh: mahasiswa/foto/nama_file.jpg)',
    ];

    /**
     * Posisi kolom (0-based) yang ditulis sebagai ANGKA. Kolom lain ditulis sebagai teks eksplisit:
     * kalau tidak, Excel mengubah NIM/NIK/no. HP/kode pos jadi angka — nol di depan hilang dan NIK
     * 16 digit melewati presisi 15 digit Excel, sehingga impor ulang menyimpan nomor yang berbeda.
     */
    public const KOLOM_ANGKA = [26, 49];

    /** Relasi yang dibutuhkan baris(); eager-load sebelum memanggilnya untuk banyak mahasiswa. */
    public const RELASI = [
        'status_akademik', 'negara', 'provinsi', 'kota', 'prodi', 'kelompok_kelas', 'semester_masuk',
        'jalur_masuk', 'jenis_daftar',
        'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah',
        'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu',
        'pendidikan_wali', 'pekerjaan_wali', 'penghasilan_wali',
    ];

    /**
     * Satu baris ekspor, urut sesuai HEADER.
     *
     * @return array<int, string|int|float|null>
     */
    public static function baris(Mahasiswa $m): array
    {
        $tanggal = fn ($value) => $value ? $value->format('Y-m-d') : null;

        return [
            $m->nama,
            $m->nim,
            $m->email,
            $m->no_wa,
            $m->handphone,
            $m->jenis_kelamin,
            $m->id_tempat_lahir,
            $tanggal($m->tanggal_lahir),
            $m->no_ktp,
            $m->status_akademik?->nama,
            $m->alamat,
            $m->rt,
            $m->rw,
            $m->dusun,
            $m->kelurahan,
            $m->kode_pos,
            $m->id_kecamatan,
            $m->negara?->nama,
            $m->provinsi?->nama,
            $m->kota?->nama,
            $m->prodi?->kode,
            $m->kelompok_kelas?->nama,
            $m->semester_masuk?->kode,
            $m->jalur_masuk?->nama,
            $m->jenis_daftar?->nama,
            $m->mulai_semester,
            $m->sks_diakui !== null ? (int) $m->sks_diakui : null,
            $m->sekolah_asal,
            $m->nis,
            $m->nisn,
            $m->npwp,
            $m->ayah,
            $m->nik_ayah,
            $tanggal($m->tgl_lahir_ayah),
            $m->pendidikan_ayah?->nama,
            $m->pekerjaan_ayah?->nama,
            $m->penghasilan_ayah?->nama,
            $m->ibu,
            $m->nik_ibu,
            $tanggal($m->tgl_lahir_ibu),
            $m->pendidikan_ibu?->nama,
            $m->pekerjaan_ibu?->nama,
            $m->penghasilan_ibu?->nama,
            $m->wali,
            $m->nik_wali,
            $tanggal($m->tgl_lahir_wali),
            $m->pendidikan_wali?->nama,
            $m->pekerjaan_wali?->nama,
            $m->penghasilan_wali?->nama,
            $m->jml_biaya_masuk !== null ? (float) $m->jml_biaya_masuk : null,
            $m->penerima_kps,
            $m->no_kps,
            $m->foto,
        ];
    }
}
