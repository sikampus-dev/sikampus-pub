<?php

namespace App\Services\Installer;

/**
 * Periksa apakah server memenuhi syarat menjalankan Sikampus, sebelum satu berkas pun ditulis.
 *
 * Semua pemeriksaan di sini murni membaca dan tidak menyentuh jaringan maupun database.
 */
class RequirementChecker
{
    /**
     * Versi PHP minimum adalah 8.3, BUKAN 8.2 seperti tertulis di composer.json "require".
     *
     * Alasannya ada di composer.json "config.platform.php" yang bernilai 8.3: vendor/ di dalam
     * zip rilis di-resolve seolah-olah PHP-nya 8.3, sehingga paket yang mensyaratkan >=8.3 ikut
     * terpasang. Menjalankannya di 8.2 tidak menghasilkan pesan yang jelas — yang muncul adalah
     * fatal error di tengah request. Kalau memang harus mendukung 8.2, yang diperbaiki adalah
     * platform saat membangun rilis, bukan ambang di sini.
     */
    public const MIN_PHP = '8.3';

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function checks(): array
    {
        return [...$this->phpChecks(), ...$this->extensionChecks(), ...$this->writableChecks()];
    }

    public function passes(): bool
    {
        foreach ($this->checks() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    private function phpChecks(): array
    {
        return [[
            'label' => 'Versi PHP minimal '.self::MIN_PHP,
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'detail' => 'Terpasang: '.PHP_VERSION,
        ]];
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    private function extensionChecks(): array
    {
        $required = [
            'pdo_mysql' => 'koneksi database',
            'mbstring' => 'pengolahan teks',
            'openssl' => 'enkripsi sesi & kata sandi',
            'tokenizer' => 'kebutuhan inti Laravel',
            'xml' => 'ekspor Excel & PDF',
            'ctype' => 'kebutuhan inti Laravel',
            'fileinfo' => 'unggah berkas',
            'zip' => 'impor/ekspor Excel & pemasangan plugin',
            'gd' => 'pemrosesan gambar (KTM mahasiswa)',
            'curl' => 'cek pembaruan & notifikasi',
        ];

        $checks = [];

        foreach ($required as $extension => $kegunaan) {
            $checks[] = [
                'label' => 'Ekstensi PHP '.$extension,
                'ok' => extension_loaded($extension),
                'detail' => $kegunaan,
            ];
        }

        return $checks;
    }

    /**
     * .env diperiksa lewat direktorinya kalau berkasnya belum ada — instalasi baru memang belum
     * punya berkas itu, dan yang menentukan adalah apakah ia BISA dibuat.
     *
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    private function writableChecks(): array
    {
        $envPath = base_path('.env');

        return [
            [
                'label' => 'Berkas .env bisa ditulis',
                'ok' => is_file($envPath) ? is_writable($envPath) : is_writable(base_path()),
                'detail' => $envPath,
            ],
            [
                'label' => 'Direktori storage/ bisa ditulis',
                'ok' => is_writable(storage_path()),
                'detail' => storage_path(),
            ],
            [
                'label' => 'Direktori bootstrap/cache/ bisa ditulis',
                'ok' => is_writable(base_path('bootstrap/cache')),
                'detail' => base_path('bootstrap/cache'),
            ],
        ];
    }
}
