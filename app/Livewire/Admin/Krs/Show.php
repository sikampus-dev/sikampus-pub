<?php

namespace App\Livewire\Admin\Krs;

use App\Exceptions\PenghapusanDiblokir;
use App\Livewire\Admin\Krs\Concerns\ForwardsIndexState;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Semester;
use App\Services\PendaftaranKrs;
use App\Services\UrutanMatkulService;
use App\Support\PanelAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $mahasiswaId;

    public string $search = '';

    public string $filterSemester = '';

    public ?int $confirmDeleteId = null;

    /**
     * Opsi di modal konfirmasi hapus — dipakai bersama oleh hapus satuan (delete()) dan hapus
     * massal (bulkDelete()): kalau dicentang, nilai yang terkait KRS ikut di-soft-delete —
     * termasuk nilai FINAL, yang sebetulnya menahan Krs::delete() lewat Krs::$hapusDiblokirOleh
     * (lihat hapusKrsBesertaNilai()). Tanpa opsi ini dicentang, perilakunya tetap seperti
     * sebelumnya: nilai belum final ikut terhapus otomatis lewat AturanHapusBerantai, nilai final
     * tetap memblokir (satuan: peringatan; massal: baris itu dilewati).
     */
    public bool $hapusNilaiTerkait = false;

    // Baris yang sudah soft-deleted disembunyikan secara default — dinyalakan lewat toggle supaya
    // admin bisa memulihkan atau menghapusnya permanen. Sama seperti pola di
    // App\Livewire\Admin\Kelas\Index dan App\Livewire\Admin\Nilai\Show.
    public bool $showTrashed = false;

    public ?int $confirmForceDeleteId = null;

    /**
     * Id KRS yang dicentang untuk hapus massal — baris hidup maupun baris yang sudah terhapus,
     * karena aksinya memang berbeda per baris (lihat bulkDelete()).
     *
     * @var array<int, string>
     */
    public array $selected = [];

    public bool $confirmingBulkDelete = false;

    // ---- Modal tambah KRS — form yang sama dengan mode create Krs\Form, tapi tanpa langkah
    // cari-pilih mahasiswa: mahasiswa sudah tetap, yaitu mahasiswa halaman ini. Lihat
    // simpanTambahKrs() untuk logika simpan (disalin dari Krs\Form::saveCreate).
    public bool $showTambahKrsModal = false;

    /** @var array<int, array{id_kelas: int|null, status: string|null}> */
    public array $tambahKrs = [];

    public string $tambahKrsError = '';

    public function mount(int $id): void
    {
        $this->mahasiswaId = $id;
        $this->resolveBackUrl();

        // Beda dari id_semester yang dibawa resolveBackUrl() (filter Index, untuk tombol Kembali):
        // ini filter semester milik halaman detail ini sendiri, dikirim balik lewat query string
        // id_semester_detail oleh Krs\Form (lihat urlDetail() di sana) setelah admin mengubah KRS,
        // supaya filter yang sedang aktif di sini tidak hilang begitu redirect kembali.
        $this->filterSemester = (string) request()->query('id_semester_detail', '');

        $mahasiswa = Mahasiswa::findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data KRS mahasiswa ini.');
            }
        }
    }

    #[Computed]
    public function mahasiswa()
    {
        return Mahasiswa::with(['prodi', 'semester_masuk', 'kelompok_kelas'])->findOrFail($this->mahasiswaId);
    }

    /**
     * Sama persis dengan KrsController::show — dosen wali diambil lewat raw query, bukan relasi.
     */
    #[Computed]
    public function dosenWali(): string
    {
        $row = DB::table('dosen_wali')
            ->join('dosen', 'dosen_wali.id_dosen', '=', 'dosen.id')
            ->where('dosen_wali.id_mahasiswa', $this->mahasiswaId)
            ->where('dosen_wali.status', 'active')
            ->whereNull('dosen_wali.deleted_at')
            ->select('dosen.nama as dosen_nama')
            ->first();

        return $row ? $row->dosen_nama : '—';
    }

    /**
     * Array asosiatif id => label (bukan Collection model) supaya cocok dipakai langsung oleh
     * <x-searchable-select> — sama seperti App\Livewire\Admin\Krs\Index::semesterOptions.
     *
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
     * Opsi kelas untuk modal tambah KRS, dibatasi ke prodi mahasiswa halaman ini — sama seperti
     * Krs\Form::kelasOptions, disederhanakan karena mahasiswanya sudah tetap (bukan hasil pilihan
     * pencarian di form create biasa).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function kelasOptionsTambahKrs(): array
    {
        $prodiId = $this->mahasiswa->id_prodi;

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

    public function bukaTambahKrsModal(): void
    {
        $this->tambahKrs = [['id_kelas' => null, 'status' => 'pending']];
        $this->tambahKrsError = '';
        $this->resetValidation();
        $this->showTambahKrsModal = true;
    }

    public function tutupTambahKrsModal(): void
    {
        $this->showTambahKrsModal = false;
        $this->tambahKrs = [];
        $this->tambahKrsError = '';
        $this->resetValidation();
    }

    public function addTambahKrsRow(): void
    {
        $this->tambahKrs[] = ['id_kelas' => null, 'status' => 'pending'];
    }

    public function removeTambahKrsRow(int $index): void
    {
        if (count($this->tambahKrs) <= 1) {
            return;
        }

        unset($this->tambahKrs[$index]);
        $this->tambahKrs = array_values($this->tambahKrs);
    }

    /**
     * Sama persis dengan Krs\Form::saveCreate (dan KrsController::store) — id_mahasiswa di sini
     * selalu mahasiswaId halaman ini, bukan hasil cari-pilih, jadi tidak ada langkah pemilihan
     * mahasiswa maupun pengecekan scope untuknya (sudah dijamin di mount()). Scope kelas tetap
     * diperiksa per baris karena opsinya bisa saja dimanipulasi dari luar daftar yang ditampilkan.
     */
    public function simpanTambahKrs(): void
    {
        $this->tambahKrsError = '';

        $validated = $this->validate([
            'tambahKrs' => ['required', 'array', 'min:1'],
            'tambahKrs.*.id_kelas' => ['required', 'integer', 'exists:kelas,id'],
            'tambahKrs.*.status' => ['nullable', 'string', Rule::in(['pending', 'acc'])],
        ]);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                foreach ($validated['tambahKrs'] as $row) {
                    $kelas = Kelas::find($row['id_kelas']);
                    if (! $kelas || ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                        abort(403, 'Anda tidak memiliki akses ke kelas ini.');
                    }
                }
            }
        }

        $errors = [];
        $createdCount = 0;

        DB::beginTransaction();
        try {
            foreach ($validated['tambahKrs'] as $row) {
                $exists = Krs::where('id_mahasiswa', $this->mahasiswaId)
                    ->where('id_kelas', $row['id_kelas'])
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    $errors[] = 'Salah satu kelas yang dipilih sudah ada di KRS mahasiswa ini.';

                    continue;
                }

                $kelasBaris = Kelas::find($row['id_kelas']);
                $sudahTerdaftar = $kelasBaris ? PendaftaranKrs::krsMataKuliahSamaDenganKelas($this->mahasiswaId, $kelasBaris) : null;
                if ($sudahTerdaftar) {
                    $errors[] = PendaftaranKrs::pesanSudahTerdaftar($sudahTerdaftar);

                    continue;
                }

                $status = $row['status'] ?: 'pending';
                $isApproved = $status === 'acc';

                Krs::create([
                    'id_mahasiswa' => $this->mahasiswaId,
                    'id_kelas' => $row['id_kelas'],
                    'approved_by' => $isApproved ? ($user->name ?? $user->email ?? null) : null,
                    'approved_at' => $isApproved ? now() : null,
                ]);

                $createdCount++;
            }

            if (! empty($errors)) {
                DB::rollBack();
                $this->tambahKrsError = implode(' ', array_unique($errors));

                return;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->tambahKrsError = 'Terjadi kesalahan saat menyimpan KRS: '.$e->getMessage();

            return;
        }

        $this->showTambahKrsModal = false;
        $this->tambahKrs = [];
        unset($this->krsList, $this->summary);

        session()->flash('status', "{$createdCount} data KRS berhasil dibuat.");
    }

    /**
     * Sama persis dengan KrsController::show.
     */
    #[Computed]
    public function krsList()
    {
        $query = Krs::with([
            // Kelas ikut withTrashed karena kelas bisa terhapus setelah KRS-nya terhapus; tanpa ini
            // relasinya null dan seluruh baris KRS terhapus gagal dirender.
            'kelas' => fn ($q) => $q->withTrashed(),
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi',
            'kelas.semester',
            'kelas.dosenPic',
            'kelas.kelompokKelas',
        ])
            ->where('id_mahasiswa', $this->mahasiswaId);

        // withTrashed() tidak ada padanannya di KrsController — API belum punya jalur ini, murni
        // fitur panel (lihat catatan pada restore()).
        if ($this->showTrashed) {
            $query->withTrashed();
        } else {
            $query->whereNull('deleted_at');
        }

        if ($this->filterSemester !== '') {
            $semesterId = (int) $this->filterSemester;
            $query->whereHas('kelas', function ($q) use ($semesterId) {
                $q->where('id_semester', $semesterId);
            });
        }

        // Sama seperti pencarian di Nilai\Show — cari lewat kode atau nama mata kuliah, bukan lewat
        // kelas itu sendiri.
        if ($this->search !== '') {
            $s = $this->search;
            $query->whereHas('kelas.kurikulumMatkul.matkul', function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                    ->orWhere('kode', 'like', "%{$s}%");
            });
        }

        // Diurutkan berdasarkan nama mata kuliah; created_at tetap jadi tie-breaker karena
        // sortBy di PHP 8 stabil.
        return UrutanMatkulService::urutkanKrs($query->orderByDesc('created_at')->get());
    }

    #[Computed]
    public function summary(): array
    {
        $totalSksDiajukan = 0;
        $totalSksDiacc = 0;

        // KRS terhapus tidak pernah ikut dihitung, walau barisnya sedang ditampilkan.
        foreach ($this->krsList->reject->trashed() as $krs) {
            $sks = $krs->kelas->kurikulumMatkul->matkul->sks ?? 0;
            $totalSksDiajukan += $sks;
            if ($krs->approved_at) {
                $totalSksDiacc += $sks;
            }
        }

        return [
            'total_krs' => $this->krsList->reject->trashed()->count(),
            'sks_diajukan' => $totalSksDiajukan,
            'sks_diacc' => $totalSksDiacc,
        ];
    }

    // Centang dibuang setiap kali daftarnya berubah: baris yang tidak lagi terlihat tetap ikut
    // terhapus kalau centangnya dibiarkan, dan tidak ada yang sadar sampai datanya hilang.
    public function updatingSearch(): void
    {
        $this->selected = [];
        unset($this->krsList, $this->summary);
    }

    public function updatingFilterSemester(): void
    {
        $this->selected = [];
        unset($this->krsList, $this->summary);
    }

    public function updatingShowTrashed(): void
    {
        $this->selected = [];
        unset($this->krsList, $this->summary);
    }

    public function toggleSelectAll(): void
    {
        $semua = $this->krsList->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = count($this->selected) === count($semua) ? [] : $semua;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
        $this->hapusNilaiTerkait = false;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
        $this->hapusNilaiTerkait = false;
    }

    /**
     * Opsi "hapus juga nilai terkait" cuma masuk akal (dan cuma ditampilkan) buat admin yang
     * memang punya hak hapus nilai — kalau tidak, pengguna KRS bisa diam-diam menghapus nilai lewat
     * jalur ini walau tombol hapus nilai sendiri di halaman Nilai disembunyikan darinya.
     */
    public function bisaHapusNilai(): bool
    {
        return PanelAccess::can(Auth::user(), 'nilai', 'delete');
    }

    /**
     * Sama persis dengan KrsController::destroy selama $hapusNilaiTerkait tidak dicentang. Scope
     * sudah dijamin lewat mount() (mahasiswa di halaman ini sudah dicek), dan where id_mahasiswa di
     * bawah memastikan id yang dikirim dari client benar-benar milik mahasiswa tsb — bukan sekadar
     * disembunyikan dari tampilan.
     */
    public function delete(): void
    {
        if (! $this->confirmDeleteId) {
            return;
        }

        $krs = Krs::where('id', $this->confirmDeleteId)
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->firstOrFail();

        if ($this->hapusNilaiTerkait && $this->bisaHapusNilai()) {
            $this->hapusKrsBesertaNilai($krs);
        } else {
            $krs->delete();
        }

        $this->confirmDeleteId = null;
        $this->hapusNilaiTerkait = false;
        unset($this->krsList, $this->summary);
    }

    /**
     * Tidak ada padanan di KrsController — API belum punya opsi ini, murni fitur panel. Nilai
     * (final maupun belum final) dihapus DULUAN secara eksplisit, baru KRS-nya — begitu nilai final
     * sudah tidak lagi "hidup", Krs::$hapusDiblokirOleh (lihat AturanHapusBerantai) tidak lagi
     * menemukan yang menahan, jadi $krs->delete() yang berikutnya berjalan normal alih-alih
     * melempar PenghapusanDiblokir.
     *
     * deleted_at nilai dan KRS dibekukan ke waktu yang SAMA (pola yang sama dengan
     * AturanHapusBerantai::hapusAnakBerantai) supaya keduanya tetap dianggap "terhapus bersama" —
     * begitu KRS ini dipulihkan, nilainya (dan komponen/revisi di bawahnya) ikut pulih lewat
     * pulihkanAnakBerantai(), bukan tertinggal terhapus sendirian.
     */
    private function hapusKrsBesertaNilai(Krs $krs): void
    {
        $user = Auth::user();
        $deletedBy = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';

        DB::transaction(function () use ($krs, $deletedBy): void {
            $jamSebelumnya = Carbon::getTestNow();
            Carbon::setTestNow(now());

            try {
                // nilai.id_krs unik (termasuk baris soft-deleted), jadi satu KRS paling banyak
                // punya satu nilai hidup untuk dihapus di sini.
                $nilai = Nilai::where('id_krs', $krs->id)->whereNull('deleted_at')->first();
                if ($nilai) {
                    $nilai->deleted_by = $deletedBy;
                    $nilai->save();
                    $nilai->delete();
                }

                $krs->delete();
            } finally {
                Carbon::setTestNow($jamSebelumnya);
            }
        });
    }

    /**
     * Tidak ada padanan di KrsController — API belum punya endpoint restore, murni fitur panel.
     * Nilai belum final, komponen, dan revisi yang terhapus BERSAMA KRS ini ikut dipulihkan lewat
     * AturanHapusBerantai (Krs::$hapusBerantai).
     *
     * Dua hal yang harus dicek dulu, dan keduanya bukan soal unique index — krs_unique
     * (id_mahasiswa + id_kelas) sudah mencakup baris terhapus, jadi baris kembar persis mustahil:
     *
     * - Kelasnya bisa saja ikut terhapus setelah KRS ini dihapus. Memulihkan KRS ke kelas mati
     *   menghasilkan baris yang tidak bisa ditampilkan (relasi kelas null di seluruh halaman).
     * - Mahasiswa bisa sudah didaftarkan ulang ke KELAS PARALEL untuk mata kuliah & semester yang
     *   sama. Memulihkan di atas itu melahirkan pendaftaran ganda — persis yang dicegah
     *   App\Services\PendaftaranKrs di jalur impor dan pengajuan.
     */
    public function restore(int $id): void
    {
        $krs = $this->findTrashedKrsMilikMahasiswa($id);

        $kelas = Kelas::withTrashed()->find($krs->id_kelas);

        if (! $kelas || $kelas->trashed()) {
            session()->flash('error', 'Tidak bisa memulihkan KRS ini: kelasnya sudah dihapus. Pulihkan kelas itu terlebih dahulu.');

            return;
        }

        $bentrok = PendaftaranKrs::krsMataKuliahSamaDenganKelas($this->mahasiswaId, $kelas, $krs->id);
        if ($bentrok) {
            session()->flash('error', 'Tidak bisa memulihkan KRS ini: '.PendaftaranKrs::pesanSudahTerdaftar($bentrok));

            return;
        }

        DB::transaction(fn () => $krs->restore());

        unset($this->krsList, $this->summary);

        session()->flash('status', 'KRS berhasil dipulihkan.');
    }

    public function confirmForceDelete(int $id): void
    {
        $this->confirmForceDeleteId = $id;
    }

    public function cancelForceDelete(): void
    {
        $this->confirmForceDeleteId = null;
    }

    /**
     * Tidak ada padanan di KrsController — API belum punya endpoint hapus permanen, murni fitur panel.
     */
    public function forceDeleteKrs(): void
    {
        if (! $this->confirmForceDeleteId) {
            return;
        }

        $krs = $this->findTrashedKrsMilikMahasiswa($this->confirmForceDeleteId);

        $blockers = $this->blockersUntuk($krs);

        if ($blockers !== []) {
            session()->flash('error', 'Tidak bisa menghapus permanen KRS ini: masih tercatat di data '.implode(', ', $blockers).'. Hapus atau pindahkan data itu terlebih dahulu.');
            $this->confirmForceDeleteId = null;

            return;
        }

        DB::transaction(fn () => $this->hapusPermanenSatu($krs));

        $this->confirmForceDeleteId = null;
        unset($this->krsList, $this->summary);

        session()->flash('status', 'KRS berhasil dihapus permanen.');
    }

    /**
     * Hapus massal. KRS yang masih hidup di-soft-delete (bisa dipulihkan), KRS yang SUDAH terhapus
     * dihapus permanen — satu tombol dengan dua akibat yang jauh berbeda, jadi modal konfirmasinya
     * menyebut jumlah masing-masing lebih dulu dan hasilnya dilaporkan terpisah.
     *
     * Baris yang ditolak (punya nilai final, atau sisa nilainya masih dipakai konversi) dilewati,
     * bukan menggagalkan seluruh aksi — KECUALI kalau opsi $hapusNilaiTerkait dicentang (dan admin
     * berhak, lihat bisaHapusNilai()): baris berstatus nilai final tidak lagi dilewati, melainkan
     * ikut dihapus beserta nilainya lewat hapusKrsBesertaNilai(), sama seperti aksi satuan di
     * delete(). Satu baris bermasalah tetap tidak boleh membatalkan puluhan baris lain yang benar.
     */
    public function bulkDelete(): void
    {
        $terpilih = $this->krsTerpilih();

        if ($terpilih->isEmpty()) {
            $this->confirmingBulkDelete = false;
            $this->hapusNilaiTerkait = false;

            return;
        }

        $hapusNilaiTerkait = $this->hapusNilaiTerkait && $this->bisaHapusNilai();

        $dihapus = 0;
        $dihapusPermanen = 0;
        $dilewati = [];

        DB::transaction(function () use ($terpilih, $hapusNilaiTerkait, &$dihapus, &$dihapusPermanen, &$dilewati): void {
            foreach ($terpilih as $krs) {
                $label = $krs->kelas?->kurikulumMatkul?->matkul?->kode ?? "ID {$krs->id}";

                if (! $krs->trashed()) {
                    if ($hapusNilaiTerkait) {
                        $this->hapusKrsBesertaNilai($krs);
                        $dihapus++;

                        continue;
                    }

                    try {
                        // Nilai final menolak KRS-nya dihapus (Krs::$hapusDiblokirOleh). Di aksi
                        // satuan penolakan itu muncul sebagai peringatan lewat hook `exception`
                        // Livewire; di sini harus ditangkap supaya baris lain tetap terproses.
                        $krs->delete();
                        $dihapus++;
                    } catch (PenghapusanDiblokir) {
                        $dilewati[] = "{$label} (punya nilai final)";
                    }

                    continue;
                }

                $blockers = $this->blockersUntuk($krs);
                if ($blockers !== []) {
                    $dilewati[] = "{$label} (masih tercatat di data ".implode(', ', $blockers).')';

                    continue;
                }

                $this->hapusPermanenSatu($krs);
                $dihapusPermanen++;
            }
        });

        $this->selected = [];
        $this->confirmingBulkDelete = false;
        $this->hapusNilaiTerkait = false;
        unset($this->krsList, $this->summary);

        $pesan = [];
        if ($dihapus > 0) {
            $pesan[] = "{$dihapus} KRS dihapus";
        }
        if ($dihapusPermanen > 0) {
            $pesan[] = "{$dihapusPermanen} KRS dihapus permanen";
        }

        if ($pesan !== []) {
            session()->flash('status', implode(' dan ', $pesan).'.');
        }

        if ($dilewati !== []) {
            session()->flash('error', count($dilewati).' KRS dilewati: '.implode(', ', $dilewati).'.');
        }
    }

    public function confirmBulkDelete(): void
    {
        if ($this->selected === []) {
            // Tombolnya sengaja tetap bisa diklik tanpa Alpine (lihat Blade), jadi keadaan ini
            // harus dijawab dengan pesan — bukan diam saja seolah kliknya tidak terdaftar.
            session()->flash('error', 'Belum ada KRS yang dicentang.');

            return;
        }

        $this->hapusNilaiTerkait = false;
        $this->confirmingBulkDelete = true;
    }

    public function cancelBulkDelete(): void
    {
        $this->confirmingBulkDelete = false;
        $this->hapusNilaiTerkait = false;
    }

    /**
     * Ringkasan untuk modal konfirmasi: berapa yang akan di-soft-delete dan berapa yang akan lenyap
     * permanen. Dihitung ulang dari database, bukan dari apa yang sedang tampil di layar.
     *
     * @return array{hapus: int, permanen: int}
     */
    #[Computed]
    public function ringkasanTerpilih(): array
    {
        $terpilih = $this->krsTerpilih();

        return [
            'hapus' => $terpilih->reject->trashed()->count(),
            'permanen' => $terpilih->filter->trashed()->count(),
        ];
    }

    /**
     * KRS terpilih yang benar-benar milik mahasiswa halaman ini. Id milik mahasiswa lain yang
     * diselipkan lewat request palsu disaring di sini — sama seperti penjagaan `where id_mahasiswa`
     * pada aksi satuan, tapi tanpa menggagalkan seluruh batch.
     *
     * @return Collection<int, Krs>
     */
    private function krsTerpilih(): Collection
    {
        $ids = array_filter(array_map('intval', $this->selected));

        if ($ids === []) {
            return collect();
        }

        return Krs::withTrashed()
            ->with('kelas.kurikulumMatkul.matkul')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereIn('id', $ids)
            ->get();
    }

    private function findTrashedKrsMilikMahasiswa(int $id): Krs
    {
        $krs = Krs::onlyTrashed()
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->find($id);

        // abort(404), bukan findOrFail(): ModelNotFoundException dari komponen Livewire tidak
        // diterjemahkan jadi respons 404, melainkan menggelembung sebagai error 500.
        abort_if($krs === null, 404);

        return $krs;
    }

    /**
     * Baris HIDUP yang menahan hapus permanen. nilai dan nilai_revisi memakai restrictOnDelete, dan
     * konversi nilai menunjuk ke baris nilai milik KRS ini. Sisa yang sudah soft-deleted tidak
     * dihitung di sini — itu justru yang ikut dihapus oleh hapusPermanenSatu().
     *
     * @return array<int, string>
     */
    private function blockersUntuk(Krs $krs): array
    {
        $blockers = [];

        if (DB::table('nilai')->where('id_krs', $krs->id)->whereNull('deleted_at')->exists()) {
            $blockers[] = 'nilai';
        }

        if (DB::table('nilai_revisi')->where('id_krs', $krs->id)->whereNull('deleted_at')->exists()) {
            $blockers[] = 'revisi nilai';
        }

        $konversiQuery = DB::table('konversi_nilai')
            ->whereIn('id_nilai', DB::table('nilai')->where('id_krs', $krs->id)->select('id'));

        if ($konversiQuery->exists()) {
            $blockers[] = 'konversi nilai';
        }

        return $blockers;
    }

    /**
     * Nilai, komponen, dan revisi milik KRS ini dihapus permanen juga — bukan hanya karena foreign
     * key restrictOnDelete akan menolak kalau ditinggalkan, tapi karena sisa itu tidak bisa
     * dipulihkan lewat jalur mana pun begitu KRS-nya lenyap. Sengaja lewat query builder: ini hapus
     * permanen, jadi tidak ada event model atau cascade soft delete yang perlu dijalankan.
     */
    private function hapusPermanenSatu(Krs $krs): void
    {
        DB::table('nilai_komponen')->where('id_krs', $krs->id)->delete();
        DB::table('nilai_revisi')->where('id_krs', $krs->id)->delete();
        DB::table('nilai')->where('id_krs', $krs->id)->delete();

        $krs->forceDelete();
    }

    public function render()
    {
        return view('livewire.admin.krs.show')->extends('layouts.web');
    }
}
