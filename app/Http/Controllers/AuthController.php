<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use App\Models\UserRoleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registrasi pengguna baru dan kembalikan token akses.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login pengguna dan buat token akses baru.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['nullable', 'string'],
            'email' => ['nullable', 'string'], // backward compatibility
            'password' => ['required', 'string'],
        ]);

        $loginInput = $credentials['login'] ?? $credentials['email'] ?? null;
        if (! $loginInput) {
            throw ValidationException::withMessages([
                'login' => ['Email atau username wajib diisi.'],
            ]);
        }

        $user = User::where('email', $loginInput)
            ->orWhere('username', $loginInput)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Login gagal. Kredensial tidak valid. Gunakan email/username dan password yang sesuai.'],
            ]);
        }

        // Cek verifikasi email untuk mahasiswa dan dosen
        if (in_array($user->role, ['mahasiswa', 'dosen']) && ! $user->email_verified_at) {
            throw ValidationException::withMessages([
                'login' => ['Email Anda belum diverifikasi. Silakan cek email Anda dan klik link verifikasi untuk mengaktifkan akun.'],
            ]);
        }

        // Akun yang dinonaktifkan admin lewat Pengaturan > Pengguna tidak boleh bisa login sama
        // sekali — berlaku untuk semua tipe akun (admin/dosen/mahasiswa), bukan cuma yang
        // butuh verifikasi email di atas.
        if ($user->status === 'inactive') {
            throw ValidationException::withMessages([
                'login' => ['Akun Anda tidak aktif. Hubungi administrator untuk mengaktifkan kembali akun Anda.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $payload = [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];

        // Portal/API prodi: Kepala Prodi atau Sekretaris Prodi (dosen)
        $payload['is_kepala_prodi'] = $user->isKepalaProdiDosen();
        if ($user->hasProdiScope()) {
            $payload['is_admin_prodi'] = true;
            $payload['prodi_scope_ids'] = $user->getProdiScopeIds();
        }
        $payload['is_prodi_only_scoped_admin'] = $user->isProdiOnlyScopedAdmin();

        return response()->json($payload);
    }

    /**
     * Kirim link reset password ke email user.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Link reset password berhasil dikirim ke email Anda.',
        ]);
    }

    /**
     * Proses reset password user berdasarkan token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Password berhasil diperbarui. Silakan login kembali.',
        ]);
    }

    /**
     * Hapus token yang sedang dipakai.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Berhasil logout',
        ]);
    }

    /**
     * Kembalikan data pengguna terautentikasi.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $response = ['user' => $user];

        // Jika role mahasiswa, tambahkan data mahasiswa + info akademik (prodi, jenjang, fakultas, dosen wali)
        if ($user->role === 'mahasiswa') {
            $mahasiswa = Mahasiswa::with([
                'prodi.jenjang',
                'prodi.fakultas',
                'semester_masuk',
                'grup_mahasiswa',
                'dosen_wali' => function ($q) {
                    $q->where('status', 'active')->with('dosen');
                },
            ])->where('id_user', $user->id)->first();
            if ($mahasiswa) {
                $baseUrl = config('app.url');
                $fotoUrl = $mahasiswa->foto ? $baseUrl.'/storage/'.$mahasiswa->foto : null;
                $dosenWaliAktif = $mahasiswa->dosen_wali->first();
                $response['mahasiswa'] = [
                    'nim' => $mahasiswa->nim,
                    'nama' => $mahasiswa->nama,
                    'foto_url' => $fotoUrl,
                    'prodi_nama' => $mahasiswa->prodi?->nama,
                    'jenjang_nama' => $mahasiswa->prodi?->jenjang?->nama,
                    'fakultas_nama' => $mahasiswa->prodi?->fakultas?->nama,
                    'dosen_wali_nama' => $dosenWaliAktif?->dosen?->nama,
                    'dosen_wali_gelar_depan' => $dosenWaliAktif?->dosen?->gelar_depan,
                    'dosen_wali_gelar_belakang' => $dosenWaliAktif?->dosen?->gelar_belakang,
                    'status_akademik_nama' => $mahasiswa->status_akademik?->nama,
                    'angkatan' => $mahasiswa->semester_masuk?->nama,
                    'grup_mahasiswa_nama' => $mahasiswa->grup_mahasiswa?->nama,
                ];
            }
        }

        // Jika role dosen, tambahkan data dosen
        if ($user->role === 'dosen') {
            $dosen = Dosen::where('id_user', $user->id)->first();
            if ($dosen) {
                $baseUrl = config('app.url');
                $fotoUrl = $dosen->foto ? $baseUrl.'/storage/'.$dosen->foto : null;
                $response['dosen'] = [
                    'id' => $dosen->id,
                    'kode_dosen' => $dosen->kode_dosen,
                    'nidn' => $dosen->nidn,
                    'nama' => $dosen->nama,
                    'foto_url' => $fotoUrl,
                ];
            }
        }

        // Ambil roles dan permissions menggunakan Spatie Permission (model_has_roles) untuk admin users.
        // Spatie adalah satu-satunya sumber kebenaran role admin; tidak lagi bergantung pada users.role legacy.
        if ($user->roles()->exists()) {
            $spatieRoles = $user->roles()->with('permissions')->get();
            $rolesData = [];

            foreach ($spatieRoles as $role) {
                $roleCode = $role->code ?? $role->name;
                $scopes = UserRoleScope::buildScopesPayload($user->id, $role->id);

                $rolesData[$roleCode] = [
                    'scopes' => $scopes,
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                ];
            }

            $response['permissions'] = $user->getAllPermissions()->pluck('name')->toArray();
            $response['roles'] = $rolesData;
        }

        // Portal/API prodi: is_admin_prodi = kaprodi atau sekprodi (prodi.id_kaprodi / id_sekprodi = dosen user)
        $response['is_kepala_prodi'] = $user->isKepalaProdiDosen();
        $response['is_admin_prodi'] = $user->hasProdiScope();
        $response['is_prodi_only_scoped_admin'] = $user->isProdiOnlyScopedAdmin();
        if ($response['is_admin_prodi']) {
            $prodiIds = $user->getProdiScopeIds();
            $response['prodi_scope_ids'] = $prodiIds;
            $prodiList = Prodi::with('jenjang')
                ->whereIn('id', $prodiIds)
                ->orderBy('nama')
                ->get()
                ->map(fn (Prodi $p) => [
                    'id' => $p->id,
                    'nama' => $p->nama,
                    'kode' => $p->kode,
                    'jenjang' => $p->jenjang ? ['kode' => $p->jenjang->kode, 'nama' => $p->jenjang->nama] : null,
                ])
                ->values()
                ->all();
            $response['prodi_list'] = $prodiList;
        }

        return response()->json($response);
    }
}
