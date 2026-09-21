<?php

use App\Exceptions\Plugins\PluginInstallException;
use App\Livewire\Admin\Sistem\Plugin as PluginComponent;
use App\Models\Plugin;
use App\Services\Plugins\PluginInstaller;
use App\Services\Plugins\PluginManifestReader;
use App\Services\Plugins\PluginZipExtractor;
use App\Support\Plugins\AdminNavRegistry;
use App\Support\Plugins\PluginBootManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * Bangun ZIP fixture plugin yang valid di $zipPath. $extraEntries dipakai untuk
 * menyisipkan entry tambahan (mis. entry zip-slip atau entry berukuran besar)
 * setelah entry manifest/provider yang valid. $migrationBody, kalau diisi,
 * menggantikan isi default migration fixture (dipakai test migrate action agar
 * TIDAK menjalankan DDL asli — CREATE TABLE di MySQL melakukan implicit commit
 * yang merusak transaksi RefreshDatabase untuk sisa test suite berjalan).
 * $settingsRoute, kalau diisi, menambahkan field settings_route ke manifest
 * DAN mendaftarkan route bernama itu di routes/web.php fixture-nya.
 * $navGroup, kalau diisi (bentuk ['label' => string, 'route' => string]),
 * menyisipkan pemanggilan AdminNavRegistry::push() di boot() provider
 * fixture DAN mendaftarkan route bernama itu di routes/web.php fixture-nya
 * — mensimulasikan plugin yang inject grup menu navbar baru.
 */
function buildPluginZip(
    string $zipPath,
    string $slug = 'test-plugin',
    array $extraEntries = [],
    ?string $migrationBody = null,
    ?string $settingsRoute = null,
    ?array $navGroup = null,
): void {
    $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    $tableSlug = str_replace('-', '_', $slug);

    $manifest = [
        'name' => 'Test Plugin',
        'slug' => $slug,
        'version' => '1.0.0',
        'description' => 'Plugin fixture untuk test.',
        'provider' => "Plugins\\{$studly}\\{$studly}ServiceProvider",
    ];

    if ($settingsRoute) {
        $manifest['settings_route'] = $settingsRoute;
    }

    $settingsRouteLine = $settingsRoute
        ? "Route::get('/plugins/{$slug}/settings', fn () => 'settings page')->name('{$settingsRoute}');\n"
        : '';

    $navRouteLine = $navGroup
        ? "Route::get('/plugins/{$slug}/nav-target', fn () => 'nav target page')->name('{$navGroup['route']}');\n"
        : '';

    $navRegistryUse = $navGroup ? "use App\\Support\\Plugins\\AdminNavRegistry;\n" : '';
    $navBootParam = $navGroup ? 'AdminNavRegistry $nav' : '';
    $navPushCall = $navGroup
        ? <<<PHP

        \$nav->push([
            'label' => '{$navGroup['label']}',
            'items' => [
                ['route' => '{$navGroup['route']}', 'label' => '{$navGroup['label']}'],
            ],
        ]);
PHP
        : '';

    $entries = [
        'plugin.json' => json_encode($manifest),
        "src/{$studly}ServiceProvider.php" => <<<PHP
<?php

namespace Plugins\\{$studly};

{$navRegistryUse}use Illuminate\\Support\\ServiceProvider;

class {$studly}ServiceProvider extends ServiceProvider
{
    public function boot({$navBootParam}): void
    {
        \$this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        \$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
{$navPushCall}
    }
}

PHP,
        'routes/web.php' => <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

Route::get('/plugins/{$slug}/ping', function () {
    return response('pong');
});
{$settingsRouteLine}{$navRouteLine}
PHP,
        "database/migrations/2026_01_01_000000_{$tableSlug}_create_dummy_table.php" => $migrationBody ?? <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_{$tableSlug}_dummy', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_{$tableSlug}_dummy');
    }
};

PHP,
    ];

    $entries = array_merge($entries, $extraEntries);

    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();
}

/**
 * Livewire::test()->set() untuk properti WithFileUploads butuh instance
 * Illuminate\Http\Testing\File (hasil UploadedFile::fake()), bukan
 * Illuminate\Http\UploadedFile biasa — internal testing harness Livewire
 * mengakses properti public $name yang cuma ada di kelas fake itu. Dibangun
 * dari createWithContent() (bukan create() yang isinya sampah acak) supaya
 * isi zip fixture yang sesungguhnya tetap terbawa dan bisa diekstrak/divalidasi
 * sungguhan oleh PluginInstaller.
 */
function pluginUploadedFile(string $zipPath): UploadedFile
{
    return UploadedFile::fake()->createWithContent(basename($zipPath), File::get($zipPath));
}

/**
 * source_path plugin disimpan relatif terhadap base_path() (lihat
 * Plugin::sourceAbsolutePath()), sedangkan config('plugins.install_path')
 * berupa path absolut (default: storage/app/plugins) — helper ini
 * menerjemahkan slug ke path relatif itu supaya test tidak hardcode lokasi.
 */
function pluginSourcePath(string $slug): string
{
    return trim(str_replace(base_path(), '', config('plugins.install_path')), '/').'/'.$slug;
}

afterEach(function () {
    File::deleteDirectory(config('plugins.install_path'));
    File::deleteDirectory(storage_path('app/private/plugin-fixtures'));

    // Test migrate action sengaja memakai migration DML-only (bukan CREATE TABLE)
    // supaya tidak memicu implicit commit MySQL yang merusak transaksi
    // RefreshDatabase untuk sisa test suite — bersihkan baris marker + baris
    // `migrations` yang tertinggal secara manual di sini.
    DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->delete();
    DB::table('migrations')->where('migration', 'like', '%test_plugin_create_dummy_table%')->delete();
});

it('installs a valid plugin zip and keeps it disabled by default', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/valid.zip');
    buildPluginZip($zipPath);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install')
        ->assertHasNoErrors();

    $plugin = Plugin::where('slug', 'test-plugin')->first();

    expect($plugin)->not->toBeNull();
    expect($plugin->enabled)->toBeFalse();
    expect($plugin->provider_class)->toBe('Plugins\\TestPlugin\\TestPluginServiceProvider');
    expect(File::isDirectory(config('plugins.install_path').'/test-plugin'))->toBeTrue();
});

it('rejects a zip-slip attempt and does not write files outside plugins/', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/zip-slip.zip');

    buildPluginZip($zipPath, 'zip-slip-plugin', [
        '../../evil.php' => '<?php echo "pwned"; ?>',
    ]);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    expect(Plugin::count())->toBe(0);
    expect(File::exists(base_path('evil.php')))->toBeFalse();
    expect(File::isDirectory(config('plugins.install_path').'/zip-slip-plugin'))->toBeFalse();
});

it('rejects a zip exceeding the configured max extracted size', function () {
    config(['plugins.max_extracted_size_kb' => 1]);

    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/oversized.zip');

    buildPluginZip($zipPath, 'oversized-plugin', [
        'src/filler.bin' => str_repeat('A', 5 * 1024),
    ]);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    expect(Plugin::count())->toBe(0);
    expect(File::isDirectory(config('plugins.install_path').'/oversized-plugin'))->toBeFalse();
});

it('makes an enabled plugin route reachable and a disabled one 404', function () {
    // Route registration hanya terjadi saat AppServiceProvider::register() jalan
    // (sekali per boot aplikasi/proses). Untuk mensimulasikan "request berikutnya
    // setelah enable" tanpa reboot penuh proses test, panggil PluginBootManager
    // langsung setelah plugin ditempatkan di disk + baris DB dibuat.
    $slug = 'ping-plugin';
    $zipPath = storage_path('app/private/plugin-fixtures/ping.zip');
    buildPluginZip($zipPath, $slug);

    $extractDir = storage_path('app/private/plugin-fixtures/ping-extracted');
    (new PluginZipExtractor)->extract($zipPath, $extractDir, 102400);
    $manifest = (new PluginManifestReader)->read($extractDir);

    File::ensureDirectoryExists(config('plugins.install_path'));
    File::copyDirectory($extractDir, config('plugins.install_path').'/'.$slug);

    $plugin = Plugin::create([
        'name' => $manifest->name,
        'slug' => $manifest->slug,
        'version' => $manifest->version,
        'description' => $manifest->description,
        'provider_class' => $manifest->providerClass,
        'source_path' => pluginSourcePath($slug),
        'has_web_routes' => true,
        'has_api_routes' => false,
        'migrations_relative_path' => pluginSourcePath($slug).'/database/migrations',
        'enabled' => false,
    ]);

    PluginBootManager::bootEnabledPlugins($this->app);
    $this->get('/plugins/'.$slug.'/ping')->assertNotFound();

    $plugin->update(['enabled' => true]);
    PluginBootManager::bootEnabledPlugins($this->app);
    $this->get('/plugins/'.$slug.'/ping')->assertOk()->assertSee('pong');
});

it('runs a plugin migration through the migrate action', function () {
    // Migration fixture di sini sengaja DML-only (insert ke tabel `settings` yang
    // sudah ada), bukan CREATE TABLE — supaya test ini tidak memicu implicit
    // commit MySQL yang merusak transaksi RefreshDatabase untuk test lain di
    // proses yang sama. Ini tetap membuktikan Artisan::call('migrate', ['--path'
    // => ...]) benar-benar menjalankan file migration milik plugin.
    $dmlOnlyMigration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'plugin_test_plugin_migrated_marker',
            'value' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->delete();
    }
};

PHP;

    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/migrate.zip');
    buildPluginZip($zipPath, migrationBody: $dmlOnlyMigration);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    $plugin = Plugin::where('slug', 'test-plugin')->firstOrFail();

    expect(DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->exists())->toBeFalse();

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('migrate', $plugin->slug);

    expect(DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->exists())->toBeTrue();
    expect($plugin->fresh()->last_migrated_at)->not->toBeNull();
});

it('deletes a plugin when the confirmation slug matches exactly', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/to-delete.zip');
    buildPluginZip($zipPath, 'to-delete-plugin');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    expect(Plugin::where('slug', 'to-delete-plugin')->exists())->toBeTrue();
    expect(File::isDirectory(config('plugins.install_path').'/to-delete-plugin'))->toBeTrue();

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('confirmDelete', 'to-delete-plugin')
        ->set('confirmSlugInput', 'to-delete-plugin')
        ->call('destroy');

    expect(Plugin::where('slug', 'to-delete-plugin')->exists())->toBeFalse();
    expect(File::isDirectory(config('plugins.install_path').'/to-delete-plugin'))->toBeFalse();
});

it('tolerates leading/trailing whitespace in the confirmation slug (autofill/mobile keyboard artifacts)', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/to-delete-trim.zip');
    buildPluginZip($zipPath, 'to-delete-trim-plugin');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('confirmDelete', 'to-delete-trim-plugin')
        ->set('confirmSlugInput', '  to-delete-trim-plugin  ')
        ->call('destroy');

    expect(Plugin::where('slug', 'to-delete-trim-plugin')->exists())->toBeFalse();
});

it('refuses to delete a plugin when the confirmation slug does not match', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/keep.zip');
    buildPluginZip($zipPath, 'keep-plugin');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('confirmDelete', 'keep-plugin')
        ->set('confirmSlugInput', 'wrong-slug')
        ->call('destroy');

    expect(Plugin::where('slug', 'keep-plugin')->exists())->toBeTrue();
    expect(File::isDirectory(config('plugins.install_path').'/keep-plugin'))->toBeTrue();
});

it('blocks non-superadmin users from the plugin management page', function () {
    $nonSuperadmin = adminUser('admin_akademik');

    $this->actingAs($nonSuperadmin)->get(route('admin.sistem.plugin'))
        ->assertForbidden();
});

it('redirects unauthenticated users to login', function () {
    $this->get(route('admin.sistem.plugin'))
        ->assertRedirect(route('login'));
});

it('shows a working Pengaturan link when a plugin is enabled and declares a valid settings_route', function () {
    $admin = adminUser();
    $slug = 'with-settings';
    $settingsRouteName = "plugins.{$slug}.edit";
    $zipPath = storage_path('app/private/plugin-fixtures/with-settings.zip');
    buildPluginZip($zipPath, $slug, settingsRoute: $settingsRouteName);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    $plugin = Plugin::where('slug', $slug)->firstOrFail();
    expect($plugin->settings_route)->toBe($settingsRouteName);
    // Belum enabled -> settingsUrl() harus null meski settings_route terisi,
    // karena route-nya belum tentu terdaftar (provider belum di-boot).
    expect($plugin->settingsUrl())->toBeNull();

    $plugin->update(['enabled' => true]);
    PluginBootManager::bootEnabledPlugins($this->app);

    // PluginBootManager dipanggil manual di tengah proses test yang sudah
    // full-boot (bukan lewat siklus booting() alami) — beda dari request asli,
    // di sini index nama route (dipakai Route::has()/route()) tidak otomatis
    // di-refresh sampai kita minta eksplisit. Di request produksi sungguhan
    // ini terjadi otomatis sebagai bagian akhir siklus boot (sudah diverifikasi
    // manual lewat browser sebelumnya untuk storage-monitor).
    Route::getRoutes()->refreshNameLookups();

    expect($plugin->settingsUrl())->not->toBeNull();
    expect($plugin->settingsUrl())->toContain('plugins/'.$slug.'/settings');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->assertSee('plugins/'.$slug.'/settings', false);
});

it('shows the Pengaturan link immediately after clicking Aktifkan, without a page reload', function () {
    $admin = adminUser();
    $slug = 'live-enable-settings';
    $settingsRouteName = "plugins.{$slug}.edit";
    $zipPath = storage_path('app/private/plugin-fixtures/live-enable-settings.zip');
    buildPluginZip($zipPath, $slug, settingsRoute: $settingsRouteName);

    $component = Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    // enable() jalan lewat method Livewire yang sesungguhnya (bukan
    // PluginBootManager::bootEnabledPlugins() manual seperti test lain di
    // atas) — reproduksi persis apa yang terjadi saat user klik "Aktifkan"
    // di browser, dalam SATU request yang sama (tanpa reload halaman).
    $component->call('enable', $slug)
        ->assertSee('plugins/'.$slug.'/settings', false);

    expect(Plugin::where('slug', $slug)->firstOrFail()->settingsUrl())
        ->toContain('plugins/'.$slug.'/settings');
});

it('hides the Pengaturan link for a disabled plugin even if settings_route is declared', function () {
    $slug = 'disabled-with-settings';

    Plugin::create([
        'name' => 'Disabled With Settings',
        'slug' => $slug,
        'version' => '1.0.0',
        'provider_class' => 'Plugins\\DisabledWithSettings\\DisabledWithSettingsServiceProvider',
        'source_path' => pluginSourcePath($slug),
        'settings_route' => "plugins.{$slug}.edit",
        'enabled' => false,
    ]);

    $plugin = Plugin::where('slug', $slug)->firstOrFail();

    expect($plugin->settingsUrl())->toBeNull();
});

it('does not crash when a declared settings_route is not actually registered', function () {
    $admin = adminUser();
    $slug = 'broken-settings';

    // settings_route diisi manual di DB (bukan lewat manifest reader, yang
    // memvalidasi format-nya) untuk mensimulasikan plugin yang provider-nya
    // gagal mendaftarkan route yang dijanjikan di manifest-nya.
    Plugin::create([
        'name' => 'Broken Settings',
        'slug' => $slug,
        'version' => '1.0.0',
        'provider_class' => 'Plugins\\BrokenSettings\\BrokenSettingsServiceProvider',
        'source_path' => pluginSourcePath($slug),
        'settings_route' => 'plugins.broken-settings.does-not-exist',
        'enabled' => true,
    ]);

    $plugin = Plugin::where('slug', $slug)->firstOrFail();
    expect($plugin->settingsUrl())->toBeNull();

    // Halaman manajemen tetap harus render normal (tidak 500) walau ada baris
    // plugin dengan settings_route yang rutenya tidak pernah terdaftar.
    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->assertOk()
        ->assertDontSee('does-not-exist');
});

it('shows a plugin-injected navbar group for a superadmin once the plugin is enabled', function () {
    $admin = adminUser();
    $slug = 'nav-plugin';
    $navGroup = ['label' => 'Presensi QR', 'route' => "plugins.{$slug}.rekap"];
    $zipPath = storage_path('app/private/plugin-fixtures/nav-group.zip');
    buildPluginZip($zipPath, $slug, navGroup: $navGroup);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    Plugin::where('slug', $slug)->firstOrFail()->update(['enabled' => true]);
    PluginBootManager::bootEnabledPlugins($this->app);

    // PluginBootManager dipanggil manual di tengah proses test yang sudah
    // full-boot — beda dari request asli, index nama route tidak otomatis
    // di-refresh sampai diminta eksplisit (pola sama dengan test settings_route
    // di atas).
    Route::getRoutes()->refreshNameLookups();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Presensi QR');
});

it('hides a plugin-injected navbar group from a non-superadmin admin', function () {
    $superadmin = adminUser();
    $nonSuperadmin = adminUser('admin_akademik');
    $slug = 'nav-plugin-hidden';
    $navGroup = ['label' => 'Presensi QR Hidden', 'route' => "plugins.{$slug}.rekap"];
    $zipPath = storage_path('app/private/plugin-fixtures/nav-group-hidden.zip');
    buildPluginZip($zipPath, $slug, navGroup: $navGroup);

    Livewire::actingAs($superadmin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    Plugin::where('slug', $slug)->firstOrFail()->update(['enabled' => true]);
    PluginBootManager::bootEnabledPlugins($this->app);
    Route::getRoutes()->refreshNameLookups();

    $this->actingAs($nonSuperadmin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Presensi QR Hidden');
});

it('drops a plugin navbar group entirely when its only route is unresolvable', function () {
    $nav = app(AdminNavRegistry::class);
    $nav->push([
        'label' => 'Broken Nav',
        'items' => [
            ['route' => 'plugins.broken-nav.does-not-exist', 'label' => 'Ghost Page'],
        ],
    ]);

    expect($nav->all())->toBe([]);
});

it('drops only the invalid child of a plugin navbar submenu, keeping the valid sibling', function () {
    Route::get('/plugins/partial-nav/real', fn () => 'ok')->name('plugins.partial-nav.real');
    Route::getRoutes()->refreshNameLookups();

    $nav = app(AdminNavRegistry::class);
    $nav->push([
        'label' => 'Partial Nav',
        'items' => [
            ['label' => 'Sub', 'children' => [
                ['route' => 'plugins.partial-nav.real', 'label' => 'Real'],
                ['route' => 'plugins.partial-nav.ghost', 'label' => 'Ghost'],
            ]],
        ],
    ]);

    $groups = $nav->all();

    expect($groups)->toHaveCount(1);
    expect($groups[0]['items'][0]['children'])->toHaveCount(1);
    expect($groups[0]['items'][0]['children'][0]['route'])->toBe('plugins.partial-nav.real');
});

it('never shows a navbar group for a disabled plugin, since its provider never boots', function () {
    $admin = adminUser();
    $slug = 'disabled-nav-plugin';
    $navGroup = ['label' => 'Disabled Nav', 'route' => "plugins.{$slug}.rekap"];
    $zipPath = storage_path('app/private/plugin-fixtures/disabled-nav-group.zip');
    buildPluginZip($zipPath, $slug, navGroup: $navGroup);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    // Sengaja dibiarkan disabled (default install() — lihat test "installs a
    // valid plugin zip and keeps it disabled by default").
    PluginBootManager::bootEnabledPlugins($this->app);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Disabled Nav');
});

// Tenant Sikampus Cloud berbagi satu server: plugin yang diunggah satu kampus bisa membaca
// berkas tenant lain (termasuk .env-nya). Karena itu unggah plugin DITUTUP di tenant managed
// -- lihat PluginInstaller::uploadsAllowed().

it('refuses to install a plugin zip on a cloud-managed installation', function () {
    config(['sikampus.managed' => true]);

    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/managed.zip');
    buildPluginZip($zipPath, 'managed-plugin');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install')
        ->assertSee('tidak dapat memasang plugin dari berkas ZIP');

    expect(Plugin::count())->toBe(0);
    expect(File::isDirectory(config('plugins.install_path').'/managed-plugin'))->toBeFalse();
});

it('enforces the managed gate in PluginInstaller itself, not only in the UI', function () {
    // Jalur pemasangan lain yang ditambahkan kelak (command, API) tidak boleh bisa melewatinya.
    config(['sikampus.managed' => true]);

    $zipPath = storage_path('app/private/plugin-fixtures/managed-direct.zip');
    buildPluginZip($zipPath, 'managed-direct-plugin');

    expect(fn () => app(PluginInstaller::class)->install(pluginUploadedFile($zipPath), adminUser()))
        ->toThrow(PluginInstallException::class, 'Sikampus Cloud');

    expect(Plugin::count())->toBe(0);
    expect(File::isDirectory(config('plugins.install_path').'/managed-direct-plugin'))->toBeFalse();
});

it('hides the upload form on a cloud-managed installation and explains why', function () {
    config(['sikampus.managed' => true]);

    Livewire::actingAs(adminUser())->test(PluginComponent::class)
        ->assertDontSee('Instal Plugin Baru')
        ->assertDontSeeHtml('wire:model="pluginZip"')
        ->assertSee('Pemasangan plugin dikelola oleh Sikampus Cloud');
});

it('keeps the upload form on a self-hosted installation', function () {
    config(['sikampus.managed' => false]);

    Livewire::actingAs(adminUser())->test(PluginComponent::class)
        ->assertSee('Instal Plugin Baru')
        ->assertDontSee('Pemasangan plugin dikelola oleh Sikampus Cloud');
});

it('still lets a cloud-managed installation enable, disable and delete an existing plugin', function () {
    // Plugin yang sudah ada di disk dipasang oleh pihak yang berwenang (portal); mengelolanya
    // tidak memasukkan kode baru, jadi tidak ikut ditutup.
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/existing.zip');
    buildPluginZip($zipPath, 'existing-plugin');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    config(['sikampus.managed' => true]);

    Livewire::actingAs($admin)->test(PluginComponent::class)->call('enable', 'existing-plugin');
    expect(Plugin::where('slug', 'existing-plugin')->value('enabled'))->toBeTruthy();

    Livewire::actingAs($admin)->test(PluginComponent::class)->call('disable', 'existing-plugin');
    expect(Plugin::where('slug', 'existing-plugin')->value('enabled'))->toBeFalsy();

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('confirmDelete', 'existing-plugin')
        ->set('confirmSlugInput', 'existing-plugin')
        ->call('destroy');

    expect(Plugin::where('slug', 'existing-plugin')->exists())->toBeFalse();
});
