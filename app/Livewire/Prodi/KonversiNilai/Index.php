<?php

namespace App\Livewire\Prodi\KonversiNilai;

use App\Models\KonversiNilai;
use App\Models\Nilai;
use App\Models\RentangNilai;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter terikat <x-searchable-select> harus string, bukan ?int — lihat catatan di
    // SKILL.md soal TypeError pada opsi kosong. Tidak ada default semester aktif — lihat catatan
    // yang sama di Prodi\JadwalKuliah\Index. "Semester" di sini berarti tahun berlaku kurikulum
    // (kurikulum.id_tahun_berlaku), BUKAN semester periode kelas seperti di modul Jadwal Kuliah/KRS.
    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'id_semester_masuk')]
    public string $filterAngkatan = '';

    public int $perPage = 10;

    /** Id baris konversi_nilai yang modal detailnya sedang dibuka. */
    public ?int $detailId = null;

    public bool $transferBusy = false;

    public string $transferMessage = '';

    public string $transferError = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAngkatan(): void
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
     * Sama persis dengan KonversiNilaiController::serializeKonversiNilaiForProdi.
     */
    private function serialize(KonversiNilai $k): array
    {
        $m = $k->mahasiswa;
        $kur = $k->kurikulum;

        return [
            'id' => $k->id,
            'is_approved' => (bool) $k->is_approved,
            'id_nilai' => $k->id_nilai ? (int) $k->id_nilai : null,
            'mahasiswa' => $m ? [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama' => $m->nama,
                'prodi' => $m->prodi ? ['id' => $m->prodi->id, 'nama' => $m->prodi->nama, 'kode' => $m->prodi->kode] : null,
                'semester_masuk' => $m->semester_masuk ? [
                    'id' => $m->semester_masuk->id,
                    'kode' => $m->semester_masuk->kode,
                    'nama' => $m->semester_masuk->nama,
                ] : null,
            ] : null,
            'kurikulum' => $kur ? [
                'id' => $kur->id,
                'kode' => $kur->kode,
                'nama' => $kur->nama,
                'tahun_berlaku' => $kur->tahunBerlaku ? [
                    'id' => $kur->tahunBerlaku->id,
                    'kode' => $kur->tahunBerlaku->kode,
                    'nama' => $kur->tahunBerlaku->nama,
                ] : null,
            ] : null,
            'jenis_konversi' => $k->jenisKonversi ? ['id' => $k->jenisKonversi->id, 'nama' => $k->jenisKonversi->nama] : null,
            'kode_mk_lama' => $k->kode_mk_lama,
            'nama_mk_lama' => $k->nama_mk_lama,
            'sks_lama' => (int) $k->sks_lama,
            'nilai_lama' => $k->nilai_lama,
            'kode_mk_baru' => $k->kode_mk_baru,
            'nama_mk_baru' => $k->nama_mk_baru,
            'sks_baru' => (int) $k->sks_baru,
            'nilai_baru' => $k->nilai_baru,
            'keterangan' => $k->keterangan,
            'created_at' => $k->created_at,
        ];
    }

    /**
     * Sama persis dengan KonversiNilaiController::setApprovalProdi.
     */
    public function toggleApproval(int $id, bool $isApproved): void
    {
        $allowedProdiIds = $this->allowedProdiIds();
        $k = KonversiNilai::with('mahasiswa')->find($id);

        abort_if($k === null, 404, 'Data konversi tidak ditemukan.');
        abort_if(
            $allowedProdiIds !== null && $k->mahasiswa && ! in_array((int) $k->mahasiswa->id_prodi, $allowedProdiIds, true),
            403,
            'Anda tidak memiliki akses ke data ini.'
        );

        $user = Auth::user();
        $k->is_approved = $isApproved;
        $k->updated_by = $user?->name ?? (string) $user?->id;
        $k->save();
    }

    /**
     * detailId bukan properti Locked (diisi dari wire:click di baris tabel), jadi dicek ulang di
     * sini — sama persis dengan KonversiNilaiController::showProdi.
     */
    public function openDetailModal(int $id): void
    {
        $allowedProdiIds = $this->allowedProdiIds();
        $k = KonversiNilai::with('mahasiswa')->find($id);

        abort_if($k === null, 404, 'Data konversi tidak ditemukan.');
        abort_if(
            $allowedProdiIds !== null && $k->mahasiswa && ! in_array((int) $k->mahasiswa->id_prodi, $allowedProdiIds, true),
            403,
            'Anda tidak memiliki akses ke data ini.'
        );

        $this->detailId = $id;
        $this->transferMessage = '';
        $this->transferError = '';
    }

    public function closeDetailModal(): void
    {
        $this->detailId = null;
        $this->transferMessage = '';
        $this->transferError = '';
    }

    /**
     * Sama persis dengan KonversiNilaiController::showProdi.
     */
    #[Computed]
    public function detailKonversiNilai(): ?array
    {
        if (! $this->detailId) {
            return null;
        }

        $k = KonversiNilai::query()
            ->whereNull('konversi_nilai.deleted_at')
            ->with([
                'mahasiswa' => function ($q) {
                    $q->select('id', 'nim', 'nama', 'id_prodi', 'id_semester_masuk')
                        ->with(['prodi:id,nama,kode', 'semester_masuk:id,kode,nama']);
                },
                'kurikulum' => function ($q) {
                    $q->select('id', 'kode', 'nama', 'id_prodi', 'id_tahun_berlaku')
                        ->with(['tahunBerlaku:id,kode,nama']);
                },
                'jenisKonversi:id,nama',
                'nilai:id,huruf_mutu,angka_mutu',
            ])
            ->find($this->detailId);

        if (! $k) {
            return null;
        }

        $payload = $this->serialize($k);
        $payload['nilai_krs'] = $k->nilai ? [
            'huruf_mutu' => $k->nilai->huruf_mutu,
            'angka_mutu' => $k->nilai->angka_mutu !== null ? (string) $k->nilai->angka_mutu : null,
        ] : null;
        $payload['updated_at'] = $k->updated_at;
        $payload['updated_by'] = $k->updated_by;

        return $payload;
    }

    /**
     * Sama persis dengan KonversiNilaiController::transferToNilaiProdi.
     */
    public function transferToNilai(): void
    {
        if (! $this->detailId) {
            return;
        }

        $this->transferBusy = true;
        $this->transferMessage = '';
        $this->transferError = '';

        $allowedProdiIds = $this->allowedProdiIds();
        $k = KonversiNilai::query()
            ->whereNull('deleted_at')
            ->with(['mahasiswa' => function ($q) {
                $q->select('id', 'nim', 'nama', 'id_prodi')->with(['prodi:id,id_jenjang,nama,kode']);
            }])
            ->find($this->detailId);

        if (! $k || ! $k->mahasiswa) {
            $this->transferError = 'Data konversi tidak ditemukan.';
            $this->transferBusy = false;

            return;
        }

        abort_if(
            $allowedProdiIds !== null && ! in_array((int) $k->mahasiswa->id_prodi, $allowedProdiIds, true),
            403,
            'Anda tidak memiliki akses ke data ini.'
        );

        if (! $k->is_approved) {
            $this->transferError = 'Konversi harus disetujui sebelum ditransfer ke nilai.';
            $this->transferBusy = false;

            return;
        }

        if ($k->id_nilai && Nilai::query()->whereKey($k->id_nilai)->whereNull('deleted_at')->exists()) {
            $this->transferError = 'Konversi ini sudah terhubung ke data nilai.';
            $this->transferBusy = false;

            return;
        }

        $prodi = $k->mahasiswa->prodi;
        if (! $prodi || ! $prodi->id_jenjang) {
            $this->transferError = 'Program studi mahasiswa belum memiliki jenjang (id_jenjang). Lengkapi data prodi dan rentang nilai.';
            $this->transferBusy = false;

            return;
        }

        $hurufKonversi = strtoupper(trim($k->nilai_baru));
        $rentang = RentangNilai::query()
            ->where('id_jenjang', (int) $prodi->id_jenjang)
            ->whereRaw('UPPER(TRIM(nilai_huruf)) = ?', [$hurufKonversi])
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        if (! $rentang) {
            $this->transferError = 'Tidak ada rentang nilai untuk huruf "'.$k->nilai_baru.'" pada jenjang prodi ini. Perbarui master rentang nilai.';
            $this->transferBusy = false;

            return;
        }

        $user = Auth::user();
        $actor = $user ? ($user->name ?? (string) $user->id) : 'system';

        $nilaiExisting = Nilai::query()->where('id_konversi_nilai', $k->id)->whereNull('deleted_at')->first();

        if ($nilaiExisting) {
            if (! $k->id_nilai) {
                DB::transaction(function () use ($k, $nilaiExisting, $actor) {
                    $k->id_nilai = $nilaiExisting->id;
                    $k->updated_by = $actor;
                    $k->save();
                });
            }

            $this->transferMessage = 'Konversi ini sudah memiliki data nilai.';
            $this->transferBusy = false;
            unset($this->detailKonversiNilai);

            return;
        }

        $sksBaru = max(1, (int) $k->sks_baru);

        try {
            DB::transaction(function () use ($k, $rentang, $sksBaru, $actor) {
                $atribut = [
                    'id_krs' => null,
                    'id_konversi_nilai' => $k->id,
                    'sks' => $sksBaru,
                    'angka_mutu' => $rentang->nilai_angka,
                    'huruf_mutu' => $rentang->nilai_huruf,
                    'is_final' => true,
                    'revisi' => 0,
                    'created_by' => $actor,
                ];

                // Nilai soft-deleted dipulihkan: unique id_konversi_nilai ikut menghitungnya.
                $nilai = Nilai::pulihkanUntukKonversiBaru($k->id);
                if ($nilai) {
                    $nilai->update($atribut);
                } else {
                    $nilai = Nilai::create($atribut);
                }

                $k->id_nilai = $nilai->id;
                $k->updated_by = $actor;
                $k->save();
            });
        } catch (\Throwable $e) {
            $this->transferError = 'Gagal menyimpan nilai.';
            $this->transferBusy = false;

            return;
        }

        $this->transferMessage = 'Konversi berhasil ditransfer ke nilai.';
        $this->transferBusy = false;
        unset($this->detailKonversiNilai);
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')->limit(100)->get(['id', 'kode', 'nama'])
            ->mapWithKeys(fn (Semester $s) => [$s->id => "{$s->kode} - {$s->nama}"])
            ->all();
    }

    /**
     * Sama persis dengan KonversiNilaiController::indexProdi.
     */
    public function render()
    {
        $allowedProdiIds = $this->allowedProdiIds();

        $query = KonversiNilai::query()
            ->whereNull('konversi_nilai.deleted_at')
            ->with([
                'mahasiswa' => function ($q) {
                    $q->select('id', 'nim', 'nama', 'id_prodi', 'id_semester_masuk')
                        ->with(['prodi:id,nama,kode', 'semester_masuk:id,kode,nama']);
                },
                'kurikulum' => function ($q) {
                    $q->select('id', 'kode', 'nama', 'id_prodi', 'id_tahun_berlaku')
                        ->with(['tahunBerlaku:id,kode,nama']);
                },
                'jenisKonversi:id,nama',
            ]);

        if ($allowedProdiIds !== null) {
            $query->whereHas('mahasiswa', fn ($q) => $q->whereIn('id_prodi', $allowedProdiIds));
        }

        if ($this->search !== '') {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->whereHas('mahasiswa', function ($mq) use ($s) {
                    $mq->where('nama', 'like', "%{$s}%")->orWhere('nim', 'like', "%{$s}%");
                })
                    ->orWhere('kode_mk_lama', 'like', "%{$s}%")
                    ->orWhere('nama_mk_lama', 'like', "%{$s}%")
                    ->orWhere('kode_mk_baru', 'like', "%{$s}%")
                    ->orWhere('nama_mk_baru', 'like', "%{$s}%");
            });
        }

        if ($this->filterAngkatan !== '') {
            $angkatanId = (int) $this->filterAngkatan;
            $query->whereHas('mahasiswa', fn ($q) => $q->where('id_semester_masuk', $angkatanId));
        }

        if ($this->filterSemester !== '') {
            $semesterId = (int) $this->filterSemester;
            $query->whereHas('kurikulum', fn ($q) => $q->where('id_tahun_berlaku', $semesterId));
        }

        $konversiList = $query->orderByDesc('konversi_nilai.created_at')
            ->orderByDesc('konversi_nilai.id')
            ->paginate($this->perPage);

        $konversiList->getCollection()->transform(fn (KonversiNilai $k) => $this->serialize($k));

        return view('livewire.prodi.konversi-nilai.index', [
            'konversiList' => $konversiList,
        ])->extends('layouts.prodi');
    }
}
