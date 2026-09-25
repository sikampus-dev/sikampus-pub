<?php

namespace App\Livewire\Admin\TugasAkhir;

use App\Models\Dosen;
use App\Models\Semester;
use App\Models\TugasAkhir;
use App\Models\TugasAkhirPembimbing;
use App\Models\TugasAkhirStatusLog;
use App\Models\UjianSidang;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public int $tugasAkhirId;

    public string $activeTab = 'detail';

    private const KEPUTUSAN_STATUS = ['acc', 'returned', 'declined'];

    // Keputusan pengajuan
    public bool $showStatusModal = false;

    public string $keputusan = '';

    public string $keteranganStatus = '';

    // Pembimbing
    public bool $showPembimbingModal = false;

    public ?int $editingPembimbingId = null;

    public ?int $pembimbingDosenId = null;

    public string $pembimbingTanggal = '';

    public ?int $confirmingPembimbingDeleteId = null;

    // Ujian sidang (buat baru)
    public bool $showSidangModal = false;

    // Terikat <select> — string, bukan ?int, supaya opsi kosong tidak melempar TypeError.
    public string $sidangSemesterId = '';

    public string $sidangTanggalMulai = '';

    public string $sidangTanggalSelesai = '';

    public function mount(int $id): void
    {
        $this->tugasAkhirId = $id;

        $tugasAkhir = TugasAkhir::with('mahasiswa')->findOrFail($id);
        $this->ensureAccess($tugasAkhir);
    }

    /**
     * Sama persis dengan TugasAkhirController::assertTugasAkhirProdiScope.
     */
    private function ensureAccess(TugasAkhir $tugasAkhir): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && $tugasAkhir->mahasiswa
                && ! in_array((int) $tugasAkhir->mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data tugas akhir ini.');
            }
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    #[Computed]
    public function tugasAkhir(): TugasAkhir
    {
        return TugasAkhir::with([
            'mahasiswa.prodi',
            'mahasiswa.semester_masuk',
            'mahasiswa.status_akademik',
            'mahasiswa.kelompok_kelas',
            'semester',
            'pembimbing.dosen',
            'ujianSidang.semester',
            'ujianSidang.penguji.dosen',
            'statusLogs.user',
        ])->findOrFail($this->tugasAkhirId);
    }

    #[Computed]
    public function dosenOptions()
    {
        return Dosen::whereNull('deleted_at')->orderBy('nama')->get()
            ->map(fn (Dosen $d) => (object) ['id' => $d->id, 'label' => $this->formatDosenLabel($d)]);
    }

    #[Computed]
    public function semesterOptions()
    {
        return Semester::orderByDesc('kode')->get(['id', 'kode', 'nama', 'is_active'])
            ->map(fn (Semester $s) => (object) [
                'id' => $s->id,
                'label' => $s->nama.' ('.$s->kode.')'.($s->is_active ? ' — aktif' : ''),
            ]);
    }

    private function formatDosenLabel(Dosen $dosen): string
    {
        $label = trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : ''));

        return $dosen->kode_dosen ? "{$label} ({$dosen->kode_dosen})" : $label;
    }

    private function actor(): string
    {
        $user = Auth::user();
        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : (string) ($user->email ?? '');
    }

    // ---------- Keputusan pengajuan ----------

    public function openStatusModal(): void
    {
        $this->resetValidation();
        $this->keputusan = '';
        $this->keteranganStatus = '';
        $this->showStatusModal = true;
    }

    public function closeStatusModal(): void
    {
        $this->showStatusModal = false;
    }

    /**
     * Sama persis dengan TugasAkhirController::updateStatus.
     */
    public function saveStatus(): void
    {
        $validated = $this->validate([
            'keputusan' => ['required', 'string', 'in:'.implode(',', self::KEPUTUSAN_STATUS)],
            'keteranganStatus' => ['nullable', 'string', 'max:2000'],
        ], [], ['keputusan' => 'keputusan', 'keteranganStatus' => 'keterangan']);

        $mapKeTugasAkhir = [
            'acc' => 'approved',
            'returned' => 'returned',
            'declined' => 'rejected',
        ];
        $statusBaru = $mapKeTugasAkhir[$validated['keputusan']];
        $tugasAkhirId = $this->tugasAkhirId;
        $actor = $this->actor();
        $userId = Auth::id();
        $keterangan = ($validated['keteranganStatus'] ?? '') !== '' ? $validated['keteranganStatus'] : null;

        DB::transaction(function () use ($tugasAkhirId, $validated, $statusBaru, $userId, $actor, $keterangan): void {
            TugasAkhirStatusLog::create([
                'id_tugas_akhir' => $tugasAkhirId,
                'status' => $validated['keputusan'],
                'keterangan' => $keterangan,
                'id_user' => $userId,
            ]);

            TugasAkhir::findOrFail($tugasAkhirId)->update([
                'status' => $statusBaru,
                'updated_by' => $actor,
            ]);
        });

        unset($this->tugasAkhir);
        $this->showStatusModal = false;
        session()->flash('status', 'Status pengajuan tugas akhir diperbarui.');
    }

    // ---------- Pembimbing ----------

    public function openPembimbingModal(?int $id = null): void
    {
        $this->resetValidation();

        if ($id) {
            $item = TugasAkhirPembimbing::findOrFail($id);
            $this->editingPembimbingId = $item->id;
            $this->pembimbingDosenId = $item->id_dosen;
            $this->pembimbingTanggal = $item->tanggal_penugasan?->format('Y-m-d') ?? '';
        } else {
            $this->editingPembimbingId = null;
            $this->pembimbingDosenId = null;
            $this->pembimbingTanggal = '';
        }

        $this->showPembimbingModal = true;
    }

    public function closePembimbingModal(): void
    {
        $this->showPembimbingModal = false;
    }

    /**
     * Sama persis dengan TugasAkhirController::storePembimbing / updatePembimbing.
     */
    public function savePembimbing(): void
    {
        $validated = $this->validate([
            'pembimbingDosenId' => ['required', 'integer', 'exists:dosen,id'],
            'pembimbingTanggal' => ['nullable', 'date'],
        ], [], ['pembimbingDosenId' => 'dosen', 'pembimbingTanggal' => 'tanggal penugasan']);

        $dup = TugasAkhirPembimbing::query()
            ->where('id_tugas_akhir', $this->tugasAkhirId)
            ->where('id_dosen', $validated['pembimbingDosenId'])
            ->where('peran', 'pembimbing')
            ->when($this->editingPembimbingId, fn ($q) => $q->where('id', '!=', $this->editingPembimbingId))
            ->exists();

        if ($dup) {
            $this->addError('pembimbingDosenId', 'Dosen ini sudah terdaftar sebagai pembimbing.');

            return;
        }

        $actor = $this->actor();
        $tanggal = ($validated['pembimbingTanggal'] ?? '') !== '' ? $validated['pembimbingTanggal'] : null;

        if ($this->editingPembimbingId) {
            TugasAkhirPembimbing::findOrFail($this->editingPembimbingId)->update([
                'id_dosen' => $validated['pembimbingDosenId'],
                'tanggal_penugasan' => $tanggal,
                'updated_by' => $actor,
            ]);
        } else {
            TugasAkhirPembimbing::create([
                'id_tugas_akhir' => $this->tugasAkhirId,
                'id_dosen' => $validated['pembimbingDosenId'],
                'peran' => 'pembimbing',
                'tanggal_penugasan' => $tanggal,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);
        }

        unset($this->tugasAkhir);
        $this->showPembimbingModal = false;
        session()->flash('status', 'Pembimbing berhasil disimpan.');
    }

    public function confirmDeletePembimbing(int $id): void
    {
        $this->confirmingPembimbingDeleteId = $id;
    }

    public function cancelDeletePembimbing(): void
    {
        $this->confirmingPembimbingDeleteId = null;
    }

    public function deletePembimbing(): void
    {
        if (! $this->confirmingPembimbingDeleteId) {
            return;
        }

        $actor = $this->actor();
        $item = TugasAkhirPembimbing::findOrFail($this->confirmingPembimbingDeleteId);
        $item->update(['deleted_by' => $actor, 'updated_by' => $actor]);
        $item->delete();

        $this->confirmingPembimbingDeleteId = null;
        unset($this->tugasAkhir);
        session()->flash('status', 'Pembimbing dihapus.');
    }

    // ---------- Ujian sidang (buat baru) ----------

    public function openSidangModal(): void
    {
        $this->resetValidation();
        $this->sidangSemesterId = '';
        $this->sidangTanggalMulai = '';
        $this->sidangTanggalSelesai = '';
        $this->showSidangModal = true;
    }

    public function closeSidangModal(): void
    {
        $this->showSidangModal = false;
    }

    /**
     * Sama persis dengan TugasAkhirController::storeUjianSidang.
     */
    public function saveSidang(): void
    {
        $validated = $this->validate([
            'sidangSemesterId' => ['required', 'integer', 'exists:semester,id'],
            'sidangTanggalMulai' => ['nullable', 'date'],
            'sidangTanggalSelesai' => ['nullable', 'date'],
        ], [], [
            'sidangSemesterId' => 'semester',
            'sidangTanggalMulai' => 'tanggal mulai',
            'sidangTanggalSelesai' => 'tanggal selesai',
        ]);

        $mulai = ($validated['sidangTanggalMulai'] ?? '') !== '' ? $validated['sidangTanggalMulai'] : null;
        $selesai = ($validated['sidangTanggalSelesai'] ?? '') !== '' ? $validated['sidangTanggalSelesai'] : null;

        if ($mulai !== null && $selesai !== null && Carbon::parse($selesai)->lt(Carbon::parse($mulai))) {
            $this->addError('sidangTanggalSelesai', 'Tanggal selesai ujian harus sama atau setelah tanggal mulai.');

            return;
        }

        $exists = UjianSidang::query()
            ->where('id_tugas_akhir', $this->tugasAkhirId)
            ->where('id_semester', $validated['sidangSemesterId'])
            ->exists();

        if ($exists) {
            $this->addError('sidangSemesterId', 'Ujian sidang untuk semester ini sudah ada.');

            return;
        }

        $actor = $this->actor();

        UjianSidang::create([
            'id_tugas_akhir' => $this->tugasAkhirId,
            'id_semester' => $validated['sidangSemesterId'],
            'tanggal_daftar' => now(),
            'tanggal_ujian_mulai' => $mulai !== null ? Carbon::parse($mulai) : null,
            'tanggal_ujian_selesai' => $selesai !== null ? Carbon::parse($selesai) : null,
            'status' => 'draft',
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        unset($this->tugasAkhir);
        $this->showSidangModal = false;
        session()->flash('status', 'Ujian sidang berhasil dibuat.');
    }

    public function render()
    {
        return view('livewire.admin.tugas-akhir.show')->extends('layouts.web');
    }
}
