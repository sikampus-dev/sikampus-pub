<?php

namespace App\Support\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Apakah aplikasi ini sudah terpasang?
 *
 * Sumber utamanya berkas kunci di storage/ — murah (satu stat) dan bertahan melewati pembaruan,
 * karena storage/ termasuk yang tidak pernah disentuh App\Services\Update\UpdatePaths.
 *
 * Kalau kunci itu tidak ada, database DIPERIKSA sekali sebagai jaring kedua. Ini bukan
 * kemewahan: instalasi yang sudah berjalan sejak sebelum installer ini ada tidak punya berkas
 * kunci sama sekali, dan tanpa pemeriksaan ini seluruh instalasi lama akan dilempar ke wizard
 * pemasangan begitu mereka memperbarui versi — lalu wizard itu menawarkan menimpa .env dan
 * membuat superadmin baru di atas data kampus yang sudah hidup.
 *
 * Pemeriksaan database yang berhasil langsung menulis berkas kuncinya, sehingga jalur mahal itu
 * hanya terjadi sekali seumur instalasi.
 */
class InstallationState
{
    public static function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    public static function isInstalled(): bool
    {
        if (is_file(static::lockPath())) {
            return true;
        }

        return static::adoptExistingInstallation();
    }

    /**
     * Tandai selesai terpasang. Dipanggil di akhir langkah pemasangan, SEBELUM layar "selesai"
     * ditampilkan — selama berkas ini belum ada, /install masih bisa diakses siapa pun yang
     * menemukan URL-nya.
     */
    public static function markInstalled(): void
    {
        @mkdir(dirname(static::lockPath()), 0755, true);

        file_put_contents(static::lockPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => config('sikampus.version'),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Database yang sudah berisi pengguna berarti instalasi ini sudah hidup, apa pun keadaan
     * berkas kuncinya. Kegagalan apa pun di sini (DB belum dikonfigurasi, tabel belum ada,
     * kredensial salah) diperlakukan sebagai "belum terpasang" — itu memang keadaan yang
     * dihadapi instalasi baru.
     */
    private static function adoptExistingInstallation(): bool
    {
        try {
            if (! Schema::hasTable('users') || ! DB::table('users')->exists()) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        try {
            static::markInstalled();
        } catch (Throwable) {
            // Gagal menulis kunci bukan alasan menyatakan aplikasi belum terpasang — itu justru
            // akan melempar instalasi hidup ke wizard pemasangan.
        }

        return true;
    }
}
