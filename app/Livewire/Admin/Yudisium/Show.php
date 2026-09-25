<?php

namespace App\Livewire\Admin\Yudisium;

use App\Models\Yudisium;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public int $yudisiumId;

    public function mount(int $id): void
    {
        $this->yudisiumId = $id;

        $yudisium = Yudisium::with('mahasiswa')->findOrFail($id);
        $this->ensureAccess($yudisium);
    }

    /**
     * Sama persis dengan YudisiumController::show.
     */
    private function ensureAccess(Yudisium $yudisium): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && $yudisium->mahasiswa
                && ! in_array((int) $yudisium->mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data yudisium ini.');
            }
        }
    }

    #[Computed]
    public function yudisium(): Yudisium
    {
        return Yudisium::with([
            'mahasiswa.prodi',
            'mahasiswa.semester_masuk',
            'mahasiswa.status_akademik',
            'mahasiswa.kelompok_kelas',
            'jenis_keluar',
        ])->findOrFail($this->yudisiumId);
    }

    public function render()
    {
        return view('livewire.admin.yudisium.show')->extends('layouts.web');
    }
}
