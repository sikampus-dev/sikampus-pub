<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jadwal extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = ['dosen', 'materiPerkuliahan', 'tugas'];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'perkuliahan' => 'pertemuan perkuliahan',
    ];

    protected $table = 'jadwal';

    protected $fillable = [
        'id_kelas',
        'id_jenis_kuliah',
        'tanggal',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'id_ruangan',
        'urutan_pertemuan',
        'bahasan',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_kelas' => 'integer',
        'id_jenis_kuliah' => 'integer',
        'tanggal' => 'date',
        'id_ruangan' => 'integer',
        'urutan_pertemuan' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string',
    ];

    const HARI = [
        'senin',
        'selasa',
        'rabu',
        'kamis',
        'jumat',
        'sabtu',
        'minggu',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas');
    }

    public function jenisKuliah()
    {
        return $this->belongsTo(JenisKuliah::class, 'id_jenis_kuliah');
    }

    public function dosen()
    {
        return $this->hasMany(JadwalDosen::class, 'id_jadwal');
    }

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'id_ruangan');
    }

    public function materiPerkuliahan()
    {
        return $this->hasMany(MateriPerkuliahan::class, 'id_jadwal');
    }

    public function tugas()
    {
        return $this->hasMany(Tugas::class, 'id_jadwal');
    }

    public function perkuliahan()
    {
        return $this->hasMany(Perkuliahan::class, 'id_jadwal');
    }
}
