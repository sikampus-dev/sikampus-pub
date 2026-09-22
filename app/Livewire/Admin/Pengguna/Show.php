<?php

namespace App\Livewire\Admin\Pengguna;

use App\Livewire\Admin\Pengguna\Concerns\ForwardsIndexState;
use App\Models\Fakultas;
use App\Models\Prodi;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class Show extends Component
{
    use ForwardsIndexState;

    public int $penggunaId;

    public string $backUrl;

    public string $activeTab = 'role';

    public bool $showRoleForm = false;

    public array $selectedRoleIds = [];

    public array $selectedFakultasIds = [];

    public array $selectedProdiIds = [];

    public array $permissionForm = [];

    public bool $confirmingDelete = false;

    public function mount(int $id): void
    {
        $this->penggunaId = $id;

        User::findOrFail($id);

        // Query string (search/filter/halaman aktif) diselipkan oleh link "Lihat Detail" di
        // Index — dibaca balik di sini supaya breadcrumb & tombol Kembali mendarat di
        // halaman/filter yang sama, bukan selalu halaman 1. Lihat Concerns\ForwardsIndexState.
        $this->backUrl = $this->resolveBackToIndexUrl();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;

        if ($tab === 'permission' && $this->permissionForm === []) {
            $this->loadPermissionForm();
        }
    }

    public function isSuperadminActor(): bool
    {
        return (bool) Auth::user()?->isSuperadmin();
    }

    #[Computed]
    public function pengguna(): User
    {
        return User::findOrFail($this->penggunaId);
    }

    #[Computed]
    public function roleOptions()
    {
        return Role::orderBy('name')->get(['id', 'code', 'name']);
    }

    #[Computed]
    public function fakultasOptions()
    {
        return Fakultas::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function prodiOptions()
    {
        return Prodi::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama', 'id_fakultas']);
    }

    /**
     * Sama dengan UserController::getRolesAndScopes.
     */
    #[Computed]
    public function rolesData(): array
    {
        $pengguna = $this->pengguna;
        $pengguna->load('roles');

        $data = [];
        foreach ($pengguna->roles as $role) {
            $roleCode = $role->code ?? $role->name;
            $data[$roleCode] = [
                'role_id' => $role->id,
                'scopes' => UserRoleScope::buildScopesPayload($pengguna->id, $role->id),
            ];
        }

        return $data;
    }

    public function openRoleForm(): void
    {
        $rolesData = $this->rolesData;

        $fakultasIds = [];
        $prodiIds = [];
        foreach ($rolesData as $row) {
            $scopes = is_array($row['scopes']) ? $row['scopes'] : [];
            foreach (($scopes['fakultas'] ?? []) as $id) {
                $fakultasIds[] = (int) $id;
            }
            foreach (($scopes['prodi'] ?? []) as $id) {
                $prodiIds[] = (int) $id;
            }
        }

        $this->selectedRoleIds = collect($rolesData)->pluck('role_id')->all();
        $this->selectedFakultasIds = array_values(array_unique($fakultasIds));
        $this->selectedProdiIds = array_values(array_unique($prodiIds));
        $this->resetErrorBag();
        $this->showRoleForm = true;
    }

    public function cancelRoleForm(): void
    {
        $this->showRoleForm = false;
        $this->selectedRoleIds = [];
        $this->selectedFakultasIds = [];
        $this->selectedProdiIds = [];
        $this->resetErrorBag();
    }

    public function updatedSelectedFakultasIds(): void
    {
        if ($this->selectedFakultasIds !== []) {
            $this->selectedProdiIds = [];
        }
    }

    public function updatedSelectedProdiIds(): void
    {
        if ($this->selectedProdiIds !== []) {
            $this->selectedFakultasIds = [];
        }
    }

    /**
     * Sama dengan UserController::storeRolesAndScopes.
     */
    public function saveRoleScope(): void
    {
        abort_unless($this->isSuperadminActor(), 403);

        // Role Spatie (Superadmin/Akademik/Keuangan) cuma berarti untuk akun bertipe admin — itu
        // yang dibaca EnsureUserIsAdmin(Web) untuk membuka panel admin. Akun dosen/mahasiswa
        // mengakses portalnya lewat kolom users.role legacy, sama sekali independen dari Spatie
        // role. Kalau dosen/mahasiswa sampai kebagian Spatie role (mis. admin salah pilih baris),
        // dia akan lolos DUA middleware sekaligus dan bisa membuka panel admin — jadi jalur ini
        // ditutup dari sumbernya, bukan cuma dibiarkan lalu dibersihkan lewat deleteRole().
        $pengguna = $this->pengguna;
        $isAdminAccount = $pengguna->role === 'admin';

        $this->validate([
            'selectedRoleIds' => $isAdminAccount ? ['required', 'array', 'min:1'] : ['array'],
            'selectedRoleIds.*' => ['integer', 'exists:roles,id'],
            'selectedFakultasIds.*' => ['integer', 'exists:fakultas,id'],
            'selectedProdiIds.*' => ['integer', 'exists:prodi,id'],
        ]);

        if (! $isAdminAccount && $this->selectedRoleIds !== []) {
            $this->addError('selectedRoleIds', 'Role Superadmin/Akademik/Keuangan hanya berlaku untuk akun bertipe Admin/Operator. Ubah tipe akun pengguna ini dulu kalau memang ingin memberinya role admin.');

            return;
        }

        if ($this->selectedFakultasIds !== [] && $this->selectedProdiIds !== []) {
            $this->addError('selectedProdiIds', 'Pilih salah satu: scope Fakultas (akses semua prodi di fakultas tsb) atau scope Program Studi (akses prodi tertentu saja). Tidak bisa keduanya sekaligus.');

            return;
        }

        if (count($this->selectedProdiIds) > 1) {
            $jumlahFakultasBerbeda = Prodi::whereIn('id', $this->selectedProdiIds)->distinct()->count('id_fakultas');
            if ($jumlahFakultasBerbeda > 1) {
                $this->addError('selectedProdiIds', 'Scope prodi untuk satu role tidak boleh lintas fakultas. Pilih prodi dari satu fakultas yang sama, atau gunakan scope fakultas.');

                return;
            }
        }

        DB::beginTransaction();
        try {
            $roleModels = Role::whereIn('id', $this->selectedRoleIds)->get();
            $pengguna->syncRoles($roleModels);

            DB::table('user_role_scopes')
                ->where('id_user', $pengguna->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            foreach ($this->selectedRoleIds as $roleId) {
                foreach ($this->selectedFakultasIds as $fakultasId) {
                    DB::table('user_role_scopes')->insert([
                        'id_user' => $pengguna->id,
                        'id_role' => $roleId,
                        'id_scope' => $fakultasId,
                        'scope_type' => 'fakultas',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($this->selectedProdiIds as $prodiId) {
                    DB::table('user_role_scopes')->insert([
                        'id_user' => $pengguna->id,
                        'id_role' => $roleId,
                        'id_scope' => $prodiId,
                        'scope_type' => 'prodi',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        unset($this->rolesData);
        $this->cancelRoleForm();
        session()->flash('status', 'Role dan scope berhasil disimpan.');
    }

    public function deleteRole(string $roleCode): void
    {
        abort_unless($this->isSuperadminActor(), 403);

        $pengguna = $this->pengguna;
        $rolesData = $this->rolesData;
        $removeId = $rolesData[$roleCode]['role_id'] ?? null;
        $remainingIds = array_values(array_diff(collect($rolesData)->pluck('role_id')->all(), [$removeId]));

        // Minimal satu role hanya wajib untuk akun bertipe admin — itu satu-satunya syarat
        // EnsureUserIsAdmin(Web) untuk membuka panel admin (lihat saveRoleScope() di atas).
        // Dosen/mahasiswa boleh berakhir tanpa Spatie role sama sekali; itu justru keadaan
        // normalnya (portalnya dijaga lewat users.role, bukan Spatie role).
        if ($remainingIds === [] && $pengguna->role === 'admin') {
            session()->flash('error', 'Pengguna harus memiliki minimal satu role.');

            return;
        }

        $pengguna->syncRoles(Role::whereIn('id', $remainingIds)->get());

        DB::table('user_role_scopes')
            ->where('id_user', $pengguna->id)
            ->where('id_role', $removeId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        unset($this->rolesData);
        session()->flash('status', 'Role berhasil dihapus.');
    }

    public function deleteScope(string $scopeType, int $scopeId): void
    {
        abort_unless($this->isSuperadminActor(), 403);

        DB::table('user_role_scopes')
            ->where('id_user', $this->penggunaId)
            ->where('scope_type', $scopeType)
            ->where('id_scope', $scopeId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        unset($this->rolesData);
        session()->flash('status', 'Scope berhasil dihapus.');
    }

    /**
     * Sama dengan pola parsing nama permission di UserController::getPermissions — TIDAK lagi
     * menormalisasi 'manage'/'view' jadi satu action 'read' di sini (lihat bug lama: 'manage X'
     * dan 'view X' bertabrakan di bucket yang sama, jadi menyimpan hasil centang "Read" saja bisa
     * diam-diam menyimpan 'manage X' — yang berarti akses PENUH, bukan cuma lihat). Normalisasi
     * dipindah ke buildPermissionResourceMap(), yang membedakan resource granular (punya
     * view/create/update/delete terpisah) dari resource yang cuma punya 'manage X' (mis. pengguna/
     * role/permission/sistem — lihat catatan di config/panel_access.php).
     *
     * @return array{0: string, 1: string}
     */
    private function groupPermissionName(string $name): array
    {
        $parts = explode(' ', $name);
        $actions = ['view', 'manage', 'create', 'update', 'delete'];

        if (count($parts) >= 2) {
            if (in_array(strtolower($parts[0]), $actions)) {
                return [implode(' ', array_slice($parts, 1)), strtolower($parts[0])];
            }

            $lastPart = strtolower($parts[count($parts) - 1]);
            if (in_array($lastPart, $actions)) {
                return [implode(' ', array_slice($parts, 0, -1)), $lastPart];
            }
        }

        return [$name, 'manage'];
    }

    /**
     * Kelompokkan semua permission per resource dan tentukan mode tampilannya:
     *
     * - 'granular': punya create/update/delete terpisah (mis. tagihan) — 'manage X' SENGAJA
     *   diabaikan sepenuhnya, karena begitu ada verb granular, itulah yang benar-benar dicek
     *   App\Support\PanelAccess di mode granular; menawarkan 'manage X' lagi di sampingnya cuma
     *   membuka jalan pintas yang melewati kontrol granular yang baru dibuat.
     * - 'view_plus_manage': punya 'view X' TAPI create/update/delete-nya sengaja tidak dipecah,
     *   cuma dibundel jadi satu 'manage X' (mis. pengguna — lihat catatan keamanan soal
     *   spatieRoleId di config/panel_access.php). 'view X' murni lihat, 'manage X' akses penuh.
     * - 'single': resource cuma punya SATU permission (biasanya 'manage X' — role/permission/
     *   sistem; atau permission section-level lama yang cuma 'view X' tanpa verb lain sama sekali,
     *   mis. 'view keuangan') — satu toggle indivisible.
     *
     * @return array<string, array{permissions: array<string, string>, mode: string}>
     */
    private function buildPermissionResourceMap(): array
    {
        $resources = [];

        foreach (Permission::where('guard_name', 'web')->orderBy('name')->get() as $permission) {
            [$resource, $action] = $this->groupPermissionName($permission->name);
            $resources[$resource]['permissions'][$action] = $permission->name;
        }

        foreach ($resources as $resource => $data) {
            $permissions = $data['permissions'];
            $hasWriteOrDelete = isset($permissions['create']) || isset($permissions['update']) || isset($permissions['delete']);

            if ($hasWriteOrDelete) {
                $resources[$resource]['mode'] = 'granular';
            } elseif (isset($permissions['view']) && isset($permissions['manage'])) {
                $resources[$resource]['mode'] = 'view_plus_manage';
            } else {
                $resources[$resource]['mode'] = 'single';
            }
        }

        return $resources;
    }

    private function loadPermissionForm(): void
    {
        $pengguna = $this->pengguna;
        $directPermissions = $pengguna->getDirectPermissions()->pluck('name')->toArray();

        $grouped = [];
        foreach ($this->buildPermissionResourceMap() as $resource => $data) {
            $permissions = $data['permissions'];

            if ($data['mode'] === 'granular') {
                $grouped[] = [
                    'resource' => $resource,
                    'mode' => 'granular',
                    'hasWrite' => isset($permissions['create']) || isset($permissions['update']),
                    'hasDelete' => isset($permissions['delete']),
                    'read' => isset($permissions['view']) && in_array($permissions['view'], $directPermissions, true),
                    'write' => (isset($permissions['create']) && in_array($permissions['create'], $directPermissions, true))
                        || (isset($permissions['update']) && in_array($permissions['update'], $directPermissions, true)),
                    'delete' => isset($permissions['delete']) && in_array($permissions['delete'], $directPermissions, true),
                ];

                continue;
            }

            if ($data['mode'] === 'view_plus_manage') {
                // Kolom Read = 'view X' (lihat saja), kolom Write = 'manage X' (akses penuh —
                // create/update/delete dibundel jadi satu, sengaja tidak dipecah lagi).
                $grouped[] = [
                    'resource' => $resource,
                    'mode' => 'view_plus_manage',
                    'hasWrite' => true,
                    'hasDelete' => false,
                    'read' => in_array($permissions['view'], $directPermissions, true),
                    'write' => in_array($permissions['manage'], $directPermissions, true),
                    'delete' => false,
                ];

                continue;
            }

            // Resource dengan satu permission saja (mis. role/permission/sistem, atau 'view
            // keuangan' yang murni section-level) — direpresentasikan sebagai satu toggle (kolom
            // Read), bukan tiga kolom terpisah yang menyiratkan bisa dipilih sebagian.
            $singleName = $permissions['manage'] ?? $permissions['view'] ?? null;
            $hasAccess = $singleName && in_array($singleName, $directPermissions, true);
            $grouped[] = [
                'resource' => $resource,
                'mode' => 'single',
                'hasWrite' => false,
                'hasDelete' => false,
                'read' => $hasAccess,
                'write' => $hasAccess,
                'delete' => $hasAccess,
            ];
        }

        $this->permissionForm = $grouped;
    }

    /**
     * Sama dengan UserController::storeUserPermissions.
     */
    public function savePermissions(): void
    {
        abort_unless($this->isSuperadminActor(), 403);

        $pengguna = $this->pengguna;
        $resourceMap = $this->buildPermissionResourceMap();

        $permissionsToAssign = [];
        foreach ($this->permissionForm as $row) {
            $data = $resourceMap[$row['resource']] ?? null;
            if (! $data) {
                continue;
            }

            $permissions = $data['permissions'];

            if ($data['mode'] === 'granular') {
                if (! empty($row['read']) && isset($permissions['view'])) {
                    $permissionsToAssign[] = $permissions['view'];
                }

                if (! empty($row['write'])) {
                    if (isset($permissions['create'])) {
                        $permissionsToAssign[] = $permissions['create'];
                    }
                    if (isset($permissions['update'])) {
                        $permissionsToAssign[] = $permissions['update'];
                    }
                }

                if (! empty($row['delete']) && isset($permissions['delete'])) {
                    $permissionsToAssign[] = $permissions['delete'];
                }

                continue;
            }

            if ($data['mode'] === 'view_plus_manage') {
                if (! empty($row['write'])) {
                    // 'manage X' mencakup akses lihat juga — centang Write otomatis memberi 'view
                    // X' juga, supaya staf full-access tidak diam-diam kehilangan akses ke
                    // halaman index/show-nya sendiri (rute itu mengecek 'view X' di mode granular).
                    $permissionsToAssign[] = $permissions['manage'];
                    $permissionsToAssign[] = $permissions['view'];
                } elseif (! empty($row['read'])) {
                    $permissionsToAssign[] = $permissions['view'];
                }

                continue;
            }

            // mode 'single'.
            $singleName = $permissions['manage'] ?? $permissions['view'] ?? null;
            if ($singleName !== null && (! empty($row['read']) || ! empty($row['write']) || ! empty($row['delete']))) {
                $permissionsToAssign[] = $singleName;
            }
        }

        $pengguna->syncPermissions($permissionsToAssign);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        session()->flash('status', 'Permissions berhasil disimpan.');
    }

    public function confirmDeleteUser(): void
    {
        // "Hapus Pengguna" bukan cuma soal lihat — tombol pemicunya disembunyikan di Blade untuk
        // pemegang 'view pengguna' saja, tapi method Livewire ini tetap bisa dipanggil langsung
        // lewat request yang dipalsukan, jadi pengecekan di sini (dan di deleteUser()) yang jadi
        // otoritas sebenarnya.
        abort_unless(PanelAccess::can(Auth::user(), 'pengguna', 'manage'), 403, 'Anda tidak memiliki hak untuk menghapus pengguna.');

        $this->confirmingDelete = true;
    }

    public function cancelDeleteUser(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteUser()
    {
        abort_unless(PanelAccess::can(Auth::user(), 'pengguna', 'manage'), 403, 'Anda tidak memiliki hak untuk menghapus pengguna.');

        User::findOrFail($this->penggunaId)->delete();

        session()->flash('status', 'Pengguna berhasil dihapus.');

        return redirect()->route('admin.pengguna.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.pengguna.show')->extends('layouts.web');
    }
}
