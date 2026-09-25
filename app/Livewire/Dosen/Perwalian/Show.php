<?php

namespace App\Livewire\Dosen\Perwalian;

use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\DosenWaliBimbingan;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    // Locked: saveBimbingan()/openBimbinganModal() memakai dosenWaliId langsung sebagai id_dosen_wali
    // pada baris baru — tanpa ini, properti publik ini bisa "disentuh" lewat request Livewire yang
    // dimanipulasi untuk menulis catatan bimbingan atas nama dosen wali lain.
    #[Locked]
    public int $idMahasiswa;

    #[Locked]
    public int $dosenId;

    #[Locked]
    public int $dosenWaliId;

    public string $activeTab = 'biodata';

    public string $filterSemesterCatatan = '';

    public bool $showBimbinganModal = false;

    public ?int $editingBimbinganId = null;

    public string $form_id_semester = '';

    public string $form_tanggal_bimbingan = '';

    public string $form_catatan_dosen = '';

    public bool $form_langsung_validasi = true;

    public ?string $existingWaktuValidasiDosen = null;

    /** @var TemporaryUploadedFile|null */
    public $form_file = null;

    public ?string $existingFileUrl = null;

    public bool $form_hapus_file = false;

    public function mount(int $idMahasiswa): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $dosenWali = DosenWali::where('id_dosen', $dosen->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        abort_unless($dosenWali, 404, 'Mahasiswa bukan bimbingan Anda atau tidak aktif.');

        $this->idMahasiswa = $idMahasiswa;
        $this->dosenWaliId = $dosenWali->id;

        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        $this->filterSemesterCatatan = $activeSemester ? (string) $activeSemester->id : '';
    }

    /**
     * Sama persis dengan DosenWaliController::getMyBimbinganMahasiswaBiodata, dipangkas ke relasi
     * yang benar-benar ditampilkan tab Biodata (kategori_biaya_mahasiswa dimuat API tapi tidak
     * pernah dirender di frontend, jadi sengaja tidak ikut di sini).
     */
    #[Computed]
    public function mahasiswa(): Mahasiswa
    {
        return Mahasiswa::with([
            'prodi.jenjang', 'status_akademik', 'kelompok_kelas', 'jalur_masuk', 'jenis_daftar',
            'negara', 'provinsi', 'kota', 'semester_masuk',
            'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah',
            'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu',
            'pendidikan_wali', 'pekerjaan_wali', 'penghasilan_wali',
        ])->findOrFail($this->idMahasiswa);
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::whereNull('deleted_at')
            ->orderByDesc('kode')
            ->get(['id', 'nama', 'kode', 'is_active'])
            ->mapWithKeys(fn (Semester $s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})".($s->is_active ? ' — aktif' : '') : $s->nama])
            ->all();
    }

    /**
     * Sama persis dengan KrsController::buildKrsBySemesterPayload.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function krsBySemester(): array
    {
        $krsList = Krs::with(['kelas.kurikulumMatkul.matkul', 'kelas.semester', 'kelas.dosenPic'])
            ->where('id_mahasiswa', $this->idMahasiswa)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        $bySemester = [];

        foreach ($krsList as $krs) {
            $semester = $krs->kelas->semester;
            if (! $semester) {
                continue;
            }

            $bySemester[$semester->id] ??= [
                'semester' => $semester,
                'krs' => [],
                'total_sks_diajukan' => 0,
                'total_sks_diacc' => 0,
            ];

            $km = $krs->kelas->kurikulumMatkul;
            $sks = $km?->sksLabel() ?? 0;

            $bySemester[$semester->id]['total_sks_diajukan'] += $sks;
            if ($krs->approved_at) {
                $bySemester[$semester->id]['total_sks_diacc'] += $sks;
            }

            $bySemester[$semester->id]['krs'][] = [
                'id' => $krs->id,
                'kode' => $km?->kodeMatkulLabel(),
                'nama' => $km?->namaMatkulLabel(),
                'sks' => $sks,
                'dosen' => $krs->kelas->dosenPic?->nama,
                'approved' => $krs->approved_at !== null,
                'approved_at' => $krs->approved_at,
            ];
        }

        $result = array_values($bySemester);
        // Terbaru dulu berdasarkan kode semester, BUKAN id — id cuma urutan pembuatan baris,
        // sehingga semester lama yang diinput belakangan ikut naik ke atas. Sama seperti
        // KrsController::getKrsBySemester.
        usort($result, fn ($a, $b) => $b['semester']->kode <=> $a['semester']->kode);

        return $result;
    }

    /**
     * Sama persis dengan NilaiController::buildTranskripLengkapPayload.
     */
    #[Computed]
    public function transkrip(): array
    {
        $krsList = Krs::with(['kelas.kurikulumMatkul.matkul', 'kelas.semester'])
            ->where('id_mahasiswa', $this->idMahasiswa)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get();

        $nilaiMap = Nilai::whereIn('id_krs', $krsList->pluck('id'))
            ->whereNull('deleted_at')
            ->where('is_final', true)
            ->get()
            ->keyBy('id_krs');

        $mataKuliah = [];
        $totalSks = 0;
        $totalAngkaMutu = 0.0;
        $totalSksDenganNilai = 0;

        foreach ($krsList as $krs) {
            $km = $krs->kelas->kurikulumMatkul;
            $semester = $krs->kelas->semester;
            $nilai = $nilaiMap->get($krs->id);

            if (! $km || ! $semester || ! $nilai || ! $nilai->huruf_mutu) {
                continue;
            }

            $sks = $km->sksLabel() ?? 0;
            $totalSks += $sks;

            if ($nilai->angka_mutu !== null && $sks > 0) {
                $totalAngkaMutu += ((float) $nilai->angka_mutu * $sks);
                $totalSksDenganNilai += $sks;
            }

            $mataKuliah[] = [
                'id_krs' => $krs->id,
                'semester' => $semester,
                'kode' => $km->kodeMatkulLabel(),
                'nama' => $km->namaMatkulLabel(),
                'sks' => $sks,
                'huruf_mutu' => $nilai->huruf_mutu,
                'angka_mutu' => $nilai->angka_mutu,
            ];
        }

        usort($mataKuliah, function (array $a, array $b) {
            $cmp = $a['semester']->kode <=> $b['semester']->kode;

            return $cmp !== 0 ? $cmp : strcmp((string) $a['kode'], (string) $b['kode']);
        });

        return [
            'ipk' => $totalSksDenganNilai > 0 ? round($totalAngkaMutu / $totalSksDenganNilai, 2) : null,
            'total_sks' => $totalSks,
            'total_sks_dengan_nilai' => $totalSksDenganNilai,
            'mata_kuliah' => $mataKuliah,
        ];
    }

    public function updatingFilterSemesterCatatan(): void
    {
        unset($this->catatanRows);
    }

    #[Computed]
    public function catatanRows()
    {
        $query = DosenWaliBimbingan::where('id_dosen_wali', $this->dosenWaliId)->with('semester');

        if ($this->filterSemesterCatatan !== '') {
            $query->where('id_semester', (int) $this->filterSemesterCatatan);
        }

        return $query->orderByDesc('tanggal_bimbingan')->orderByDesc('id')->get();
    }

    public function openBimbinganModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->form_file = null;
        $this->form_hapus_file = false;

        if ($id) {
            $row = DosenWaliBimbingan::where('id_dosen_wali', $this->dosenWaliId)->where('id', $id)->first();

            abort_unless($row, 404, 'Data bimbingan tidak ditemukan.');

            $this->editingBimbinganId = $row->id;
            $this->form_id_semester = (string) $row->id_semester;
            $this->form_tanggal_bimbingan = $row->tanggal_bimbingan?->format('Y-m-d') ?? '';
            $this->form_catatan_dosen = (string) $row->catatan_dosen;
            $this->existingWaktuValidasiDosen = $row->waktu_validasi_dosen?->translatedFormat('d F Y H:i');
            $this->existingFileUrl = $row->file_url;
            $this->form_langsung_validasi = false;
        } else {
            $this->editingBimbinganId = null;
            $this->form_id_semester = $this->filterSemesterCatatan;
            $this->form_tanggal_bimbingan = now()->format('Y-m-d');
            $this->form_catatan_dosen = '';
            $this->existingWaktuValidasiDosen = null;
            $this->existingFileUrl = null;
            $this->form_langsung_validasi = true;
        }

        $this->showBimbinganModal = true;
    }

    public function closeBimbinganModal(): void
    {
        $this->showBimbinganModal = false;
        $this->editingBimbinganId = null;
    }

    /**
     * Sama persis dengan DosenWaliBimbinganController::store/update (jalur storeForBimbinganAkademikWali
     * / updateForBimbinganAkademikWali) — dosen wali portal tidak boleh menghapus entri (tidak ada
     * rute delete untuk portal ini), hanya store dan update.
     */
    public function saveBimbingan(): void
    {
        $validated = $this->validate([
            'form_id_semester' => ['required', 'integer', 'exists:semester,id'],
            'form_tanggal_bimbingan' => ['nullable', 'date'],
            'form_catatan_dosen' => ['nullable', 'string'],
            'form_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $path = null;
        if ($this->form_file) {
            $path = $this->form_file->store('dosen_wali_bimbingan', 'public');
        }

        if ($this->editingBimbinganId) {
            $row = DosenWaliBimbingan::where('id_dosen_wali', $this->dosenWaliId)->findOrFail($this->editingBimbinganId);

            if ($this->form_hapus_file && $row->file) {
                Storage::disk('public')->delete($row->file);
                $row->file = null;
            }

            if ($path) {
                if ($row->file) {
                    Storage::disk('public')->delete($row->file);
                }
                $row->file = $path;
            }

            $row->id_semester = (int) $validated['form_id_semester'];
            $row->catatan_dosen = $validated['form_catatan_dosen'] !== '' ? $validated['form_catatan_dosen'] : null;
            $row->tanggal_bimbingan = $validated['form_tanggal_bimbingan'] !== '' ? $validated['form_tanggal_bimbingan'] : null;

            if ($this->form_langsung_validasi) {
                $row->waktu_validasi_dosen = now();
            }

            $row->save();
        } else {
            DosenWaliBimbingan::create([
                'id_dosen_wali' => $this->dosenWaliId,
                'id_semester' => (int) $validated['form_id_semester'],
                'catatan_dosen' => $validated['form_catatan_dosen'] !== '' ? $validated['form_catatan_dosen'] : null,
                'catatan_mhs' => null,
                'tanggal_bimbingan' => $validated['form_tanggal_bimbingan'] !== '' ? $validated['form_tanggal_bimbingan'] : null,
                'waktu_validasi_dosen' => $this->form_langsung_validasi ? now() : null,
                'waktu_validasi_mhs' => null,
                'file' => $path,
            ]);
        }

        unset($this->catatanRows);
        $this->closeBimbinganModal();
        session()->flash('status', 'Catatan bimbingan berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.dosen.perwalian.show')->extends('layouts.dosen');
    }
}
