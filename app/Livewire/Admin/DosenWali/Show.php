<?php

namespace App\Livewire\Admin\DosenWali;

use App\Livewire\Admin\DosenWali\Concerns\ForwardsIndexState;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Mahasiswa;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use ForwardsIndexState, WithPagination;

    public int $dosenId;

    // Prefix mhs_ (bukan 'search' polos) supaya tidak bentrok dengan query 'search' milik Index
    // yang ikut terbawa di URL ini — lihat DosenWali\Concerns\ForwardsIndexState.
    #[Url(as: 'mhs_search')]
    public string $search = '';

    public int $perPage = 10;

    public bool $showModal = false;

    public string $mahasiswaSearch = '';

    public ?int $selectedMahasiswaId = null;

    public string $selectedMahasiswaLabel = '';

    // Umpan balik di dalam modal — banner session('status') di halaman tertutup overlay selama
    // modal tetap terbuka untuk input berikutnya.
    public string $lastAddedLabel = '';

    public ?int $confirmingDeleteId = null;

    public function mount(int $id): void
    {
        $this->dosenId = $id;

        Dosen::findOrFail($id);

        $this->resolveBackToIndexUrl();
    }

    public function updatingSearch(): void
    {
        // PageName 'mhs_page' (bukan default 'page') karena alasan yang sama dengan #[Url] di atas.
        $this->resetPage('mhs_page');
    }

    #[Computed]
    public function dosen(): Dosen
    {
        return Dosen::with('prodiAsKaprodi')->findOrFail($this->dosenId);
    }

    /**
     * Sama dengan DosenWaliController::index — di-scope ke id_dosen ini.
     */
    #[Computed]
    public function bimbinganList()
    {
        $query = DosenWali::query()
            ->where('id_dosen', $this->dosenId)
            ->with(['mahasiswa.prodi']);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereHas('mahasiswa', function ($q) use ($allowedProdiIds) {
                    $q->whereIn('id_prodi', $allowedProdiIds);
                });
            }
        }

        if ($this->search !== '') {
            $query->whereHas('mahasiswa', function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('nim', 'like', "%{$this->search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate($this->perPage, ['*'], 'mhs_page');
    }

    /**
     * Pencarian cari-lalu-pilih untuk modal tambah — mahasiswa yang sudah punya dosen wali
     * aktif ditandai has_dosen_wali dan tidak bisa dipilih (mirror DosenWaliController::getMahasiswaOptions).
     */
    #[Computed]
    public function mahasiswaResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        $query = Mahasiswa::query()
            ->with('prodi:id,nama,kode')
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->mahasiswaSearch}%")
                    ->orWhere('nim', 'like', "%{$this->mahasiswaSearch}%");
            });

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        $existingIds = DosenWali::whereNull('deleted_at')
            ->where('status', 'active')
            ->pluck('id_mahasiswa');

        return $query->orderBy('nama')->limit(20)->get()->map(function ($m) use ($existingIds) {
            $m->has_dosen_wali = $existingIds->contains($m->id);

            return $m;
        });
    }

    public function openModal(): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak ubah, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di save()/confirmDelete()/delete() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah data bimbingan.');

        $this->resetValidation();
        $this->selectedMahasiswaId = null;
        $this->selectedMahasiswaLabel = '';
        $this->mahasiswaSearch = '';
        $this->lastAddedLabel = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->lastAddedLabel = '';
    }

    public function selectMahasiswa(int $id, string $label): void
    {
        $this->selectedMahasiswaId = $id;
        $this->selectedMahasiswaLabel = $label;
        $this->mahasiswaSearch = '';
        $this->lastAddedLabel = '';
    }

    /**
     * Sama dengan DosenWaliController::store — cek scope, kuota bimbingan akademik dosen,
     * dan kombinasi (id_dosen, id_mahasiswa) yang sudah ada (termasuk yang soft-deleted).
     */
    public function save(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah data bimbingan.');

        $this->validate([
            'selectedMahasiswaId' => ['required', 'integer', 'exists:mahasiswa,id'],
        ], [], ['selectedMahasiswaId' => 'mahasiswa']);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $mahasiswaTarget = Mahasiswa::find($this->selectedMahasiswaId);
                if (! $mahasiswaTarget || ! in_array((int) $mahasiswaTarget->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke mahasiswa ini.');
                }
            }
        }

        $dosen = Dosen::findOrFail($this->dosenId);
        $kuota = (int) ($dosen->kuota_bimbingan_akademik ?? 0);
        if ($kuota > 0) {
            $jumlahAktif = DosenWali::where('id_dosen', $this->dosenId)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->count();

            if ($jumlahAktif + 1 > $kuota) {
                $this->addError('selectedMahasiswaId', "Kuota bimbingan akademik dosen ini sudah tercapai. Jumlah mahasiswa bimbingan aktif ({$jumlahAktif}) tidak boleh melebihi kuota ({$kuota}).");

                return;
            }
        }

        $existing = DosenWali::withTrashed()
            ->where('id_dosen', $this->dosenId)
            ->where('id_mahasiswa', $this->selectedMahasiswaId)
            ->first();

        if ($existing) {
            $existing->restore();
            $existing->update(['status' => 'active']);
        } else {
            DosenWali::create([
                'id_dosen' => $this->dosenId,
                'id_mahasiswa' => $this->selectedMahasiswaId,
                'status' => 'active',
            ]);
        }

        // Modal sengaja dibiarkan terbuka dan dikosongkan supaya admin bisa langsung menambah
        // mahasiswa berikutnya. Kembali ke halaman 1 karena daftar diurutkan created_at desc —
        // baris yang baru ditambahkan ada di sana.
        $this->lastAddedLabel = $this->selectedMahasiswaLabel;
        $this->selectedMahasiswaId = null;
        $this->selectedMahasiswaLabel = '';
        $this->mahasiswaSearch = '';
        $this->resetValidation();
        $this->resetPage('mhs_page');
        unset($this->bimbinganList, $this->mahasiswaResults);
    }

    public function confirmDelete(int $id): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus data bimbingan.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama dengan DosenWaliController::destroy — cek scope lewat mahasiswa terkait sebelum hapus.
     */
    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus data bimbingan.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        $item = DosenWali::with('mahasiswa')->findOrFail($this->confirmingDeleteId);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && (! $item->mahasiswa || ! in_array((int) $item->mahasiswa->id_prodi, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke data bimbingan ini.');
            }
        }

        $item->delete();

        $this->confirmingDeleteId = null;
        unset($this->bimbinganList);
        session()->flash('status', 'Data bimbingan dihapus.');
    }

    public function render()
    {
        // Diselipkan ke link "Riwayat bimbingan" supaya tombol Kembali di halaman riwayat bisa
        // mendarat di halaman/pencarian yang sama persis — lihat DosenWali\Concerns\ForwardsIndexState.
        //
        // Nama variabel sengaja BUKAN 'returnQuery': ForwardsIndexState sudah mendeklarasikan
        // property publik $returnQuery (dipakai resolveBackToIndexUrl() untuk backUrl ke Index).
        // Property publik Livewire otomatis diekspos ke view dan MENIMPA data view biasa dengan
        // key yang sama — data 'returnQuery' yang dikirim lewat view() di sini akan senyap
        // digantikan oleh $this->returnQuery kalau nama key-nya sama.
        $returnParams = array_filter([
            'mhs_search' => $this->search !== '' ? $this->search : null,
            'mhs_page' => $this->bimbinganList->currentPage() > 1 ? $this->bimbinganList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.admin.dosen-wali.show', [
            'riwayatQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
