<?php

namespace App\Services\Update;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gerbang lisensi untuk pembaruan mandiri: pembaruan hanya boleh berjalan kalau instalasi punya
 * license key yang DIKENALI Sikampus Platform.
 *
 * Berbeda dari pengecekan pembaruan, yang tetap terbuka untuk siapa pun. Instalasi tanpa lisensi
 * tetap diberi tahu ada versi baru — menyembunyikannya hanya membuat mereka tidak tahu sedang
 * tertinggal, tanpa menambah apa pun bagi siapa pun.
 *
 * TIGA HASIL, bukan dua, dan pembedaannya penting: "tidak ada key", "key tidak dikenali", dan
 * "portal tidak bisa dihubungi". Ketiganya sama-sama memblokir, tapi hanya yang kedua yang
 * berarti ada masalah dengan lisensi. Menyatukan yang ketiga ke dalam pesan "lisensi tidak
 * valid" akan membuat kampus mengira lisensinya bermasalah padahal portal yang sedang mati —
 * kesalahan yang mahal untuk dukungan teknis, dan membuat orang mengganti-ganti key yang
 * sebenarnya sudah benar.
 */
class LicenseGate
{
    public const ALLOWED = 'allowed';

    public const MISSING = 'missing';

    public const UNKNOWN = 'unknown';

    public const UNREACHABLE = 'unreachable';

    /**
     * @return array{state: string, message: ?string, license_key: ?string}
     */
    public function check(): array
    {
        $key = $this->licenseKey();

        if ($key === null) {
            return $this->result(self::MISSING,
                'Pembaruan membutuhkan license key. Isi license key di Pengaturan > Sistem > License Key, lalu coba lagi.',
                null);
        }

        $url = trim((string) config('sikampus_server.url'));

        if ($url === '') {
            return $this->result(self::UNREACHABLE,
                'Alamat Sikampus Platform belum dikonfigurasi (SIKAMPUS_SERVER_URL), sehingga license key tidak bisa diverifikasi.',
                $key);
        }

        try {
            $response = Http::timeout((int) config('sikampus.update.timeout'))
                ->acceptJson()
                ->post(rtrim($url, '/').'/api/licenses/verify', ['license_key' => $key]);
        } catch (Throwable $e) {
            return $this->result(self::UNREACHABLE,
                'Sikampus Platform tidak bisa dihubungi untuk memverifikasi license key. '
                .'Ini bukan berarti lisensi Anda bermasalah — periksa koneksi internet server, lalu coba lagi.',
                $key);
        }

        if ($response->status() === 404 || $response->json('valid') === false) {
            return $this->result(self::UNKNOWN,
                'License key ini tidak dikenali Sikampus Platform. Periksa kembali isinya di Pengaturan > Sistem > License Key.',
                $key);
        }

        if (! $response->successful()) {
            // Jawaban selain 404 yang tidak sukses (500, 502, portal sedang dipelihara) BUKAN
            // pernyataan bahwa lisensinya salah — diperlakukan sebagai tidak terjangkau.
            return $this->result(self::UNREACHABLE,
                'Sikampus Platform menjawab dengan kesalahan saat memverifikasi license key (HTTP '
                .$response->status().'). Ini bukan berarti lisensi Anda bermasalah — coba lagi beberapa saat.',
                $key);
        }

        return $this->result(self::ALLOWED, null, $key);
    }

    public function allows(): bool
    {
        return $this->check()['state'] === self::ALLOWED;
    }

    public function licenseKey(): ?string
    {
        try {
            $key = trim((string) Setting::where('key', 'app_license_key')->value('value'));
        } catch (Throwable) {
            // Tabel settings belum ada (mis. sebelum migrate pertama) — sama artinya dengan
            // belum punya license key.
            return null;
        }

        return $key !== '' ? $key : null;
    }

    /**
     * @return array{state: string, message: ?string, license_key: ?string}
     */
    private function result(string $state, ?string $message, ?string $key): array
    {
        return ['state' => $state, 'message' => $message, 'license_key' => $key];
    }
}
