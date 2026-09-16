<?php

namespace App\Livewire\Admin\JadwalUjian;

use App\Livewire\Admin\JadwalUjian\Concerns\ForwardsIndexState;
use App\Models\Kelas;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\Semester;
use App\Models\Ujian;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $ujianId = null;

    // Dua properti ini murni untuk menyaring opsi id_kelas — tidak dikirim ke server.
    public ?int $filterProdi = null;

    public ?int $filterSemester = null;

    public ?int $id_kelas = null;

    public string $jenis_ujian = 'UTS';

    public ?int $id_ruangan = null;

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public function mount(?int $id = null): void
    {
        $this->ujianId = $id;
        $this->resolveBackUrl();

        if ($id === null) {
            return;
        }

        $ujian = Ujian::with('kelas')->findOrFail($id);
        $this->ensureAccess($ujian);

        $this->id_kelas = $ujian->id_kelas;
        $this->filterProdi = $ujian->kelas?->id_prodi;
        $this->filterSemester = $ujian->kelas?->id_semester;
        $this->jenis_ujian = $ujian->jenis_ujian;
        $this->id_ruangan = $ujian->id_ruangan;
        $this->tanggal_mulai = $ujian->tanggal_mulai ? $ujian->tanggal_mulai->format('Y-m-d\TH:i') : '';
        $this->tanggal_selesai = $ujian->tanggal_selesai ? $ujian->tanggal_selesai->format('Y-m-d\TH:i') : '';
    }

    /**
     * Sama persis dengan UjianController — pengecekan scope prodi lewat kelas.
     */
    private function ensureAccess(Ujian $ujian): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $kelas = $ujian->kelas ?? Kelas::withTrashed()->find($ujian->id_kelas);
                if (! $kelas || ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke jadwal ujian ini.');
                }
            }
        }
    }

    /**
     * Ganti prodi/semester filter = daftar kelas yang tersedia berubah, kelas lama dibuang.
     */
    public function updatedFilterProdi(): void
    {
        $this->id_kelas = null;
    }

    public function updatedFilterSemester(): void
    {
        $this->id_kelas = null;
    }

    /**
     * Tidak dibatasi limit() — sudah disaring lewat scope prodi user plus filterProdi/filterSemester
     * di form, jadi hasilnya bounded oleh filter itu sendiri, bukan angka arbitrer. Regresi yang
     * sudah pernah diperbaiki di App\Livewire\Admin\Jadwal\Form::kelasOptions() — kombinasi
     * prodi+semester dengan lebih dari 200 kelas kehilangan sisanya begitu saja kalau dibatasi.
     */
    #[Computed]
    public function kelasOptions()
    {
        $user = Auth::user();
        $query = Kelas::with(['kurikulumMatkul.matkul', 'semester'])->whereNull('deleted_at');

        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        if ($this->filterProdi) {
            $query->where('id_prodi', $this->filterProdi);
        }
        if ($this->filterSemester) {
            $query->where('id_semester', $this->filterSemester);
        }

        return $query->orderBy('id')->get()->map(fn (Kelas $k) => (object) [
            'id' => $k->id,
            'label' => trim(($k->kurikulumMatkul?->matkul?->kode ? "{$k->kurikulumMatkul->matkul->kode} - " : '').($k->kurikulumMatkul?->matkul?->nama ?? 'Kelas').($k->semester ? " ({$k->semester->nama} {$k->semester->kode})" : '')),
        ]);
    }

    /**
     * Rule sama persis dengan UjianController::store/update.
     */
    protected function rules(): array
    {
        return [
            'id_kelas' => ['required', Rule::exists('kelas', 'id')->whereNull('deleted_at')],
            'jenis_ujian' => ['required', Rule::in(Ujian::JENIS)],
            'id_ruangan' => ['nullable', Rule::exists('ruangan', 'id')->whereNull('deleted_at')],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
        ];
    }

    /**
     * Sama persis dengan UjianController::assertTanggalUjianTidakMundur.
     */
    private function tanggalUjianMundur(): bool
    {
        if ($this->tanggal_mulai === '' || $this->tanggal_selesai === '') {
            return false;
        }

        return Carbon::parse($this->tanggal_selesai)->lt(Carbon::parse($this->tanggal_mulai));
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->tanggalUjianMundur()) {
            $this->addError('tanggal_selesai', 'Tanggal selesai harus sama atau setelah tanggal mulai.');

            return null;
        }

        $kelas = Kelas::whereNull('deleted_at')->findOrFail($validated['id_kelas']);
        $idSemester = (int) $kelas->id_semester;

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                if ($this->ujianId) {
                    $ujianLama = Ujian::findOrFail($this->ujianId);
                    $kelasLama = Kelas::withTrashed()->find((int) $ujianLama->id_kelas);
                    if ($kelasLama && ! in_array((int) $kelasLama->id_prodi, $allowedProdiIds, true)) {
                        abort(403, 'Anda tidak memiliki akses ke jadwal ujian ini.');
                    }
                }
                if (! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke kelas ini.');
                }
            }
        }

        $dupe = Ujian::query()
            ->when($this->ujianId, fn ($q) => $q->where('id', '!=', $this->ujianId))
            ->where('id_kelas', $validated['id_kelas'])
            ->where('id_semester', $idSemester)
            ->where('jenis_ujian', $validated['jenis_ujian'])
            ->exists();
        if ($dupe) {
            $this->addError('id_kelas', 'Kombinasi kelas, semester, dan jenis ujian harus unik.');

            return null;
        }

        $actor = $user ? ((string) ($user->name ?? $user->id)) : 'system';

        $data = [
            'id_kelas' => $validated['id_kelas'],
            'jenis_ujian' => $validated['jenis_ujian'],
            'id_ruangan' => $this->id_ruangan,
            'id_semester' => $idSemester,
            'tanggal_mulai' => $this->tanggal_mulai !== '' ? $this->tanggal_mulai : null,
            'tanggal_selesai' => $this->tanggal_selesai !== '' ? $this->tanggal_selesai : null,
        ];

        if ($this->ujianId) {
            Ujian::findOrFail($this->ujianId)->update($data + ['updated_by' => $actor]);
        } else {
            Ujian::create($data + ['created_by' => $actor, 'updated_by' => $actor]);
        }

        session()->flash('status', 'Jadwal ujian berhasil disimpan.');

        return redirect($this->backUrl);
    }

    public function render()
    {
        $user = Auth::user();
        $prodiQuery = Prodi::with('jenjang')->whereNull('deleted_at');
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $prodiQuery->whereIn('id', $allowedProdiIds);
            }
        }

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.jadwal-ujian.form', [
            'prodiOptions' => $prodiQuery->orderBy('nama')->get()->map(fn (Prodi $p) => (object) [
                'id' => $p->id,
                'label' => $p->jenjang?->kode ? "{$p->nama} ({$p->jenjang->kode})" : $p->nama,
            ]),
            'semesterOptions' => Semester::whereNull('deleted_at')->orderByDesc('kode')->get(['id', 'kode', 'nama'])
                ->map(fn (Semester $s) => (object) ['id' => $s->id, 'label' => "{$s->nama} ({$s->kode})"]),
            'jenisUjianOptions' => collect(Ujian::JENIS)->mapWithKeys(fn ($j) => [$j => ucfirst(strtolower($j))])->all(),
            'ruanganOptions' => Ruangan::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.web');
    }
}
