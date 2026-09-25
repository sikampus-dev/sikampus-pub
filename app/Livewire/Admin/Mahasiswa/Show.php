<?php

namespace App\Livewire\Admin\Mahasiswa;

use App\Livewire\Admin\Mahasiswa\Concerns\ForwardsIndexState;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Semester;
use App\Models\StatusMahasiswa;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $mahasiswaId;

    public string $activeTab = 'biodata';

    public bool $confirmingMahasiswaDelete = false;

    public function mount(int $id): void
    {
        $this->mahasiswaId = $id;

        $mahasiswa = Mahasiswa::findOrFail($id);

        $this->authorizeScope($mahasiswa);

        $this->resolveBackToIndexUrl();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Sama dengan MahasiswaController::show — scope-check + eager load.
     */
    protected function authorizeScope(Mahasiswa $mahasiswa): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data mahasiswa ini.');
            }
        }
    }

    #[Computed]
    public function mahasiswa(): Mahasiswa
    {
        return Mahasiswa::with([
            'prodi',
            'status_akademik',
            'kelompok_kelas',
            'jalur_masuk',
            'jenis_daftar',
            'negara',
            'provinsi',
            'kota',
            'semester_masuk',
            'pendidikan_ayah',
            'pekerjaan_ayah',
            'penghasilan_ayah',
            'pendidikan_ibu',
            'pekerjaan_ibu',
            'penghasilan_ibu',
            'pendidikan_wali',
            'pekerjaan_wali',
            'penghasilan_wali',
        ])->findOrFail($this->mahasiswaId);
    }

    /**
     * Sama dengan KrsController::buildKrsBySemesterPayload, dikelompokkan per semester.
     */
    #[Computed]
    public function krsBySemester()
    {
        $krsList = Krs::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.semester',
            'kelas.dosenPic',
        ])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (Krs $krs) => $krs->kelas && $krs->kelas->semester);

        return $krsList->groupBy(fn (Krs $krs) => $krs->kelas->semester->id)
            ->map(function ($items) {
                $sksOf = fn (Krs $krs) => $krs->kelas->kurikulumMatkul->matkul->sks ?? 0;

                return [
                    'semester' => $items->first()->kelas->semester,
                    'krs' => $items,
                    'total_sks_diajukan' => $items->sum($sksOf),
                    'total_sks_diacc' => $items->filter(fn (Krs $krs) => $krs->approved_at)->sum($sksOf),
                ];
            })
            // Kode, bukan id — lihat KrsController::buildKrsBySemesterPayload.
            ->sortByDesc(fn ($group) => $group['semester']->kode)
            ->values();
    }

    /**
     * Sama dengan NilaiController::getNilaiBySemesterForMahasiswa, dikelompokkan per semester.
     */
    #[Computed]
    public function nilaiBySemester()
    {
        $krsList = Krs::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.semester',
            'kelas.dosenPic',
        ])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (Krs $krs) => $krs->kelas && $krs->kelas->semester);

        $nilaiMap = Nilai::whereIn('id_krs', $krsList->pluck('id'))
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id_krs');

        return $krsList->groupBy(fn (Krs $krs) => $krs->kelas->semester->id)
            ->map(function ($items) use ($nilaiMap) {
                $totalSks = 0;
                $totalAngkaMutu = 0;
                $totalSksDenganNilai = 0;

                $nilaiList = $items->map(function (Krs $krs) use ($nilaiMap, &$totalSks, &$totalAngkaMutu, &$totalSksDenganNilai) {
                    $sks = $krs->kelas->kurikulumMatkul->matkul->sks ?? 0;
                    $nilai = $nilaiMap->get($krs->id);

                    $totalSks += $sks;
                    if ($nilai) {
                        $totalAngkaMutu += ($nilai->angka_mutu ?? 0) * $sks;
                        $totalSksDenganNilai += $sks;
                    }

                    return ['krs' => $krs, 'nilai' => $nilai, 'sks' => $sks];
                });

                return [
                    'semester' => $items->first()->kelas->semester,
                    'nilai_list' => $nilaiList,
                    'total_sks' => $totalSks,
                    'total_sks_dengan_nilai' => $totalSksDenganNilai,
                    'ip' => $totalSksDenganNilai > 0 ? round($totalAngkaMutu / $totalSksDenganNilai, 2) : 0,
                ];
            })
            ->sortByDesc(fn ($group) => $group['semester']->kode)
            ->values();
    }

    /**
     * Sama dengan MahasiswaController::getStatusAkademikBySemester.
     */
    #[Computed]
    public function aktivitasList()
    {
        return StatusMahasiswa::query()
            ->with(['semester:id,kode,nama', 'status_akademik:id,nama'])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->orderBy(
                Semester::select('kode')->whereColumn('semester.id', 'status_mahasiswa.id_semester')
            )
            ->get();
    }

    public function confirmDeleteMahasiswa(): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak hapus, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di deleteMahasiswa() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'mahasiswa', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus mahasiswa.');

        $this->confirmingMahasiswaDelete = true;
    }

    public function cancelDeleteMahasiswa(): void
    {
        $this->confirmingMahasiswaDelete = false;
    }

    /**
     * Sama seperti MahasiswaController::destroy — scope dicek ulang supaya admin
     * yang di-scope ke prodi tertentu tidak bisa hapus lewat id langsung.
     */
    public function deleteMahasiswa()
    {
        abort_unless(PanelAccess::can(Auth::user(), 'mahasiswa', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus mahasiswa.');

        $mahasiswa = Mahasiswa::findOrFail($this->mahasiswaId);

        $this->authorizeScope($mahasiswa);

        $mahasiswa->delete();

        session()->flash('status', 'Mahasiswa berhasil dihapus.');

        return redirect()->route('admin.administrasi.mahasiswa');
    }

    public function render()
    {
        return view('livewire.admin.mahasiswa.show')->extends('layouts.web');
    }
}
