<?php

namespace App\Livewire\Admin\Kelas;

use App\Exceptions\PenghapusanDiblokir;
use App\Livewire\Admin\Kelas\Concerns\ForwardsIndexState;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Perkuliahan;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $kelasId;

    public bool $confirmingDelete = false;

    /** @var array<int> id jadwal yang dicentang untuk dihapus massal — lihat bulkDeleteJadwal(). */
    public array $selectedJadwalIds = [];

    public bool $confirmingBulkDelete = false;

    public function mount(int $id): void
    {
        $this->kelasId = $id;
        $this->resolveBackUrl();

        $kelas = Kelas::findOrFail($id);
        $this->ensureAccess($kelas);
    }

    /**
     * Sama persis dengan KelasController::show/edit/destroy — pengecekan scope prodi.
     */
    private function ensureAccess(Kelas $kelas): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        }
    }

    /**
     * Sama persis dengan KelasController::getDetailWithJadwal.
     */
    #[Computed]
    public function kelas(): Kelas
    {
        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'angkatan',
            'dosenPic',
            'kelompokKelas',
            'kelasDosen' => function ($q) {
                $q->whereNull('deleted_at');
            },
            'kelasDosen.dosen',
        ])->findOrFail($this->kelasId);

        $map = [];
        $ordered = Semester::withTrashed()->orderBy('kode')->pluck('id')->values();
        foreach ($ordered as $i => $semId) {
            $map[(int) $semId] = $i;
        }
        $idxS = $map[(int) $kelas->id_semester] ?? null;
        $idxA = $map[(int) $kelas->id_angkatan] ?? null;
        $kelas->setAttribute(
            'semester_kuliah_ke',
            ($idxS === null || $idxA === null || $idxS < $idxA) ? null : $idxS - $idxA + 1
        );

        return $kelas;
    }

    #[Computed]
    public function jadwalList()
    {
        return Jadwal::with(['jenisKuliah', 'ruangan', 'dosen.dosen'])
            ->where('id_kelas', $this->kelasId)
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();
    }

    #[Computed]
    public function jumlahMahasiswa(): int
    {
        return (int) Krs::where('id_kelas', $this->kelasId)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(DISTINCT id_mahasiswa) as count')
            ->value('count');
    }

    #[Computed]
    public function jumlahPerkuliahan(): int
    {
        $jadwalIds = $this->jadwalList->pluck('id');

        if ($jadwalIds->isEmpty()) {
            return 0;
        }

        return Perkuliahan::whereIn('id_jadwal', $jadwalIds)->count();
    }

    public function confirmDelete(): void
    {
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    /**
     * Sama persis dengan KelasController::destroy.
     */
    public function delete()
    {
        $kelas = Kelas::findOrFail($this->kelasId);
        $this->ensureAccess($kelas);

        $kelas->delete();

        session()->flash('status', 'Kelas dihapus.');

        return redirect()->route('admin.akademik.kelas');
    }

    /**
     * Centang semua / kosongkan semua — aksi tombol tunggal yang membalik keadaan sekarang: kalau
     * belum semua tercentang, centang semua; kalau sudah semua tercentang, kosongkan.
     */
    public function toggleAllJadwal(): void
    {
        $allIds = $this->jadwalList->pluck('id')->all();

        if ($allIds !== [] && count($this->selectedJadwalIds) === count($allIds)) {
            $this->selectedJadwalIds = [];
        } else {
            $this->selectedJadwalIds = $allIds;
        }
    }

    public function confirmBulkDelete(): void
    {
        if ($this->selectedJadwalIds === []) {
            return;
        }

        $this->confirmingBulkDelete = true;
    }

    public function cancelBulkDelete(): void
    {
        $this->confirmingBulkDelete = false;
    }

    /**
     * Hapus (soft delete) semua jadwal yang tercentang sekaligus — sama seperti
     * Jadwal\Index::delete() per baris, tapi untuk beberapa baris dalam satu aksi. id_kelas selalu
     * ikut disaring supaya id jadwal yang (secara tidak wajar) bukan milik kelas ini tidak ikut
     * terhapus lewat properti publik yang bisa dimanipulasi dari luar.
     */
    public function bulkDeleteJadwal(): void
    {
        if ($this->selectedJadwalIds === []) {
            $this->confirmingBulkDelete = false;

            return;
        }

        $kelas = Kelas::findOrFail($this->kelasId);
        $this->ensureAccess($kelas);

        $jadwalList = Jadwal::where('id_kelas', $this->kelasId)
            ->whereIn('id', $this->selectedJadwalIds)
            ->get();
        $count = $jadwalList->count();

        // Per model, bukan Jadwal::where()->delete(): hapus lewat query builder melewati event
        // model, sehingga AturanHapusBerantai tidak jalan — pertemuan perkuliahan tidak menahan
        // penghapusan dan dosen/materi/tugas jadwal itu tertinggal sebagai yatim. Diperiksa
        // semua dulu supaya pilihan yang sebagian ditolak tidak terhapus setengah jalan.
        $pemakai = [];
        foreach ($jadwalList as $jadwal) {
            foreach ($jadwal->pemakaiYangMemblokirHapus() as $label => $jumlah) {
                $pemakai[$label] = ($pemakai[$label] ?? 0) + $jumlah;
            }
        }
        if ($pemakai !== []) {
            $this->confirmingBulkDelete = false;

            throw new PenghapusanDiblokir($pemakai);
        }

        DB::transaction(fn () => $jadwalList->each->delete());

        $this->selectedJadwalIds = [];
        $this->confirmingBulkDelete = false;

        session()->flash('status', $count > 0 ? "{$count} jadwal berhasil dihapus." : 'Tidak ada jadwal yang dihapus.');
    }

    public function render()
    {
        return view('livewire.admin.kelas.show')->extends('layouts.web');
    }
}
