<?php

namespace App\Livewire\Admin\Kelas;

use App\Livewire\Admin\Kelas\Concerns\ForwardsIndexState;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JenisKuliah;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\KelompokKelas;
use App\Models\KurikulumMatkul;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\Semester;
use App\Services\JadwalBatchGenerator;
use App\Services\KelasAngkatanService;
use App\Services\KelasKodeGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $kelasId = null;

    // FK boleh ?int karena diikat lewat <x-searchable-select> (entangle), bukan <select> polos.
    // :live="true" di view supaya daftar kurikulum mata kuliah ikut dimuat ulang saat prodi berganti.
    public ?int $id_prodi = null;

    public ?int $id_kurikulum_matkul = null;

    public ?int $id_semester = null;

    public ?int $id_angkatan = null;

    public ?int $id_kelompok_kelas = null;

    public ?int $id_dosen_pic = null;

    public string $kode = '';

    // Terikat <input type="number">, bukan <select> — tetap string karena input kosong mengirim
    // "" yang tidak bisa dikonversi PHP ke int typed property (lihat SKILL.md).
    public string $jml_pertemuan = '16';

    public string $kuota = '0';

    public bool $is_mingguan = true;

    public bool $is_active = true;

    /** Pencarian dosen untuk ditambahkan sebagai tim pengampu (di luar PIC). */
    public string $dosenSearch = '';

    /** @var array<int> id dosen tim pengampu (tidak termasuk PIC), dikirim sebagai dosen_tim_ids. */
    public array $dosenTimIds = [];

    /** @var array<int, string> label tampilan untuk id dosen terpilih (PIC + tim). */
    public array $dosenLabelById = [];

    // Opsi "buat jadwal otomatis" (create maupun edit) — properti jadwalXxx murni untuk sub-form
    // ini, bukan kolom Kelas, jadi sengaja tidak mengikuti nama kolom DB (Kelas sudah punya
    // is_active miliknya sendiri). Selalu mulai nonaktif — bukan atribut kelas yang disimpan,
    // hanya aksi sesaat di titik save().
    public bool $buatJadwalOtomatis = false;

    public ?string $jadwalHari = null;

    public string $jadwalJamMulai = '';

    public string $jadwalJamSelesai = '';

    public ?int $jadwalIdRuangan = null;

    public ?int $jadwalIdJenisKuliah = null;

    public string $jadwalTanggal = '';

    public bool $jadwalTanggalHariOtomatis = false;

    public bool $jadwalIsActive = false;

    public function mount(?int $id = null): void
    {
        $this->kelasId = $id;
        $this->resolveBackUrl();

        if ($id === null) {
            // Sama seperti default filter semester di Kelas\Index/Jadwal\Index: semester aktif
            // ter-pilih otomatis untuk kelas baru — bukan kolom wajib diisi manual tiap kali.
            $semesterAktif = Semester::where('is_active', true)->first();
            if ($semesterAktif) {
                $this->id_semester = $semesterAktif->id;
            }

            return;
        }

        $kelas = Kelas::with(['kelasDosen' => function ($q) {
            $q->whereNull('deleted_at');
        }, 'kelasDosen.dosen'])->findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        }

        $this->id_prodi = $kelas->id_prodi;
        $this->id_kurikulum_matkul = $kelas->id_kurikulum_matkul;
        $this->id_semester = $kelas->id_semester;
        $this->id_angkatan = $kelas->id_angkatan;
        $this->id_kelompok_kelas = $kelas->id_kelompok_kelas;
        $this->id_dosen_pic = $kelas->id_dosen_pic;
        $this->kode = (string) $kelas->kode;
        $this->jml_pertemuan = (string) ($kelas->jml_pertemuan ?? 16);
        $this->kuota = (string) ($kelas->kuota ?? 0);
        $this->is_mingguan = $kelas->is_mingguan !== false;
        $this->is_active = (bool) $kelas->is_active;

        foreach ($kelas->kelasDosen as $kd) {
            if (! $kd->dosen) {
                continue;
            }
            if ($kd->is_pic) {
                $this->dosenLabelById[$kd->id_dosen] = $this->formatDosenLabel($kd->dosen);

                continue;
            }
            $this->dosenTimIds[] = (int) $kd->id_dosen;
            $this->dosenLabelById[$kd->id_dosen] = $this->formatDosenLabel($kd->dosen);
        }
    }

    /**
     * Reset kurikulum mata kuliah DAN kelas mahasiswa saat prodi berganti — opsi kelompokKelasOptions()
     * di render() ikut disaring oleh id_prodi (lihat catatannya), jadi pilihan lama bisa saja sudah
     * tidak muncul lagi di daftar yang baru.
     */
    public function updatedIdProdi(): void
    {
        $this->id_kurikulum_matkul = null;
        $this->id_kelompok_kelas = null;
    }

    /**
     * Isikan angkatan dari kelompok kelas yang dipilih. id_angkatan = semester masuk mahasiswa,
     * bukan semester berjalan — salah isi bikin kelas tidak pernah muncul di pengajuan KRS.
     */
    public function updatedIdKelompokKelas(): void
    {
        $saran = KelasAngkatanService::angkatanSaranForKelompokKelas($this->id_kelompok_kelas);
        if ($saran !== null) {
            $this->id_angkatan = $saran;
            $this->resetErrorBag('id_angkatan');
        }
    }

    private function formatDosenLabel(Dosen $dosen): string
    {
        $label = trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : ''));

        return $dosen->kode_dosen ? "{$label} ({$dosen->kode_dosen})" : $label;
    }

    #[Computed]
    public function kurikulumMatkulOptions()
    {
        if (! $this->id_prodi) {
            return collect();
        }

        return KurikulumMatkul::with(['matkul', 'kurikulum'])
            ->whereHas('kurikulum', function ($q) {
                $q->where('id_prodi', $this->id_prodi);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (KurikulumMatkul $km) => (object) [
                'id' => $km->id,
                'label' => trim(($km->matkul?->kode ? "{$km->matkul->kode} - " : '').($km->matkul?->nama ?? 'Mata Kuliah').($km->kurikulum?->nama ? " ({$km->kurikulum->nama})" : '')),
            ]);
    }

    #[Computed]
    public function dosenSearchResults()
    {
        if ($this->dosenSearch === '') {
            return collect();
        }

        $excludedIds = $this->dosenTimIds;
        if ($this->id_dosen_pic) {
            $excludedIds[] = $this->id_dosen_pic;
        }

        return Dosen::query()
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->dosenSearch}%")
                    ->orWhere('kode_dosen', 'like', "%{$this->dosenSearch}%");
            })
            ->whereNotIn('id', $excludedIds)
            ->orderBy('nama')
            ->limit(20)
            ->get();
    }

    public function addDosenTim(int $id): void
    {
        if (in_array($id, $this->dosenTimIds, true)) {
            return;
        }

        $dosen = Dosen::find($id);
        if (! $dosen) {
            return;
        }

        $this->dosenTimIds[] = $id;
        $this->dosenLabelById[$id] = $this->formatDosenLabel($dosen);
        $this->dosenSearch = '';
    }

    public function removeDosenTim(int $id): void
    {
        $this->dosenTimIds = array_values(array_diff($this->dosenTimIds, [$id]));
    }

    /**
     * Cek duplikat berdasarkan unique (id_kelompok_kelas, id_kurikulum_matkul, id_semester,
     * id_angkatan) — sama persis dengan KelasController::kelasDuplicateExists.
     */
    private function kelasDuplicateExists(): bool
    {
        $q = Kelas::query()
            ->where('id_kurikulum_matkul', $this->id_kurikulum_matkul)
            ->where('id_semester', $this->id_semester)
            ->where('id_angkatan', $this->id_angkatan);

        if ($this->id_kelompok_kelas) {
            $q->where('id_kelompok_kelas', $this->id_kelompok_kelas);
        } else {
            $q->whereNull('id_kelompok_kelas');
        }

        if ($this->kelasId) {
            $q->where('id', '!=', $this->kelasId);
        }

        return $q->exists();
    }

    /**
     * PIC + tim pengampu digabung tanpa duplikat — dipakai untuk sinkronisasi kelas_dosen DAN
     * (kalau opsi buat jadwal otomatis aktif) sebagai dosen pengajar pada jadwal yang dibuat,
     * supaya admin tidak perlu memilih dosen dua kali untuk kelas dan jadwalnya.
     *
     * @return array<int>
     */
    private function resolvedDosenIds(): array
    {
        $allIds = $this->dosenTimIds;
        if ($this->id_dosen_pic !== null && ! in_array($this->id_dosen_pic, $allIds, true)) {
            $allIds[] = $this->id_dosen_pic;
        }

        return $allIds;
    }

    /**
     * Sinkronisasi kelas_dosen: tim pengampu + dosen PIC (is_pic = true) — sama persis dengan
     * KelasController::syncKelasDosen.
     */
    private function syncKelasDosen(Kelas $kelas): void
    {
        $picId = $this->id_dosen_pic;
        $allIds = $this->resolvedDosenIds();

        $rows = KelasDosen::withTrashed()->where('id_kelas', $kelas->id)->get();
        $byDosen = $rows->keyBy('id_dosen');

        foreach ($allIds as $dosenId) {
            $isPic = $picId !== null && $dosenId === $picId;
            $existing = $byDosen->get($dosenId);
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                if ((bool) $existing->is_pic !== $isPic) {
                    $existing->update(['is_pic' => $isPic]);
                }
            } else {
                KelasDosen::create([
                    'id_kelas' => $kelas->id,
                    'id_dosen' => $dosenId,
                    'is_pic' => $isPic,
                ]);
            }
        }

        foreach ($rows as $row) {
            if ($row->trashed()) {
                continue;
            }
            if (! in_array((int) $row->id_dosen, $allIds, true)) {
                $row->delete();
            }
        }
    }

    /**
     * Rule inti sama persis dengan KelasController::store/update. Rule jadwalXxx (di bawah)
     * murni untuk sub-form "buat jadwal otomatis" panel ini — tidak ada padanannya di
     * KelasController karena opsi ini tidak ada di API/frontend, hanya kenyamanan panel admin.
     */
    protected function rules(): array
    {
        $rules = [
            'id_kurikulum_matkul' => ['required', 'integer', 'exists:kurikulum_matkul,id'],
            'id_prodi' => ['required', 'integer', 'exists:prodi,id'],
            'id_semester' => ['required', 'integer', 'exists:semester,id'],
            'id_angkatan' => ['required', 'integer', 'exists:semester,id'],
            'id_dosen_pic' => ['nullable', 'integer', 'exists:dosen,id'],
            'id_kelompok_kelas' => ['nullable', 'integer', 'exists:kelompok_kelas,id'],
            'kode' => ['nullable', 'string', 'max:255'],
            'jml_pertemuan' => ['nullable', 'integer', 'min:1', 'max:99'],
            'kuota' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ];

        if ($this->buatJadwalOtomatis) {
            $rules += [
                'jadwalHari' => ['nullable', 'string', Rule::in(Jadwal::HARI)],
                'jadwalJamMulai' => ['nullable', 'date_format:H:i'],
                'jadwalJamSelesai' => ['nullable', 'date_format:H:i'],
                'jadwalIdRuangan' => ['nullable', 'integer', 'exists:ruangan,id'],
                'jadwalIdJenisKuliah' => ['nullable', 'integer', 'exists:jenis_kuliah,id'],
                'jadwalTanggal' => ['nullable', 'date'],
            ];
        }

        return $rules;
    }

    public function save()
    {
        $validated = $this->validate();

        // Kode kosong dibuatkan sistem dari nama kelompok kelas (cadangan: kode mata kuliah).
        if ($validated['kode'] === '') {
            $validated['kode'] = KelasKodeGenerator::untukKelas(
                $validated['id_kelompok_kelas'] ?? null,
                $validated['id_kurikulum_matkul'] ?? null,
            );
        }
        $validated['jml_pertemuan'] = (int) $validated['jml_pertemuan'];
        $validated['kuota'] = (int) $validated['kuota'];
        $validated['is_mingguan'] = $this->is_mingguan;
        $validated['is_active'] = $this->is_active;

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $validated['id_prodi'], $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        $pesanAngkatan = KelasAngkatanService::pesanKetidakcocokan(
            $validated['id_kelompok_kelas'] ?? null,
            $validated['id_angkatan'] ?? null,
        );
        if ($pesanAngkatan !== null) {
            $this->addError('id_angkatan', $pesanAngkatan);

            return;
        }

        if ($this->kelasDuplicateExists()) {
            $this->addError('id_kurikulum_matkul', 'Kelas dengan kombinasi kelompok, kurikulum mata kuliah, semester berjalan, dan angkatan yang sama sudah ada.');

            return;
        }

        // Divalidasi di luar transaksi (sama seperti Jadwal\Form::saveCreate()) supaya gagal di
        // sini tidak meninggalkan Kelas setengah tersimpan.
        if ($this->buatJadwalOtomatis) {
            if ($this->jadwalTanggalHariOtomatis && ! $this->jadwalTanggal) {
                $this->addError('jadwalTanggal', 'Tanggal mulai wajib diisi jika opsi tanggal & hari otomatis diaktifkan.');

                return;
            }
            if ($this->jadwalJamMulai && $this->jadwalJamSelesai && strtotime($this->jadwalJamSelesai) <= strtotime($this->jadwalJamMulai)) {
                $this->addError('jadwalJamSelesai', 'Jam selesai harus lebih besar dari jam mulai.');

                return;
            }
            // Kelas baru pasti belum punya jadwal sama sekali — cek bentrok slot hanya relevan
            // untuk kelas yang sudah ada (edit) dan mungkin sudah punya sebagian jadwal terisi.
            if ($this->kelasId) {
                $slotError = JadwalBatchGenerator::cekSlotTersedia($this->kelasId, $validated['jml_pertemuan'], $this->jadwalIdRuangan);
                if ($slotError !== null) {
                    $this->addError('jadwalIdRuangan', $slotError);

                    return;
                }
            }
        }

        DB::transaction(function () use ($validated): void {
            if ($this->kelasId) {
                $kelas = Kelas::findOrFail($this->kelasId);
                $kelas->update($validated);
                $kelas->refresh();
            } else {
                $kelas = Kelas::create($validated);
            }

            $this->syncKelasDosen($kelas);

            if ($this->buatJadwalOtomatis) {
                JadwalBatchGenerator::generate($kelas, $kelas->id, $validated['jml_pertemuan'], [
                    'id_jenis_kuliah' => $this->jadwalIdJenisKuliah,
                    'tanggal' => $this->jadwalTanggal ?: null,
                    'hari' => $this->jadwalHari,
                    'jam_mulai' => $this->jadwalJamMulai,
                    'jam_selesai' => $this->jadwalJamSelesai,
                    'id_ruangan' => $this->jadwalIdRuangan,
                    'is_active' => $this->jadwalIsActive,
                    'tanggal_hari_otomatis' => $this->jadwalTanggalHariOtomatis,
                ], $this->resolvedDosenIds());
            }
        });

        session()->flash('status', 'Kelas berhasil disimpan.'.($this->buatJadwalOtomatis ? ' Jadwal otomatis juga sudah dibuat.' : ''));

        // Sengaja BUKAN $this->backUrl (yang membawa filter dari sebelum form dibuka) — begitu
        // simpan berhasil, filter Index diarahkan mengikuti prodi & semester milik kelas yang baru
        // saja disimpan, supaya kelas itu langsung kelihatan di daftar tanpa admin mengatur ulang
        // filter secara manual. search/kelas mahasiswa/page dari backUrl sengaja tidak dibawa —
        // kombinasi lama itu bisa saja tidak lagi cocok dengan prodi/semester yang baru.
        return redirect()->route('admin.akademik.kelas', [
            'id_prodi' => $validated['id_prodi'],
            'id_semester' => $validated['id_semester'],
        ]);
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

        // Kelas Mahasiswa mengikuti prodi yang dipilih (updatedIdProdi() membuang pilihan lama yang
        // mungkin sudah tidak relevan) — kalau belum ada prodi terpilih, tampilkan semua kelas
        // mahasiswa, sama seperti filter serupa di App\Livewire\Admin\Kelas\Index::render().
        $kelompokKelasQuery = KelompokKelas::whereNull('deleted_at');
        if ($this->id_prodi) {
            $kelompokKelasQuery->where('id_prodi', $this->id_prodi);
        }

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.kelas.form', [
            'prodiOptions' => $prodiQuery->orderBy('nama')->get()->map(fn (Prodi $p) => (object) [
                'id' => $p->id,
                'label' => $p->jenjang?->kode ? "{$p->nama} ({$p->jenjang->kode})" : $p->nama,
            ]),
            'semesterOptions' => Semester::whereNull('deleted_at')->orderByDesc('kode')->get(['id', 'kode', 'nama'])
                ->map(fn (Semester $s) => (object) ['id' => $s->id, 'label' => "{$s->nama} ({$s->kode})"]),
            'kelompokKelasOptions' => $kelompokKelasQuery->orderBy('nama')->get(['id', 'nama']),
            'dosenOptions' => Dosen::whereNull('deleted_at')->orderBy('nama')->get()->map(fn (Dosen $d) => (object) [
                'id' => $d->id,
                'label' => $this->formatDosenLabel($d),
            ]),
            // Untuk sub-form "buat jadwal otomatis" — sama seperti opsi yang dipakai Jadwal\Form.
            'jadwalHariOptions' => collect(Jadwal::HARI)->mapWithKeys(fn ($h) => [$h => ucfirst($h)])->all(),
            'jadwalJenisKuliahOptions' => JenisKuliah::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
            'jadwalRuanganOptions' => Ruangan::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.web');
    }
}
