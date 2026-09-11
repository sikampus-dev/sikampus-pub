<?php

namespace App\Livewire\Admin\Pengguna;

use App\Models\User;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // user_roles adalah tabel legacy — model App\Models\UserRole cuma dipakai lewat relasi
    // User::userRoles(), tidak ada seeder/controller yang pernah menulis ke situ (Spatie
    // model_has_roles adalah sumber kebenaran sesungguhnya, lihat CLAUDE.md). Tetap dicek karena
    // constrained('users')->restrictOnDelete() di migration-nya masih berlaku di DB apa adanya.
    private const FORCE_DELETE_BLOCKERS = [
        'user_roles' => ['column' => 'id_user', 'label' => 'role pengguna (legacy)'],
    ];

    public string $search = '';

    public string $filterRole = '';

    public string $filterStatus = '';

    // Baris yang sudah soft-deleted disembunyikan secara default — dinyalakan lewat toggle supaya
    // admin bisa menemukan lalu memulihkan pengguna yang email/username-nya "terkunci" oleh baris
    // terhapus. Sama seperti pola di App\Livewire\Admin\Matkul\Index dkk.
    public bool $showTrashed = false;

    public ?int $confirmingForceDeleteId = null;

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingShowTrashed(): void
    {
        $this->resetPage();
    }

    /**
     * Tidak ada padanan di UserController — API belum punya endpoint restore, murni fitur panel.
     * Tidak ada pengecekan konflik email/username seperti kode+id_prodi di
     * App\Livewire\Admin\Matkul\Index::restore(): keduanya unique() satu kolom (bukan komposit) di
     * migration, dan MySQL menegakkan itu tanpa pengecualian untuk nilai non-null — sama seperti
     * nim/email di App\Livewire\Admin\Mahasiswa\Index::restore(), jadi baris aktif lain dengan
     * nilai yang sama tidak akan pernah bisa ada sejak awal.
     */
    public function restore(int $id): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'pengguna', 'manage'), 403, 'Anda tidak memiliki hak untuk memulihkan pengguna.');

        $pengguna = User::onlyTrashed()->findOrFail($id);
        $pengguna->restore();

        session()->flash('status', 'Pengguna berhasil dipulihkan.');
        $this->resetPage();
    }

    public function confirmForceDelete(int $id): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'pengguna', 'manage'), 403, 'Anda tidak memiliki hak untuk menghapus pengguna.');

        $this->confirmingForceDeleteId = $id;
    }

    public function cancelForceDelete(): void
    {
        $this->confirmingForceDeleteId = null;
    }

    /**
     * Tidak ada padanan di UserController — API belum punya endpoint hapus permanen, murni fitur
     * panel. Lihat FORCE_DELETE_BLOCKERS untuk daftar tabel yang restrictOnDelete().
     */
    public function forceDeleteUser(): void
    {
        if (! $this->confirmingForceDeleteId) {
            return;
        }

        abort_unless(PanelAccess::can(Auth::user(), 'pengguna', 'manage'), 403, 'Anda tidak memiliki hak untuk menghapus pengguna.');

        $pengguna = User::onlyTrashed()->findOrFail($this->confirmingForceDeleteId);

        $blockers = [];
        foreach (self::FORCE_DELETE_BLOCKERS as $table => $meta) {
            if (DB::table($table)->where($meta['column'], $pengguna->id)->exists()) {
                $blockers[] = $meta['label'];
            }
        }

        if ($blockers !== []) {
            session()->flash('error', 'Tidak bisa menghapus permanen pengguna ini: masih tercatat di data '.implode(', ', $blockers).'. Hapus atau pindahkan data itu terlebih dahulu.');
            $this->confirmingForceDeleteId = null;

            return;
        }

        $pengguna->forceDelete();

        $this->confirmingForceDeleteId = null;
        session()->flash('status', 'Pengguna berhasil dihapus permanen.');
        $this->resetPage();
    }

    /**
     * Sama persis dengan UserController::index, plus withTrashed() opsional lewat $showTrashed
     * (tidak ada padanan di API — lihat catatan pada restore()).
     */
    public function render()
    {
        $query = User::query();

        if ($this->showTrashed) {
            $query->withTrashed();
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterRole !== '') {
            $query->where('role', $this->filterRole);
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        $penggunaList = $query->orderBy('name')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.pengguna.index', [
            'penggunaList' => $penggunaList,
        ])->extends('layouts.web');
    }
}
