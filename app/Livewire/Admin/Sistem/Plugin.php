<?php

namespace App\Livewire\Admin\Sistem;

use App\Exceptions\Plugins\PluginInstallException;
// Nama kelas komponen ini ("Plugin") sama dengan App\Models\Plugin — WAJIB
// di-alias di sini, kalau tidak PHP akan bingung mana yang dimaksud tiap kali
// dipakai di dalam file ini sendiri.
use App\Models\Plugin as PluginModel;
use App\Services\Plugins\PluginInstaller;
use App\Support\Plugins\PluginBootManager;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Plugin extends Component
{
    use WithFileUploads;

    public $pluginZip = null;

    public ?string $slugToDelete = null;

    public string $confirmSlugInput = '';

    #[Computed]
    public function plugins()
    {
        return PluginModel::orderBy('name')->get();
    }

    public function install(): void
    {
        // Dicek sebelum validasi supaya tenant Cloud mendapat alasan yang benar, bukan pesan
        // "pilih berkas". Gerbang sesungguhnya tetap di PluginInstaller::install().
        if (! PluginInstaller::uploadsAllowed()) {
            $this->pluginZip = null;
            session()->flash('error', 'Instalasi Sikampus Cloud tidak dapat memasang plugin dari berkas ZIP. Hubungi tim Sikampus untuk memasang plugin.');

            return;
        }

        $this->validate([
            'pluginZip' => ['required', 'file', 'mimes:zip', 'max:'.config('plugins.max_zip_size_kb')],
        ], [
            'pluginZip.required' => 'Silakan pilih berkas ZIP plugin yang akan diunggah.',
            'pluginZip.file' => 'Berkas yang dipilih tidak valid.',
            'pluginZip.mimes' => 'Format berkas harus zip.',
            'pluginZip.max' => 'Ukuran berkas melebihi batas maksimum yang diizinkan.',
        ]);

        try {
            // TemporaryUploadedFile milik Livewire adalah subclass dari
            // Illuminate\Http\UploadedFile, jadi kompatibel langsung dengan
            // PluginInstaller::install() tanpa konversi apa pun.
            $plugin = app(PluginInstaller::class)->install($this->pluginZip, auth()->user());
        } catch (PluginInstallException $e) {
            session()->flash('error', 'Gagal instal plugin: '.$e->getMessage());

            return;
        }

        $this->pluginZip = null;
        unset($this->plugins);

        session()->flash('status', "Plugin \"{$plugin->name}\" berhasil diinstal. Aktifkan dari daftar di bawah untuk mulai menggunakannya.");
    }

    public function migrate(string $slug): void
    {
        $plugin = PluginModel::where('slug', $slug)->firstOrFail();

        if ($plugin->migrations_relative_path === null) {
            session()->flash('error', "Plugin \"{$plugin->name}\" tidak memiliki migration.");

            return;
        }

        Artisan::call('migrate', [
            '--path' => $plugin->migrations_relative_path,
            '--force' => true,
        ]);

        $plugin->update(['last_migrated_at' => now()]);
        unset($this->plugins);

        session()->flash('status', "Migration plugin \"{$plugin->name}\" dijalankan.\n\n".Artisan::output());
    }

    public function enable(string $slug): void
    {
        $plugin = PluginModel::where('slug', $slug)->firstOrFail();

        // Dicek SEBELUM clearCaches() (yang memanggil route:clear) supaya
        // pesannya mencerminkan kondisi nyata yang tadi mencegah route plugin
        // ini terdaftar — loadRoutesFrom() di provider plugin no-op kalau
        // route sedang di-cache (lihat PluginBootManager).
        $app = App::getFacadeRoot();
        $hadCachedRoutes = ($plugin->has_web_routes || $plugin->has_api_routes)
            && $app instanceof CachesRoutes
            && $app->routesAreCached();

        $plugin->update(['enabled' => true, 'disabled_at' => null]);
        $this->clearCaches();

        // PluginBootManager::bootEnabledPlugins() cuma jalan sekali di awal
        // REQUEST INI (sebelum plugin ini di-enable), jadi provider plugin
        // ini belum pernah register()/boot() sama sekali sejauh ini — tanpa
        // baris ini, tombol "Pengaturan" (settingsUrl() -> Route::has())
        // baru muncul setelah user reload halaman. refreshNameLookups()
        // dipanggil manual karena RouteServiceProvider bawaan Laravel cuma
        // melakukannya sekali di awal request, sebelum plugin ini enabled.
        PluginBootManager::bootPlugin(App::getFacadeRoot(), $plugin->slug);
        Route::getRoutes()->refreshNameLookups();

        unset($this->plugins);

        $status = "Plugin \"{$plugin->name}\" diaktifkan.";

        if ($hadCachedRoutes) {
            $status .= ' Catatan: routes sempat ter-cache saat plugin ini diaktifkan (sudah dibersihkan otomatis) — '
                .'pastikan proses deploy aplikasi TIDAK menjalankan `route:cache`/`artisan optimize`, karena itu akan '
                .'membuat route plugin berhenti terdaftar lagi sampai `route:clear` dijalankan ulang.';
        }

        session()->flash('status', $status);
    }

    public function disable(string $slug): void
    {
        $plugin = PluginModel::where('slug', $slug)->firstOrFail();

        $plugin->update(['enabled' => false, 'disabled_at' => now()]);
        $this->clearCaches();
        unset($this->plugins);

        session()->flash('status', "Plugin \"{$plugin->name}\" dinonaktifkan.");
    }

    public function confirmDelete(string $slug): void
    {
        $this->slugToDelete = $slug;
        $this->confirmSlugInput = '';
    }

    public function cancelDelete(): void
    {
        $this->slugToDelete = null;
        $this->confirmSlugInput = '';
    }

    public function destroy(): void
    {
        if (! $this->slugToDelete) {
            return;
        }

        $plugin = PluginModel::where('slug', $this->slugToDelete)->firstOrFail();

        // trim() jaga-jaga terhadap spasi tersisa dari autofill/autocomplete
        // browser atau saran keyboard mobile — bukan dari perilaku disengaja.
        if (trim($this->confirmSlugInput) !== $plugin->slug) {
            session()->flash('error', 'Slug konfirmasi tidak cocok. Plugin tidak dihapus.');

            return;
        }

        $name = $plugin->name;

        if (File::isDirectory($plugin->sourceAbsolutePath())) {
            File::deleteDirectory($plugin->sourceAbsolutePath());
        }

        $plugin->delete();
        $this->clearCaches();
        unset($this->plugins);

        $this->slugToDelete = null;
        $this->confirmSlugInput = '';

        session()->flash('status', "Plugin \"{$name}\" dihapus. Migration yang sudah dijalankan TIDAK di-rollback otomatis.");
    }

    /**
     * Route cache & opcache bisa membuat perubahan enabled/disabled/uninstall
     * tidak langsung terlihat — dibersihkan best-effort di sini.
     */
    private function clearCaches(): void
    {
        Artisan::call('route:clear');

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    public function render()
    {
        return view('livewire.admin.sistem.plugin', [
            'uploadsAllowed' => PluginInstaller::uploadsAllowed(),
        ])->extends('layouts.web');
    }
}
