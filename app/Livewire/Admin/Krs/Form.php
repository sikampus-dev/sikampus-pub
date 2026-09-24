<?php

namespace App\Livewire\Admin\Krs;

use App\Livewire\Admin\Krs\Concerns\ForwardsIndexState;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Services\PendaftaranKrs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    // ---- Mode: null = create (batch multi-kelas), diisi = edit (satu baris krs) ----
    public ?int $krsId = null;

    /**
     * Tujuan tombol Batal. Mode edit dibuka dari halaman detail KRS satu mahasiswa, jadi Batal
     * kembali ke detail itu — bukan ke Index, yang membuat admin kehilangan mahasiswa yang sedang
     * ia periksa. Mode create dibuka dari Index, jadi kembali ke Index.
     */
    public string $cancelUrl = '';

    /** Mode edit: id mahasiswa pemilik baris KRS, untuk URL detail. */
    public ?int $editMahasiswaId = null;

    /**
     * Mode edit: filter semester yang sedang aktif di halaman detail KRS SAAT tombol Ubah diklik
     * (lihat query string id_semester_detail dikirim krs/show.blade.php) — bukan filter Index.
     * Diteruskan lagi lewat urlDetail() supaya begitu Batal/simpan mengembalikan admin ke halaman
     * detail, filter semester yang sama otomatis terpilih lagi, bukan balik ke "Semua Semester".
     */
    public string $filterSemesterDetail = '';

    public string $submitError = '';

    // ---- Mode create: cari-lalu-pilih mahasiswa (bisa ribuan baris, tidak realistis preload
    // semua lewat <x-searchable-select>) — pola disalin dari App\Livewire\Admin\KelompokKelas\Form.
    public string $mahasiswaSearch = '';

    public ?int $selectedMahasiswaId = null;

    /** @var array<int, array{id_kelas: int|null, status: string|null}> */
    public array $krs = [];

    // ---- Mode edit: satu baris krs + info mahasiswa read-only (di-mount sekali, bukan computed,
    // karena tidak berubah selama form dibuka) ----
    public ?int $editMahasiswaProdiId = null;

    public string $mahasiswaNim = '';

    public string $mahasiswaNama = '';

    public string $mahasiswaProdiNama = '';

    public string $mahasiswaKelompokKelas = '';

    public string $mahasiswaDosenWali = '';

    public string $mahasiswaSemesterMasuk = '';

    public string $currentMatkulLabel = '';

    public string $currentSemesterLabel = '';

    public ?int $currentSks = null;

    public ?int $editIdKelas = null;

    public string $editStatus = '';

    public function mount(?int $id = null): void
    {
        $this->krsId = $id;
        $this->resolveBackUrl();
        $this->cancelUrl = $this->backUrl;

        if ($id === null) {
            $this->krs = [['id_kelas' => null, 'status' => 'pending']];

            return;
        }

        $krs = Krs::with([
            'mahasiswa.prodi',
            'mahasiswa.semester_masuk',
            'mahasiswa.kelompok_kelas',
            'kelas.kurikulumMatkul.matkul',
            'kelas.semester',
        ])->findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            $mahasiswaProdiId = $krs->mahasiswa->id_prodi ?? null;
            if ($allowedProdiIds !== null && (! $mahasiswaProdiId || ! in_array((int) $mahasiswaProdiId, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke data KRS ini.');
            }
        }

        $dosenWali = DB::table('dosen_wali')
            ->join('dosen', 'dosen_wali.id_dosen', '=', 'dosen.id')
            ->where('dosen_wali.id_mahasiswa', $krs->id_mahasiswa)
            ->where('dosen_wali.status', 'active')
            ->whereNull('dosen_wali.deleted_at')
            ->value('dosen.nama');

        $mahasiswa = $krs->mahasiswa;
        $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;

        $this->editMahasiswaId = (int) $krs->id_mahasiswa;
        $this->filterSemesterDetail = (string) request()->query('id_semester_detail', '');
        $this->cancelUrl = $this->urlDetail();

        $this->editMahasiswaProdiId = $mahasiswa->id_prodi ?? null;
        $this->mahasiswaNim = $mahasiswa->nim ?? '';
        $this->mahasiswaNama = $mahasiswa->nama ?? '';
        $this->mahasiswaProdiNama = $mahasiswa->prodi
            ? $mahasiswa->prodi->nama.($mahasiswa->prodi->kode ? ' ('.$mahasiswa->prodi->kode.')' : '')
            : '—';
        $this->mahasiswaKelompokKelas = $mahasiswa->kelompok_kelas->nama ?? '—';
        $this->mahasiswaDosenWali = $dosenWali ?: '—';
        $this->mahasiswaSemesterMasuk = $mahasiswa->semester_masuk
            ? $mahasiswa->semester_masuk->nama.' ('.$mahasiswa->semester_masuk->kode.')'
            : '—';
        $this->currentMatkulLabel = $matkul
            ? ($matkul->kode ? $matkul->kode.' - ' : '').$matkul->nama
            : '—';
        $this->currentSemesterLabel = $krs->kelas->semester
            ? $krs->kelas->semester->nama.' ('.$krs->kelas->semester->kode.')'
            : '—';
        $this->currentSks = $matkul->sks ?? null;
        $this->editIdKelas = $krs->id_kelas;
        $this->editStatus = $krs->approved_at ? 'acc' : 'pending';
    }

    /**
     * Sama persis dengan aturan di KrsController::store/update.
     */
    protected function rules(): array
    {
        if ($this->krsId) {
            return [
                'editIdKelas' => ['required', 'integer', 'exists:kelas,id'],
                'editStatus' => ['nullable', 'string', Rule::in(['pending', 'acc'])],
            ];
        }

        return [
            'selectedMahasiswaId' => ['required', 'integer', 'exists:mahasiswa,id'],
            'krs' => ['required', 'array', 'min:1'],
            'krs.*.id_kelas' => ['required', 'integer', 'exists:kelas,id'],
            'krs.*.status' => ['nullable', 'string', Rule::in(['pending', 'acc'])],
        ];
    }

    #[Computed]
    public function mahasiswaSearchResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        $query = Mahasiswa::query()
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

        return $query->orderBy('nama')->limit(20)->get(['id', 'nim', 'nama']);
    }

    /**
     * Sama persis dengan KrsController::getMahasiswaDetail.
     */
    #[Computed]
    public function selectedMahasiswa()
    {
        if (! $this->selectedMahasiswaId) {
            return null;
        }

        return Mahasiswa::with(['prodi', 'semester_masuk', 'kelompok_kelas'])->find($this->selectedMahasiswaId);
    }

    #[Computed]
    public function selectedMahasiswaDosenWali(): string
    {
        if (! $this->selectedMahasiswaId) {
            return '—';
        }

        $nama = DB::table('dosen_wali')
            ->join('dosen', 'dosen_wali.id_dosen', '=', 'dosen.id')
            ->where('dosen_wali.id_mahasiswa', $this->selectedMahasiswaId)
            ->where('dosen_wali.status', 'active')
            ->whereNull('dosen_wali.deleted_at')
            ->value('dosen.nama');

        return $nama ?: '—';
    }

    /**
     * Opsi kelas dibatasi ke prodi mahasiswa yang sedang diproses (create: mahasiswa terpilih,
     * edit: mahasiswa pemilik krs ini) — bounded, bukan preload semua kelas se-kampus.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function kelasOptions(): array
    {
        $prodiId = $this->krsId ? $this->editMahasiswaProdiId : $this->selectedMahasiswa?->id_prodi;

        if (! $prodiId) {
            return [];
        }

        return Kelas::with(['kurikulumMatkul.matkul', 'semester', 'kelompokKelas'])
            ->where('id_prodi', $prodiId)
            ->orderByDesc('id_semester')
            ->get()
            ->mapWithKeys(function ($kelas) {
                $matkul = $kelas->kurikulumMatkul->matkul ?? null;
                $label = ($matkul?->kode ? $matkul->kode.' - ' : '').($matkul?->nama ?? 'Mata Kuliah');
                if ($kelas->kelompokKelas?->nama) {
                    $label .= ' · Kelompok: '.$kelas->kelompokKelas->nama;
                }
                if ($kelas->semester) {
                    $label .= ' (Semester: '.$kelas->semester->nama.')';
                }

                return [$kelas->id => $label];
            })
            ->all();
    }

    public function selectMahasiswaOption(int $id): void
    {
        $this->selectedMahasiswaId = $id;
        $this->mahasiswaSearch = '';
        $this->krs = [['id_kelas' => null, 'status' => 'pending']];
        unset($this->selectedMahasiswa, $this->selectedMahasiswaDosenWali, $this->kelasOptions);
    }

    public function clearSelectedMahasiswa(): void
    {
        $this->selectedMahasiswaId = null;
        $this->krs = [];
        unset($this->selectedMahasiswa, $this->selectedMahasiswaDosenWali, $this->kelasOptions);
    }

    public function addRow(): void
    {
        $this->krs[] = ['id_kelas' => null, 'status' => 'pending'];
    }

    public function removeRow(int $index): void
    {
        if (count($this->krs) <= 1) {
            return;
        }

        unset($this->krs[$index]);
        $this->krs = array_values($this->krs);
    }

    public function save()
    {
        $this->submitError = '';

        return $this->krsId ? $this->saveEdit() : $this->saveCreate();
    }

    /**
     * Sama persis dengan KrsController::store — baris duplikat dikumpulkan sebagai error dan
     * seluruh batch di-rollback (bukan simpan sebagian), sesuai perilaku transaksi controller.
     */
    protected function saveCreate()
    {
        $validated = $this->validate();

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $mahasiswa = Mahasiswa::find($validated['selectedMahasiswaId']);
                if (! $mahasiswa || ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke mahasiswa ini.');
                }
                foreach ($validated['krs'] as $row) {
                    $kelas = Kelas::find($row['id_kelas']);
                    if (! $kelas || ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                        abort(403, 'Anda tidak memiliki akses ke kelas ini.');
                    }
                }
            }
        }

        $idMahasiswa = $validated['selectedMahasiswaId'];
        $errors = [];
        $createdCount = 0;

        DB::beginTransaction();
        try {
            foreach ($validated['krs'] as $row) {
                $exists = Krs::where('id_mahasiswa', $idMahasiswa)
                    ->where('id_kelas', $row['id_kelas'])
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    $errors[] = 'Salah satu kelas yang dipilih sudah ada di KRS mahasiswa ini.';

                    continue;
                }

                // Mata kuliah yang sama di kelas paralel lain pada semester yang sama. KRS yang dibuat
                // baris sebelumnya di batch ini sudah tersimpan di transaksi yang sama, jadi dua baris
                // form yang memilih kelas paralel dari mata kuliah yang sama ikut tertangkap.
                $kelasBaris = Kelas::find($row['id_kelas']);
                $sudahTerdaftar = $kelasBaris ? PendaftaranKrs::krsMataKuliahSamaDenganKelas((int) $idMahasiswa, $kelasBaris) : null;
                if ($sudahTerdaftar) {
                    $errors[] = PendaftaranKrs::pesanSudahTerdaftar($sudahTerdaftar);

                    continue;
                }

                $status = $row['status'] ?: 'pending';
                $isApproved = $status === 'acc';

                Krs::create([
                    'id_mahasiswa' => $idMahasiswa,
                    'id_kelas' => $row['id_kelas'],
                    'approved_by' => $isApproved ? ($user->name ?? $user->email ?? null) : null,
                    'approved_at' => $isApproved ? now() : null,
                ]);

                $createdCount++;
            }

            if (! empty($errors)) {
                DB::rollBack();
                $this->submitError = implode(' ', array_unique($errors));

                return null;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->submitError = 'Terjadi kesalahan saat menyimpan KRS: '.$e->getMessage();

            return null;
        }

        session()->flash('status', "{$createdCount} data KRS berhasil dibuat.");

        return redirect($this->backUrl);
    }

    /**
     * Sama persis dengan KrsController::update — TIDAK menyalin baris 'status' => 'active' yang
     * ada di controller karena kolom itu tidak ada di tabel krs / $fillable, jadi efektif no-op
     * di sana (status sebenarnya cuma direpresentasikan approved_at/approved_by).
     */
    protected function saveEdit()
    {
        $validated = $this->validate();

        $krs = Krs::with('mahasiswa')->findOrFail($this->krsId);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            $mahasiswa = $krs->mahasiswa;
            if ($allowedProdiIds !== null && (! $mahasiswa || ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke data KRS ini.');
            }
        }

        $idKelas = $validated['editIdKelas'];

        if ((int) $idKelas !== (int) $krs->id_kelas) {
            $exists = Krs::where('id_mahasiswa', $krs->id_mahasiswa)
                ->where('id_kelas', $idKelas)
                ->where('id', '!=', $krs->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $this->submitError = 'KRS dengan kelas ini sudah ada untuk mahasiswa ini.';

                return null;
            }

            $kelasBaru = Kelas::find($idKelas);
            $sudahTerdaftar = $kelasBaru ? PendaftaranKrs::krsMataKuliahSamaDenganKelas((int) $krs->id_mahasiswa, $kelasBaru, (int) $krs->id) : null;
            if ($sudahTerdaftar) {
                $this->submitError = PendaftaranKrs::pesanSudahTerdaftar($sudahTerdaftar);

                return null;
            }

            if ($user && $user->hasScopeRestriction()) {
                $allowedProdiIds = $user->getAllowedProdiIds();
                if ($allowedProdiIds !== null) {
                    $newKelas = Kelas::find($idKelas);
                    if (! $newKelas || ! in_array((int) $newKelas->id_prodi, $allowedProdiIds, true)) {
                        abort(403, 'Anda tidak memiliki akses ke kelas ini.');
                    }
                }
            }
        }

        $status = $validated['editStatus'] ?: 'pending';
        $isApproved = $status === 'acc';

        $krs->update([
            'id_kelas' => $idKelas,
            'approved_by' => $isApproved ? ($user->name ?? $user->email ?? null) : null,
            'approved_at' => $isApproved ? now() : null,
        ]);

        session()->flash('status', 'Data KRS berhasil diperbarui.');

        return redirect($this->urlDetail());
    }

    /** URL detail KRS mahasiswa (mode edit), membawa state Index supaya Kembali di sana tetap benar. */
    private function urlDetail(): string
    {
        $url = $this->urlDenganState(route('admin.akademik.krs.show', $this->editMahasiswaId));

        if ($this->filterSemesterDetail !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?').'id_semester_detail='.urlencode($this->filterSemesterDetail);
        }

        return $url;
    }

    public function render()
    {
        return view('livewire.admin.krs.form')->extends('layouts.web');
    }
}
