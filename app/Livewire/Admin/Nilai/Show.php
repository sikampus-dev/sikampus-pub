<?php

namespace App\Livewire\Admin\Nilai;

use App\Livewire\Admin\Nilai\Concerns\ForwardsIndexState;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Semester;
use App\Services\SemesterService;
use App\Services\UrutanMatkulService;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $mahasiswaId;

    public string $search = '';

    public string $filterSemester = '';

    public ?int $confirmDeleteId = null;

    // Nilai yang sudah soft-deleted disembunyikan secara default — dinyalakan lewat toggle supaya
    // admin bisa memulihkan atau menghapusnya permanen. Sama seperti pola di
    // App\Livewire\Admin\Kelas\Index.
    public bool $showTrashed = false;

    public ?int $confirmForceDeleteId = null;

    // Tabel yang constrained('nilai')->restrictOnDelete() — restrict itu berlaku di level baris DB
    // apa adanya, termasuk baris yang di tabel itu sendiri sudah soft-deleted, jadi dicek lewat
    // DB::table mentah di forceDeleteNilai(). Sama seperti FORCE_DELETE_BLOCKERS di Kelas\Index.
    private const FORCE_DELETE_BLOCKERS = [
        'konversi_nilai' => ['column' => 'id_nilai', 'label' => 'konversi nilai'],
    ];

    public function mount(int $id): void
    {
        $this->mahasiswaId = $id;
        $this->resolveBackUrl();

        $mahasiswa = Mahasiswa::findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data nilai mahasiswa ini.');
            }
        }
    }

    public function updatingSearch(): void
    {
        unset($this->krsList, $this->statistik, $this->semesterDitempuh);
    }

    public function updatingFilterSemester(): void
    {
        unset($this->krsList, $this->statistik, $this->semesterDitempuh);
    }

    #[Computed]
    public function mahasiswa()
    {
        return Mahasiswa::with(['prodi', 'semester_masuk'])->findOrFail($this->mahasiswaId);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'kode', 'nama'])
            ->mapWithKeys(fn ($semester) => [$semester->id => "{$semester->nama} ({$semester->kode})"])
            ->all();
    }

    /**
     * Sama persis dengan NilaiController::show.
     */
    #[Computed]
    public function krsList()
    {
        $query = Krs::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi',
            'kelas.semester',
        ])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at');

        if ($this->filterSemester !== '') {
            $semesterId = (int) $this->filterSemester;
            $query->whereHas('kelas', function ($q) use ($semesterId) {
                $q->where('id_semester', $semesterId);
            });
        }

        if ($this->search !== '') {
            $s = $this->search;
            $query->whereHas('kelas.kurikulumMatkul.matkul', function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                    ->orWhere('kode', 'like', "%{$s}%");
            });
        }

        // Diurutkan berdasarkan nama mata kuliah; created_at tetap jadi tie-breaker karena
        // sortBy di PHP 8 stabil.
        $krsList = UrutanMatkulService::urutkanKrs($query->orderByDesc('created_at')->get());

        $krsIds = $krsList->pluck('id')->toArray();
        $nilaiMap = empty($krsIds)
            ? collect()
            : Nilai::whereIn('id_krs', $krsIds)->whereNull('deleted_at')->get()->keyBy('id_krs');

        foreach ($krsList as $krs) {
            $krs->setRelation('nilai', $nilaiMap->get($krs->id));
        }

        return $krsList;
    }

    /**
     * Nilai terhapus per id_krs, hanya saat toggle menyala. nilai.id_krs unik termasuk untuk baris
     * soft-deleted, jadi satu KRS paling banyak punya satu nilai — hidup ATAU terhapus, tidak
     * pernah keduanya. Karena itu nilai terhapus cukup ditampilkan di baris KRS-nya sendiri.
     * Tidak ikut statistik/IPK maupun export, yang tetap hanya membaca nilai hidup.
     */
    #[Computed]
    public function trashedNilaiMap()
    {
        if (! $this->showTrashed) {
            return collect();
        }

        $krsIds = $this->krsList->pluck('id')->all();

        return empty($krsIds)
            ? collect()
            : Nilai::onlyTrashed()->whereIn('id_krs', $krsIds)->get()->keyBy('id_krs');
    }

    public function updatingShowTrashed(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'nilai', 'delete'), 403, 'Anda tidak memiliki hak untuk melihat nilai yang dihapus.');

        unset($this->trashedNilaiMap);
    }

    /**
     * Sama persis dengan perhitungan statistik di halaman detail nilai frontend: hanya nilai
     * final yang dihitung ke IPK.
     */
    #[Computed]
    public function statistik(): array
    {
        $totalSks = 0;
        $totalAngkaMutu = 0.0;
        $totalSksDenganNilai = 0;

        foreach ($this->krsList as $krs) {
            $sks = $krs->kelas->kurikulumMatkul->matkul->sks ?? 0;
            $totalSks += $sks;

            $nilai = $krs->nilai;
            if ($nilai && $nilai->is_final && $nilai->angka_mutu !== null) {
                $totalAngkaMutu += (float) $nilai->angka_mutu * $sks;
                $totalSksDenganNilai += $sks;
            }
        }

        return [
            'total_sks' => $totalSks,
            'total_angka_mutu' => $totalAngkaMutu,
            'total_sks_dengan_nilai' => $totalSksDenganNilai,
            'ipk' => $totalSksDenganNilai > 0 ? round($totalAngkaMutu / $totalSksDenganNilai, 2) : null,
        ];
    }

    #[Computed]
    public function semesterDitempuh(): ?int
    {
        $mahasiswa = $this->mahasiswa;
        if (! $mahasiswa->semester_masuk) {
            return null;
        }

        $target = $this->filterSemester !== ''
            ? Semester::find((int) $this->filterSemester)
            : Semester::where('is_active', true)->first();

        if (! $target) {
            return null;
        }

        return SemesterService::hitungSemesterDitempuhFromModel($mahasiswa->semester_masuk, $target);
    }

    public function confirmDelete(int $nilaiId): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak hapus, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di delete() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'nilai', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus nilai.');

        $this->confirmDeleteId = $nilaiId;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    /**
     * Sama persis dengan NilaiController::destroy — soft delete nilai + komponen + revisi terkait
     * id_krs yang sama. Scope sudah dijamin lewat mount() (mahasiswa halaman ini sudah dicek).
     */
    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'nilai', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus nilai.');

        if (! $this->confirmDeleteId) {
            return;
        }

        $user = Auth::user();

        $nilai = Nilai::with('krs')->findOrFail($this->confirmDeleteId);

        if (! $nilai->krs || (int) $nilai->krs->id_mahasiswa !== $this->mahasiswaId) {
            abort(404);
        }

        $deletedBy = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';

        // Komponen dan revisi ikut terhapus lewat AturanHapusBerantai (Nilai::$hapusBerantai),
        // dengan deleted_at yang sama sehingga bisa dipulihkan utuh. Dulu dihapus manual lewat
        // DB::table dan query builder, yang memberi cap waktu berbeda dan melewati event model.
        DB::transaction(function () use ($nilai, $deletedBy): void {
            $nilai->deleted_by = $deletedBy;
            $nilai->save();
            $nilai->delete();
        });

        $this->confirmDeleteId = null;
        unset($this->krsList, $this->statistik);

        session()->flash('status', 'Nilai berhasil dihapus.');
    }

    /**
     * Tidak ada padanan di NilaiController — API belum punya endpoint restore, murni fitur panel.
     * Komponen dan revisi yang terhapus BERSAMA nilai ini ikut dipulihkan lewat AturanHapusBerantai;
     * yang dihapus sendiri sebelumnya tetap terhapus. Tidak perlu cek bentrok unik: nilai.id_krs,
     * nilai_komponen (id_krs, id_jenis_penilaian), dan nilai_revisi (id_krs, huruf_mutu) semuanya
     * unik termasuk baris terhapus, jadi baris hidup pengganti mustahil ada.
     */
    public function restore(int $nilaiId): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'nilai', 'delete'), 403, 'Anda tidak memiliki hak untuk memulihkan nilai.');

        $nilai = $this->findTrashedNilaiMilikMahasiswa($nilaiId);

        DB::transaction(function () use ($nilai): void {
            $nilai->restore();
            $nilai->deleted_by = null;
            $nilai->save();
        });

        unset($this->krsList, $this->statistik, $this->trashedNilaiMap);

        session()->flash('status', 'Nilai berhasil dipulihkan.');
    }

    public function confirmForceDelete(int $nilaiId): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'nilai', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus nilai.');

        $this->confirmForceDeleteId = $nilaiId;
    }

    public function cancelForceDelete(): void
    {
        $this->confirmForceDeleteId = null;
    }

    /**
     * Tidak ada padanan di NilaiController — API belum punya endpoint hapus permanen, murni fitur
     * panel. Komponen dan revisi tercatat per id_krs (bukan per id nilai), jadi foreign key tidak
     * ikut menghapusnya: yang terhapus BERSAMA nilai ini (deleted_at identik, lihat
     * AturanHapusBerantai) dihapus permanen di sini juga, supaya tidak tertinggal sebagai sisa
     * yang tidak bisa dipulihkan lewat mana pun.
     */
    public function forceDeleteNilai(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'nilai', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus nilai.');

        if (! $this->confirmForceDeleteId) {
            return;
        }

        $nilai = $this->findTrashedNilaiMilikMahasiswa($this->confirmForceDeleteId);

        $blockers = [];
        foreach (self::FORCE_DELETE_BLOCKERS as $table => $meta) {
            if (DB::table($table)->where($meta['column'], $nilai->id)->exists()) {
                $blockers[] = $meta['label'];
            }
        }

        if ($blockers !== []) {
            session()->flash('error', 'Tidak bisa menghapus permanen nilai ini: masih tercatat di data '.implode(', ', $blockers).'. Hapus atau pindahkan data itu terlebih dahulu.');
            $this->confirmForceDeleteId = null;

            return;
        }

        DB::transaction(function () use ($nilai): void {
            $waktuHapus = $nilai->getRawOriginal('deleted_at');

            foreach (['nilaiKomponen', 'nilaiRevisi'] as $relasi) {
                $query = $nilai->{$relasi}();
                $query->onlyTrashed()
                    ->where($query->getRelated()->getQualifiedDeletedAtColumn(), $waktuHapus)
                    ->get()
                    ->each
                    ->forceDelete();
            }

            $nilai->forceDelete();
        });

        $this->confirmForceDeleteId = null;
        unset($this->krsList, $this->statistik, $this->trashedNilaiMap);

        session()->flash('status', 'Nilai berhasil dihapus permanen.');
    }

    /**
     * Scope sudah dijamin lewat mount(); di sini dipastikan nilai itu memang milik mahasiswa halaman
     * ini, supaya id nilai mahasiswa lain yang dikirim lewat request palsu tidak bisa disentuh.
     * KRS-nya harus masih hidup — nilai yang ikut terhapus bersama KRS dipulihkan lewat KRS-nya.
     */
    private function findTrashedNilaiMilikMahasiswa(int $nilaiId): Nilai
    {
        $nilai = Nilai::onlyTrashed()->with('krs')->findOrFail($nilaiId);

        if (! $nilai->krs || (int) $nilai->krs->id_mahasiswa !== $this->mahasiswaId) {
            abort(404);
        }

        return $nilai;
    }

    public function render()
    {
        return view('livewire.admin.nilai.show')->extends('layouts.web');
    }
}
