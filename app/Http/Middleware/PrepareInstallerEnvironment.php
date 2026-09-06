<?php

namespace App\Http\Middleware;

use App\Services\Installer\EnvWriter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Menyiapkan lingkungan minimum supaya wizard pemasangan bisa berjalan pada aplikasi yang belum
 * dikonfigurasi sama sekali.
 *
 * Tiga masalah yang diselesaikan di sini, dan semuanya harus selesai SEBELUM middleware sesi
 * dan cookie berjalan:
 *
 *   1. .env belum ada. Disalin dari .env.example tanpa perlu input pengguna. Ini sekaligus uji
 *      izin tulis paling awal — kalau gagal, pemasang langsung tahu sebabnya alih-alih
 *      tersandung halaman error Laravel yang tidak menjelaskan apa pun.
 *
 *   2. APP_KEY masih kosong. Tanpa kunci, EncryptCookies melempar exception sebelum satu baris
 *      pun dari wizard sempat dirender. Kunci dibuat di sini, ditulis ke .env, lalu dimasukkan
 *      ke config supaya request yang sedang berjalan ini pun sudah memakainya.
 *
 *   3. SESSION_DRIVER dan CACHE_STORE bawaan menunjuk ke "database" — padahal database itulah
 *      yang sedang hendak dikonfigurasi pengguna. Keduanya dipaksa ke berkas khusus untuk rute
 *      installer, sehingga wizard punya sesi dan token CSRF tanpa pernah menyentuh database.
 */
class PrepareInstallerEnvironment
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Dipaksa lebih dulu: StartSession di belakang middleware ini akan langsung memakainya.
        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
        ]);

        $env = EnvWriter::forApp();

        if (! $env->exists() && ! $env->createFromExample()) {
            return $this->cannotWrite(
                'Berkas <code>.env</code> belum ada dan tidak bisa dibuat.',
                'Direktori aplikasi tidak bisa ditulis oleh PHP. Minta administrator server menjadikan '
                .'berkas aplikasi milik user yang menjalankan PHP, lalu muat ulang halaman ini.'
            );
        }

        if (blank(config('app.key'))) {
            try {
                $key = 'base64:'.base64_encode(random_bytes(32));
                $env->put(['APP_KEY' => $key]);
                config(['app.key' => $key]);
            } catch (Throwable $e) {
                return $this->cannotWrite(
                    'Kunci aplikasi (<code>APP_KEY</code>) tidak bisa dibuat.',
                    'Berkas <code>.env</code> tidak bisa ditulis: '.e($e->getMessage())
                );
            }
        }

        return $next($request);
    }

    /**
     * Halaman kesalahan yang dirakit tangan, tanpa Blade dan tanpa sesi: pada titik ini
     * lingkungan aplikasi memang belum tentu cukup untuk merender view.
     */
    private function cannotWrite(string $title, string $detail): SymfonyResponse
    {
        $html = <<<HTML
        <!doctype html>
        <html lang="id"><head><meta charset="utf-8"><title>Pemasangan Sikampus</title>
        <style>body{font-family:system-ui,sans-serif;max-width:38rem;margin:6rem auto;padding:0 1.5rem;color:#171717;line-height:1.6}
        h1{font-size:1.25rem}code{background:#f5f5f5;padding:.1rem .35rem;border-radius:.25rem;font-size:.9em}
        .box{border:1px solid #fde68a;background:#fffbeb;padding:1rem 1.25rem;border-radius:.5rem}</style></head>
        <body><h1>Pemasangan belum bisa dimulai</h1>
        <div class="box"><p><strong>{$title}</strong></p><p>{$detail}</p></div></body></html>
        HTML;

        return new Response($html, 500);
    }
}
