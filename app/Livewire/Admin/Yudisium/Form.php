<?php

namespace App\Livewire\Admin\Yudisium;

use App\Models\JenisKeluar;
use App\Models\Mahasiswa;
use App\Models\Yudisium;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    // Tidak ada endpoint update di API (lihat YudisiumController) — komponen ini sengaja
    // hanya menangani create, berbeda dari pola Form create+edit modul lain.
    public string $mahasiswaSearch = '';

    public ?int $selectedMahasiswaId = null;

    public ?int $id_jenis_keluar = null;

    public string $tgl_keluar = '';

    public string $no_ijazah = '';

    public string $no_sk_yudisium = '';

    public string $tanggal_sk_yudisium = '';

    public string $ipk = '';

    public string $judul_skripsi = '';

    public string $keterangan = '';

    #[Computed]
    public function mahasiswaResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        return Mahasiswa::query()
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->mahasiswaSearch}%")
                    ->orWhere('nim', 'like', "%{$this->mahasiswaSearch}%");
            })
            ->orderBy('nama')
            ->limit(20)
            ->get(['id', 'nama', 'nim']);
    }

    #[Computed]
    public function selectedMahasiswa(): ?Mahasiswa
    {
        if (! $this->selectedMahasiswaId) {
            return null;
        }

        return Mahasiswa::with(['prodi', 'semester_masuk', 'status_akademik', 'kelompok_kelas'])
            ->find($this->selectedMahasiswaId);
    }

    public function selectMahasiswa(int $id): void
    {
        $this->selectedMahasiswaId = $id;
        $this->mahasiswaSearch = '';
        $this->resetValidation('selectedMahasiswaId');
    }

    public function clearMahasiswa(): void
    {
        $this->selectedMahasiswaId = null;
    }

    /**
     * Sama persis dengan YudisiumController::store.
     */
    protected function rules(): array
    {
        return [
            'selectedMahasiswaId' => ['required', 'integer', 'exists:mahasiswa,id'],
            'id_jenis_keluar' => [
                'required',
                'integer',
                'exists:jenis_keluar,id',
                Rule::unique('yudisium', 'id_jenis_keluar')->where(function ($query) {
                    return $query->where('id_mahasiswa', $this->selectedMahasiswaId);
                }),
            ],
            'tgl_keluar' => ['nullable', 'string', 'max:255'],
            'no_ijazah' => ['nullable', 'string', 'max:255'],
            'no_sk_yudisium' => ['nullable', 'string', 'max:255'],
            'tanggal_sk_yudisium' => ['nullable', 'string', 'max:255'],
            'ipk' => ['nullable', 'numeric', 'min:0', 'max:4.00'],
            'judul_skripsi' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'selectedMahasiswaId' => 'mahasiswa',
            'id_jenis_keluar' => 'jenis keluar',
        ];
    }

    protected function messages(): array
    {
        return [
            'id_jenis_keluar.unique' => 'Mahasiswa ini sudah memiliki yudisium dengan jenis keluar yang sama.',
        ];
    }

    private function ensureMahasiswaInScope(int $idMahasiswa): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $mahasiswa = Mahasiswa::find($idMahasiswa);
            if ($mahasiswa) {
                $allowedProdiIds = $user->getAllowedProdiIds();
                if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke mahasiswa prodi ini.');
                }
            }
        }
    }

    public function save(bool $createNew = false)
    {
        $validated = $this->validate();

        $this->ensureMahasiswaInScope($validated['selectedMahasiswaId']);

        foreach (['tgl_keluar', 'no_ijazah', 'no_sk_yudisium', 'tanggal_sk_yudisium', 'judul_skripsi'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }
        if ($validated['ipk'] === '') {
            $validated['ipk'] = null;
        }
        if ($validated['keterangan'] === '') {
            $validated['keterangan'] = null;
        }

        Yudisium::create([
            'id_mahasiswa' => $validated['selectedMahasiswaId'],
            'id_jenis_keluar' => $validated['id_jenis_keluar'],
            'tgl_keluar' => $validated['tgl_keluar'],
            'no_ijazah' => $validated['no_ijazah'],
            'no_sk_yudisium' => $validated['no_sk_yudisium'],
            'tanggal_sk_yudisium' => $validated['tanggal_sk_yudisium'],
            'ipk' => $validated['ipk'],
            'judul_skripsi' => $validated['judul_skripsi'],
            'keterangan' => $validated['keterangan'],
        ]);

        if ($createNew) {
            $this->reset([
                'selectedMahasiswaId', 'mahasiswaSearch', 'id_jenis_keluar', 'tgl_keluar',
                'no_ijazah', 'no_sk_yudisium', 'tanggal_sk_yudisium', 'ipk', 'judul_skripsi', 'keterangan',
            ]);
            unset($this->selectedMahasiswa);
            session()->flash('status', 'Data yudisium berhasil dibuat.');

            return;
        }

        session()->flash('status', 'Data yudisium berhasil dibuat.');

        return redirect()->route('admin.akademik.yudisium');
    }

    public function render()
    {
        return view('livewire.admin.yudisium.form', [
            'jenisKeluarOptions' => JenisKeluar::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.web');
    }
}
