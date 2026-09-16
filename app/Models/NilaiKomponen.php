<?php

namespace App\Models;

use App\Models\Concerns\MencatatPelaku;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nilai per komponen penilaian (UTS, UAS, tugas, ...) untuk satu KRS.
 *
 * Tabel ini sebelumnya hanya diakses lewat DB::table. Model ini ada supaya baris-barisnya bisa
 * ikut di-soft-delete dan dipulihkan bersama KRS/nilainya lewat AturanHapusBerantai — foreign key
 * `ON DELETE CASCADE` di tabel ini hanya berlaku untuk hapus permanen, tidak untuk soft delete.
 * MencatatPelaku mempertahankan `deleted_by` yang dulu diisi manual oleh alur "hapus nilai".
 */
class NilaiKomponen extends Model
{
    use MencatatPelaku, SoftDeletes;

    protected $table = 'nilai_komponen';

    protected $fillable = [
        'id_krs',
        'id_jenis_penilaian',
        'nilai',
        'id_dosen',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function krs(): BelongsTo
    {
        return $this->belongsTo(Krs::class, 'id_krs');
    }
}
