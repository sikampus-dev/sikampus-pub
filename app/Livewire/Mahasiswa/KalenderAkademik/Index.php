<?php

namespace App\Livewire\Mahasiswa\KalenderAkademik;

use App\Models\KalenderAkademik;
use App\Models\Semester;
use App\Services\KalenderAkademikGateService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    #[Computed]
    public function activeSemester(): ?Semester
    {
        return Semester::where('is_active', true)->first();
    }

    /**
     * @return Collection<int, KalenderAkademik>
     */
    #[Computed]
    public function events(): Collection
    {
        return KalenderAkademikGateService::relevantEventsFor($this->activeSemester?->id);
    }

    public function render()
    {
        return view('livewire.mahasiswa.kalender-akademik.index')->extends('layouts.mahasiswa');
    }
}
