<?php

namespace App\Services\Plugins;

use App\Exceptions\Plugins\PluginInstallException;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orkestrasi install plugin: simpan ZIP upload -> extract ke scratch dir (aman
 * dari zip-slip/zip-bomb) -> validasi manifest -> pindahkan ke
 * config('plugins.install_path')/<slug> -> catat di tabel `plugins` dengan
 * enabled=false (install tidak otomatis aktif).
 */
class PluginInstaller
{
    public function __construct(
        private readonly PluginZipExtractor $extractor,
        private readonly PluginManifestReader $manifestReader,
    ) {}

    /**
     * Unggah plugin DITUTUP di tenant Sikampus Cloud (config('sikampus.managed')).
     *
     * Plugin adalah kode PHP yang berjalan dengan hak penuh aplikasi. Di Cloud, semua tenant
     * berbagi satu server -- plugin yang diunggah superadmin satu kampus bisa membaca berkas
     * tenant lain, termasuk .env berisi kredensial database-nya. Di self-hosted risikonya
     * hanya milik pemilik server itu sendiri, jadi di sana unggah tetap diizinkan.
     *
     * Gerbangnya di sini, bukan hanya di komponen Livewire, supaya jalur pemasangan mana pun
     * yang ditambahkan kelak ikut tertutup. Plugin untuk tenant Cloud dipasang oleh portal
     * Sikampus. Mengaktifkan, migrasi, dan menghapus plugin yang sudah ada tetap boleh --
     * ketiganya tidak memasukkan kode baru.
     */
    public static function uploadsAllowed(): bool
    {
        return ! config('sikampus.managed');
    }

    public function install(UploadedFile $file, ?User $installedBy): Plugin
    {
        if (! static::uploadsAllowed()) {
            throw new PluginInstallException(
                'Instalasi Sikampus Cloud tidak dapat memasang plugin dari berkas ZIP. Hubungi tim Sikampus untuk memasang plugin.'
            );
        }

        $diskName = config('plugins.upload_disk');
        $disk = Storage::disk($diskName);

        $uploadedRelativePath = $file->store('plugin-uploads', $diskName);

        if ($uploadedRelativePath === false) {
            throw new PluginInstallException('Gagal menyimpan berkas ZIP yang diunggah.');
        }

        $uploadedAbsolutePath = $disk->path($uploadedRelativePath);
        $checksum = hash_file('sha256', $uploadedAbsolutePath) ?: null;
        $scratchDir = storage_path('app/private/plugin-tmp/'.(string) Str::uuid());

        try {
            $this->extractor->extract($uploadedAbsolutePath, $scratchDir, (int) config('plugins.max_extracted_size_kb'));

            $manifest = $this->manifestReader->read($scratchDir);

            if (Plugin::where('slug', $manifest->slug)->exists()) {
                throw new PluginInstallException("Plugin dengan slug \"{$manifest->slug}\" sudah terinstal.");
            }

            $installPath = config('plugins.install_path');
            $targetDir = $installPath.DIRECTORY_SEPARATOR.$manifest->slug;

            // install_path bisa dikonfigurasi ke lokasi mana pun (default:
            // storage/app/plugins, sudah writable di hampir semua deployment
            // Laravel tanpa chmod manual) — source_path & migrations_relative_path
            // disimpan relatif terhadap base_path() karena itu yang dipakai
            // Plugin::sourceAbsolutePath()/migrationsAbsolutePath() dan
            // PluginBootManager saat runtime.
            $sourcePath = trim(Str::after($installPath, base_path()), DIRECTORY_SEPARATOR).'/'.$manifest->slug;

            if (File::exists($targetDir)) {
                throw new PluginInstallException(
                    "Direktori {$sourcePath} sudah ada di disk (kemungkinan sisa instalasi sebelumnya yang gagal). Hapus direktori tersebut secara manual sebelum instal ulang."
                );
            }

            File::ensureDirectoryExists($installPath);
            File::moveDirectory($scratchDir, $targetDir);

            $migrationsRelativePath = $manifest->hasMigrations
                ? $sourcePath.'/database/migrations'
                : null;

            $plugin = Plugin::create([
                'name' => $manifest->name,
                'slug' => $manifest->slug,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'provider_class' => $manifest->providerClass,
                'source_path' => $sourcePath,
                'has_web_routes' => $manifest->hasWebRoutes,
                'has_api_routes' => $manifest->hasApiRoutes,
                'migrations_relative_path' => $migrationsRelativePath,
                'settings_route' => $manifest->settingsRoute,
                'checksum' => $checksum,
                'enabled' => false,
                'id_user' => $installedBy?->id,
                'installed_at' => now(),
            ]);

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            return $plugin;
        } finally {
            if (File::isDirectory($scratchDir)) {
                File::deleteDirectory($scratchDir);
            }

            $disk->delete($uploadedRelativePath);
        }
    }
}
