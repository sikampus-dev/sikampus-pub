<?php

namespace App\Livewire\Admin\KalenderAkademik\Concerns;

/**
 * Dipakai oleh Form. Sama polanya dengan App\Livewire\Admin\Pengumuman\Concerns\ForwardsIndexState —
 * membaca kembali search/filter/halaman aktif dari query string (whitelist eksplisit) supaya
 * tombol Batal dan redirect setelah simpan mendarat di halaman/filter yang sama.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'kategori', 'semester', 'status', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.akademik.kalender-akademik')
            : route('admin.akademik.kalender-akademik').'?'.$this->returnQuery;
    }
}
