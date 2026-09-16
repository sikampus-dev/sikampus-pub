<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar App\Models\Concerns\AturanHapusBerantai saat baris yang akan di-soft-delete masih
 * dipakai data riwayat (KRS, pembayaran disetujui, pertemuan perkuliahan, ...).
 *
 * Ditangani terpusat — bukan di tiap tombol hapus — supaya jalur hapus yang ditambahkan nanti
 * tidak bisa lupa: API dijawab 422 (bootstrap/app.php), komponen Livewire memunculkan peringatan
 * lewat hook `exception` (AppServiceProvider).
 */
class PenghapusanDiblokir extends RuntimeException
{
    /**
     * @param  array<string, int>  $pemakai  label => jumlah baris hidup yang menahan penghapusan
     */
    public function __construct(public readonly array $pemakai)
    {
        $rincian = collect($pemakai)
            ->map(fn (int $jumlah, string $label) => "{$jumlah} {$label}")
            ->implode(', ');

        parent::__construct("Data ini tidak bisa dihapus karena masih dipakai oleh {$rincian}. Hapus atau pindahkan data tersebut lebih dulu.");
    }
}
