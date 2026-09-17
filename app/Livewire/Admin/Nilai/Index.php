<?php

namespace App\Livewire\Admin\Nilai;

use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Pagination\LengthAwarePaginator;
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
    // halaman detail/ubah (lihat Nilai\Concerns\ForwardsIndexState). `page` sudah ditangani
    // WithPagination; filter tidak — tanpa ini, kembali ke ?page=3 memulihkan halamannya tapi
    // membuang filternya, sehingga yang tampil halaman 3 dari daftar yang lain.
    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'id_semester_masuk')]
    public string $filterSemesterMasuk = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemesterMasuk(): void
    {
        $this->resetPage();
    }

    /**
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
     * Sama persis dengan NilaiController::index.
     */
    public function render()
    {
        $user = Auth::user();

        $query = Mahasiswa::select([
            'mahasiswa.id',
            'mahasiswa.nim',
            'mahasiswa.nama',
            'prodi.id as id_prodi',
            'prodi.nama as prodi_nama',
            DB::raw('COUNT(DISTINCT krs.id) as jumlah_mata_kuliah'),
        ])
            ->leftJoin('prodi', 'mahasiswa.id_prodi', '=', 'prodi.id')
            ->leftJoin('krs', function ($join) {
                $join->on('krs.id_mahasiswa', '=', 'mahasiswa.id')
                    ->whereNull('krs.deleted_at');
            })
            ->whereNull('mahasiswa.deleted_at')
            ->groupBy('mahasiswa.id', 'mahasiswa.nim', 'mahasiswa.nama', 'prodi.id', 'prodi.nama');

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('mahasiswa.nama', 'like', "%{$s}%")
                    ->orWhere('mahasiswa.nim', 'like', "%{$s}%");
            });
        }

        $filterProdi = $this->filterProdi;
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('mahasiswa.id_prodi', $allowedProdiIds);
                if ($filterProdi !== '' && ! in_array((int) $filterProdi, $allowedProdiIds, true)) {
                    $filterProdi = '';
                }
            }
        }

        if ($filterProdi !== '') {
            $query->where('mahasiswa.id_prodi', (int) $filterProdi);
        }

        if ($this->filterSemesterMasuk !== '') {
            $query->where('mahasiswa.id_semester_masuk', (int) $this->filterSemesterMasuk);
        }

        // groupBy bikin ->paginate() bawaan Eloquent salah menghitung total — total dihitung
        // manual lewat subquery, sama seperti NilaiController::index.
        $totalQuery = clone $query;
        $total = DB::table(DB::raw('('.$totalQuery->toSql().') as sub'))
            ->mergeBindings($totalQuery->getQuery())
            ->count();

        $perPage = $this->perPage;
        $page = $this->getPage();
        $offset = ($page - 1) * $perPage;

        $results = $query->orderBy('mahasiswa.nim')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $nilaiList = new LengthAwarePaginator(
            $results->values()->all(),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        // Diteruskan ke link Detail, lalu dibaca kembali oleh Nilai\Concerns\ForwardsIndexState.
        // Kunci harus sama dengan alias #[Url] di atas.
        $returnQuery = http_build_query(array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'id_semester_masuk' => $this->filterSemesterMasuk !== '' ? $this->filterSemesterMasuk : null,
            'page' => $nilaiList->currentPage() > 1 ? $nilaiList->currentPage() : null,
        ], fn ($value) => $value !== null));

        return view('livewire.admin.nilai.index', [
            'nilaiList' => $nilaiList,
            'returnQuery' => $returnQuery,
        ])->extends('layouts.web');
    }
}
