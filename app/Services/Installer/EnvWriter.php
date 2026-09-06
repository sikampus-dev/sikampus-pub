<?php

namespace App\Services\Installer;

use RuntimeException;

/**
 * Tulis pasangan key/value ke berkas .env, mempertahankan baris lain apa adanya.
 *
 * Menyunting baris yang sudah ada alih-alih menimpa seluruh berkas: .env instalasi bisa berisi
 * penyesuaian yang tidak diketahui installer (kredensial SMTP, kunci pihak ketiga, pengaturan
 * hosting), dan menimpanya berarti menghapus hal-hal itu tanpa sepengetahuan siapa pun.
 */
class EnvWriter
{
    public function __construct(private readonly string $path) {}

    public static function forApp(): self
    {
        return new self(base_path('.env'));
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Buat .env dari .env.example. Mengembalikan false (bukan melempar) kalau tidak bisa —
     * pemanggilnya perlu menampilkan halaman penjelasan, bukan halaman error Laravel yang tidak
     * bisa dibaca orang yang sedang memasang aplikasi.
     */
    public function createFromExample(): bool
    {
        if ($this->exists()) {
            return true;
        }

        $example = base_path('.env.example');

        if (! is_file($example) || ! is_writable(dirname($this->path))) {
            return false;
        }

        return @copy($example, $this->path);
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        $contents = (string) file_get_contents($this->path);

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return null;
        }

        return trim($matches[1], " \t\"'");
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function put(array $values): void
    {
        if (! $this->exists()) {
            throw new RuntimeException('Berkas .env tidak ada.');
        }

        if (! is_writable($this->path)) {
            throw new RuntimeException('Berkas .env tidak bisa ditulis. Periksa izin berkas di server.');
        }

        $contents = (string) file_get_contents($this->path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents) === 1
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        if (file_put_contents($this->path, $contents) === false) {
            throw new RuntimeException('Gagal menulis berkas .env.');
        }
    }

    /**
     * Nilai yang mengandung spasi atau karakter khusus dikutip. Password database lazim memuat
     * keduanya, dan tanpa kutip baris .env akan terbaca terpotong — kegagalan yang muncul
     * belakangan sebagai "access denied" yang membingungkan.
     */
    private function format(string $value): string
    {
        return preg_match('/[\s#"\'=]/', $value) === 1
            ? '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"'
            : $value;
    }
}
