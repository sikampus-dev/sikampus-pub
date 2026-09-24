<?php

namespace App\Livewire\Admin\Kelas;

use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\KelompokKelas;
use App\Models\MateriPerkuliahan;
use App\Models\Perkuliahan;
use App\Models\Prodi;
use App\Models\Rps;
use App\Models\RpsCpl;
use App\Models\RpsCpmk;
use App\Models\RpsPembelajaran;
use App\Models\RpsSubcpmk;
use App\Models\Semester;
use App\Models\Tugas;
use App\Models\TugasMahasiswa;
use App\Models\Ujian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // Kelas memakai App\Models\Concerns\AturanHapusBerantai dengan 4 relasi hapusBerantai: jadwal,
    // kelasDosen, ujian, rps (lihat Kelas::$hapusBerantai) — begitu Kelas di-soft-delete, keempatnya
    // OTOMATIS ikut ter-soft-delete bersamaan (timestamp sama persis). Trait itu sendiri sengaja
    // TIDAK menyentuh forceDelete() ("forceDelete tidak disentuh: di situ foreign key database yang
    // berlaku" — lihat docblock trait-nya), jadi kalau keempat tabel itu diblokir mentah-mentah di
    // sini seperti tabel restrictOnDelete lain, kelas yang dihapus lewat jalur normal nyaris tidak
    // pernah bisa dihapus permanen — persis gejala yang dilaporkan.
    //
    // Makanya keempatnya ditangani KHUSUS di forceDeleteKelas() (lihat blokirHapusBerantaiKelas()
    // dan cascadeForceDeleteJadwalTerhapus()/cascadeForceDeleteRpsTerhapus()): baris yang MASIH AKTIF
    // tetap memblokir sama seperti sebelumnya, tapi baris yang SUDAH soft-deleted ikut dihapus
    // permanen bersama kelasnya, bukan sekadar diblokir. 'krs' TETAP di daftar ini tanpa perubahan —
    // itu hapusDiblokirOleh (bukan hapusBerantai) di Kelas, riwayat akademik yang sengaja TIDAK
    // PERNAH ikut dihapus otomatis meski sudah soft-deleted.
    private const FORCE_DELETE_BLOCKERS = [
        'krs' => ['column' => 'id_kelas', 'label' => 'KRS'],
    ];

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail/ubah (lihat Kelas\Concerns\ForwardsIndexState) — bukan cuma kosmetik alamat browser.
    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter yang terikat <select> harus string, bukan ?int — lihat catatan di SKILL.md.
    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'id_kelompok_kelas')]
    public string $filterKelompokKelas = '';

    // Tidak ada padanan di KelasController::index — API belum punya filter ini, murni fitur
    // panel. Angkatan disimpan sebagai baris Semester juga (Kelas::angkatan() -> belongsTo
    // Semester, lihat id_angkatan), jadi dropdown-nya memakai $semesterOptions yang sama dengan
    // filter Semester, bukan master data terpisah.
    #[Url(as: 'id_angkatan')]
    public string $filterAngkatan = '';

    // Baris yang sudah soft-deleted disembunyikan secara default — dinyalakan lewat toggle supaya
    // admin bisa menemukan lalu memulihkan kelas yang kombinasi kelompok+kurikulum_matkul+
    // semester+angkatan-nya "terkunci" oleh baris terhapus (unique index kelas_unique tidak
    // mengecualikan baris soft-deleted). Sama seperti pola di App\Livewire\Admin\Matkul\Index.
    #[Url(as: 'trashed')]
    public bool $showTrashed = false;

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public ?int $confirmingForceDeleteId = null;

    public function mount(): void
    {
        // Sama seperti default filter semester di app/admin/kelas/page.tsx: semester aktif ter-pilih
        // otomatis — tapi hanya kalau belum ada filter dari query string (mis. saat kembali dari
        // halaman detail/ubah dengan filter semester tertentu yang sudah dipilih sebelumnya).
        if ($this->filterSemester !== '') {
            return;
        }

        $semesterAktif = Semester::where('is_active', true)->first();
        if ($semesterAktif) {
            $this->filterSemester = (string) $semesterAktif->id;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        // Opsi "Kelas Mahasiswa" ikut disaring oleh prodi (lihat render()) — nilai lama yang
        // mungkin sudah tidak relevan untuk prodi baru harus dibuang.
        $this->filterKelompokKelas = '';
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKelompokKelas(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAngkatan(): void
    {
        $this->resetPage();
    }

    public function updatingShowTrashed(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama persis dengan KelasController::destroy — scope-filter dicek ulang di sini.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $kelas = Kelas::findOrFail($this->confirmingDeleteId);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        }

        $kelas->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Tidak ada padanan di KelasController — API belum punya endpoint restore, murni fitur panel.
     * Sama seperti App\Livewire\Admin\Matkul\Index::restore(): kelas_unique (id_kelompok_kelas +
     * id_kurikulum_matkul + id_semester + id_angkatan) tidak mengecualikan baris soft-deleted, jadi
     * dicek dulu supaya tidak menabrak unique constraint dengan error mentah.
     */
    public function restore(int $id): void
    {
        $kelas = Kelas::onlyTrashed()->findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        }

        $conflictQuery = Kelas::where('id_kurikulum_matkul', $kelas->id_kurikulum_matkul)
            ->where('id_semester', $kelas->id_semester)
            ->where('id_angkatan', $kelas->id_angkatan)
            ->whereNull('deleted_at');

        if ($kelas->id_kelompok_kelas !== null) {
            $conflictQuery->where('id_kelompok_kelas', $kelas->id_kelompok_kelas);
        } else {
            $conflictQuery->whereNull('id_kelompok_kelas');
        }

        if ($conflictQuery->exists()) {
            session()->flash('error', 'Tidak bisa memulihkan kelas ini: sudah ada kelas aktif lain dengan kombinasi kelompok kelas, mata kuliah, semester, dan angkatan yang sama.');

            return;
        }

        $kelas->restore();

        session()->flash('status', 'Kelas berhasil dipulihkan.');
        $this->resetPage();
    }

    public function confirmForceDelete(int $id): void
    {
        $this->confirmingForceDeleteId = $id;
    }

    public function cancelForceDelete(): void
    {
        $this->confirmingForceDeleteId = null;
    }

    /**
     * Jadwal yang SUDAH di-soft-delete masih punya data turunan yang MASIH AKTIF (belum
     * di-soft-delete)? AturanHapusBerantai pada Jadwal sudah men-cascade dosen/materiPerkuliahan/
     * tugas begitu jadwal itu sendiri di-soft-delete lewat jalur normal ($jadwal->delete()) — jadi
     * pengecekan jadwal_dosen/materi_perkuliahan di sini murni jaga-jaga kalau jadwalnya sempat
     * dihapus lewat jalur lain (query builder, yang melewati event model dan cascade-nya). Dua
     * relasi lain — perkuliahan (dan kehadiran mahasiswa di bawahnya) serta tugas (dan pengumpulan
     * tugas mahasiswa di bawahnya) — memang TIDAK PERNAH ikut ter-cascade oleh trait itu (keduanya
     * dideklarasikan hapusDiblokirOleh, bukan hapusBerantai, di Jadwal/Tugas), jadi genuinely bisa
     * saja masih hidup walau jadwal induknya sudah soft-deleted — itu sebabnya dicek eksplisit di sini.
     */
    private function jadwalTerhapusPunyaTurunanAktif(int $idJadwal): bool
    {
        if (JadwalDosen::where('id_jadwal', $idJadwal)->whereNull('deleted_at')->exists()) {
            return true;
        }
        if (MateriPerkuliahan::where('id_jadwal', $idJadwal)->whereNull('deleted_at')->exists()) {
            return true;
        }
        if (Perkuliahan::where('id_jadwal', $idJadwal)->whereNull('deleted_at')->exists()) {
            return true;
        }

        $idPerkuliahanTerhapus = Perkuliahan::onlyTrashed()->where('id_jadwal', $idJadwal)->pluck('id');
        if ($idPerkuliahanTerhapus->isNotEmpty()
            && Kehadiran::whereIn('id_perkuliahan', $idPerkuliahanTerhapus)->whereNull('deleted_at')->exists()) {
            return true;
        }

        if (Tugas::where('id_jadwal', $idJadwal)->whereNull('deleted_at')->exists()) {
            return true;
        }

        $idTugasTerhapus = Tugas::onlyTrashed()->where('id_jadwal', $idJadwal)->pluck('id');
        if ($idTugasTerhapus->isNotEmpty()
            && TugasMahasiswa::whereIn('id_tugas', $idTugasTerhapus)->whereNull('deleted_at')->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Hapus permanen satu jadwal (yang sudah dipastikan aman lewat jadwalTerhapusPunyaTurunanAktif())
     * beserta seluruh turunannya yang juga sudah soft-deleted — kehadiran di bawah perkuliahan lalu
     * perkuliahan itu sendiri, pengumpulan tugas mahasiswa lalu tugas itu sendiri, materi_perkuliahan,
     * jadwal_dosen, baru jadwalnya. Urutan dari anak ke induk supaya tidak menabrak restrictOnDelete
     * masing-masing tabel.
     */
    private function cascadeForceDeleteJadwalTerhapus(int $idJadwal): void
    {
        $idPerkuliahan = Perkuliahan::withTrashed()->where('id_jadwal', $idJadwal)->pluck('id');
        if ($idPerkuliahan->isNotEmpty()) {
            Kehadiran::withTrashed()->whereIn('id_perkuliahan', $idPerkuliahan)->forceDelete();
        }
        Perkuliahan::withTrashed()->where('id_jadwal', $idJadwal)->forceDelete();

        $idTugas = Tugas::withTrashed()->where('id_jadwal', $idJadwal)->pluck('id');
        if ($idTugas->isNotEmpty()) {
            TugasMahasiswa::withTrashed()->whereIn('id_tugas', $idTugas)->forceDelete();
        }
        Tugas::withTrashed()->where('id_jadwal', $idJadwal)->forceDelete();

        MateriPerkuliahan::withTrashed()->where('id_jadwal', $idJadwal)->forceDelete();
        JadwalDosen::withTrashed()->where('id_jadwal', $idJadwal)->forceDelete();
        Jadwal::withTrashed()->where('id', $idJadwal)->forceDelete();
    }

    /**
     * Hapus permanen satu RPS beserta seluruh pohon hapusBerantai-nya yang juga sudah soft-deleted
     * (rps_subcpmk di bawah rps_cpmk, rps_cpmk, rps_cpl, rps_pembelajaran) — tidak perlu pengecekan
     * "turunan aktif" terpisah seperti jadwal, karena Rps::$hapusBerantai men-cascade seluruh pohon
     * ini tanpa satu pun hapusDiblokirOleh di dalamnya (lihat App\Models\Rps dan RpsCpmk).
     */
    private function cascadeForceDeleteRpsTerhapus(int $idRps): void
    {
        $idCpmk = RpsCpmk::withTrashed()->where('id_rps', $idRps)->pluck('id');
        if ($idCpmk->isNotEmpty()) {
            RpsSubcpmk::withTrashed()->whereIn('id_cpmk', $idCpmk)->forceDelete();
        }
        RpsCpmk::withTrashed()->where('id_rps', $idRps)->forceDelete();
        RpsCpl::withTrashed()->where('id_rps', $idRps)->forceDelete();
        RpsPembelajaran::withTrashed()->where('id_rps', $idRps)->forceDelete();
        Rps::withTrashed()->where('id', $idRps)->forceDelete();
    }

    /**
     * Blocker untuk keempat relasi hapusBerantai Kelas (lihat catatan FORCE_DELETE_BLOCKERS) —
     * baris yang masih aktif tetap memblokir sama seperti tabel lain; jadwal juga dicek lebih dalam
     * untuk baris yang sudah soft-deleted tapi masih punya turunan aktif (lihat
     * jadwalTerhapusPunyaTurunanAktif()). kelas_dosen dan ujian adalah leaf (tidak direferensikan
     * tabel lain), jadi baris yang sudah soft-deleted di keduanya selalu aman dihapus permanen
     * tanpa pengecekan tambahan.
     *
     * @return array<int, string>
     */
    private function blokirHapusBerantaiKelas(int $idKelas): array
    {
        $blockers = [];

        if (KelasDosen::where('id_kelas', $idKelas)->whereNull('deleted_at')->exists()) {
            $blockers[] = 'dosen pengampu';
        }
        if (Ujian::where('id_kelas', $idKelas)->whereNull('deleted_at')->exists()) {
            $blockers[] = 'ujian';
        }
        if (Rps::where('id_kelas', $idKelas)->whereNull('deleted_at')->exists()) {
            $blockers[] = 'RPS';
        }
        if (Jadwal::where('id_kelas', $idKelas)->whereNull('deleted_at')->exists()) {
            $blockers[] = 'jadwal';
        }

        $idJadwalTerhapus = Jadwal::onlyTrashed()->where('id_kelas', $idKelas)->pluck('id');
        if ($idJadwalTerhapus->contains(fn (int $id) => $this->jadwalTerhapusPunyaTurunanAktif($id))) {
            $blockers[] = 'jadwal yang sudah dihapus (masih ada presensi/dosen pengampu/materi/tugas yang belum dihapus)';
        }

        return $blockers;
    }

    /**
     * Tidak ada padanan di KelasController — API belum punya endpoint hapus permanen, murni fitur
     * panel. Lihat FORCE_DELETE_BLOCKERS untuk 'krs' (satu-satunya yang masih diblokir mentah-mentah
     * apa adanya) dan blokirHapusBerantaiKelas() untuk jadwal/kelas_dosen/ujian/rps.
     */
    public function forceDeleteKelas(): void
    {
        if (! $this->confirmingForceDeleteId) {
            return;
        }

        $kelas = Kelas::onlyTrashed()->findOrFail($this->confirmingForceDeleteId);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        }

        $blockers = [];
        foreach (self::FORCE_DELETE_BLOCKERS as $table => $meta) {
            if (DB::table($table)->where($meta['column'], $kelas->id)->exists()) {
                $blockers[] = $meta['label'];
            }
        }
        array_push($blockers, ...$this->blokirHapusBerantaiKelas($kelas->id));

        if ($blockers !== []) {
            session()->flash('error', 'Tidak bisa menghapus permanen kelas ini: masih tercatat di data '.implode(', ', $blockers).'. Hapus atau pindahkan data itu terlebih dahulu.');
            $this->confirmingForceDeleteId = null;

            return;
        }

        $idJadwalTerhapus = Jadwal::onlyTrashed()->where('id_kelas', $kelas->id)->pluck('id');
        $idRpsTerhapus = Rps::onlyTrashed()->where('id_kelas', $kelas->id)->pluck('id');

        DB::transaction(function () use ($kelas, $idJadwalTerhapus, $idRpsTerhapus): void {
            foreach ($idJadwalTerhapus as $idJadwal) {
                $this->cascadeForceDeleteJadwalTerhapus($idJadwal);
            }
            foreach ($idRpsTerhapus as $idRps) {
                $this->cascadeForceDeleteRpsTerhapus($idRps);
            }
            KelasDosen::withTrashed()->where('id_kelas', $kelas->id)->forceDelete();
            Ujian::withTrashed()->where('id_kelas', $kelas->id)->forceDelete();

            $kelas->forceDelete();
        });

        $this->confirmingForceDeleteId = null;
        session()->flash('status', 'Kelas berhasil dihapus permanen.');
        $this->resetPage();
    }

    /**
     * Urutan master semester (kode ASC), dipakai untuk menghitung "semester kuliah ke-N".
     * Sama persis dengan KelasController::semesterIdToIndexMap.
     *
     * @return array<int, int>
     */
    private function semesterIdToIndexMap(): array
    {
        $ordered = Semester::withTrashed()->orderBy('kode')->pluck('id')->values();
        $map = [];
        foreach ($ordered as $i => $semId) {
            $map[(int) $semId] = $i;
        }

        return $map;
    }

    /**
     * Sama persis dengan KelasController::applySemesterKuliahKe.
     *
     * @param  Collection<int, Kelas>  $collection
     */
    private function applySemesterKuliahKeToCollection($collection, array $map): void
    {
        foreach ($collection as $kelas) {
            $idxS = $map[(int) $kelas->id_semester] ?? null;
            $idxA = $map[(int) $kelas->id_angkatan] ?? null;
            if ($idxS === null || $idxA === null || $idxS < $idxA) {
                $kelas->setAttribute('semester_kuliah_ke', null);

                continue;
            }
            $kelas->setAttribute('semester_kuliah_ke', $idxS - $idxA + 1);
        }
    }

    /**
     * Sama persis dengan KelasController::index, plus withTrashed() opsional lewat $showTrashed
     * (tidak ada padanan di API — lihat catatan pada restore()).
     */
    public function render()
    {
        $query = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'angkatan',
            'dosenPic',
            'kelompokKelas',
        ])
            // Kolom "Jumlah Pertemuan" di tabel dihitung dari baris Jadwal yang benar-benar ada
            // (jadwal_count), BUKAN dari kelas.jml_pertemuan — kolom itu cuma target/rencana yang
            // diisi manual saat kelas dibuat, bisa berbeda dari jumlah slot jadwal yang sungguhan
            // terbentuk (mis. sebagian belum dibuat, atau dibuat lebih lewat import terpisah).
            ->withCount('jadwal')
            // Kolom "Jumlah Mahasiswa" dihitung dari KRS berstatus aktif (approved_at terisi) saja
            // — sama seperti badge "Aktif" di Krs\Show. KRS yang masih pending atau sudah
            // soft-deleted (dikecualikan otomatis oleh global scope SoftDeletes pada Krs) tidak
            // ikut dihitung. Tidak perlu distinct id_mahasiswa: krs_unique (id_mahasiswa +
            // id_kelas) membuat satu mahasiswa mustahil punya lebih dari satu baris KRS aktif
            // untuk kelas yang sama.
            ->withCount(['krs as jumlah_mahasiswa' => function ($q) {
                $q->whereNotNull('approved_at');
            }]);

        if ($this->showTrashed) {
            $query->withTrashed();
        }

        $user = Auth::user();
        $prodiId = $this->filterProdi !== '' ? (int) $this->filterProdi : null;

        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
                if ($prodiId && ! in_array($prodiId, $allowedProdiIds, true)) {
                    $prodiId = null;
                }
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->whereHas('kurikulumMatkul.matkul', function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                        ->orWhere('kode', 'like', "%{$this->search}%");
                })
                    ->orWhereHas('prodi', function ($q) {
                        $q->where('nama', 'like', "%{$this->search}%")
                            ->orWhere('kode', 'like', "%{$this->search}%");
                    })
                    ->orWhereHas('dosenPic', function ($q) {
                        $q->where('nama', 'like', "%{$this->search}%")
                            ->orWhere('kode_dosen', 'like', "%{$this->search}%");
                    });
            });
        }

        if ($prodiId) {
            $query->where('id_prodi', $prodiId);
        }

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        if ($this->filterKelompokKelas !== '') {
            $query->where('id_kelompok_kelas', (int) $this->filterKelompokKelas);
        }

        if ($this->filterAngkatan !== '') {
            $query->where('id_angkatan', (int) $this->filterAngkatan);
        }

        $kelasList = $query->orderBy('id')->paginate($this->perPage);
        $this->applySemesterKuliahKeToCollection($kelasList->getCollection(), $this->semesterIdToIndexMap());

        $prodiQuery = Prodi::with('jenjang')->whereNull('deleted_at');
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $prodiQuery->whereIn('id', $allowedProdiIds);
            }
        }

        // Opsi "Kelas Mahasiswa" mengikuti prodi yang dipilih; kalau belum ada prodi terpilih,
        // tampilkan semua kelas mahasiswa (tidak disaring).
        $kelompokKelasQuery = KelompokKelas::whereNull('deleted_at');
        if ($prodiId) {
            $kelompokKelasQuery->where('id_prodi', $prodiId);
        }

        // Diselipkan ke link "Lihat"/"Ubah" supaya tombol Kembali di halaman detail/ubah bisa
        // mendarat di halaman/filter yang sama persis — lihat Kelas\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'id_semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'id_kelompok_kelas' => $this->filterKelompokKelas !== '' ? $this->filterKelompokKelas : null,
            'id_angkatan' => $this->filterAngkatan !== '' ? $this->filterAngkatan : null,
            'page' => $kelasList->currentPage() > 1 ? $kelasList->currentPage() : null,
        ], fn ($value) => $value !== null);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.kelas.index', [
            'kelasList' => $kelasList,
            'prodiOptions' => $prodiQuery->orderBy('nama')->get()->map(fn (Prodi $p) => (object) [
                'id' => $p->id,
                'label' => $p->jenjang?->kode ? "{$p->nama} ({$p->jenjang->kode})" : $p->nama,
            ]),
            'semesterOptions' => Semester::whereNull('deleted_at')->orderByDesc('kode')->get(['id', 'kode', 'nama'])
                ->map(fn (Semester $s) => (object) ['id' => $s->id, 'label' => "{$s->nama} ({$s->kode})"]),
            'kelompokKelasOptions' => $kelompokKelasQuery->orderBy('nama')->get(['id', 'nama']),
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
