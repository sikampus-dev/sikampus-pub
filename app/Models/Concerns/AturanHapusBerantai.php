<?php

namespace App\Models\Concerns;

use App\Exceptions\PenghapusanDiblokir;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Soft delete yang sadar relasi.
 *
 * Foreign key database (`ON DELETE CASCADE`) tidak pernah terpicu oleh soft delete — soft delete
 * adalah UPDATE deleted_at, bukan DELETE — jadi aturannya harus hidup di aplikasi. Model yang
 * memakai trait ini mendeklarasikan dua daftar relasi:
 *
 *   protected array $hapusBerantai = ['jadwal', 'kelasDosen'];
 *       Anak yang tidak punya arti tanpa induknya. Ikut di-soft-delete bersama induk, dan ikut
 *       dipulihkan bersamanya.
 *
 *   protected array $hapusDiblokirOleh = ['krs' => 'KRS mahasiswa'];
 *       Anak yang menyimpan riwayat akademik/keuangan. Selama masih ada yang hidup, induk
 *       menolak dihapus (PenghapusanDiblokir) — lebih baik ditolak dengan jelas daripada riwayat
 *       itu ikut lenyap dari laporan tanpa ada yang sadar.
 *
 * Tiga keputusan yang mudah dirusak:
 *
 * - Pemeriksaan penolakan berjalan di delete(), SEBELUM event `deleting`. Event model tidak punya
 *   urutan prioritas; kalau pemeriksaan ini jalan setelah MencatatPelaku, `deleted_by` sudah
 *   tersimpan untuk baris yang akhirnya batal dihapus. Pemeriksaannya juga menelusuri seluruh
 *   pohon cascade lebih dulu, supaya tidak ada anak yang terlanjur terhapus sebelum cucu di
 *   bawahnya ternyata menolak.
 * - Seluruh pohon cascade diberi deleted_at yang SAMA PERSIS dengan induk (jam dibekukan selama
 *   cascade). Hanya dengan itu pemulihan bisa simetris: yang dipulihkan bersama induk hanya anak
 *   yang terhapus BERSAMA induk, bukan anak yang memang sudah dihapus sendiri sebelumnya.
 * - Hanya berlaku untuk $model->delete() / ->restore(). Query builder (Model::where()->delete())
 *   melewati event model sama sekali, jadi jalur seperti itu harus memakai hapus per-model.
 *
 * forceDelete tidak disentuh: di situ foreign key database yang berlaku.
 */
trait AturanHapusBerantai
{
    /** deleted_at induk yang direkam di `restoring`, dipakai `restored` untuk mencari anaknya. */
    protected ?string $waktuHapusUntukPulih = null;

    public static function bootAturanHapusBerantai(): void
    {
        static::deleted(function (Model $model): void {
            if ($model->isForceDeleting()) {
                return;
            }

            $model->hapusAnakBerantai();
        });

        static::restoring(function (Model $model): void {
            $model->waktuHapusUntukPulih = $model->fromDateTime($model->{$model->getDeletedAtColumn()});
        });

        static::restored(function (Model $model): void {
            $model->pulihkanAnakBerantai();
        });
    }

    public function initializeAturanHapusBerantai(): void
    {
        if (! method_exists($this, 'runSoftDelete')) {
            throw new LogicException(static::class.' memakai AturanHapusBerantai tanpa SoftDeletes — cascade akan menghapus permanen.');
        }
    }

    public function delete()
    {
        if (! $this->isForceDeleting() && $this->exists) {
            $pemakai = $this->pemakaiYangMemblokirHapus();
            if ($pemakai !== []) {
                throw new PenghapusanDiblokir($pemakai);
            }
        }

        return parent::delete();
    }

    /**
     * Baris hidup yang menahan penghapusan, di model ini DAN di seluruh anak cascade-nya.
     *
     * @return array<string, int> label => jumlah
     */
    public function pemakaiYangMemblokirHapus(): array
    {
        $pemakai = [];

        foreach ($this->hapusDiblokirOleh ?? [] as $relasi => $label) {
            $jumlah = $this->{$relasi}()->count();
            if ($jumlah > 0) {
                $pemakai[$label] = ($pemakai[$label] ?? 0) + $jumlah;
            }
        }

        foreach ($this->hapusBerantai ?? [] as $relasi) {
            foreach ($this->{$relasi}()->get() as $anak) {
                if (! method_exists($anak, 'pemakaiYangMemblokirHapus')) {
                    continue;
                }
                foreach ($anak->pemakaiYangMemblokirHapus() as $label => $jumlah) {
                    $pemakai[$label] = ($pemakai[$label] ?? 0) + $jumlah;
                }
            }
        }

        return $pemakai;
    }

    protected function hapusAnakBerantai(): void
    {
        if (($this->hapusBerantai ?? []) === []) {
            return;
        }

        // Bekukan jam pada deleted_at induk: setiap soft delete di pohon ini (termasuk cucu,
        // lewat event deleted milik anak) memakai Date::now(), jadi semuanya tercatat identik.
        $jamSebelumnya = Carbon::getTestNow();
        Carbon::setTestNow($this->{$this->getDeletedAtColumn()});

        try {
            foreach ($this->hapusBerantai as $relasi) {
                foreach ($this->{$relasi}()->get() as $anak) {
                    $anak->delete();
                }
            }
        } finally {
            Carbon::setTestNow($jamSebelumnya);
        }
    }

    protected function pulihkanAnakBerantai(): void
    {
        $waktu = $this->waktuHapusUntukPulih;
        $this->waktuHapusUntukPulih = null;

        if ($waktu === null) {
            return;
        }

        foreach ($this->hapusBerantai ?? [] as $relasi) {
            $query = $this->{$relasi}();

            $query->onlyTrashed()
                ->where($query->getRelated()->getQualifiedDeletedAtColumn(), $waktu)
                ->get()
                ->each
                ->restore();
        }
    }
}
