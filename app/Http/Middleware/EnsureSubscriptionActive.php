<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blokir SELURUH akses (semua peran -- admin, dosen, mahasiswa, prodi) ketika langganan
 * Sikampus Cloud tenant ini sudah suspended: masa tenggang (grace period) 30 hari sejak
 * lisensi expired sudah habis dan belum diperpanjang. Lihat App\Services\SubscriptionStatus
 * untuk kontrak datanya.
 *
 * SENGAJA hanya bereaksi pada status 'suspended', BUKAN 'grace': selama masih di masa
 * tenggang, aplikasi tetap harus dipakai normal (kebijakan bisnisnya begitu) -- yang tampil di
 * situ hanya banner peringatan (lihat App\Providers\AppServiceProvider::boot(),
 * partials.subscription-grace-banner), bukan blokir.
 *
 * SENGAJA tidak ada pengecualian untuk peran apa pun (termasuk superadmin/halaman maintenance
 * seperti /konfigurasi, /migrasi): satu tenant = satu langganan, dan satu-satunya jalan keluar
 * dari status ini adalah memperpanjang lewat akun Sikampus Platform (di luar tenant ini sama
 * sekali) -- bukan sesuatu yang bisa "diperbaiki" dari dalam.
 *
 * Didaftarkan lewat prependToGroup('web', ...) di bootstrap/app.php, PERSIS seperti
 * App\Http\Middleware\EnsureAppIsInstalled -- otomatis mencakup rute LOGIN itu sendiri
 * (ada di grup 'web' yang sama, tidak dikecualikan) karena requirement-nya eksplisit
 * "pengguna tidak bisa mengakses Sikampus" tanpa syarat sudah login atau belum. Juga
 * didaftarkan sebagai alias 'subscription.active' di dalam grup auth:sanctum
 * (routes/api.php) untuk klien API selain panel Blade/Livewire ini -- TIDAK pernah
 * ditambahkan ke grup partner.api.key (integrasi sistem-ke-sistem, bukan "pengguna").
 *
 * abort(402, ...) SENGAJA (bukan redirect seperti EnsureAppIsInstalled): tidak ada wizard
 * multi-langkah untuk kasus ini, cukup satu halaman informasi
 * (resources/views/errors/402.blade.php) yang otomatis dipilih Laravel untuk kode status ini,
 * atau JSON otomatis untuk request api/*|expectsJson() lewat shouldRenderJsonWhen() yang
 * sudah didaftarkan di bootstrap/app.php.
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $subscription = SubscriptionStatus::load();

        if (! $subscription->isSuspended()) {
            return $next($request);
        }

        abort(402, $subscription->message() ?: 'Layanan Sikampus Cloud sedang dihentikan sementara karena langganan belum diperpanjang.');
    }
}
