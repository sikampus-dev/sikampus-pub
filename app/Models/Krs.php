<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Krs extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = ['nilaiBelumFinal', 'nilaiKomponen', 'nilaiRevisi'];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'nilaiFinal' => 'nilai final',
    ];

    protected $table = 'krs';

    protected $fillable = ['id_mahasiswa', 'id_kelas', 'approved_by', 'approved_at'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_mahasiswa' => 'integer',
        'id_kelas' => 'integer',
        'approved_by' => 'string',
        'approved_at' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa', 'id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id');
    }

    /**
     * Nilai dipecah dua karena aturan hapusnya berbeda: nilai final adalah catatan akademik resmi
     * (menahan penghapusan KRS), sedangkan nilai yang belum final masih pekerjaan dosen yang tidak
     * berarti tanpa KRS-nya (ikut terhapus). `is_final` NULL diperlakukan sebagai belum final.
     */
    public function nilaiFinal()
    {
        return $this->hasMany(Nilai::class, 'id_krs')->where('is_final', true);
    }

    public function nilaiBelumFinal()
    {
        return $this->hasMany(Nilai::class, 'id_krs')
            ->where(fn ($q) => $q->where('is_final', false)->orWhereNull('is_final'));
    }

    /** Komponen bisa ada tanpa baris nilai (dosen sudah input UTS/UAS, nilai akhir belum dihitung). */
    public function nilaiKomponen()
    {
        return $this->hasMany(NilaiKomponen::class, 'id_krs');
    }

    public function nilaiRevisi()
    {
        return $this->hasMany(NilaiRevisi::class, 'id_krs');
    }
}
