<?php

namespace App\Livewire\Admin\Pengguna\Concerns;

/**
 * Dipakai oleh Index (menyelipkan pencarian/filter/halaman aktif ke query string link "Lihat
 * Detail" dan "Ubah") dan Show (membaca kembali query string itu supaya breadcrumb & tombol
 * "Kembali" mendarat di halaman/filter yang sama, bukan selalu halaman 1 Index) — pola yang sama
 * dengan App\Livewire\Admin\Mahasiswa\Concerns\ForwardsIndexState.
 */
trait ForwardsIndexState
{
    public string $returnQuery = '';

    private function whitelistedQuery(): array
    {
        return collect(request()->query())
            ->only(['search', 'filterRole', 'filterStatus', 'showTrashed', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    protected function resolveBackToIndexUrl(): string
    {
        $this->returnQuery = http_build_query($this->whitelistedQuery());

        return $this->returnQuery === ''
            ? route('admin.pengguna.index')
            : route('admin.pengguna.index').'?'.$this->returnQuery;
    }
}
