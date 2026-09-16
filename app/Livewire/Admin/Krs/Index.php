<?php

namespace App\Livewire\Admin\Krs;

use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail/ubah (lihat Krs\Concerns\ForwardsIndexState). `page` sudah ditangani
    // WithPagination; filter tidak — tanpa ini, kembali ke ?page=3 memulihkan halamannya tapi
    // membuang filternya, sehingga yang tampil halaman 3 dari daftar yang lain.
    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'status')]
    public string $filterStatusPengajuan = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatusPengajuan(): void
    {
        $this->resetPage();
    }

    /**
     * Array asosiatif id => label (bukan Collection model) supaya label komposit "Nama - Kode
     * Jenjang" tetap tampil persis seperti sebelumnya lewat <x-searchable-select>, yang kalau
     * diberi Collection akan pakai default optionLabel 'nama' saja (tanpa suffix jenjang).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function prodiOptions(): array
    {
        $query = Prodi::query()->with('jenjang')->orderBy('nama');

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id', $allowedProdiIds);
            }
        }

        return $query->get(['id', 'nama', 'kode', 'id_jenjang'])
            ->mapWithKeys(fn ($prodi) => [$prodi->id => $prodi->nama.($prodi->jenjang ? ' - '.$prodi->jenjang->kode : '')])
            ->all();
    }

    /**
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
     * @return array<string, string>
     */
    public function statusPengajuanOptions(): array
    {
        return [
            'belum_mengajukan' => 'Belum mengajukan (di semester terpilih)',
            'ada_belum_acc' => 'Ada yang belum di-ACC',
            'sudah_acc_semua' => 'Sudah ACC semua',
        ];
    }

    /**
     * Mahasiswa yang tidak punya baris KRS untuk kelas pada semester tertentu.
     * Sama persis dengan KrsController::mahasiswaTanpaKrsSemesterQuery.
     *
     * @param  array<int>|null  $allowedProdiIds
     */
    private function mahasiswaTanpaKrsSemesterQuery(int $semesterId, ?array $allowedProdiIds): Builder
    {
        $q = Mahasiswa::query()
            ->select([
                'mahasiswa.id as id_mahasiswa',
                'mahasiswa.nim',
                'mahasiswa.nama',
                'prodi.id as id_prodi',
                'prodi.nama as prodi_nama',
                'prodi.id_jenjang',
            ])
            ->join('prodi', 'mahasiswa.id_prodi', '=', 'prodi.id')
            ->whereNull('mahasiswa.deleted_at')
            ->whereNull('prodi.deleted_at')
            ->whereNotExists(function ($sub) use ($semesterId): void {
                $sub->select(DB::raw('1'))
                    ->from('krs')
                    ->join('kelas', 'krs.id_kelas', '=', 'kelas.id')
                    ->whereColumn('krs.id_mahasiswa', 'mahasiswa.id')
                    ->where('kelas.id_semester', $semesterId)
                    ->whereNull('krs.deleted_at');
            });

        if ($allowedProdiIds !== null) {
            $q->whereIn('mahasiswa.id_prodi', $allowedProdiIds);
        }

        if ($this->search !== '') {
            $s = $this->search;
            $q->where(function ($w) use ($s): void {
                $w->where('mahasiswa.nama', 'like', "%{$s}%")
                    ->orWhere('mahasiswa.nim', 'like', "%{$s}%");
            });
        }

        if ($this->filterProdi !== '') {
            $q->where('mahasiswa.id_prodi', (int) $this->filterProdi);
        }

        return $q;
    }

    /**
     * Query agregasi grouped per mahasiswa. Sama persis dengan KrsController::index (cabang normal).
     *
     * @param  array<int>|null  $allowedProdiIds
     */
    private function aggregateQuery(?array $allowedProdiIds): Builder
    {
        $query = Krs::select([
            'krs.id_mahasiswa',
            DB::raw('MAX(mahasiswa.nim) as nim'),
            DB::raw('MAX(mahasiswa.nama) as nama'),
            DB::raw('MAX(prodi.id) as id_prodi'),
            DB::raw('MAX(prodi.nama) as prodi_nama'),
            DB::raw('MAX(prodi.id_jenjang) as id_jenjang'),
            DB::raw('COUNT(DISTINCT krs.id) as total_kelas'),
            DB::raw('COALESCE(SUM(CASE WHEN krs.approved_at IS NOT NULL THEN matkul.sks ELSE 0 END), 0) as sks_diacc'),
            DB::raw('COALESCE(SUM(matkul.sks), 0) as sks_diajukan'),
        ])
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->join('prodi', 'mahasiswa.id_prodi', '=', 'prodi.id')
            ->join('kelas', 'krs.id_kelas', '=', 'kelas.id')
            ->join('kurikulum_matkul', 'kelas.id_kurikulum_matkul', '=', 'kurikulum_matkul.id')
            ->join('matkul', 'kurikulum_matkul.id_matkul', '=', 'matkul.id')
            ->whereNull('krs.deleted_at')
            ->whereNull('mahasiswa.deleted_at')
            ->groupBy('krs.id_mahasiswa');

        if ($allowedProdiIds !== null) {
            $query->whereIn('mahasiswa.id_prodi', $allowedProdiIds);
        }

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('mahasiswa.nama', 'like', "%{$s}%")
                    ->orWhere('mahasiswa.nim', 'like', "%{$s}%");
            });
        }

        if ($this->filterProdi !== '') {
            $query->where('mahasiswa.id_prodi', (int) $this->filterProdi);
        }

        if ($this->filterSemester !== '') {
            $query->where('kelas.id_semester', (int) $this->filterSemester);
        }

        if ($this->filterStatusPengajuan === 'ada_belum_acc') {
            $query->havingRaw('SUM(CASE WHEN krs.approved_at IS NULL THEN 1 ELSE 0 END) > 0');
        } elseif ($this->filterStatusPengajuan === 'sudah_acc_semua') {
            $query->havingRaw('COUNT(DISTINCT krs.id) > 0 AND SUM(CASE WHEN krs.approved_at IS NULL THEN 1 ELSE 0 END) = 0');
        }

        return $query;
    }

    /**
     * Sama persis dengan KrsController::mapKrsIndexRows — dosen wali & jenjang diambil
     * lewat raw query terpisah (bukan eager load) karena baris hasil query di sini bukan
     * model Eloquent biasa (hasil groupBy/select mentah).
     *
     * @param  Collection<int, object>  $results
     * @return array<int, array<string, mixed>>
     */
    private function mapRows($results): array
    {
        $mahasiswaIds = $results->pluck('id_mahasiswa')->toArray();
        $dosenWaliData = [];
        if (! empty($mahasiswaIds)) {
            $dosenWaliResults = DB::table('dosen_wali')
                ->join('dosen', 'dosen_wali.id_dosen', '=', 'dosen.id')
                ->whereIn('dosen_wali.id_mahasiswa', $mahasiswaIds)
                ->where('dosen_wali.status', 'active')
                ->whereNull('dosen_wali.deleted_at')
                ->select('dosen_wali.id_mahasiswa', 'dosen.nama as dosen_nama')
                ->get();

            foreach ($dosenWaliResults as $dw) {
                $dosenWaliData[$dw->id_mahasiswa] = $dw->dosen_nama;
            }
        }

        $prodiIds = $results->pluck('id_prodi')->filter()->unique()->toArray();
        $jenjangData = [];
        if (! empty($prodiIds)) {
            $jenjangResults = DB::table('prodi')
                ->join('jenjang', 'prodi.id_jenjang', '=', 'jenjang.id')
                ->whereIn('prodi.id', $prodiIds)
                ->whereNull('prodi.deleted_at')
                ->whereNull('jenjang.deleted_at')
                ->select('prodi.id as prodi_id', 'jenjang.kode as jenjang_kode')
                ->get();

            foreach ($jenjangResults as $j) {
                $jenjangData[$j->prodi_id] = $j->jenjang_kode;
            }
        }

        return $results->map(function ($item) use ($dosenWaliData, $jenjangData) {
            return [
                'id_mahasiswa' => (int) $item->id_mahasiswa,
                'nim' => $item->nim,
                'nama' => $item->nama,
                'prodi_nama' => $item->prodi_nama,
                'jenjang_kode' => $jenjangData[$item->id_prodi] ?? null,
                'dosen_wali' => $dosenWaliData[$item->id_mahasiswa] ?? '—',
                'sks_diajukan' => isset($item->sks_diajukan) ? (int) $item->sks_diajukan : 0,
                'sks_diacc' => isset($item->sks_diacc) ? (int) $item->sks_diacc : 0,
                'total_kelas' => isset($item->total_kelas) ? (int) $item->total_kelas : 0,
            ];
        })->values()->all();
    }

    /**
     * Query string state Index untuk diteruskan ke link Detail — dibaca kembali oleh
     * Krs\Concerns\ForwardsIndexState. Kunci harus sama dengan alias #[Url] di atas.
     */
    private function returnQuery(int $halaman): string
    {
        return http_build_query(array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'id_semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'status' => $this->filterStatusPengajuan !== '' ? $this->filterStatusPengajuan : null,
            'page' => $halaman > 1 ? $halaman : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * Sama persis dengan KrsController::index — dua cabang query disalin apa adanya
     * (bukan diekstrak jadi shared service, mengikuti pola modul lain).
     */
    public function render()
    {
        $user = Auth::user();
        $allowedProdiIds = null;
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
        }

        $perPage = $this->perPage;
        $page = $this->getPage();
        $belumMengajukanError = '';

        if ($this->filterStatusPengajuan === 'belum_mengajukan' && $this->filterSemester === '') {
            $belumMengajukanError = 'Untuk status belum mengajukan, pilih filter semester terlebih dahulu.';
            $krsList = new LengthAwarePaginator([], 0, $perPage, $page, ['path' => request()->url(), 'pageName' => 'page']);

            return view('livewire.admin.krs.index', [
                'krsList' => $krsList,
                'belumMengajukanError' => $belumMengajukanError,
                'returnQuery' => $this->returnQuery($page),
            ])->extends('layouts.web');
        }

        if ($this->filterStatusPengajuan === 'belum_mengajukan') {
            $query = $this->mahasiswaTanpaKrsSemesterQuery((int) $this->filterSemester, $allowedProdiIds);
            $orderColumn = 'mahasiswa.nim';
        } else {
            $query = $this->aggregateQuery($allowedProdiIds);
            $orderColumn = 'mahasiswa.nim';
        }

        // groupBy/having bikin ->paginate() bawaan Eloquent salah menghitung total, jadi total
        // dihitung manual lewat subquery — sama seperti KrsController::index.
        $totalQuery = clone $query;
        $total = DB::table(DB::raw('('.$totalQuery->toSql().') as sub'))
            ->mergeBindings($totalQuery->getQuery())
            ->count();

        $offset = ($page - 1) * $perPage;
        $results = $query->orderBy($orderColumn)->offset($offset)->limit($perPage)->get();

        $rows = $this->mapRows($results);

        $krsList = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        return view('livewire.admin.krs.index', [
            'krsList' => $krsList,
            'belumMengajukanError' => $belumMengajukanError,
            'returnQuery' => $this->returnQuery($krsList->currentPage()),
        ])->extends('layouts.web');
    }
}
