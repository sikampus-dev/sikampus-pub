<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Installer\EnvWriter;
use App\Services\Installer\RequirementChecker;
use App\Support\Installer\InstallationState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PDO;
use Throwable;

/**
 * Wizard pemasangan awal.
 *
 * Seluruh rutenya berada di grup middleware "install" (lihat bootstrap/app.php) yang memaksa
 * sesi & cache berbasis berkas, menyiapkan .env + APP_KEY, dan menutup diri begitu aplikasi
 * terpasang.
 */
class InstallController extends Controller
{
    private const SESSION_DB = 'install.database';

    private const SESSION_ACCOUNT = 'install.account';

    public function index(RequirementChecker $checker): View
    {
        return view('install.persyaratan', [
            'checks' => $checker->checks(),
            'passes' => $checker->passes(),
        ]);
    }

    public function database(): View
    {
        return view('install.database');
    }

    /**
     * Koneksi diuji SEBELUM apa pun ditulis. Kredensial yang salah adalah kesalahan paling
     * lazim saat memasang, dan menemukannya di sini jauh lebih baik daripada setelah .env
     * terlanjur ditulis lalu migrasi gagal setengah jalan.
     */
    public function storeDatabase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:64'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ], [
            'db_host.required' => 'Host database wajib diisi',
            'db_database.required' => 'Nama database wajib diisi',
            'db_username.required' => 'Username database wajib diisi',
            'db_port.integer' => 'Port harus berupa angka',
        ]);

        try {
            $pdo = $this->connect($validated);
        } catch (Throwable $e) {
            return back()
                ->withErrors(['db_host' => 'Tidak bisa terhubung ke database: '.$e->getMessage()])
                // Password tidak dikembalikan ke form: mengembalikannya berarti menyimpannya di
                // sesi lalu merendernya lagi ke HTML.
                ->withInput($request->except('db_password'));
        }

        // Database yang sudah berisi tabel `users` hampir pasti instalasi lain yang masih hidup.
        // Memasang di atasnya akan menjalankan migrasi pada data orang lain.
        if ($this->hasUsersTable($pdo, $validated['db_database'])) {
            return back()
                ->withErrors(['db_database' => 'Database ini sudah berisi tabel Sikampus. Pakai database kosong, atau backup dan kosongkan dulu database ini.'])
                ->withInput($request->except('db_password'));
        }

        session([self::SESSION_DB => $validated]);

        return redirect()->route('install.account');
    }

    public function account(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has(self::SESSION_DB)) {
            return redirect()->route('install.database');
        }

        return view('install.akun');
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'institution_name.required' => 'Nama perguruan tinggi wajib diisi',
            'admin_name.required' => 'Nama admin wajib diisi',
            'admin_email.required' => 'Email admin wajib diisi',
            'admin_email.email' => 'Format email tidak valid',
            'admin_password.required' => 'Password wajib diisi',
            'admin_password.min' => 'Password minimal 8 karakter',
            'admin_password.confirmed' => 'Konfirmasi password tidak cocok',
        ]);

        session([self::SESSION_ACCOUNT => $validated]);

        return redirect()->route('install.run');
    }

    public function run(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has(self::SESSION_DB) || ! $request->session()->has(self::SESSION_ACCOUNT)) {
            return redirect()->route('install.database');
        }

        return view('install.jalankan', [
            'database' => $request->session()->get(self::SESSION_DB),
            'account' => $request->session()->get(self::SESSION_ACCOUNT),
        ]);
    }

    /**
     * Menjalankan pemasangan, lalu merender layar selesai LANGSUNG (bukan redirect): begitu
     * InstallationState::markInstalled() dipanggil, seluruh rute installer menjawab 404, jadi
     * halaman tujuan redirect tidak akan pernah bisa dibuka.
     */
    public function execute(Request $request): RedirectResponse|Response|View
    {
        // Di-resolve dari container (lihat AppServiceProvider) supaya test bisa mengarahkannya
        // ke berkas sementara alih-alih .env sungguhan.
        $env = app(EnvWriter::class);

        $database = $request->session()->get(self::SESSION_DB);
        $account = $request->session()->get(self::SESSION_ACCOUNT);

        if (! $database || ! $account) {
            return redirect()->route('install.database');
        }

        $notes = [];

        try {
            $env->put([
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => $request->getSchemeAndHttpHost(),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $database['db_host'],
                'DB_PORT' => (string) $database['db_port'],
                'DB_DATABASE' => $database['db_database'],
                'DB_USERNAME' => $database['db_username'],
                'DB_PASSWORD' => (string) ($database['db_password'] ?? ''),
            ]);

            $this->useDatabase($database);

            Artisan::call('migrate', ['--force' => true]);
            $notes[] = 'Struktur database dibuat.';

            // DatabaseSeeder hanya berisi data referensi — akun default sudah dikeluarkan dari
            // sana karena kredensialnya publik. Lihat docblock seeder itu.
            Artisan::call('db:seed', ['--force' => true]);
            $notes[] = 'Data referensi dimuat.';

            Artisan::call('sikampus:create-admin', [
                '--name' => $account['admin_name'],
                '--email' => $account['admin_email'],
                '--password' => $account['admin_password'],
                '--force' => true,
            ]);
            $notes[] = 'Akun superadmin dibuat.';

            Setting::updateOrCreate(['key' => 'app_univ_name'], ['value' => $account['institution_name']]);

            $notes[] = $this->linkStorage();

            foreach (['config:clear', 'view:clear'] as $command) {
                Artisan::call($command);
            }
        } catch (Throwable $e) {
            report($e);

            // 422, bukan 200: halaman gagal dan halaman konfirmasi memakai view yang sama, jadi
            // tanpa status yang berbeda tidak ada cara membedakan pemasangan yang berhasil dari
            // yang gagal selain membaca isi halamannya. Bagi manusia bedanya terlihat (ada kotak
            // merah), tapi pemantauan otomatis akan membaca kegagalan sebagai keberhasilan.
            return response()->view('install.jalankan', [
                'database' => $database,
                'account' => $account,
                'error' => $e->getMessage(),
            ], 422);
        }

        // Ditulis SEBELUM layar selesai dirender: selama berkas ini belum ada, /install masih
        // bisa dibuka siapa pun yang menemukan URL-nya.
        InstallationState::markInstalled();

        // Kredensial tidak boleh tertinggal di penyimpanan sesi setelah dipakai.
        $request->session()->forget([self::SESSION_DB, self::SESSION_ACCOUNT]);

        return view('install.selesai', [
            'notes' => $notes,
            'email' => $account['admin_email'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function connect(array $config): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['db_host'], $config['db_port'], $config['db_database']),
            $config['db_username'],
            (string) ($config['db_password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
    }

    private function hasUsersTable(PDO $pdo, string $database): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
        );
        $statement->execute([$database, 'users']);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Arahkan koneksi milik proses yang sedang berjalan ke database yang baru dikonfigurasi.
     * Menulis .env saja tidak cukup — nilai itu baru terbaca pada request berikutnya, sedangkan
     * migrasi harus jalan sekarang.
     *
     * @param  array<string, mixed>  $database
     */
    private function useDatabase(array $database): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $database['db_host'],
            'database.connections.mysql.port' => $database['db_port'],
            'database.connections.mysql.database' => $database['db_database'],
            'database.connections.mysql.username' => $database['db_username'],
            'database.connections.mysql.password' => (string) ($database['db_password'] ?? ''),
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    /**
     * symlink() lazim dimatikan di shared hosting. Kegagalannya bukan alasan menggagalkan
     * pemasangan — aplikasi tetap berjalan, hanya berkas unggahan yang belum tampil — tapi harus
     * disampaikan, karena kerusakannya tidak memunculkan error di mana pun.
     */
    private function linkStorage(): string
    {
        try {
            Artisan::call('storage:link');

            return 'Symlink storage dibuat.';
        } catch (Throwable $e) {
            return 'PERINGATAN: symlink storage gagal dibuat ('.$e->getMessage().'). '
                .'Berkas unggahan belum akan tampil sampai "php artisan storage:link" dijalankan di server.';
        }
    }
}
