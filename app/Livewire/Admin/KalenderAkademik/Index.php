<?php

namespace App\Livewire\Admin\KalenderAkademik;

use App\Models\KalenderAkademik;
use App\Models\Semester;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter yang terikat <select> harus string, bukan ?enum/?int — lihat catatan di
    // SKILL.md siak-livewire-module.
    #[Url(as: 'kategori')]
    public string $filterKategori = '';

    #[Url(as: 'semester')]
    public string $filterSemester = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKategori(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak hapus, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di delete() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'kalender akademik', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus event kalender akademik.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'kalender akademik', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus event kalender akademik.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        $event = KalenderAkademik::findOrFail($this->confirmingDeleteId);
        $event->deleted_by = auth()->id();
        $event->save();
        $event->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    public function render()
    {
        $query = KalenderAkademik::query()->with('semester');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('deskripsi', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterKategori !== '') {
            $query->where('kategori', $this->filterKategori);
        }

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        $now = now();
        if ($this->filterStatus === 'aktif') {
            $query->where('tanggal_mulai', '<=', $now)->where('tanggal_selesai', '>=', $now);
        } elseif ($this->filterStatus === 'akan_datang') {
            $query->where('tanggal_mulai', '>', $now);
        } elseif ($this->filterStatus === 'selesai') {
            $query->where('tanggal_selesai', '<', $now);
        }

        $eventList = $query->orderByDesc('tanggal_mulai')->paginate($this->perPage);

        // Diselipkan ke link "Ubah" supaya tombol Batal di halaman ubah bisa mendarat di
        // halaman/filter yang sama persis — lihat KalenderAkademik\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'kategori' => $this->filterKategori !== '' ? $this->filterKategori : null,
            'semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'status' => $this->filterStatus !== '' ? $this->filterStatus : null,
            'page' => $eventList->currentPage() > 1 ? $eventList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.admin.kalender-akademik.index', [
            'eventList' => $eventList,
            'returnQuery' => http_build_query($returnParams),
            'kategoriOptions' => KalenderAkademik::KATEGORI_OPTIONS,
            'semesterOptions' => Semester::whereNull('deleted_at')->orderByDesc('kode')->get(['id', 'kode', 'nama'])
                ->mapWithKeys(fn ($s) => [(string) $s->id => "{$s->nama} ({$s->kode})"])->all(),
        ])->extends('layouts.web');
    }
}
