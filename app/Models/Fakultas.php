<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fakultas extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = [];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'prodi' => 'program studi',
    ];

    protected $table = 'fakultas';

    protected $fillable = ['nama', 'kode', 'deskripsi', 'website', 'email', 'telepon', 'alamat', 'kota', 'provinsi', 'kode_pos', 'negara', 'id_dekan', 'status'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_dekan' => 'integer',
        'status' => 'string',
    ];

    public function dekan()
    {
        return $this->belongsTo(Dosen::class, 'id_dekan');
    }

    public function prodi()
    {
        return $this->hasMany(Prodi::class, 'id_fakultas');
    }
}
