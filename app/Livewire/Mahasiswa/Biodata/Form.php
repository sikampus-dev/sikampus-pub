<?php

namespace App\Livewire\Mahasiswa\Biodata;

use App\Models\Kota;
use App\Models\Mahasiswa;
use App\Models\Negara;
use App\Models\Pekerjaan;
use App\Models\Pendidikan;
use App\Models\Penghasilan;
use App\Models\Provinsi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    // Locked: diisi hanya dari user login di mount(). Tanpa ini, properti publik biasa bisa
    // di-override lewat request Livewire yang dimanipulasi untuk menulis ke baris mahasiswa lain.
    #[Locked]
    public int $mahasiswaId;

    #[Locked]
    public string $nim = '';

    public string $nama = '';

    public string $email = '';

    public string $no_wa = '';

    public string $handphone = '';

    public string $jenis_kelamin = '';

    public string $id_tempat_lahir = '';

    public string $tanggal_lahir = '';

    public string $no_ktp = '';

    public string $sekolah_asal = '';

    public string $nis = '';

    public string $alamat = '';

    public string $rt = '';

    public string $rw = '';

    public string $dusun = '';

    public string $kelurahan = '';

    public string $kode_pos = '';

    // String (bukan ?int) karena terikat ke <x-searchable-select>: opsi kosong mengirim "",
    // yang tidak bisa di-cast ke int dan akan melempar TypeError.
    public string $id_kecamatan = '';

    public string $id_kota = '';

    public string $id_provinsi = '';

    public string $id_negara = '';

    // Sama seperti blok wilayah di atas, id_pddk_*/id_pekerjaan_*/id_penghasilan_* dipegang
    // sebagai string meski kolomnya integer — semuanya terikat ke <x-searchable-select>.
    public string $ayah = '';

    public string $nik_ayah = '';

    public string $tgl_lahir_ayah = '';

    public string $id_pddk_ayah = '';

    public string $id_pekerjaan_ayah = '';

    public string $id_penghasilan_ayah = '';

    public string $ibu = '';

    public string $nik_ibu = '';

    public string $tgl_lahir_ibu = '';

    public string $id_pddk_ibu = '';

    public string $id_pekerjaan_ibu = '';

    public string $id_penghasilan_ibu = '';

    public string $wali = '';

    public string $nik_wali = '';

    public string $tgl_lahir_wali = '';

    public string $id_pddk_wali = '';

    public string $id_pekerjaan_wali = '';

    public string $id_penghasilan_wali = '';

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();

        $this->mahasiswaId = $mahasiswa->id;
        $this->nim = (string) $mahasiswa->nim;
        $this->nama = (string) $mahasiswa->nama;
        $this->email = (string) $mahasiswa->email;
        $this->no_wa = (string) $mahasiswa->no_wa;
        $this->handphone = (string) $mahasiswa->handphone;
        $this->jenis_kelamin = (string) $mahasiswa->jenis_kelamin;
        $this->id_tempat_lahir = (string) $mahasiswa->id_tempat_lahir;
        $this->tanggal_lahir = $mahasiswa->tanggal_lahir?->format('Y-m-d') ?? '';
        $this->no_ktp = (string) $mahasiswa->no_ktp;
        $this->sekolah_asal = (string) $mahasiswa->sekolah_asal;
        $this->nis = (string) $mahasiswa->nis;
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

        $this->ayah = (string) $mahasiswa->ayah;
        $this->nik_ayah = (string) $mahasiswa->nik_ayah;
        $this->tgl_lahir_ayah = $mahasiswa->tgl_lahir_ayah?->format('Y-m-d') ?? '';
        $this->id_pddk_ayah = (string) $mahasiswa->id_pddk_ayah;
        $this->id_pekerjaan_ayah = (string) $mahasiswa->id_pekerjaan_ayah;
        $this->id_penghasilan_ayah = (string) $mahasiswa->id_penghasilan_ayah;

        $this->ibu = (string) $mahasiswa->ibu;
        $this->nik_ibu = (string) $mahasiswa->nik_ibu;
        $this->tgl_lahir_ibu = $mahasiswa->tgl_lahir_ibu?->format('Y-m-d') ?? '';
        $this->id_pddk_ibu = (string) $mahasiswa->id_pddk_ibu;
        $this->id_pekerjaan_ibu = (string) $mahasiswa->id_pekerjaan_ibu;
        $this->id_penghasilan_ibu = (string) $mahasiswa->id_penghasilan_ibu;

        $this->wali = (string) $mahasiswa->wali;
        $this->nik_wali = (string) $mahasiswa->nik_wali;
        $this->tgl_lahir_wali = $mahasiswa->tgl_lahir_wali?->format('Y-m-d') ?? '';
        $this->id_pddk_wali = (string) $mahasiswa->id_pddk_wali;
        $this->id_pekerjaan_wali = (string) $mahasiswa->id_pekerjaan_wali;
        $this->id_penghasilan_wali = (string) $mahasiswa->id_penghasilan_wali;
    }

    /**
     * Sama persis dengan MahasiswaController::updateMyProfile — daftar field & aturannya disalin
     * apa adanya supaya panel dan API tidak pernah berbeda perilaku. `nama` di API bertanda
     * 'sometimes' karena request-nya bisa parsial; form ini selalu mengirim seluruh field, jadi
     * efeknya sama dengan 'required'.
     *
     * Field akademik (prodi, status akademik, jalur masuk) serta NISN/NPWP/KPS sengaja TIDAK ada
     * di sini: API pun tidak mengizinkan mahasiswa mengubahnya lewat self-service.
     */
    public function save()
    {
        $mahasiswa = Mahasiswa::findOrFail($this->mahasiswaId);

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('mahasiswa', 'email')->ignore($mahasiswa->id),
            ],
            'no_wa' => ['nullable', 'string', 'max:20'],
            'handphone' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'id_kecamatan' => ['nullable', 'string'],
            'id_kota' => ['nullable', 'string'],
            'id_provinsi' => ['nullable', 'string'],
            'id_negara' => ['nullable', 'string'],
            'jenis_kelamin' => ['nullable', 'string', Rule::in(['L', 'P'])],
            'id_tempat_lahir' => ['nullable', 'string'],
            'tanggal_lahir' => ['nullable', 'date'],
            'no_ktp' => ['nullable', 'string', 'max:20'],
            'sekolah_asal' => ['nullable', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50'],
            'rt' => ['nullable', 'string', 'max:10'],
            'rw' => ['nullable', 'string', 'max:10'],
            'dusun' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
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
        ]);

        // Input HTML mengirim "" untuk field kosong; kolomnya nullable, jadi simpan null supaya
        // tidak ada campuran ""/null di database (pola yang sama dipakai Mahasiswa\Profil).
        foreach ($validated as $field => $value) {
            if ($field !== 'nama' && $value === '') {
                $validated[$field] = null;
            }
        }

        $mahasiswa->update($validated);

        session()->flash('status', 'Biodata berhasil diperbarui.');

        return redirect()->route('mahasiswa.biodata');
    }

    public function render()
    {
        return view('livewire.mahasiswa.biodata.form', [
            'negaraOptions' => Negara::orderBy('nama')->get(['id', 'nama']),
            'provinsiOptions' => Provinsi::orderBy('nama')->get(['id', 'nama']),
            'kotaOptions' => Kota::orderBy('nama')->get(['id', 'nama']),
            'pendidikanOptions' => Pendidikan::orderBy('nama')->get(['id', 'nama']),
            'pekerjaanOptions' => Pekerjaan::orderBy('nama')->get(['id', 'nama']),
            'penghasilanOptions' => Penghasilan::orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.mahasiswa');
    }
}
