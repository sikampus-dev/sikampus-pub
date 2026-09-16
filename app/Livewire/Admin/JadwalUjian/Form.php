<?php

namespace App\Livewire\Admin\JadwalUjian;

use App\Livewire\Admin\JadwalUjian\Concerns\ForwardsIndexState;
use App\Models\Kelas;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\Semester;
use App\Models\Ujian;
use App\Services\JadwalUjianDuplikat;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
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

    /**
     * Id jadwal ujian TERHAPUS yang menduduki kombinasi unik yang sedang disimpan.
     * Terisi = modal tawaran pulihkan / hapus permanen sedang tampil.
     */
    public ?int $duplikatTerhapusId = null;

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
     *
     * Label menyertakan nama kelompok kelas (kalau ada) — tanpa ini, kelas dengan mata kuliah dan
     * semester yang sama tapi kelompok kelas berbeda (mis. Kelas A vs Kelas B) tampil dengan label
     * identik di dropdown dan tidak bisa dibedakan. Pola sama seperti Jadwal\Form::kelasOptions()
     * dan Krs\Form::kelasOptions().
     */
    #[Computed]
    public function kelasOptions()
    {
        $user = Auth::user();
        $query = Kelas::with(['kurikulumMatkul.matkul', 'semester', 'kelompokKelas'])->whereNull('deleted_at');

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

        return $query->orderBy('id')->get()->map(function (Kelas $k) {
            $label = trim(($k->kurikulumMatkul?->matkul?->kode ? "{$k->kurikulumMatkul->matkul->kode} - " : '').($k->kurikulumMatkul?->matkul?->nama ?? 'Kelas'));
            if ($k->kelompokKelas?->nama) {
                $label .= ' · Kelompok: '.$k->kelompokKelas->nama;
            }
            if ($k->semester) {
                $label .= " ({$k->semester->nama} {$k->semester->kode})";
            }

            return (object) ['id' => $k->id, 'label' => $label];
        });
    }

    /** Baris terhapus yang sedang ditawarkan, lengkap dengan relasi untuk ditampilkan di modal. */
    #[Computed]
    public function duplikatTerhapus(): ?Ujian
    {
        if (! $this->duplikatTerhapusId) {
            return null;
        }

        return Ujian::onlyTrashed()
            ->with(['kelas.kurikulumMatkul.matkul', 'kelas.kelompokKelas', 'semester', 'ruangan'])
            ->find($this->duplikatTerhapusId);
    }

    public function batalkanDuplikat(): void
    {
        $this->duplikatTerhapusId = null;
    }

    /**
     * Pulihkan jadwal lama APA ADANYA — isian form sengaja diabaikan, dan modal menyatakan itu.
     * Memakai isian form akan diam-diam mengubah tanggal/ruangan jadwal yang admin kira ia
     * kembalikan utuh.
     */
    public function pulihkanDuplikat()
    {
        $ujian = $this->duplikatTerhapusUntukAksi();
        if (! $ujian) {
            return null;
        }

        $ujian->restore();
        $ujian->update(['deleted_by' => null, 'updated_by' => $this->actor()]);

        $this->duplikatTerhapusId = null;
        session()->flash('status', 'Jadwal ujian yang terhapus berhasil dipulihkan beserta tanggal dan ruangan lamanya.');

        return redirect($this->backUrl);
    }

    /** Hapus permanen jadwal lama, lalu lanjutkan menyimpan isian form seperti biasa. */
    public function hapusPermanenDuplikat()
    {
        $ujian = $this->duplikatTerhapusUntukAksi();
        if (! $ujian) {
            return null;
        }

        $ujian->forceDelete();
        $this->duplikatTerhapusId = null;

        return $this->save();
    }

    /**
     * Baris terhapus yang boleh disentuh user ini. Scope prodi diperiksa ULANG di sini, bukan
     * hanya saat modal dibuka — id-nya properti publik Livewire, jadi bisa diganti dari klien.
     */
    private function duplikatTerhapusUntukAksi(): ?Ujian
    {
        if (! $this->duplikatTerhapusId) {
            return null;
        }

        $ujian = Ujian::onlyTrashed()->find($this->duplikatTerhapusId);
        if (! $ujian) {
            $this->duplikatTerhapusId = null;
            $this->addError('id_kelas', 'Jadwal ujian terhapus itu sudah tidak ada lagi. Silakan simpan ulang.');

            return null;
        }

        $this->ensureAccess($ujian);

        return $ujian;
    }

    private function actor(): string
    {
        $user = Auth::user();

        return $user ? ((string) ($user->name ?? $user->id)) : 'system';
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

        // withTrashed(): unique `ujian_unique` tidak menyertakan deleted_at, jadi baris yang sudah
        // dihapus tetap menduduki slotnya. Tanpa ini, baris itu lolos cek lalu menabrak constraint
        // di database dan berakhir sebagai 500.
        $bentrok = JadwalUjianDuplikat::cari(
            (int) $validated['id_kelas'],
            $idSemester,
            $validated['jenis_ujian'],
            $this->ujianId,
        );

        if ($bentrok && ! $bentrok->trashed()) {
            $this->addError('id_kelas', JadwalUjianDuplikat::pesanBentrokHidup());

            return null;
        }

        if ($bentrok) {
            // Bentrok dengan jadwal terhapus: tawarkan pulihkan / hapus permanen lewat modal,
            // bukan menolak mentah-mentah — admin tidak punya cara lain melihat baris itu.
            $this->duplikatTerhapusId = (int) $bentrok->id;

            return null;
        }

        $actor = $this->actor();

        $data = [
            'id_kelas' => $validated['id_kelas'],
            'jenis_ujian' => $validated['jenis_ujian'],
            'id_ruangan' => $this->id_ruangan,
            'id_semester' => $idSemester,
            'tanggal_mulai' => $this->tanggal_mulai !== '' ? $this->tanggal_mulai : null,
            'tanggal_selesai' => $this->tanggal_selesai !== '' ? $this->tanggal_selesai : null,
        ];

        // Jaring pengaman untuk balapan: dua admin menyimpan kombinasi sama nyaris bersamaan,
        // keduanya lolos cek di atas, lalu yang kalah menabrak unique di database. Tetap pesan
        // yang bisa dibaca, bukan 500.
        try {
            if ($this->ujianId) {
                Ujian::findOrFail($this->ujianId)->update($data + ['updated_by' => $actor]);
            } else {
                Ujian::create($data + ['created_by' => $actor, 'updated_by' => $actor]);
            }
        } catch (UniqueConstraintViolationException) {
            $this->addError('id_kelas', JadwalUjianDuplikat::pesanBentrokHidup());

            return null;
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
