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

    /**
     * Pulihkan nilai soft-deleted milik KRS ini sebagai pengganti Nilai::create(), kalau ada.
     *
     * `unique('id_krs')` di tabel nilai ikut menghitung baris soft-deleted, jadi jalur yang mencari
     * nilai hidup lalu jatuh ke create() (atau updateOrCreate, yang juga hanya melihat baris hidup)
     * melanggar constraint begitu KRS itu pernah punya nilai yang dihapus. Panggil ini tepat di
     * titik create: setelahnya baris itu hidup lagi dan akan ditemukan sebagai nilai yang ada.
     *
     * Kolom nilainya dikosongkan ke default create() — pemanggil sedang MEMBUAT nilai, dan isi baris
     * yang sudah dihapus tidak boleh bocor ke sana (is_final=true lama yang ikut hidup akan mengunci
     * nilai yang semestinya belum final). Yang ikut kembali hanya komponen & revisi yang terhapus
     * bersamanya (AturanHapusBerantai), beserta kolom `revisi` yang menghitung baris-baris itu.
     */
    public static function pulihkanUntukNilaiBaru(int $idKrs): ?self
    {
        $nilai = static::onlyTrashed()->where('id_krs', $idKrs)->first();
        if (! $nilai) {
            return null;
        }

        // Di-set sebelum restore() supaya ikut tersimpan dalam save() yang sama.
        $nilai->fill(['sks' => null, 'angka_mutu' => null, 'huruf_mutu' => null, 'is_final' => false]);
        $nilai->deleted_by = null;
        $nilai->restore();

        return $nilai;
    }

    /**
     * Pulihkan nilai soft-deleted milik konversi ini sebagai pengganti Nilai::create(), kalau ada.
     *
     * `unique('id_konversi_nilai')` di tabel nilai ikut menghitung baris soft-deleted, jadi transfer
     * konversi yang mencari nilai hidup lalu jatuh ke create() melanggar constraint begitu konversi
     * itu pernah punya nilai yang dihapus. Panggil ini tepat di titik create, lalu isi nilainya.
     *
     * Kolom nilainya dikosongkan dulu — pemanggil sedang MEMBUAT nilai, dan isi baris yang sudah
     * dihapus tidak boleh bocor ke sana. Nilai konversi tidak punya id_krs, jadi tidak ada komponen
     * atau revisi yang ikut kembali lewat AturanHapusBerantai.
     */
    public static function pulihkanUntukKonversiBaru(int $idKonversiNilai): ?self
    {
        $nilai = static::onlyTrashed()->where('id_konversi_nilai', $idKonversiNilai)->first();
        if (! $nilai) {
            return null;
        }

        // Di-set sebelum restore() supaya ikut tersimpan dalam save() yang sama.
        $nilai->fill(['sks' => null, 'angka_mutu' => null, 'huruf_mutu' => null, 'is_final' => false, 'revisi' => 0]);
        $nilai->deleted_by = null;
        $nilai->restore();

        return $nilai;
    }
}
