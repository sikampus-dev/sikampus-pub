<?php

namespace App\Livewire\Admin\Krs;

use App\Exceptions\PenghapusanDiblokir;
use App\Livewire\Admin\Krs\Concerns\ForwardsIndexState;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Services\PendaftaranKrs;
use App\Services\UrutanMatkulService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $mahasiswaId;

    public string $filterSemester = '';

    public ?int $confirmDeleteId = null;

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

    public function mount(int $id): void
    {
        $this->mahasiswaId = $id;
        $this->resolveBackUrl();

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
        return Mahasiswa::with(['prodi', 'semester_masuk'])->findOrFail($this->mahasiswaId);
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
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    /**
     * Sama persis dengan KrsController::destroy. Scope sudah dijamin lewat mount() (mahasiswa di
     * halaman ini sudah dicek), dan where id_mahasiswa di bawah memastikan id yang dikirim dari
     * client benar-benar milik mahasiswa tsb — bukan sekadar disembunyikan dari tampilan.
     */
    public function delete(): void
    {
        if (! $this->confirmDeleteId) {
            return;
        }

        Krs::where('id', $this->confirmDeleteId)
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->firstOrFail()
            ->delete();

        $this->confirmDeleteId = null;
        unset($this->krsList, $this->summary);
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
     * bukan menggagalkan seluruh aksi: satu baris bermasalah tidak boleh membatalkan puluhan baris
     * lain yang sudah benar.
     */
    public function bulkDelete(): void
    {
        $terpilih = $this->krsTerpilih();

        if ($terpilih->isEmpty()) {
            $this->confirmingBulkDelete = false;

            return;
        }

        $dihapus = 0;
        $dihapusPermanen = 0;
        $dilewati = [];

        DB::transaction(function () use ($terpilih, &$dihapus, &$dihapusPermanen, &$dilewati): void {
            foreach ($terpilih as $krs) {
                $label = $krs->kelas?->kurikulumMatkul?->matkul?->kode ?? "ID {$krs->id}";

                if (! $krs->trashed()) {
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
            return;
        }

        $this->confirmingBulkDelete = true;
    }

    public function cancelBulkDelete(): void
    {
        $this->confirmingBulkDelete = false;
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
