<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelas extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = ['jadwal', 'kelasDosen', 'ujian', 'rps'];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'krs' => 'KRS mahasiswa',
    ];

    protected $table = 'kelas';

    protected $fillable = [
        'kode',
        'id_kurikulum_matkul',
        'id_prodi',
        'id_kelompok_kelas',
        'id_angkatan',
        'id_semester',
        'id_dosen_pic',
        'jml_pertemuan',
        'is_mingguan',
        'kuota',
        'is_active',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_kurikulum_matkul' => 'integer',
        'id_prodi' => 'integer',
        'id_semester' => 'integer',
        'id_angkatan' => 'integer',
        'id_dosen_pic' => 'integer',
        'id_kelompok_kelas' => 'integer',
        'jml_pertemuan' => 'integer',
        'is_mingguan' => 'boolean',
        'kuota' => 'integer',
        'is_active' => 'boolean',
    ];

    public function kurikulumMatkul()
    {
        return $this->belongsTo(KurikulumMatkul::class, 'id_kurikulum_matkul');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function angkatan()
    {
        return $this->belongsTo(Semester::class, 'id_angkatan');
    }

    public function kelasDosen()
    {
        return $this->hasMany(KelasDosen::class, 'id_kelas');
    }

    public function dosenPic()
    {
        return $this->belongsTo(Dosen::class, 'id_dosen_pic');
    }

    public function kelompokKelas()
    {
        return $this->belongsTo(KelompokKelas::class, 'id_kelompok_kelas');
    }

    public function jadwal()
    {
        return $this->hasMany(Jadwal::class, 'id_kelas');
    }

    public function perkuliahan()
    {
        return $this->hasManyThrough(Perkuliahan::class, Jadwal::class, 'id_kelas', 'id_jadwal');
    }

    public function rps()
    {
        return $this->hasOne(Rps::class, 'id_kelas');
    }

    public function ujian()
    {
        return $this->hasMany(Ujian::class, 'id_kelas');
    }

    public function krs()
    {
        return $this->hasMany(Krs::class, 'id_kelas');
    }
}
