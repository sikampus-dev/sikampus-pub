<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prodi extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = [];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'mahasiswa' => 'mahasiswa',
        'kelas' => 'kelas',
        'kurikulum' => 'kurikulum',
        'matkul' => 'mata kuliah',
    ];

    protected $table = 'prodi';

    protected $fillable = [
        'nama',
        'nama_en',
        'kode',
        'deskripsi',
        'logo',
        'website',
        'email',
        'telepon',
        'alamat',
        'kota',
        'provinsi',
        'kode_pos',
        'negara',
        'id_fakultas',
        'id_kaprodi',
        'id_sekprodi',
        'id_jenjang',
        'id_semester_aktif',
        'sks_minimal',
        'ipk_lulus_minimal',
        'gelar',
        'gelar_singkat',
        'maks_dosen_pembimbing',
        'maks_dosen_penguji',
        'is_pmb_open',
        'status',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_fakultas' => 'integer',
        'id_kaprodi' => 'integer',
        'id_sekprodi' => 'integer',
        'id_jenjang' => 'integer',
        'id_semester_aktif' => 'integer',
        'sks_minimal' => 'integer',
        'ipk_lulus_minimal' => 'float',
        'maks_dosen_pembimbing' => 'integer',
        'maks_dosen_penguji' => 'integer',
        'is_pmb_open' => 'boolean',
    ];

    public function fakultas()
    {
        return $this->belongsTo(Fakultas::class, 'id_fakultas');
    }

    public function kaprodi()
    {
        return $this->belongsTo(Dosen::class, 'id_kaprodi');
    }

    public function sekprodi()
    {
        return $this->belongsTo(Dosen::class, 'id_sekprodi');
    }

    public function jenjang()
    {
        return $this->belongsTo(Jenjang::class, 'id_jenjang');
    }

    public function semesterAktif()
    {
        return $this->belongsTo(Semester::class, 'id_semester_aktif');
    }

    public function mahasiswa()
    {
        return $this->hasMany(Mahasiswa::class, 'id_prodi');
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'id_prodi');
    }

    public function kurikulum()
    {
        return $this->hasMany(Kurikulum::class, 'id_prodi');
    }

    public function matkul()
    {
        return $this->hasMany(Matkul::class, 'id_prodi');
    }
}
