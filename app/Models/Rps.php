<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rps extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = ['rpsCpl', 'rpsCpmk', 'rpsPembelajaran'];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [];

    protected $table = 'rps';

    protected $fillable = [
        'id_kelas',
        'deskripsi_matkul',
        'deskripsi_matkul_en',
        'materi_kuliah',
        'model_pembelajaran',
        'pustaka_utama',
        'pustaka_pendukung',
        'media_perangkat_lunak',
        'media_perangkat_keras',
        'tanggal_penyusunan',
        'file_rps',
        'created_by',
        'verified_by',
        'approved_by',
        'verified_at',
        'approved_at',
    ];

    protected $casts = [
        'id_kelas' => 'integer',
        'tanggal_penyusunan' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'id_kelas');
    }

    public function rpsCpl(): HasMany
    {
        return $this->hasMany(RpsCpl::class, 'id_rps')->orderBy('id');
    }

    public function rpsCpmk(): HasMany
    {
        return $this->hasMany(RpsCpmk::class, 'id_rps')->orderBy('id');
    }

    public function rpsPembelajaran(): HasMany
    {
        return $this->hasMany(RpsPembelajaran::class, 'id_rps')
            ->orderBy('urutan_pertemuan')
            ->orderBy('id');
    }
}
