<?php

namespace App\Livewire\Prodi\Krs;

use App\Models\KelompokKelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Semester;
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

    // Properti filter terikat <x-searchable-select> harus string, bukan ?int — lihat catatan di
    // SKILL.md soal TypeError pada opsi kosong. Tidak ada default semester aktif di sini — lihat
    // catatan yang sama di Prodi\JadwalKuliah\Index: default itu pernah bikin halaman tampak
    // kosong padahal datanya ada, hanya belum tentu di semester yang sedang aktif.
    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'id_semester_masuk')]
    public string $filterAngkatan = '';

    // Filter kelompok kelas memakai mahasiswa.id_kelompok_kelas, bukan id_grup_mahasiswa seperti di
    // app/prodi/krs/page.tsx: tabel grup_mahasiswa sudah tidak dipakai (kosong), jadi dropdown dan
    // filter grup di sana tidak pernah berfungsi. Pola yang sama dengan Prodi\Mahasiswa\Index.
    #[Url(as: 'id_kelompok_kelas')]
    public string $filterKelompokKelas = '';

    public int $perPage = 10;

    /** Id mahasiswa yang modal detail KRS-nya sedang dibuka. */
    public ?int $detailMahasiswaId = null;

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAngkatan(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKelompokKelas(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int>|null
     */
    private function allowedProdiIds(): ?array
    {
        $user = Auth::user();

        return $user && $user->hasScopeRestriction() ? $user->getAllowedProdiIds() : null;
    }

    /**
     * Sama persis dengan KrsController::getKrsBySemesterForMahasiswaProdi — 404 (bukan 403) kalau
     * mahasiswa di luar scope, karena dari sudut pandang admin prodi lain, mahasiswa itu memang
     * "tidak ada". detailMahasiswaId bukan properti Locked (diisi dari wire:click di baris tabel),
     * jadi dicek ulang di sini terlepas dari baris tabel yang sudah difilter scope.
     */
    public function openDetailModal(int $idMahasiswa): void
    {
        $allowedProdiIds = $this->allowedProdiIds();
        abort_if($allowedProdiIds === null || $allowedProdiIds === [], 403, 'Anda tidak memiliki akses.');

        $mahasiswa = Mahasiswa::find($idMahasiswa);
        abort_if(
            $mahasiswa === null || ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true),
            404,
            'Mahasiswa tidak ditemukan.'
        );

        $this->detailMahasiswaId = $idMahasiswa;
    }

    public function closeDetailModal(): void
    {
        $this->detailMahasiswaId = null;
    }

    #[Computed]
    public function detailMahasiswa(): ?Mahasiswa
    {
        if (! $this->detailMahasiswaId) {
            return null;
        }

        return Mahasiswa::with(['prodi', 'semester_masuk'])->find($this->detailMahasiswaId);
    }

    /**
     * Sama persis dengan KrsController::getKrsBySemesterForMahasiswaProdi — dikelompokkan per
     * semester (bukan daftar rata seperti Admin\Krs\Show), dan ikut disaring oleh filter semester
     * yang sama dengan tabel utama, sama seperti modal di app/prodi/krs/page.tsx.
     *
     * @return array<int, array{semester: array, krs: array, total_sks_diajukan: int, total_sks_diacc: int}>
     */
    #[Computed]
    public function detailKrsBySemester(): array
    {
        if (! $this->detailMahasiswaId) {
            return [];
        }

        $query = Krs::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi',
            'kelas.semester',
            'kelas.dosenPic',
        ])
            ->where('id_mahasiswa', $this->detailMahasiswaId)
            ->whereNull('deleted_at');

        if ($this->filterSemester !== '') {
            $semesterId = (int) $this->filterSemester;
            $query->whereHas('kelas', function ($q) use ($semesterId) {
                $q->where('id_semester', $semesterId);
            });
        }

        $krsList = $query->orderByDesc('created_at')->get();

        $bySemester = [];
        foreach ($krsList as $krs) {
            $semester = $krs->kelas->semester ?? null;
            if (! $semester) {
                continue;
            }
            $semesterId = $semester->id;
            if (! isset($bySemester[$semesterId])) {
                $bySemester[$semesterId] = [
                    'semester' => ['id' => $semester->id, 'kode' => $semester->kode, 'nama' => $semester->nama],
                    'krs' => [],
                    'total_sks_diajukan' => 0,
                    'total_sks_diacc' => 0,
                ];
            }
            $sks = $krs->kelas->kurikulumMatkul->matkul->sks ?? 0;
            $bySemester[$semesterId]['total_sks_diajukan'] += $sks;
            if ($krs->approved_at) {
                $bySemester[$semesterId]['total_sks_diacc'] += $sks;
            }
            $bySemester[$semesterId]['krs'][] = [
                'id' => $krs->id,
                'matkul_nama' => $krs->kelas->kurikulumMatkul->matkul->nama ?? null,
                'matkul_kode' => $krs->kelas->kurikulumMatkul->matkul->kode ?? null,
                'kelas_nama' => $krs->kelas->nama ?? null,
                'dosen_nama' => $krs->kelas->dosenPic->nama ?? null,
                'sks' => $sks,
                'status' => $krs->approved_at ? 'approved' : 'pending',
            ];
        }

        // Terbaru dulu berdasarkan kode semester, BUKAN id — id cuma urutan pembuatan baris,
        // sehingga semester lama yang diinput belakangan ikut naik ke atas. Sama seperti
        // KrsController::getKrsBySemester.
        usort($bySemester, fn ($a, $b) => $b['semester']['kode'] <=> $a['semester']['kode']);

        return array_values($bySemester);
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')->limit(100)->get(['id', 'kode', 'nama'])
            ->mapWithKeys(fn (Semester $s) => [$s->id => "{$s->kode} - {$s->nama}"])
            ->all();
    }

    #[Computed]
    public function kelompokKelasOptions()
    {
        return KelompokKelas::orderBy('nama')->limit(100)->get(['id', 'nama']);
    }

    /**
     * Sama persis dengan KrsController::mapKrsIndexRows.
     *
     * @param  Collection<int, object>  $results
     * @return array<int, array<string, mixed>>
     */
    private function mapRows($results): array
    {
        $mahasiswaIds = $results->pluck('id_mahasiswa')->toArray();
        $dosenWaliData = [];
        if ($mahasiswaIds !== []) {
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
        if ($prodiIds !== []) {
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

        return $results->map(fn ($item) => [
            'id_mahasiswa' => (int) $item->id_mahasiswa,
            'nim' => $item->nim,
            'nama' => $item->nama,
            'prodi_nama' => $item->prodi_nama,
            'jenjang_kode' => $jenjangData[$item->id_prodi] ?? null,
            'dosen_wali' => $dosenWaliData[$item->id_mahasiswa] ?? '—',
            'sks_diajukan' => isset($item->sks_diajukan) ? (int) $item->sks_diajukan : 0,
            'sks_diacc' => isset($item->sks_diacc) ? (int) $item->sks_diacc : 0,
            'total_kelas' => isset($item->total_kelas) ? (int) $item->total_kelas : 0,
        ])->values()->all();
    }

    /**
     * Sama persis dengan cabang normal KrsController::indexProdi (bukan cabang
     * status_pengajuan=belum_mengajukan — filter itu tidak ada di app/prodi/krs/page.tsx, jadi
     * tidak diporting, sama seperti filter id_jenis_matkul yang di-skip di Prodi\Matkul\Index).
     */
    public function render()
    {
        $allowedProdiIds = $this->allowedProdiIds();
        $perPage = $this->perPage;
        $page = $this->getPage();

        if ($allowedProdiIds === null || $allowedProdiIds === []) {
            $krsList = new LengthAwarePaginator([], 0, $perPage, $page, ['path' => request()->url(), 'pageName' => 'page']);

            return view('livewire.prodi.krs.index', ['krsList' => $krsList])->extends('layouts.prodi');
        }

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
            ->whereIn('mahasiswa.id_prodi', $allowedProdiIds)
            ->groupBy('krs.id_mahasiswa');

        if ($this->filterAngkatan !== '') {
            $query->where('mahasiswa.id_semester_masuk', (int) $this->filterAngkatan);
        }

        if ($this->filterSemester !== '') {
            $query->where('kelas.id_semester', (int) $this->filterSemester);
        }

        if ($this->filterKelompokKelas !== '') {
            $query->where('mahasiswa.id_kelompok_kelas', (int) $this->filterKelompokKelas);
        }

        $totalQuery = clone $query;
        $total = DB::table(DB::raw('('.$totalQuery->toSql().') as sub'))
            ->mergeBindings($totalQuery->getQuery())
            ->count();

        $offset = ($page - 1) * $perPage;
        $results = $query->orderBy('mahasiswa.nim')->offset($offset)->limit($perPage)->get();

        $krsList = new LengthAwarePaginator(
            $this->mapRows($results),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        return view('livewire.prodi.krs.index', ['krsList' => $krsList])->extends('layouts.prodi');
    }
}
