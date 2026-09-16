<?php

namespace App\Livewire\Admin\Krs\Concerns;

/**
 * Dipakai bersama oleh Show dan Form. Index (lihat Index::render()) menyelipkan
 * pencarian/filter/halaman aktif ke query string link "Detail" — trait ini membaca kembali query
 * string tersebut (whitelist eksplisit) supaya tombol Kembali/Batal dan redirect setelah simpan
 * mendarat di halaman/filter yang sama, bukan selalu halaman 1 Index. Sama polanya dengan
 * App\Livewire\Admin\JadwalUjian\Concerns\ForwardsIndexState.
 *
 * Filter IKUT diteruskan, bukan hanya `page`: kembali ke "halaman 3" tanpa filter yang aktif saat
 * itu berarti halaman 3 dari daftar yang sama sekali lain.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'id_prodi', 'id_semester', 'status', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->urlDenganState(route('admin.akademik.krs'));
    }

    /** Tempelkan state Index ke URL mana pun di modul KRS. */
    protected function urlDenganState(string $url): string
    {
        return $this->returnQuery === '' ? $url : $url.'?'.$this->returnQuery;
    }
}
