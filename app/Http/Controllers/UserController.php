<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $role = $request->get('role');
        $status = $request->get('status');

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $data = $query->orderBy('name')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['admin', 'dosen', 'mahasiswa'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'avatar' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'id_mahasiswa' => ['nullable', 'integer', 'exists:mahasiswa,id'],
            'id_dosen' => ['nullable', 'integer', 'exists:dosen,id'],
        ]);

        // Validasi role dengan id_mahasiswa/id_dosen
        if ($validated['role'] === 'mahasiswa' && ! isset($validated['id_mahasiswa'])) {
            return response()->json([
                'message' => 'ID mahasiswa harus diisi untuk role mahasiswa.',
                'errors' => ['id_mahasiswa' => ['ID mahasiswa wajib diisi untuk role mahasiswa.']],
            ], 422);
        }

        if ($validated['role'] === 'dosen' && ! isset($validated['id_dosen'])) {
            return response()->json([
                'message' => 'ID dosen harus diisi untuk role dosen.',
                'errors' => ['id_dosen' => ['ID dosen wajib diisi untuk role dosen.']],
            ], 422);
        }

        // Validasi bahwa mahasiswa/dosen belum punya user
        if (isset($validated['id_mahasiswa'])) {
            $mahasiswa = Mahasiswa::find($validated['id_mahasiswa']);
            if ($mahasiswa && $mahasiswa->id_user) {
                return response()->json([
                    'message' => 'Mahasiswa ini sudah memiliki user.',
                    'errors' => ['id_mahasiswa' => ['Mahasiswa ini sudah memiliki user.']],
                ], 422);
            }

            if ($validated['role'] === 'mahasiswa') {
                $username = $mahasiswa?->nim;
                if (! $username) {
                    return response()->json([
                        'message' => 'NIM mahasiswa tidak ditemukan, username tidak dapat dibuat.',
                        'errors' => ['id_mahasiswa' => ['NIM mahasiswa tidak ditemukan.']],
                    ], 422);
                }
                if (User::where('username', $username)->exists()) {
                    return response()->json([
                        'message' => 'Username sudah digunakan. Silakan pilih data mahasiswa lain atau hubungi admin.',
                        'errors' => ['id_mahasiswa' => ['Username dari NIM mahasiswa sudah digunakan.']],
                    ], 422);
                }
                $validated['username'] = $username;
            }
        }

        if (isset($validated['id_dosen'])) {
            $dosen = Dosen::find($validated['id_dosen']);
            if ($dosen && $dosen->id_user) {
                return response()->json([
                    'message' => 'Dosen ini sudah memiliki user.',
                    'errors' => ['id_dosen' => ['Dosen ini sudah memiliki user.']],
                ], 422);
            }

            if ($validated['role'] === 'dosen') {
                $username = $dosen?->kode_dosen;
                if (! $username) {
                    return response()->json([
                        'message' => 'Kode dosen tidak ditemukan, username tidak dapat dibuat.',
                        'errors' => ['id_dosen' => ['Kode dosen tidak ditemukan.']],
                    ], 422);
                }
                if (User::where('username', $username)->exists()) {
                    return response()->json([
                        'message' => 'Username sudah digunakan. Silakan pilih data dosen lain atau hubungi admin.',
                        'errors' => ['id_dosen' => ['Username dari kode dosen sudah digunakan.']],
                    ], 422);
                }
                $validated['username'] = $username;
            }
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['email_verified_at'] = now();

        DB::beginTransaction();
        try {
            $user = User::create($validated);

            // Jika role mahasiswa, update id user ke tabel mahasiswa
            if ($validated['role'] === 'mahasiswa' && isset($validated['id_mahasiswa'])) {
                $mahasiswa = Mahasiswa::find($validated['id_mahasiswa']);
                if ($mahasiswa) {
                    $mahasiswa->update(['id_user' => $user->id]);
                }
            }

            // Jika role dosen, update id user ke tabel dosen
            if ($validated['role'] === 'dosen' && isset($validated['id_dosen'])) {
                $dosen = Dosen::find($validated['id_dosen']);
                if ($dosen) {
                    $dosen->update(['id_user' => $user->id]);
                }
            }

            DB::commit();

            return response()->json($user, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'required', 'string', Rule::in(['admin', 'dosen', 'mahasiswa'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'avatar' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'Pengguna dihapus']);
    }

    public function getAvailableMahasiswa(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 100);
        $search = $request->get('search');

        $query = Mahasiswa::whereNull('id_user');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function getAvailableDosen(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 100);
        $search = $request->get('search');

        $query = Dosen::whereNull('id_user');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode_dosen', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('nidn', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function getMahasiswaDetail($id): JsonResponse
    {
        $mahasiswa = Mahasiswa::find($id);

        if (! $mahasiswa) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        if ($mahasiswa->id_user) {
            return response()->json(['message' => 'Mahasiswa ini sudah memiliki user'], 400);
        }

        return response()->json($mahasiswa);
    }

    public function getDosenDetail($id): JsonResponse
    {
        $dosen = Dosen::find($id);

        if (! $dosen) {
            return response()->json(['message' => 'Dosen tidak ditemukan'], 404);
        }

        if ($dosen->id_user) {
            return response()->json(['message' => 'Dosen ini sudah memiliki user'], 400);
        }

        return response()->json($dosen);
    }

    public function getRolesAndScopes(User $user): JsonResponse
    {
        $rolesData = [];

        $user->load('roles');
        foreach ($user->roles as $role) {
            $roleCode = $role->code ?? $role->name;
            $scopes = UserRoleScope::buildScopesPayload($user->id, $role->id);

            $rolesData[$roleCode] = [
                'scopes' => $scopes,
            ];
        }

        return response()->json([
            'roles' => $rolesData,
        ]);
    }

    public function storeRolesAndScopes(Request $request, User $user): JsonResponse
    {
        // Role Spatie (Superadmin/Akademik/Keuangan) cuma berarti untuk akun bertipe admin — lihat
        // catatan lengkap di App\Livewire\Admin\Pengguna\Show::saveRoleScope() (versi panel dari
        // endpoint ini). 'min:1' tetap dipaksakan untuk akun admin (itu satu-satunya syarat
        // EnsureUserIsAdmin untuk membuka panel), tapi dosen/mahasiswa boleh dikirimi array kosong.
        $isAdminAccount = $user->role === 'admin';

        $validated = $request->validate([
            'roles' => $isAdminAccount ? ['required', 'array', 'min:1'] : ['present', 'array'],
            'roles.*' => ['required', 'integer', 'exists:roles,id'],
            'scopes' => ['nullable', 'array'],
            'scopes.fakultas' => ['nullable', 'array'],
            'scopes.fakultas.*' => ['integer', 'exists:fakultas,id'],
            'scopes.prodi' => ['nullable', 'array'],
            'scopes.prodi.*' => ['integer', 'exists:prodi,id'],
        ]);

        if (! $isAdminAccount && $validated['roles'] !== []) {
            return response()->json([
                'message' => 'Role Superadmin/Akademik/Keuangan hanya berlaku untuk akun bertipe Admin/Operator.',
            ], 422);
        }

        // Meski endpoint ini sudah dibatasi ke Superadmin, tetap cegah pemanggil yang
        // (secara tidak lazim) punya scope sendiri untuk memberi scope di luar cakupannya.
        $actor = $request->user();
        if ($actor && $actor->hasScopeRestriction()) {
            $allowedFakultasIds = $actor->getFakultasScopeIds();
            $allowedProdiIds = $actor->getAllowedProdiIds() ?? [];

            $requestedFakultasIds = $validated['scopes']['fakultas'] ?? [];
            $requestedProdiIds = $validated['scopes']['prodi'] ?? [];

            $fakultasDiluarScope = array_diff($requestedFakultasIds, $allowedFakultasIds);
            $prodiDiluarScope = array_diff($requestedProdiIds, $allowedProdiIds);

            if ($fakultasDiluarScope !== [] || $prodiDiluarScope !== []) {
                return response()->json([
                    'message' => 'Anda tidak berwenang memberikan scope di luar cakupan Anda sendiri.',
                ], 403);
            }
        }

        // Fakultas dan prodi adalah dua "level" scope yang terpisah (universitas/fakultas/prodi) —
        // tidak boleh dipilih sekaligus untuk role yang sama, karena scope fakultas otomatis membuka
        // akses ke semua prodi di fakultas tsb sehingga scope prodi jadi tidak berarti (dan membingungkan).
        $requestedFakultasIds = $validated['scopes']['fakultas'] ?? [];
        $requestedProdiIdsForLevelCheck = $validated['scopes']['prodi'] ?? [];
        if ($requestedFakultasIds !== [] && $requestedProdiIdsForLevelCheck !== []) {
            return response()->json([
                'message' => 'Pilih salah satu: scope Fakultas (akses semua prodi di fakultas tsb) atau scope Program Studi (akses prodi tertentu saja). Tidak bisa keduanya sekaligus untuk role yang sama.',
            ], 422);
        }

        // Level prodi boleh diberi lebih dari satu prodi, tapi semuanya harus dalam satu fakultas yang sama.
        $requestedProdiIds = $validated['scopes']['prodi'] ?? [];
        if (count($requestedProdiIds) > 1) {
            $jumlahFakultasBerbeda = Prodi::whereIn('id', $requestedProdiIds)
                ->distinct()
                ->count('id_fakultas');
            if ($jumlahFakultasBerbeda > 1) {
                return response()->json([
                    'message' => 'Scope prodi untuk satu role tidak boleh lintas fakultas. Pilih prodi dari satu fakultas yang sama, atau gunakan scope fakultas.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $roleModels = Role::whereIn('id', $validated['roles'])->get();
            if ($roleModels->count() !== count($validated['roles'])) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Satu atau lebih role tidak valid.',
                ], 422);
            }

            $user->syncRoles($roleModels);

            DB::table('user_role_scopes')
                ->where('id_user', $user->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            foreach ($validated['roles'] as $roleId) {
                if (! isset($validated['scopes'])) {
                    continue;
                }

                if (isset($validated['scopes']['fakultas']) && is_array($validated['scopes']['fakultas'])) {
                    foreach ($validated['scopes']['fakultas'] as $fakultasId) {
                        DB::table('user_role_scopes')->insert([
                            'id_user' => $user->id,
                            'id_role' => $roleId,
                            'id_scope' => $fakultasId,
                            'scope_type' => 'fakultas',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if (isset($validated['scopes']['prodi']) && is_array($validated['scopes']['prodi'])) {
                    foreach ($validated['scopes']['prodi'] as $prodiId) {
                        DB::table('user_role_scopes')->insert([
                            'id_user' => $user->id,
                            'id_role' => $roleId,
                            'id_scope' => $prodiId,
                            'scope_type' => 'prodi',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Role dan scope berhasil disimpan',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all available permissions grouped by resource
     */
    public function getPermissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        // Group permissions by resource
        $grouped = [];
        foreach ($permissions as $permission) {
            // Parse permission name: format bisa "resource action" atau "action resource"
            // Contoh: "manage semester", "view akademik", "semester read", dll
            $parts = explode(' ', $permission->name);

            // Cari pattern: {resource} {action} atau {action} {resource}
            // Action yang umum: read, write, delete, view, manage, create, update
            $actions = ['read', 'write', 'delete', 'view', 'manage', 'create', 'update'];

            $resource = '';
            $action = '';

            if (count($parts) >= 2) {
                // Cek apakah bagian pertama adalah action
                if (in_array(strtolower($parts[0]), $actions)) {
                    $action = strtolower($parts[0]);
                    $resource = implode(' ', array_slice($parts, 1));
                } else {
                    // Cek apakah bagian terakhir adalah action
                    $lastPart = strtolower($parts[count($parts) - 1]);
                    if (in_array($lastPart, $actions)) {
                        $action = $lastPart;
                        $resource = implode(' ', array_slice($parts, 0, -1));
                    } else {
                        // Jika tidak ada action yang jelas, gunakan seluruh nama sebagai resource
                        $resource = $permission->name;
                        $action = 'manage';
                    }
                }
            } else {
                $resource = $permission->name;
                $action = 'manage';
            }

            // Normalize action: view/manage -> read, create/update -> write
            if (in_array($action, ['view', 'manage'])) {
                $action = 'read';
            } elseif (in_array($action, ['create', 'update'])) {
                $action = 'write';
            }

            if (! isset($grouped[$resource])) {
                $grouped[$resource] = [
                    'resource' => $resource,
                    'permissions' => [],
                ];
            }

            $grouped[$resource]['permissions'][$action] = [
                'id' => $permission->id,
                'name' => $permission->name,
            ];
        }

        return response()->json([
            'permissions' => array_values($grouped),
        ]);
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(User $user): JsonResponse
    {
        // Get only DIRECT permissions (not from roles)
        // This is important because we only want to manage direct permissions in this tab
        $directPermissions = $user->getDirectPermissions()->pluck('name')->toArray();

        // Get all available permissions grouped by resource
        $allPermissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $grouped = [];
        foreach ($allPermissions as $permission) {
            $parts = explode(' ', $permission->name);
            $actions = ['read', 'write', 'delete', 'view', 'manage', 'create', 'update'];

            $resource = '';
            $action = '';

            if (count($parts) >= 2) {
                if (in_array(strtolower($parts[0]), $actions)) {
                    $action = strtolower($parts[0]);
                    $resource = implode(' ', array_slice($parts, 1));
                } else {
                    $lastPart = strtolower($parts[count($parts) - 1]);
                    if (in_array($lastPart, $actions)) {
                        $action = $lastPart;
                        $resource = implode(' ', array_slice($parts, 0, -1));
                    } else {
                        $resource = $permission->name;
                        $action = 'manage';
                    }
                }
            } else {
                $resource = $permission->name;
                $action = 'manage';
            }

            // Normalize action
            if (in_array($action, ['view', 'manage'])) {
                $action = 'read';
            } elseif (in_array($action, ['create', 'update'])) {
                $action = 'write';
            }

            if (! isset($grouped[$resource])) {
                $grouped[$resource] = [
                    'resource' => $resource,
                    'read' => false,
                    'write' => false,
                    'delete' => false,
                ];
            }

            // Check if user has this permission as DIRECT permission only
            if (in_array($permission->name, $directPermissions)) {
                $grouped[$resource][$action] = true;
            }
        }

        return response()->json([
            'permissions' => array_values($grouped),
        ]);
    }

    /**
     * Store user permissions
     */
    public function storeUserPermissions(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*.resource' => ['required', 'string'],
            'permissions.*.read' => ['nullable', 'boolean'],
            'permissions.*.write' => ['nullable', 'boolean'],
            'permissions.*.delete' => ['nullable', 'boolean'],
        ]);

        try {
            // Get all available permissions
            $allPermissions = Permission::where('guard_name', 'web')->get();
            $permissionMap = [];

            foreach ($allPermissions as $perm) {
                $parts = explode(' ', $perm->name);
                $actions = ['read', 'write', 'delete', 'view', 'manage', 'create', 'update'];

                $resource = '';
                $action = '';

                if (count($parts) >= 2) {
                    if (in_array(strtolower($parts[0]), $actions)) {
                        $action = strtolower($parts[0]);
                        $resource = implode(' ', array_slice($parts, 1));
                    } else {
                        $lastPart = strtolower($parts[count($parts) - 1]);
                        if (in_array($lastPart, $actions)) {
                            $action = $lastPart;
                            $resource = implode(' ', array_slice($parts, 0, -1));
                        } else {
                            $resource = $perm->name;
                            $action = 'manage';
                        }
                    }
                } else {
                    $resource = $perm->name;
                    $action = 'manage';
                }

                // Normalize action
                if (in_array($action, ['view', 'manage'])) {
                    $action = 'read';
                } elseif (in_array($action, ['create', 'update'])) {
                    $action = 'write';
                }

                if (! isset($permissionMap[$resource])) {
                    $permissionMap[$resource] = [];
                }
                $permissionMap[$resource][$action] = $perm;
            }

            // Collect permissions to assign
            $permissionsToAssign = [];
            foreach ($validated['permissions'] as $permData) {
                $resource = $permData['resource'];
                if (! isset($permissionMap[$resource])) {
                    continue;
                }

                // Only add if checkbox is explicitly checked (boolean true)
                // Check for read permission
                if (isset($permData['read']) && $permData['read'] === true && isset($permissionMap[$resource]['read'])) {
                    $permissionsToAssign[] = $permissionMap[$resource]['read']->name;
                }
                // Check for write permission
                if (isset($permData['write']) && $permData['write'] === true && isset($permissionMap[$resource]['write'])) {
                    $permissionsToAssign[] = $permissionMap[$resource]['write']->name;
                }
                // Check for delete permission
                if (isset($permData['delete']) && $permData['delete'] === true && isset($permissionMap[$resource]['delete'])) {
                    $permissionsToAssign[] = $permissionMap[$resource]['delete']->name;
                }
            }

            // Sync DIRECT permissions only (this will remove all direct permissions and assign new ones)
            // Note: syncPermissions only affects direct permissions, not permissions from roles
            // This will remove ALL existing direct permissions and assign only the ones in $permissionsToAssign
            $user->syncPermissions($permissionsToAssign);

            // Clear permission cache to ensure changes are reflected immediately
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            // Verify the sync worked
            $directPermsAfter = $user->getDirectPermissions()->pluck('name')->toArray();

            return response()->json([
                'message' => 'Permissions berhasil disimpan',
                'assigned_count' => count($permissionsToAssign),
                'direct_permissions_after' => count($directPermsAfter),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan permissions: '.$e->getMessage(),
            ], 500);
        }
    }
}
