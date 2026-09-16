<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Semester extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = [];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'kelas' => 'kelas',
        'kelasAngkatan' => 'kelas (sebagai angkatan)',
        'tagihan' => 'tagihan',
        'mahasiswaMasuk' => 'mahasiswa (semester masuk)',
    ];

    protected $table = 'semester';

    protected $fillable = [
        'kode',
        'nama',
        'is_active',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'id_semester');
    }

    public function kelasAngkatan()
    {
        return $this->hasMany(Kelas::class, 'id_angkatan');
    }

    public function tagihan()
    {
        return $this->hasMany(Tagihan::class, 'id_semester');
    }

    public function mahasiswaMasuk()
    {
        return $this->hasMany(Mahasiswa::class, 'id_semester_masuk');
    }
}
