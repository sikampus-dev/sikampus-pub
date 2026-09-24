<?php

namespace App\Livewire\Dosen;

use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\JadwalDosen;
use App\Models\Krs;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Dashboard extends Component
{
    // Locked: dosenId hanya boleh diisi lewat mount() (dari user login), bukan lewat request
    // Livewire yang dimanipulasi — properti publik biasa bisa di-override langsung oleh client.
    #[Locked]
    public int $dosenId;

    public function mount(): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();

        $this->dosenId = $dosen->id;
    }

    /**
     * Nama dosen lengkap dengan gelar depan/belakang (kalau ada) untuk subtitle "Selamat datang,
     * ..." — bukan Auth::user()->name mentah. Pola penggabungannya sama dengan yang sudah dipakai
     * di banyak tempat lain (mis. App\Livewire\Dosen\Jadwal\Detail) dan di composer
     * layouts.dosen (dosenSidebarNamaLengkap) untuk kartu info sidebar.
     */
    #[Computed]
    public function namaLengkapDosen(): string
    {
        $dosen = Dosen::find($this->dosenId);
        $nama = trim(
            ($dosen?->gelar_depan ? $dosen->gelar_depan.' ' : '').
            (string) ($dosen?->nama ?? Auth::user()?->name ?? '').
            ($dosen?->gelar_belakang ? ', '.$dosen->gelar_belakang : '')
        );

        return $nama !== '' ? $nama : (string) (Auth::user()?->name ?? 'Dosen');
    }

    /**
     * Kartu aksi cepat — sama seperti quick actions di dosen/page.tsx (Jadwal, Dosen Wali,
     * Persetujuan KRS, Nilai). Route::has() jaga-jaga selama modulnya masih coming-soon.
     */
    #[Computed]
    public function quickLinks(): array
    {
        $candidates = [
            ['route' => 'dosen.jadwal', 'label' => 'Jadwal Mengajar', 'section' => 'Jadwal', 'icon' => 'calendar', 'color' => 'sky'],
            ['route' => 'dosen.perwalian', 'label' => 'Daftar Mahasiswa', 'section' => 'Dosen Wali', 'icon' => 'users', 'color' => 'emerald'],
            ['route' => 'dosen.krs', 'label' => 'Persetujuan KRS', 'section' => 'KRS', 'icon' => 'book-open', 'color' => 'pink'],
            ['route' => 'dosen.nilai', 'label' => 'Input Nilai', 'section' => 'Nilai', 'icon' => 'file-text', 'color' => 'amber'],
        ];

        return collect($candidates)
            ->filter(fn ($link) => RouteFacade::has($link['route']))
            ->map(fn ($link) => [...$link, 'url' => route($link['route'])])
            ->values()
            ->all();
    }

    /**
     * Sama persis dengan agregasi statistik_krs di KrsController::getMahasiswaBimbingan
     * (jumlah SKS diajukan/disetujui berdasarkan approved_at), tapi dijumlahkan lintas semua
     * mahasiswa bimbingan aktif dosen ini, bukan per mahasiswa — untuk kartu ringkasan dashboard.
     */
    #[Computed]
    public function krsStats(): array
    {
        $idMahasiswaBimbingan = DosenWali::where('id_dosen', $this->dosenId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('id_mahasiswa');

        if ($idMahasiswaBimbingan->isEmpty()) {
            return ['diajukan' => 0, 'disetujui' => 0, 'belum_disetujui' => 0];
        }

        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();

        $krsQuery = Krs::whereIn('id_mahasiswa', $idMahasiswaBimbingan)->whereNull('deleted_at');
        if ($activeSemester) {
            $krsQuery->whereHas('kelas', fn ($q) => $q->where('id_semester', $activeSemester->id));
        }

        $krsList = $krsQuery->with('kelas.kurikulumMatkul.matkul:id,sks')->get();

        $sks = fn (Krs $krs): int => (int) ($krs->kelas?->kurikulumMatkul?->matkul?->sks ?? 0);

        $diajukan = (int) $krsList->sum($sks);
        $disetujui = (int) $krsList->whereNotNull('approved_at')->sum($sks);

        return [
            'diajukan' => $diajukan,
            'disetujui' => $disetujui,
            'belum_disetujui' => max($diajukan - $disetujui, 0),
        ];
    }

    /**
     * Sama persis dengan JadwalDosenController::getMyJadwal (jadwal_dosen status active, semester
     * aktif), lalu dipangkas ke minggu berjalan (Senin–Minggu) mengikuti getEffectiveJadwalDate
     * di dosen/page.tsx: pakai tanggal eksplisit kalau ada, kalau tidak proyeksikan dari `hari`.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function jadwalMingguIni(): array
    {
        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        if (! $activeSemester) {
            return [];
        }

        $startOfWeek = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $endOfWeek = now()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $hariOrder = ['senin' => 0, 'selasa' => 1, 'rabu' => 2, 'kamis' => 3, 'jumat' => 4, 'sabtu' => 5, 'minggu' => 6];

        $jadwalDosenList = JadwalDosen::with([
            'jadwal.kelas.kurikulumMatkul.matkul',
            'jadwal.kelas.kelompokKelas',
            'jadwal.ruangan',
        ])
            ->where('id_dosen', $this->dosenId)
            ->where('status', 'active')
            ->whereHas('jadwal.kelas', fn ($q) => $q->where('id_semester', $activeSemester->id))
            ->get();

        $rows = [];
        foreach ($jadwalDosenList as $jadwalDosen) {
            $jadwal = $jadwalDosen->jadwal;
            if (! $jadwal) {
                continue;
            }

            if ($jadwal->tanggal) {
                $effectiveDate = $jadwal->tanggal->copy()->startOfDay();
            } else {
                $offset = $hariOrder[strtolower((string) $jadwal->hari)] ?? null;
                if ($offset === null) {
                    continue;
                }
                $effectiveDate = $startOfWeek->copy()->addDays($offset);
            }

            if ($effectiveDate->lt($startOfWeek) || $effectiveDate->gt($endOfWeek)) {
                continue;
            }

            $km = $jadwal->kelas?->kurikulumMatkul;

            $rows[] = [
                'id_jadwal' => $jadwal->id,
                'id_kelas' => $jadwal->kelas?->id,
                'id_semester' => $activeSemester->id,
                'hari' => $jadwal->hari,
                'tanggal' => $effectiveDate,
                'jam_mulai' => $jadwal->jam_mulai,
                'jam_selesai' => $jadwal->jam_selesai,
                'kode_matkul' => $km?->kodeMatkulLabel(),
                'nama_matkul' => $km?->namaMatkulLabel() ?? '—',
                'nama_kelas' => $jadwal->kelas?->kelompokKelas?->nama ?? $jadwal->kelas?->kode,
                'nama_ruangan' => $jadwal->ruangan?->nama,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $cmp = $a['tanggal']->timestamp <=> $b['tanggal']->timestamp;

            return $cmp !== 0 ? $cmp : strcmp((string) $a['jam_mulai'], (string) $b['jam_mulai']);
        });

        return $rows;
    }

    public function render()
    {
        return view('livewire.dosen.dashboard')->extends('layouts.dosen');
    }
}
