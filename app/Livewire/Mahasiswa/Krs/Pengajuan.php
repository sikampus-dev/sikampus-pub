<?php

namespace App\Livewire\Mahasiswa\Krs;

use App\Models\DosenWali;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\MatkulPrasyarat;
use App\Models\Nilai;
use App\Models\Notifikasi;
use App\Models\Semester;
use App\Services\KalenderAkademikGateService;
use App\Services\KeuanganAksesMahasiswaService;
use App\Services\PendaftaranKrs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Pengajuan extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public string $search = '';

    /** @var array<int, int> */
    public array $selectedKelas = [];

    public ?int $confirmingCancelId = null;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $this->selectedKelas = collect($this->data)
            ->where('sudah_dipilih', true)
            ->pluck('id_kelas')
            ->all();
    }

    /**
     * Sama persis dengan KrsController::getJadwalPengajuan.
     */
    #[Computed]
    public function data(): array
    {
        $mahasiswa = Mahasiswa::with(['prodi', 'semester_masuk', 'kelompok_kelas'])->findOrFail($this->mahasiswaId);
        $activeSemester = $this->activeSemester;

        if (! $activeSemester) {
            return [];
        }

        $query = Kelas::with([
            'kurikulumMatkul.matkul',
            'dosenPic',
            'jadwal' => function ($q) {
                $q->with(['ruangan', 'jenisKuliah'])->orderBy('hari')->orderBy('jam_mulai');
            },
        ])
            ->whereNull('deleted_at')
            ->where('id_semester', $activeSemester->id)
            ->where('is_active', true);

        if ($mahasiswa->id_prodi) {
            $query->where('id_prodi', $mahasiswa->id_prodi);
        }
        if ($mahasiswa->id_semester_masuk) {
            $query->where('id_angkatan', $mahasiswa->id_semester_masuk);
        }
        if ($mahasiswa->id_kelompok_kelas !== null && $mahasiswa->id_kelompok_kelas !== '') {
            $query->where('id_kelompok_kelas', $mahasiswa->id_kelompok_kelas);
        }

        $kelasList = $query->orderBy('id')->get();

        $krsByKelas = Krs::where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->whereIn('id_kelas', $kelasList->pluck('id'))
            ->get()
            ->keyBy('id_kelas');

        return $kelasList->map(function (Kelas $kelas) use ($krsByKelas) {
            $krs = $krsByKelas->get($kelas->id);

            return [
                'id_kelas' => $kelas->id,
                'kode_kelas' => $kelas->kode,
                'matkul' => $kelas->kurikulumMatkul->matkul ?? null,
                'dosen' => $kelas->dosenPic,
                'jadwal' => $kelas->jadwal,
                'sudah_dipilih' => $krs !== null,
                'krs_status' => $krs ? ($krs->approved_at ? 'acc' : 'pending') : null,
                'id_krs' => $krs?->id,
            ];
        })->values()->all();
    }

    #[Computed]
    public function filteredData(): array
    {
        if ($this->search === '') {
            return $this->data;
        }

        $needle = mb_strtolower($this->search);

        return array_values(array_filter($this->data, function (array $item) use ($needle) {
            $matkul = $item['matkul'];
            $matchMatkul = $matkul && (str_contains(mb_strtolower((string) $matkul->kode), $needle) || str_contains(mb_strtolower((string) $matkul->nama), $needle));
            $matchDosen = $item['dosen'] && str_contains(mb_strtolower((string) $item['dosen']->nama), $needle);
            $matchRuangan = collect($item['jadwal'])->contains(fn ($j) => $j->ruangan && str_contains(mb_strtolower((string) $j->ruangan->nama), $needle));

            return $matchMatkul || $matchDosen || $matchRuangan;
        }));
    }

    #[Computed]
    public function activeSemester(): ?Semester
    {
        return Semester::where('is_active', true)->first();
    }

    /**
     * Sama persis dengan TagihanController::cekAksesKeuanganMahasiswa (kode 'krs').
     */
    #[Computed]
    public function financeCheck(): array
    {
        return KeuanganAksesMahasiswaService::canAccessByKode($this->mahasiswaId, 'krs', $this->activeSemester?->id);
    }

    /**
     * Gerbang periode dari kalender akademik (kategori 'krs') — lihat
     * App\Services\KalenderAkademikGateService. Panel admin TIDAK melalui gerbang ini; hanya
     * jalur self-service mahasiswa ini dan KrsController::submitPengajuanKrs (API).
     */
    #[Computed]
    public function periodeKrsCheck(): array
    {
        return KalenderAkademikGateService::isPeriodeAktif('krs', $this->activeSemester?->id);
    }

    #[Computed]
    public function canSubmitNewKrs(): bool
    {
        $financeOk = $this->financeCheck['allowed'] || $this->financeCheck['persentase_minimum_required'] === null;

        return $financeOk && $this->periodeKrsCheck['allowed'];
    }

    #[Computed]
    public function totalSks(): int
    {
        return collect($this->data)
            ->whereIn('id_kelas', $this->selectedKelas)
            ->sum(fn (array $item) => $item['matkul']->sks ?? 0);
    }

    #[Computed]
    public function newSelectionsCount(): int
    {
        return collect($this->data)
            ->filter(fn (array $item) => in_array($item['id_kelas'], $this->selectedKelas, true) && ! $item['sudah_dipilih'])
            ->count();
    }

    public function toggleKelas(int $idKelas): void
    {
        $item = collect($this->data)->firstWhere('id_kelas', $idKelas);
        if (! $item || $item['sudah_dipilih']) {
            return;
        }
        if (! $this->financeCheck['allowed'] && $this->financeCheck['persentase_minimum_required'] !== null) {
            return;
        }

        if (! $this->periodeKrsCheck['allowed']) {
            return;
        }

        if (in_array($idKelas, $this->selectedKelas, true)) {
            $this->selectedKelas = array_values(array_diff($this->selectedKelas, [$idKelas]));
        } else {
            $this->selectedKelas[] = $idKelas;
        }
    }

    public function confirmCancel(int $idKrs): void
    {
        $this->confirmingCancelId = $idKrs;
    }

    public function cancelCancel(): void
    {
        $this->confirmingCancelId = null;
    }

    /**
     * Sama persis dengan KrsController::cancelPengajuanKrs.
     */
    public function cancelKrs(): void
    {
        if (! $this->confirmingCancelId) {
            return;
        }

        $krs = Krs::where('id', $this->confirmingCancelId)
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->first();

        abort_if($krs === null, 404, 'KRS tidak ditemukan atau tidak dapat dibatalkan.');
        abort_if($krs->approved_at !== null, 422, 'KRS yang sudah disetujui tidak dapat dibatalkan.');
        // KRS pending tetap bisa punya nilai final: finalisasi nilai dosen tidak menyaring
        // approved_at. Nilai final menahan penghapusan KRS (AturanHapusBerantai) — dicek di sini
        // supaya mahasiswa mendapat pesan yang ditujukan kepadanya, bukan pesan untuk admin.
        abort_if($krs->pemakaiYangMemblokirHapus() !== [], 422, 'KRS ini sudah memiliki nilai final dan tidak dapat dibatalkan. Silakan hubungi bagian akademik.');

        $krs->delete();

        $this->confirmingCancelId = null;
        unset($this->data, $this->filteredData);
        session()->flash('status', 'Pengajuan berhasil dibatalkan.');
    }

    private function hurufMutuMemenuhiMinimalC(?string $huruf): bool
    {
        if ($huruf === null || trim($huruf) === '') {
            return false;
        }

        return in_array(strtoupper(substr(trim($huruf), 0, 1)), ['A', 'B', 'C'], true);
    }

    private function mahasiswaHasLulusMatkulMinimalC(int $idMatkul): bool
    {
        return Nilai::query()
            ->whereHas('krs', function ($q) use ($idMatkul) {
                $q->where('id_mahasiswa', $this->mahasiswaId)
                    ->whereNull('deleted_at')
                    ->whereHas('kelas.kurikulumMatkul', fn ($q2) => $q2->where('id_matkul', $idMatkul));
            })
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('is_final', true)->orWhereNull('is_final'))
            ->get()
            ->contains(fn (Nilai $n) => $this->hurufMutuMemenuhiMinimalC($n->huruf_mutu));
    }

    /**
     * Sama persis dengan KrsController::submitPengajuanKrs.
     */
    public function submit(): void
    {
        $newSelections = collect($this->data)
            ->filter(fn (array $item) => in_array($item['id_kelas'], $this->selectedKelas, true) && ! $item['sudah_dipilih'])
            ->values();

        if ($newSelections->isEmpty()) {
            $this->addError('selectedKelas', 'Pilih minimal satu mata kuliah baru.');

            return;
        }

        if (! $this->financeCheck['allowed'] && $this->financeCheck['persentase_minimum_required'] !== null) {
            $this->addError('selectedKelas', 'Pengajuan KRS belum dapat dilakukan karena persyaratan administratif keuangan belum terpenuhi.');

            return;
        }

        if (! $this->periodeKrsCheck['allowed']) {
            $this->addError('selectedKelas', $this->periodeKrsCheck['alasan'] ?? 'Pengajuan KRS sedang tidak dibuka.');

            return;
        }

        // Satu mata kuliah hanya sekali per semester — termasuk memilih dua kelas paralel dari mata
        // kuliah yang sama di pengajuan ini. Lihat App\Services\PendaftaranKrs.
        $pelanggaran = PendaftaranKrs::pelanggaranPengajuan($this->mahasiswaId, $newSelections->pluck('id_kelas'));
        if ($pelanggaran !== []) {
            $this->addError('selectedKelas', implode(' ', $pelanggaran));

            return;
        }

        $prasyaratViolations = [];
        foreach ($newSelections as $item) {
            $kelas = Kelas::with('kurikulumMatkul.matkul')->find($item['id_kelas']);
            if (! $kelas || ! $kelas->kurikulumMatkul) {
                continue;
            }

            $idMatkulInduk = (int) $kelas->kurikulumMatkul->id_matkul;
            $namaMatkulInduk = $kelas->kurikulumMatkul->matkul->nama ?? 'Mata kuliah';

            $prasyaratIds = MatkulPrasyarat::where('id_matkul', $idMatkulInduk)
                ->whereNull('deleted_at')
                ->pluck('id_matkul_prasyarat')
                ->unique();

            foreach ($prasyaratIds as $idMatkulPrasyarat) {
                if ($this->mahasiswaHasLulusMatkulMinimalC((int) $idMatkulPrasyarat)) {
                    continue;
                }
                $mkPr = Matkul::find($idMatkulPrasyarat);
                $prasyaratViolations[] = [
                    'matkul_diajukan' => $namaMatkulInduk,
                    'kode_matkul_prasyarat' => $mkPr->kode ?? null,
                    'matkul_prasyarat' => $mkPr->nama ?? 'Mata kuliah prasyarat',
                ];
            }
        }

        if ($prasyaratViolations !== []) {
            session()->flash('prasyarat_violations', $prasyaratViolations);
            $this->addError('selectedKelas', 'Mata kuliah prasyarat belum terpenuhi. Anda harus memiliki nilai minimal C (lulus) untuk mata kuliah prasyarat.');

            return;
        }

        $jumlahPengajuanBaru = 0;

        DB::transaction(function () use ($newSelections, &$jumlahPengajuanBaru) {
            foreach ($newSelections as $item) {
                $existing = Krs::withTrashed()
                    ->where('id_mahasiswa', $this->mahasiswaId)
                    ->where('id_kelas', $item['id_kelas'])
                    ->first();

                if ($existing) {
                    if (! $existing->trashed()) {
                        continue;
                    }
                    $existing->restore();
                    $existing->update(['approved_by' => null, 'approved_at' => null]);
                } else {
                    Krs::create([
                        'id_mahasiswa' => $this->mahasiswaId,
                        'id_kelas' => $item['id_kelas'],
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);
                }

                $jumlahPengajuanBaru++;
            }
        });

        if ($jumlahPengajuanBaru > 0) {
            $dosenWaliAktif = DosenWali::where('id_mahasiswa', $this->mahasiswaId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->with('dosen')
                ->first();
            $idUserDosenWali = $dosenWaliAktif?->dosen?->id_user;
            if ($idUserDosenWali) {
                $mahasiswa = Mahasiswa::find($this->mahasiswaId);
                $pesan = $jumlahPengajuanBaru === 1
                    ? "{$mahasiswa->nama} mengajukan 1 mata kuliah KRS yang perlu Anda setujui."
                    : "{$mahasiswa->nama} mengajukan {$jumlahPengajuanBaru} mata kuliah KRS yang perlu Anda setujui.";
                Notifikasi::kirim(
                    idUser: $idUserDosenWali,
                    tipe: 'krs_diajukan',
                    judul: 'Pengajuan KRS baru',
                    pesan: $pesan,
                    url: '/dosen/perwalian/persetujuan-krs',
                );
            }
        }

        unset($this->data, $this->filteredData);
        $this->resetValidation();
        session()->forget('prasyarat_violations');
        session()->flash('status', 'Pengajuan KRS berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.mahasiswa.krs.pengajuan')->extends('layouts.mahasiswa');
    }
}
