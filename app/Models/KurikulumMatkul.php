<?php

namespace App\Models;

use App\Models\Concerns\AturanHapusBerantai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KurikulumMatkul extends Model
{
    use AturanHapusBerantai, HasFactory, SoftDeletes;

    /** Anak yang ikut di-soft-delete dan dipulihkan bersama baris ini (lihat AturanHapusBerantai). */
    protected array $hapusBerantai = ['bobotPenilaian'];

    /** Anak berisi riwayat yang, selama masih hidup, menolak baris ini dihapus. */
    protected array $hapusDiblokirOleh = [
        'kelas' => 'kelas',
    ];

    protected $table = 'kurikulum_matkul';

    protected $fillable = [
        'id_kurikulum',
        'id_matkul',
        'kode_matkul',
        'nama_matkul',
        'nama_matkul_en',
        'sks',
        'semester_rekomendasi',
        'is_wajib',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_kurikulum' => 'integer',
        'id_matkul' => 'integer',
        'kode_matkul' => 'string',
        'nama_matkul' => 'string',
        'nama_matkul_en' => 'string',
        'sks' => 'integer',
        'semester_rekomendasi' => 'integer',
        'is_wajib' => 'boolean',
    ];

    public function kurikulum()
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum');
    }

    public function matkul()
    {
        return $this->belongsTo(Matkul::class, 'id_matkul');
    }

    public function bobotPenilaian()
    {
        return $this->hasMany(BobotPenilaian::class, 'id_kurikulum_matkul');
    }

    /**
     * kode_matkul/nama_matkul/sks pada baris ini adalah override opsional (mis. mata kuliah yang
     * namanya berbeda di kurikulum tertentu) — kalau kosong, jatuh ke data induk di `matkul`.
     * Dipakai di mana pun tampilan perlu label mata kuliah (dosen, admin, laporan) supaya logika
     * fallback ini tidak diulang-ulang berbeda-beda di setiap tempat.
     */
    public function kodeMatkulLabel(): ?string
    {
        return $this->kode_matkul ?: $this->matkul?->kode;
    }

    public function namaMatkulLabel(): ?string
    {
        return $this->nama_matkul ?: $this->matkul?->nama;
    }

    public function sksLabel(): ?int
    {
        return $this->sks ?? $this->matkul?->sks;
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'id_kurikulum_matkul');
    }
}
