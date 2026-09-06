<?php

use App\Http\Controllers\Web\InstallController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Wizard Pemasangan
|--------------------------------------------------------------------------
|
| Berkas TERPISAH dari routes/web.php, dan itu bukan soal kerapian. Rute di web.php otomatis
| mendapat grup middleware "web", yang kini memuat EnsureAppIsInstalled (mengalihkan pengunjung
| ke wizard selama belum terpasang) dan memakai sesi berbasis database. Kalau installer ikut
| grup itu, dua hal rusak sekaligus: wizard mengalihkan dirinya sendiri tanpa henti, dan sesinya
| mencoba menulis ke database yang justru sedang hendak dikonfigurasi.
|
| Pendaftarannya ada di bootstrap/app.php lewat withRouting(then: ...), memakai grup "install".
|
*/

Route::get('/', [InstallController::class, 'index'])->name('install.index');
Route::get('/database', [InstallController::class, 'database'])->name('install.database');
Route::post('/database', [InstallController::class, 'storeDatabase'])->name('install.database.store');
Route::get('/akun', [InstallController::class, 'account'])->name('install.account');
Route::post('/akun', [InstallController::class, 'storeAccount'])->name('install.account.store');
Route::get('/jalankan', [InstallController::class, 'run'])->name('install.run');
Route::post('/jalankan', [InstallController::class, 'execute'])->name('install.execute');
