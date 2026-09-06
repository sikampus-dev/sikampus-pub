<?php

namespace App\Http\Middleware;

use App\Support\Installer\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lempar pengunjung ke wizard pemasangan selama aplikasi belum terpasang.
 *
 * Dipasang di grup "web". Rute installer berada di grup terpisah sehingga tidak pernah melewati
 * middleware ini — kalau sampai lewat, terjadi pengalihan berputar ke dirinya sendiri.
 */
class EnsureAppIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallationState::isInstalled()) {
            return $next($request);
        }

        // Jaring pengaman terhadap pengalihan berputar: rute installer memang didaftarkan di
        // luar grup "web" (lihat routes/install.php), tapi kalau suatu saat ada yang
        // memindahkannya ke web.php, gejalanya adalah wizard yang mengalihkan dirinya sendiri
        // tanpa henti — kegagalan yang membingungkan untuk dilacak.
        if ($request->routeIs('install.*')) {
            return $next($request);
        }

        return redirect()->route('install.index');
    }
}
