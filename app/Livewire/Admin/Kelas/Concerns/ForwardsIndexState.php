<?php

namespace App\Livewire\Admin\Kelas\Concerns;

/**
 * Dipakai bersama oleh Show dan Form. Index (lihat Index::render()) menyelipkan
 * pencarian/filter/halaman aktif ke query string link "Lihat"/"Ubah" — trait ini membaca
 * kembali query string tersebut (whitelist eksplisit) supaya tombol Kembali/Batal dan redirect
 * setelah simpan mendarat di halaman/filter yang sama, bukan selalu halaman 1 Index.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'id_prodi', 'id_semester', 'id_kelompok_kelas', 'id_angkatan', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->returnQuery === ''
            ? route('admin.akademik.kelas')
            : route('admin.akademik.kelas').'?'.$this->returnQuery;
    }
}
