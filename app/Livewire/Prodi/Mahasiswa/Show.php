<?php

namespace App\Livewire\Prodi\Mahasiswa;

use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public int $mahasiswaId;

    public string $tab = 'biodata';

    /**
     * Sama persis dengan MahasiswaController::showProdi — 403 kalau user sama sekali tidak punya
     * scope, 404 kalau mahasiswa tidak ada, 403 (bukan 404) kalau mahasiswa ada tapi di luar scope
     * prodi user.
     */
    public function mount(int $id): void
    {
        $this->mahasiswaId = $id;

        $user = Auth::user();
        $allowedProdiIds = $user && $user->hasScopeRestriction() ? $user->getAllowedProdiIds() : null;
        abort_if($user && $user->hasScopeRestriction() && empty($allowedProdiIds), 403, 'Akses ditolak.');

        $mahasiswa = Mahasiswa::find($id);
        abort_if($mahasiswa === null, 404, 'Mahasiswa tidak ditemukan.');
        abort_if(
            $allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true),
            403,
            'Anda tidak memiliki akses ke data mahasiswa ini.'
        );
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['biodata', 'nilai', 'tagihan'], true) ? $tab : 'biodata';
    }

    #[Computed]
    public function mahasiswa(): Mahasiswa
    {
        return Mahasiswa::with([
            'prodi', 'status_akademik', 'kelompok_kelas', 'jalur_masuk', 'jenis_daftar',
            'negara', 'provinsi', 'kota', 'semester_masuk',
            'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah',
            'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu',
            'pendidikan_wali', 'pekerjaan_wali', 'penghasilan_wali',
        ])->findOrFail($this->mahasiswaId);
    }

    /**
     * Sama persis dengan MahasiswaController::showProdi (bagian dosen_wali).
     */
    #[Computed]
    public function dosenWali(): ?array
    {
        $row = DB::table('dosen_wali')
            ->join('dosen', 'dosen_wali.id_dosen', '=', 'dosen.id')
            ->where('dosen_wali.id_mahasiswa', $this->mahasiswaId)
            ->where('dosen_wali.status', 'active')
            ->whereNull('dosen_wali.deleted_at')
            ->select('dosen.id as id_dosen', 'dosen.nama as nama', 'dosen.nidn as nidn')
            ->first();

        return $row ? ['id' => $row->id_dosen, 'nama' => $row->nama, 'nidn' => $row->nidn] : null;
    }

    /**
     * Sama persis dengan NilaiController::getNilaiBySemesterForMahasiswaProdi, dikelompokkan per
     * semester. Hanya KRS yang sudah di-ACC (whereNotNull approved_at) yang diikutkan.
     */
    #[Computed]
    public function nilaiBySemester()
    {
        $krsList = Krs::with(['kelas.kurikulumMatkul.matkul', 'kelas.semester', 'kelas.dosenPic'])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (Krs $krs) => $krs->kelas && $krs->kelas->semester);

        $nilaiMap = Nilai::whereIn('id_krs', $krsList->pluck('id'))->whereNull('deleted_at')->get()->keyBy('id_krs');

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
     * Sama persis dengan TagihanController::getTagihanBySemesterForMahasiswaProdi, dikelompokkan
     * per semester. Hanya pembayaran yang sudah di-approve yang dihitung sebagai total pembayaran.
     */
    #[Computed]
    public function tagihanBySemester()
    {
        $tagihanList = Tagihan::with('semester')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->orderByDesc('tanggal_tagihan')
            ->get()
            ->filter(fn (Tagihan $t) => $t->semester !== null);

        $tagihanIds = $tagihanList->pluck('id');
        $totalPembayaranByTagihan = Pembayaran::whereIn('id_tagihan', $tagihanIds)
            ->whereNull('deleted_at')
            ->whereNotNull('approved_at')
            ->selectRaw('id_tagihan, SUM(nominal) as total')
            ->groupBy('id_tagihan')
            ->pluck('total', 'id_tagihan');

        return $tagihanList->groupBy(fn (Tagihan $t) => $t->semester->id)
            ->map(function ($items) use ($totalPembayaranByTagihan) {
                $totalTagihanSemester = 0;
                $totalPembayaranSemester = 0;

                $tagihanListMapped = $items->map(function (Tagihan $t) use ($totalPembayaranByTagihan, &$totalTagihanSemester, &$totalPembayaranSemester) {
                    $totalPembayaran = (float) ($totalPembayaranByTagihan[$t->id] ?? 0);
                    $total = (float) $t->total;
                    $totalTagihanSemester += $total;
                    $totalPembayaranSemester += $totalPembayaran;

                    return [
                        'tagihan' => $t,
                        'total_pembayaran' => $totalPembayaran,
                        'sisa_tagihan' => $total - $totalPembayaran,
                    ];
                });

                return [
                    'semester' => $items->first()->semester,
                    'tagihan_list' => $tagihanListMapped,
                    'total_tagihan_semester' => $totalTagihanSemester,
                    'total_pembayaran_semester' => $totalPembayaranSemester,
                ];
            })
            ->sortByDesc(fn ($group) => $group['semester']->kode)
            ->values();
    }

    public function render()
    {
        return view('livewire.prodi.mahasiswa.show')->extends('layouts.prodi');
    }
}
