<?php

namespace App\Http\Middleware;

use App\Support\Installer\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tutup wizard pemasangan begitu aplikasi terpasang.
 *
 * INI PENJAGAAN TERPENTING PADA INSTALLER. Kalau /install masih hidup setelah aplikasi
 * terpasang, siapa pun yang menemukan URL-nya bisa menimpa .env dan membuat superadmin baru —
 * kendali penuh atas data akademik satu kampus. Ini kesalahan yang berulang kali terjadi pada
 * aplikasi PHP yang memakai pola installer web.
 *
 * Menjawab 404, bukan 403: 403 mengonfirmasi bahwa endpoint-nya ada dan hanya sedang tertutup.
 */
class EnsureAppIsNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(InstallationState::isInstalled(), 404);

        return $next($request);
    }
}
