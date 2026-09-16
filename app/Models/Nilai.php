<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nilai extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = ['nilaiKomponen', 'nilaiRevisi'];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [];

    protected $table = 'nilai';

    protected $fillable = [
        'id_krs',
        'id_konversi_nilai',
        'sks',
        'angka_mutu',
        'huruf_mutu',
        'is_final',
        'revisi',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_krs' => 'integer',
        'id_konversi_nilai' => 'integer',
        'sks' => 'integer',
        'angka_mutu' => 'decimal:2',
        'is_final' => 'boolean',
        'revisi' => 'integer',
    ];

    public function krs()
    {
        return $this->belongsTo(Krs::class, 'id_krs');
    }

    public function konversiNilai()
    {
        return $this->belongsTo(KonversiNilai::class, 'id_konversi_nilai');
    }

    /**
     * Komponen dan revisi tercatat per KRS, bukan per baris nilai — tapi alur "hapus nilai" sejak
     * awal memperlakukannya sebagai milik nilai (dihapus bersamanya). Relasi lewat id_krs ini
     * menjadikan aturan itu deklaratif, sehingga penghapusan nilai kini juga bisa dipulihkan utuh.
     */
    public function nilaiKomponen()
    {
        return $this->hasMany(NilaiKomponen::class, 'id_krs', 'id_krs');
    }

    public function nilaiRevisi()
    {
        return $this->hasMany(NilaiRevisi::class, 'id_krs', 'id_krs');
    }
}
