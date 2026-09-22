<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'status',
        'avatar',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function userRoles()
    {
        return $this->hasMany(UserRole::class, 'id_user');
    }

    /**
     * Scope fakultas/prodi di user_role_scopes (per pasangan user–role Spatie).
     */
    public function roleScopes()
    {
        return $this->hasMany(UserRoleScope::class, 'id_user');
    }

    /**
     * Query scope rows yang masih berlaku: hanya untuk role yang ada di model_has_roles (Spatie).
     */
    public function activeRoleScopesQuery(): Builder
    {
        $roleIds = $this->roles()->pluck('roles.id');

        $q = UserRoleScope::query()
            ->where('id_user', $this->id)
            ->whereNull('deleted_at');

        if ($roleIds->isEmpty()) {
            return $q->whereRaw('1 = 0');
        }

        return $q->whereIn('id_role', $roleIds);
    }

    /**
     * Daftar id prodi di mana dosen (akun user ini) menjadi Kepala Prodi.
     * Kolom prodi.id_kaprodi mereferensi dosen.id (bukan users.id).
     *
     * @return array<int>
     */
    public function getKaprodiProdiIds(): array
    {
        if ($this->role !== 'dosen') {
            return [];
        }

        $dosen = Dosen::query()
            ->where('id_user', $this->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $dosen) {
            return [];
        }

        return Prodi::query()
            ->where('id_kaprodi', $dosen->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Daftar id prodi di mana dosen (akun user ini) menjadi Sekretaris Prodi.
     * Kolom prodi.id_sekprodi mereferensi dosen.id (bukan users.id).
     *
     * @return array<int>
     */
    public function getSekprodiProdiIds(): array
    {
        if ($this->role !== 'dosen') {
            return [];
        }

        $dosen = Dosen::query()
            ->where('id_user', $this->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $dosen) {
            return [];
        }

        return Prodi::query()
            ->where('id_sekprodi', $dosen->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * User berperan sebagai dosen dan menjadi Kepala Prodi untuk minimal satu prodi.
     */
    public function isKepalaProdiDosen(): bool
    {
        return $this->role === 'dosen' && $this->getKaprodiProdiIds() !== [];
    }

    /**
     * User berperan sebagai dosen dan menjadi Sekretaris Prodi untuk minimal satu prodi.
     */
    public function isSekretarisProdiDosen(): bool
    {
        return $this->role === 'dosen' && $this->getSekprodiProdiIds() !== [];
    }

    /**
     * Akses portal/API admin prodi: Kepala Prodi atau Sekretaris Prodi (prodi.id_kaprodi / id_sekprodi = dosen user).
     */
    public function hasProdiScope(): bool
    {
        return $this->isKepalaProdiDosen() || $this->isSekretarisProdiDosen();
    }

    /**
     * Id prodi yang boleh diakses melalui portal admin prodi (gabungan kaprodi + sekprodi).
     *
     * @return array<int>
     */
    public function getProdiScopeIds(): array
    {
        return array_values(array_unique(array_merge(
            $this->getKaprodiProdiIds(),
            $this->getSekprodiProdiIds()
        )));
    }

    /**
     * Cek apakah user memiliki scope fakultas dari user_role_scopes.
     */
    public function hasFakultasScope(): bool
    {
        return $this->activeRoleScopesQuery()
            ->where('scope_type', 'fakultas')
            ->exists();
    }

    /**
     * Kepala Prodi tanpa scope fakultas — hanya portal /prodi, bukan panel /admin penuh.
     */
    public function isProdiOnlyScopedAdmin(): bool
    {
        return $this->hasProdiScope() && ! $this->hasFakultasScope();
    }

    /**
     * Ambil daftar id_scope fakultas yang dimiliki user.
     * id_scope di user_role_scopes = id fakultas ketika scope_type = 'fakultas'.
     *
     * @return array<int>
     */
    public function getFakultasScopeIds(): array
    {
        return $this->activeRoleScopesQuery()
            ->where('scope_type', 'fakultas')
            ->pluck('id_scope')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ambil daftar id prodi yang di-assign langsung ke user via user_role_scopes
     * (scope_type = prodi) — dipakai untuk admin/staf yang bukan dosen kaprodi/sekprodi.
     *
     * @return array<int>
     */
    public function getDirectProdiScopeIds(): array
    {
        return $this->activeRoleScopesQuery()
            ->where('scope_type', 'prodi')
            ->pluck('id_scope')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Cek apakah user memiliki pembatasan scope (fakultas dan/atau prodi, baik lewat
     * status kaprodi/sekprodi maupun assignment langsung di user_role_scopes).
     * Jika true, data yang boleh diakses harus difilter menurut scope.
     */
    public function hasScopeRestriction(): bool
    {
        return $this->hasFakultasScope() || $this->hasProdiScope() || $this->getDirectProdiScopeIds() !== [];
    }

    /**
     * Superadmin (role Spatie): cocokkan name (mis. "Superadmin" dari seeder), varian huruf
     * kecil, dan kolom code = superadmin — selaras EnsureUserHasKeuanganAccess.
     */
    public function isSuperadmin(): bool
    {
        if ($this->hasAnyRole(['superadmin', 'Superadmin'])) {
            return true;
        }

        return $this->roles()
            ->where(function ($q): void {
                $q->where('code', 'superadmin')
                    ->orWhereIn('name', ['superadmin', 'Superadmin']);
            })
            ->exists();
    }

    /**
     * Alias untuk pemakaian di middleware login web Blade.
     */
    public function isSuperadminWeb(): bool
    {
        return $this->isSuperadmin();
    }

    /**
     * Role Spatie "Akademik" — sama polanya dengan isSuperadmin(), dipakai untuk menentukan
     * visibilitas bagian dashboard akademik pada dashboard admin gabungan.
     */
    public function isAkademik(): bool
    {
        if ($this->hasAnyRole(['akademik', 'Akademik'])) {
            return true;
        }

        return $this->roles()
            ->where(function ($q): void {
                $q->where('code', 'akademik')
                    ->orWhereIn('name', ['akademik', 'Akademik']);
            })
            ->exists();
    }

    /**
     * Role Spatie "Keuangan" — sama polanya dengan isSuperadmin(), dipakai untuk menentukan
     * visibilitas bagian dashboard keuangan pada dashboard admin gabungan.
     */
    public function isKeuangan(): bool
    {
        if ($this->hasAnyRole(['keuangan', 'Keuangan'])) {
            return true;
        }

        return $this->roles()
            ->where(function ($q): void {
                $q->where('code', 'keuangan')
                    ->orWhereIn('name', ['keuangan', 'Keuangan']);
            })
            ->exists();
    }

    /**
     * Ambil daftar id prodi yang boleh diakses user berdasarkan scope.
     * - Scope prodi: id prodi dari user_role_scopes (scope_type = prodi).
     * - Scope fakultas: id prodi yang id_fakultas-nya ada di user_role_scopes (scope_type = fakultas).
     * Gabungan keduanya (tanpa duplikat).
     * Jika user tidak punya scope fakultas/prodi, kembalikan null (artinya tidak ada filter).
     *
     * @return array<int>|null null = tanpa filter (akses penuh)
     */
    public function getAllowedProdiIds(): ?array
    {
        $fakultasIds = $this->getFakultasScopeIds();
        $prodiIds = array_values(array_unique(array_merge(
            $this->getProdiScopeIds(),
            $this->getDirectProdiScopeIds()
        )));

        if (empty($fakultasIds) && empty($prodiIds)) {
            return null;
        }

        $allowed = $prodiIds;

        if (! empty($fakultasIds)) {
            $prodiFromFakultas = Prodi::whereIn('id_fakultas', $fakultasIds)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            $allowed = array_values(array_unique(array_merge($allowed, $prodiFromFakultas)));
        }

        return $allowed;
    }

    /**
     * Nama route dashboard web (sesi Blade) yang cocok untuk user ini, dipakai oleh
     * login gabungan di "/" untuk menentukan tujuan redirect setelah autentikasi.
     * Null berarti akun ini tidak punya panel web yang bisa dituju.
     */
    public function webDashboardRouteName(): ?string
    {
        if ($this->hasAnyRole(['superadmin', 'akademik', 'keuangan', 'Superadmin', 'Akademik', 'Keuangan'])) {
            return 'admin.dashboard';
        }

        return match ($this->role) {
            'dosen' => 'dosen.dashboard',
            'mahasiswa' => 'mahasiswa.dashboard',
            default => null,
        };
    }

    /**
     * Cabut semua akses yang sudah terlanjur ada — token Sanctum (API) dan sesi web (database
     * session driver) — supaya menonaktifkan akun (status = inactive) benar-benar berlaku
     * seketika, bukan cuma mencegah login BARU. Tanpa ini, token/sesi yang sudah diterbitkan
     * sebelum akun dinonaktifkan tetap valid sampai kedaluwarsa sendiri atau logout manual.
     *
     * Dipanggil dari App\Livewire\Admin\Pengguna\Form::save() dan
     * App\Http\Controllers\UserController::update() setiap kali status disimpan sebagai
     * 'inactive' — lihat catatan lengkap di kedua tempat itu.
     */
    public function revokeAccess(): void
    {
        $this->tokens()->delete();

        DB::table('sessions')->where('user_id', $this->id)->delete();
    }
}
