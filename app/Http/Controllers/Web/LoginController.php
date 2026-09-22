<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Login sesi web tunggal di halaman utama ("/") untuk semua tipe akun (admin/akademik/
     * keuangan, dosen, mahasiswa) — pengguna dikenali lewat email atau username, sama seperti
     * AuthController::login (API), lalu diarahkan ke dashboard sesuai role/tipe akunnya.
     */
    public function create(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $destination = $user->webDashboardRouteName();

            if ($destination !== null) {
                return redirect()->route($destination);
            }

            // Sesi ada tapi akun tidak punya panel web yang cocok — jangan biarkan macet
            // di halaman login yang tidak akan pernah lolos, akhiri sesinya.
            Auth::logout();
        }

        return view('auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['login'])
            ->orWhere('username', $credentials['login'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'Login gagal. Periksa kembali email/username dan kata sandi Anda.',
            ]);
        }

        if (in_array($user->role, ['mahasiswa', 'dosen'], true) && ! $user->email_verified_at) {
            throw ValidationException::withMessages([
                'login' => 'Email Anda belum diverifikasi. Silakan cek email Anda dan klik link verifikasi untuk mengaktifkan akun.',
            ]);
        }

        // Sama seperti AuthController::login (API) — akun yang dinonaktifkan admin tidak boleh
        // bisa login sama sekali, berlaku untuk semua tipe akun.
        if ($user->status === 'inactive') {
            throw ValidationException::withMessages([
                'login' => 'Akun Anda tidak aktif. Hubungi administrator untuk mengaktifkan kembali akun Anda.',
            ]);
        }

        $destination = $user->webDashboardRouteName();

        if ($destination === null) {
            throw ValidationException::withMessages([
                'login' => 'Akun ini tidak memiliki akses ke panel manapun.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route($destination));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
