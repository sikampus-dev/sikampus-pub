@if ($subscriptionGrace ?? null)
    {{--
        Peringatan masa tenggang (grace period) lisensi Sikampus Cloud -- lihat
        App\Services\SubscriptionStatus & App\Providers\AppServiceProvider::boot(). HANYA
        di-include dari layouts.web (panel admin), sesuai requirement asli "banner di admin
        Sikampus" -- bukan di layouts.dosen/mahasiswa/prodi. Teks pesan ($subscriptionGrace->
        message()) sudah disusun siap-tampil oleh Sikampus Platform, bukan disusun ulang di sini,
        supaya kalimatnya konsisten dengan email reminder yang dikirim portal untuk siklus
        expiry yang sama.
    --}}
    <div class="print:hidden sticky top-0 z-30 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950">
        <i data-lucide="alert-triangle" class="h-4 w-4 flex-shrink-0" aria-hidden="true"></i>
        <span>{{ $subscriptionGrace->message() }}</span>
    </div>
@endif
