<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KalenderAkademik extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kalender_akademik';

    protected $fillable = [
        'nama',
        'kategori',
        'id_semester',
        'tanggal_mulai',
        'tanggal_selesai',
        'deskripsi',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    /**
     * Kategori tetap (bukan tabel referensi terpisah) — daftar ini yang dipakai select di form
     * admin. Menambah kategori baru cukup menambah baris di sini.
     */
    public const KATEGORI_OPTIONS = [
        'krs' => 'Pengisian KRS',
        'nilai' => 'Pengisian Nilai',
        'libur' => 'Hari Libur',
        'wisuda' => 'Wisuda',
        'lainnya' => 'Lainnya',
    ];

    /**
     * Kategori yang dimaksudkan untuk "menggerbang" fitur lain (dipakai
     * App\Services\KalenderAkademikGateService di Fase 3) — sisanya (libur, wisuda, lainnya)
     * murni informasi dan tidak pernah memblokir apa pun.
     */
    public const KATEGORI_GATING = ['krs', 'nilai'];

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }
}
