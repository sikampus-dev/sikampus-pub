<?php

namespace App\Livewire\Admin\Nilai\Concerns;

/**
 * Dipakai bersama oleh Show dan Form. Index (lihat Index::render()) menyelipkan
 * pencarian/filter/halaman aktif ke query string link "Detail" — trait ini membaca kembali query
 * string tersebut (whitelist eksplisit) supaya tombol Kembali/Batal dan redirect setelah simpan
 * mendarat di halaman/filter yang sama, bukan selalu halaman 1 Index. Sama polanya dengan
 * App\Livewire\Admin\Krs\Concerns\ForwardsIndexState.
 *
 * Filter IKUT diteruskan, bukan hanya `page`: kembali ke "halaman 3" tanpa filter yang aktif saat
 * itu berarti halaman 3 dari daftar yang sama sekali lain.
 *
 * Semester masuk memakai kunci `id_semester_masuk`, bukan `id_semester`: halaman Detail sudah
 * memakai `?id_semester=` untuk link ekspor/cetak berdasarkan semester kuliah, yang artinya lain.
 */
trait ForwardsIndexState
{
    public string $backUrl;

    public string $returnQuery = '';

    protected function resolveBackUrl(): void
    {
        $forwarded = collect(request()->query())
            ->only(['search', 'id_prodi', 'id_semester_masuk', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $this->returnQuery = http_build_query($forwarded);
        $this->backUrl = $this->urlDenganState(route('admin.akademik.nilai'));
    }

    /** Tempelkan state Index ke URL mana pun di modul Nilai. */
    protected function urlDenganState(string $url): string
    {
        return $this->returnQuery === '' ? $url : $url.'?'.$this->returnQuery;
    }
}
