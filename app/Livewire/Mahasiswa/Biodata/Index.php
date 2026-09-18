<?php

namespace App\Livewire\Mahasiswa\Biodata;

use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    // Locked: diisi hanya dari user login di mount(), tidak boleh di-override lewat request
    // Livewire yang dimanipulasi — kalau bisa, mahasiswa lain bisa dibaca biodatanya.
    #[Locked]
    public int $mahasiswaId;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama dengan MahasiswaController::getMyProfile — daftar relasinya identik, hanya `prodi`
     * dinaikkan jadi `prodi.jenjang` karena halaman ini menampilkan jenjang di samping nama prodi
     * (pola yang sama dipakai Dosen\Perwalian\Show untuk tab Biodata).
     */
    #[Computed]
    public function mahasiswa(): Mahasiswa
    {
        return Mahasiswa::with([
            'user',
            'prodi.jenjang',
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

    public function render()
    {
        return view('livewire.mahasiswa.biodata.index')->extends('layouts.mahasiswa');
    }
}
