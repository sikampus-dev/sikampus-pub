<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mahasiswa extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = [];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'krs' => 'KRS',
        'tagihan' => 'tagihan',
        'yudisium' => 'data yudisium',
        'wisudaMahasiswa' => 'data wisuda',
        'konversiNilai' => 'konversi nilai',
    ];

    protected $table = 'mahasiswa';

    protected $fillable = [
        'id_user',
        'nama',
        'nim',
        'email',
        'no_wa',
        'handphone',
        'alamat',
        'kode_pos',
        'id_semester_masuk',
        'id_prodi',
        'id_grup_mahasiswa',
        'id_kelompok_kelas',
        'id_kecamatan',
        'id_kota',
        'id_provinsi',
        'id_negara',
        'foto',
        'id_status_akademik',
        'jenis_kelamin',
        'id_tempat_lahir',
        'tanggal_lahir',
        'no_ktp',
        'sekolah_asal',
        'nis',
        'nisn',
        'npwp',
        'mulai_semester',
        'rt',
        'rw',
        'dusun',
        'kelurahan',
        'handphone',
        'penerima_kps',
        'no_kps',
        'ayah',
        'nik_ayah',
        'tgl_lahir_ayah',
        'id_pddk_ayah',
        'id_pekerjaan_ayah',
        'id_penghasilan_ayah',
        'ibu',
        'nik_ibu',
        'tgl_lahir_ibu',
        'id_pddk_ibu',
        'id_pekerjaan_ibu',
        'id_penghasilan_ibu',
        'wali',
        'nik_wali',
        'tgl_lahir_wali',
        'id_pddk_wali',
        'id_pekerjaan_wali',
        'id_penghasilan_wali',
        'jml_biaya_masuk',
        'sks_diakui',
        'id_jalur_masuk',
        'id_jenis_daftar',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_user' => 'integer',
        'id_semester_masuk' => 'integer',
        'id_prodi' => 'integer',
        'id_grup_mahasiswa' => 'integer',
        'id_kelompok_kelas' => 'integer',
        'id_status_akademik' => 'integer',
        'tanggal_lahir' => 'date',
        'tgl_lahir_ayah' => 'date',
        'tgl_lahir_ibu' => 'date',
        'tgl_lahir_wali' => 'date',
        'id_pddk_ayah' => 'integer',
        'id_pekerjaan_ayah' => 'integer',
        'id_penghasilan_ayah' => 'integer',
        'id_pddk_ibu' => 'integer',
        'id_pekerjaan_ibu' => 'integer',
        'id_penghasilan_ibu' => 'integer',
        'id_pddk_wali' => 'integer',
        'id_pekerjaan_wali' => 'integer',
        'id_penghasilan_wali' => 'integer',
        'jml_biaya_masuk' => 'decimal:2',
        'sks_diakui' => 'integer',
        'id_jalur_masuk' => 'integer',
        'id_jenis_daftar' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function semester_masuk()
    {
        return $this->belongsTo(Semester::class, 'id_semester_masuk');
    }

    public function jalur_masuk()
    {
        return $this->belongsTo(JalurMasuk::class, 'id_jalur_masuk');
    }

    public function jenis_daftar()
    {
        return $this->belongsTo(JenisDaftar::class, 'id_jenis_daftar');
    }

    public function kota()
    {
        return $this->belongsTo(Kota::class, 'id_kota');
    }

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'id_provinsi');
    }

    public function negara()
    {
        return $this->belongsTo(Negara::class, 'id_negara');
    }

    public function kelompok_kelas()
    {
        return $this->belongsTo(KelompokKelas::class, 'id_kelompok_kelas');
    }

    public function grup_mahasiswa()
    {
        return $this->belongsTo(GrupMahasiswa::class, 'id_grup_mahasiswa');
    }

    public function status_akademik()
    {
        return $this->belongsTo(StatusAkademik::class, 'id_status_akademik');
    }

    public function pendidikan_ayah()
    {
        return $this->belongsTo(Pendidikan::class, 'id_pddk_ayah');
    }

    public function pekerjaan_ayah()
    {
        return $this->belongsTo(Pekerjaan::class, 'id_pekerjaan_ayah');
    }

    public function penghasilan_ayah()
    {
        return $this->belongsTo(Penghasilan::class, 'id_penghasilan_ayah');
    }

    public function pendidikan_ibu()
    {
        return $this->belongsTo(Pendidikan::class, 'id_pddk_ibu');
    }

    public function pekerjaan_ibu()
    {
        return $this->belongsTo(Pekerjaan::class, 'id_pekerjaan_ibu');
    }

    public function penghasilan_ibu()
    {
        return $this->belongsTo(Penghasilan::class, 'id_penghasilan_ibu');
    }

    public function pendidikan_wali()
    {
        return $this->belongsTo(Pendidikan::class, 'id_pddk_wali');
    }

    public function pekerjaan_wali()
    {
        return $this->belongsTo(Pekerjaan::class, 'id_pekerjaan_wali');
    }

    public function penghasilan_wali()
    {
        return $this->belongsTo(Penghasilan::class, 'id_penghasilan_wali');
    }

    public function dosen_wali()
    {
        return $this->hasMany(DosenWali::class, 'id_mahasiswa');
    }

    public function krs()
    {
        return $this->hasMany(Krs::class, 'id_mahasiswa', 'id');
    }

    public function kategori_biaya_mahasiswa()
    {
        return $this->hasMany(KategoriBiayaMahasiswa::class, 'id_mahasiswa', 'id');
    }

    public function konversiNilai()
    {
        return $this->hasMany(KonversiNilai::class, 'id_mahasiswa');
    }

    public function ktm()
    {
        return $this->hasOne(Ktm::class, 'id_mahasiswa');
    }

    public function tagihan()
    {
        return $this->hasMany(Tagihan::class, 'id_mahasiswa');
    }

    public function yudisium()
    {
        return $this->hasMany(Yudisium::class, 'id_mahasiswa');
    }

    public function wisudaMahasiswa()
    {
        return $this->hasMany(WisudaMahasiswa::class, 'id_mahasiswa');
    }
}
