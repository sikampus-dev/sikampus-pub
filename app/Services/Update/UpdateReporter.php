<?php

namespace App\Services\Update;

use App\Models\UpdateRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Laporkan ke Sikampus Platform bahwa instalasi ini baru saja berpindah versi.
 *
 * TIDAK PERNAH menggagalkan pembaruan. Pada titik ini berkas sudah tertukar, migrasi sudah
 * jalan, dan aplikasi sudah berjalan di versi baru — melempar exception hanya akan menandai
 * pembaruan yang SEBENARNYA BERHASIL sebagai gagal, lalu membuat orang mencoba mengulanginya.
 * Kegagalan melapor dicatat sebagai catatan pada jejak langkah, bukan sebagai kesalahan.
 */
class UpdateReporter
{
    public function __construct(private readonly LicenseGate $gate) {}

    /**
     * @return string Keterangan singkat untuk jejak langkah pembaruan.
     */
    public function report(UpdateRun $run): string
    {
        $key = $this->gate->licenseKey();
        $url = trim((string) config('sikampus_server.url'));

        if ($key === null || $url === '') {
            return 'Pelaporan pembaruan dilewati (license key atau alamat Sikampus Platform tidak tersedia).';
        }

        try {
            $response = Http::timeout((int) config('sikampus.update.timeout'))
                ->acceptJson()
                ->post(rtrim($url, '/').'/api/installations/updated', [
                    'license_key' => $key,
                    'from_version' => $run->version_from,
                    // Diambil dari catatan run, BUKAN dari config('sikampus.version'): keduanya
                    // memang sama di titik ini, tapi yang dimaksud laporan ini adalah versi yang
                    // dituju run tersebut — bukan versi apa pun yang kebetulan sedang terbaca.
                    'to_version' => $run->version_to,
                    'app_url' => config('app.url'),
                ]);

            if ($response->successful()) {
                return 'Pembaruan dilaporkan ke Sikampus Platform.';
            }

            return 'Gagal melaporkan pembaruan ke Sikampus Platform (HTTP '.$response->status().'). '
                .'Pembaruan sendiri tetap berhasil.';
        } catch (Throwable $e) {
            Log::warning('Gagal melaporkan pembaruan ke Sikampus Platform.', [
                'update_run_id' => $run->id,
                'message' => $e->getMessage(),
            ]);

            return 'Gagal melaporkan pembaruan ke Sikampus Platform: '.$e->getMessage()
                .' Pembaruan sendiri tetap berhasil.';
        }
    }
}
