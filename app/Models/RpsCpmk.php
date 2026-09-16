<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RpsCpmk extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = ['rpsSubcpmk'];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [];

    protected $table = 'rps_cpmk';

    protected $fillable = [
        'id_rps',
        'cpmk',
        'cpmk_en',
    ];

    protected $casts = [
        'id_rps' => 'integer',
    ];

    public function rps(): BelongsTo
    {
        return $this->belongsTo(Rps::class, 'id_rps');
    }

    public function rpsSubcpmk(): HasMany
    {
        return $this->hasMany(RpsSubcpmk::class, 'id_cpmk')->orderBy('id');
    }
}
