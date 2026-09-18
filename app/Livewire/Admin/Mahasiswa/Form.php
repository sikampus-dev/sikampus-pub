<?php

namespace App\Livewire\Admin\Mahasiswa;

use App\Livewire\Admin\Mahasiswa\Concerns\ForwardsIndexState;
use App\Models\JalurMasuk;
use App\Models\JenisDaftar;
use App\Models\KelompokKelas;
use App\Models\Kota;
use App\Models\Mahasiswa;
use App\Models\Negara;
use App\Models\Pekerjaan;
use App\Models\Pendidikan;
use App\Models\Penghasilan;
use App\Models\Prodi;
use App\Models\Provinsi;
use App\Models\Semester;
use App\Models\StatusAkademik;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $mahasiswaId = null;

    public string $nama = '';

    public string $nim = '';

    public string $email = '';

    public string $no_wa = '';

    public string $handphone = '';

    public string $jenis_kelamin = '';

    public string $id_tempat_lahir = '';

    public string $tanggal_lahir = '';

    public string $no_ktp = '';

    public string $npwp = '';

    public string $alamat = '';

    public string $rt = '';

    public string $rw = '';

    public string $dusun = '';

    public string $kelurahan = '';

    public string $kode_pos = '';

    public string $id_kecamatan = '';

    public string $id_kota = '';

    public string $id_provinsi = '';

    public string $id_negara = '';

    public ?int $id_prodi = null;

    public ?int $id_kelompok_kelas = null;

    public ?int $id_semester_masuk = null;

    public ?int $id_status_akademik = null;

    public ?int $id_jalur_masuk = null;

    public ?int $id_jenis_daftar = null;

    public string $mulai_semester = '';

    public int $sks_diakui = 0;

    public string $sekolah_asal = '';

    public string $nis = '';

    public string $nisn = '';

    public string $ayah = '';

    public string $nik_ayah = '';

    public string $tgl_lahir_ayah = '';

    public ?int $id_pddk_ayah = null;

    public ?int $id_pekerjaan_ayah = null;

    public ?int $id_penghasilan_ayah = null;

    public string $ibu = '';

    public string $nik_ibu = '';

    public string $tgl_lahir_ibu = '';

    public ?int $id_pddk_ibu = null;

    public ?int $id_pekerjaan_ibu = null;

    public ?int $id_penghasilan_ibu = null;

    public string $wali = '';

    public string $nik_wali = '';

    public string $tgl_lahir_wali = '';

    public ?int $id_pddk_wali = null;

    public ?int $id_pekerjaan_wali = null;

    public ?int $id_penghasilan_wali = null;

    public string $jml_biaya_masuk = '';

    public string $penerima_kps = '';

    public string $no_kps = '';

    public function mount(?int $id = null): void
    {
        $this->mahasiswaId = $id;

        if ($id === null) {
            $this->resolveBackToIndexUrl();

            return;
        }

        $this->resolveBackToShowUrl($id);

        $mahasiswa = Mahasiswa::findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data mahasiswa ini.');
            }
        }

        $this->nama = $mahasiswa->nama;
        $this->nim = (string) $mahasiswa->nim;
        $this->email = (string) $mahasiswa->email;
        $this->no_wa = (string) $mahasiswa->no_wa;
        $this->handphone = (string) $mahasiswa->handphone;
        $this->jenis_kelamin = (string) $mahasiswa->jenis_kelamin;
        $this->id_tempat_lahir = (string) $mahasiswa->id_tempat_lahir;
        $this->tanggal_lahir = $mahasiswa->tanggal_lahir?->format('Y-m-d') ?? '';
        $this->no_ktp = (string) $mahasiswa->no_ktp;
        $this->npwp = (string) $mahasiswa->npwp;
        $this->alamat = (string) $mahasiswa->alamat;
        $this->rt = (string) $mahasiswa->rt;
        $this->rw = (string) $mahasiswa->rw;
        $this->dusun = (string) $mahasiswa->dusun;
        $this->kelurahan = (string) $mahasiswa->kelurahan;
        $this->kode_pos = (string) $mahasiswa->kode_pos;
        $this->id_kecamatan = (string) $mahasiswa->id_kecamatan;
        $this->id_kota = (string) $mahasiswa->id_kota;
        $this->id_provinsi = (string) $mahasiswa->id_provinsi;
        $this->id_negara = (string) $mahasiswa->id_negara;
        $this->id_prodi = $mahasiswa->id_prodi;
        $this->id_kelompok_kelas = $mahasiswa->id_kelompok_kelas;
        $this->id_semester_masuk = $mahasiswa->id_semester_masuk;
        $this->id_status_akademik = $mahasiswa->id_status_akademik;
        $this->id_jalur_masuk = $mahasiswa->id_jalur_masuk;
        $this->id_jenis_daftar = $mahasiswa->id_jenis_daftar;
        $this->mulai_semester = (string) $mahasiswa->mulai_semester;
        $this->sks_diakui = $mahasiswa->sks_diakui ?? 0;
        $this->sekolah_asal = (string) $mahasiswa->sekolah_asal;
        $this->nis = (string) $mahasiswa->nis;
        $this->nisn = (string) $mahasiswa->nisn;
        $this->ayah = (string) $mahasiswa->ayah;
        $this->nik_ayah = (string) $mahasiswa->nik_ayah;
        $this->tgl_lahir_ayah = $mahasiswa->tgl_lahir_ayah?->format('Y-m-d') ?? '';
        $this->id_pddk_ayah = $mahasiswa->id_pddk_ayah;
        $this->id_pekerjaan_ayah = $mahasiswa->id_pekerjaan_ayah;
        $this->id_penghasilan_ayah = $mahasiswa->id_penghasilan_ayah;
        $this->ibu = (string) $mahasiswa->ibu;
        $this->nik_ibu = (string) $mahasiswa->nik_ibu;
        $this->tgl_lahir_ibu = $mahasiswa->tgl_lahir_ibu?->format('Y-m-d') ?? '';
        $this->id_pddk_ibu = $mahasiswa->id_pddk_ibu;
        $this->id_pekerjaan_ibu = $mahasiswa->id_pekerjaan_ibu;
        $this->id_penghasilan_ibu = $mahasiswa->id_penghasilan_ibu;
        $this->wali = (string) $mahasiswa->wali;
        $this->nik_wali = (string) $mahasiswa->nik_wali;
        $this->tgl_lahir_wali = $mahasiswa->tgl_lahir_wali?->format('Y-m-d') ?? '';
        $this->id_pddk_wali = $mahasiswa->id_pddk_wali;
        $this->id_pekerjaan_wali = $mahasiswa->id_pekerjaan_wali;
        $this->id_penghasilan_wali = $mahasiswa->id_penghasilan_wali;
        $this->jml_biaya_masuk = $mahasiswa->jml_biaya_masuk !== null ? (string) $mahasiswa->jml_biaya_masuk : '';
        $this->penerima_kps = (string) $mahasiswa->penerima_kps;
        $this->no_kps = (string) $mahasiswa->no_kps;
    }

    /**
     * Sama dengan MahasiswaController::update, kecuali no_hp -> no_wa (kolom sungguhan
     * di tabel mahasiswa; no_hp yang divalidasi controller API bukan kolom fillable
     * sehingga tidak pernah benar-benar tersimpan).
     */
    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nim' => ['nullable', 'string', 'max:50', Rule::unique('mahasiswa', 'nim')->ignore($this->mahasiswaId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('mahasiswa', 'email')->ignore($this->mahasiswaId)],
            'no_wa' => ['nullable', 'string', 'max:20'],
            'handphone' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'id_semester_masuk' => ['nullable', 'integer'],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'id_kelompok_kelas' => ['nullable', 'integer', 'exists:kelompok_kelas,id'],
            'id_kecamatan' => ['nullable', 'string'],
            'id_kota' => ['nullable', 'string'],
            'id_provinsi' => ['nullable', 'string'],
            'id_negara' => ['nullable', 'string'],
            'id_status_akademik' => ['nullable', 'integer', 'exists:status_akademik,id'],
            'jenis_kelamin' => ['nullable', 'string', Rule::in(['L', 'P'])],
            'id_tempat_lahir' => ['nullable', 'string'],
            'tanggal_lahir' => ['nullable', 'date'],
            'no_ktp' => ['nullable', 'string', 'max:20'],
            'sekolah_asal' => ['nullable', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50'],
            'nisn' => ['nullable', 'string', 'max:50'],
            'npwp' => ['nullable', 'string', 'max:20'],
            'mulai_semester' => ['nullable', 'string', 'max:50'],
            'rt' => ['nullable', 'string', 'max:10'],
            'rw' => ['nullable', 'string', 'max:10'],
            'dusun' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'penerima_kps' => ['nullable', 'string', 'max:10'],
            'no_kps' => ['nullable', 'string', 'max:50'],
            // 'ayah'/'ibu' sempat tidak punya aturan padahal inputnya ada di form — tanpa aturan,
            // validate() tidak mengembalikannya dan nama yang diketik admin hilang saat simpan.
            'ayah' => ['nullable', 'string', 'max:255'],
            'nik_ayah' => ['nullable', 'string', 'max:20'],
            'tgl_lahir_ayah' => ['nullable', 'date'],
            'id_pddk_ayah' => ['nullable', 'integer'],
            'id_pekerjaan_ayah' => ['nullable', 'integer'],
            'id_penghasilan_ayah' => ['nullable', 'integer'],
            'ibu' => ['nullable', 'string', 'max:255'],
            'nik_ibu' => ['nullable', 'string', 'max:20'],
            'tgl_lahir_ibu' => ['nullable', 'date'],
            'id_pddk_ibu' => ['nullable', 'integer'],
            'id_pekerjaan_ibu' => ['nullable', 'integer'],
            'id_penghasilan_ibu' => ['nullable', 'integer'],
            'wali' => ['nullable', 'string', 'max:255'],
            'nik_wali' => ['nullable', 'string', 'max:20'],
            'tgl_lahir_wali' => ['nullable', 'date'],
            'id_pddk_wali' => ['nullable', 'integer'],
            'id_pekerjaan_wali' => ['nullable', 'integer'],
            'id_penghasilan_wali' => ['nullable', 'integer'],
            'jml_biaya_masuk' => ['nullable', 'numeric', 'min:0'],
            'sks_diakui' => ['nullable', 'integer', 'min:0'],
            'id_jalur_masuk' => ['nullable', 'integer'],
            'id_jenis_daftar' => ['nullable', 'integer'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction() && array_key_exists('id_prodi', $validated)) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $validated['id_prodi'], $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        foreach ([
            'nim', 'email', 'no_wa', 'handphone', 'alamat', 'kode_pos', 'id_kecamatan', 'id_kota',
            'id_provinsi', 'id_negara', 'jenis_kelamin', 'id_tempat_lahir', 'tanggal_lahir', 'no_ktp',
            'sekolah_asal', 'nis', 'nisn', 'npwp', 'mulai_semester', 'rt', 'rw', 'dusun', 'kelurahan',
            'penerima_kps', 'no_kps', 'ayah', 'nik_ayah', 'tgl_lahir_ayah', 'ibu', 'nik_ibu',
            'tgl_lahir_ibu', 'wali', 'nik_wali', 'tgl_lahir_wali',
        ] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        if (($validated['jml_biaya_masuk'] ?? null) === '' || ! array_key_exists('jml_biaya_masuk', $validated)) {
            $validated['jml_biaya_masuk'] = null;
        }

        if ($this->mahasiswaId) {
            Mahasiswa::findOrFail($this->mahasiswaId)->update($validated);
            $redirectUrl = $this->backUrl;
        } else {
            $mahasiswa = Mahasiswa::create($validated);
            $redirectUrl = route('admin.administrasi.mahasiswa.show', $mahasiswa->id);
        }

        session()->flash('status', 'Mahasiswa berhasil disimpan.');

        return redirect($redirectUrl);
    }

    public function render()
    {
        $user = Auth::user();

        $prodiQuery = Prodi::query()->with('jenjang')->orderBy('nama');
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $prodiQuery->whereIn('id', $allowedProdiIds);
            }
        }

        return view('livewire.admin.mahasiswa.form', [
            'prodiOptions' => $prodiQuery->get(['id', 'nama', 'kode', 'id_jenjang']),
            'kelompokKelasOptions' => KelompokKelas::orderBy('nama')->get(['id', 'nama']),
            'semesterOptions' => Semester::orderByDesc('kode')->get(['id', 'kode', 'nama']),
            'statusAkademikOptions' => StatusAkademik::orderBy('nama')->get(['id', 'nama']),
            'jalurMasukOptions' => JalurMasuk::orderBy('nama')->get(['id', 'nama']),
            'jenisDaftarOptions' => JenisDaftar::orderBy('nama')->get(['id', 'nama']),
            'negaraOptions' => Negara::orderBy('nama')->get(['id', 'nama']),
            'provinsiOptions' => Provinsi::orderBy('nama')->get(['id', 'nama']),
            'kotaOptions' => Kota::orderBy('nama')->get(['id', 'nama']),
            'pendidikanOptions' => Pendidikan::orderBy('nama')->get(['id', 'nama']),
            'pekerjaanOptions' => Pekerjaan::orderBy('nama')->get(['id', 'nama']),
            'penghasilanOptions' => Penghasilan::orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.web');
    }
}
