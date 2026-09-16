<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tugas extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = [];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'tugasMahasiswa' => 'pengumpulan tugas mahasiswa',
    ];

    protected $table = 'tugas';

    protected $fillable = [
        'id_jadwal',
        'id_dosen',
        'nama',
        'deskripsi',
        'file',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected $casts = [
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }

    public function tugasMahasiswa(): HasMany
    {
        return $this->hasMany(TugasMahasiswa::class, 'id_tugas');
    }
}
