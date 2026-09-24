<?php

namespace App\Livewire\Admin\Pengguna;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    // Batas hasil pencarian mahasiswa — bukan seluruh data, cuma cari-lalu-pilih (lihat catatan
    // di mahasiswaResults()). Dinaikkan dari 20 karena pencarian nama umum bisa cocok >20 baris
    // dan mahasiswa yang dicari admin (yang belum punya akun sekalipun) jadi tidak pernah
    // terlihat kalau kebetulan jatuh di luar batas — bukan datanya hilang, cuma tidak ditampilkan.
    public int $mahasiswaSearchLimit = 50;

    public ?int $penggunaId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?string $role = null;

    // Hanya dipakai saat create tipe akun 'admin' — menentukan role Spatie (Superadmin/Akademik/
    // Keuangan) yang otomatis di-assign begitu user dibuat, supaya akun admin baru langsung punya
    // permission modulnya (lewat role_has_permissions) tanpa perlu mampir dulu ke tab Role di
    // halaman Show (yang tetap jadi tempat mengubah role/scope untuk user yang sudah ada).
    public ?int $spatieRoleId = null;

    public string $status = 'active';

    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $zip = '';

    public string $country = '';

    // Hanya dipakai saat create — lihat catatan di UserController::store soal role mahasiswa/dosen.
    public ?int $id_mahasiswa = null;

    public ?int $id_dosen = null;

    public string $mahasiswaSearch = '';

    public function mount(?int $id = null): void
    {
        $this->penggunaId = $id;

        if ($id === null) {
            return;
        }

        $pengguna = User::findOrFail($id);

        $this->name = $pengguna->name;
        $this->email = $pengguna->email;
        $this->role = $pengguna->role;
        $this->status = $pengguna->status ?? 'active';
        $this->phone = (string) $pengguna->phone;
        $this->address = (string) $pengguna->address;
        $this->city = (string) $pengguna->city;
        $this->state = (string) $pengguna->state;
        $this->zip = (string) $pengguna->zip;
        $this->country = (string) $pengguna->country;
    }

    public function updatedRole(): void
    {
        $this->id_mahasiswa = null;
        $this->id_dosen = null;
        $this->mahasiswaSearch = '';
        $this->spatieRoleId = null;
    }

    /**
     * Ambil 1 baris lebih banyak dari $mahasiswaSearchLimit — bukan untuk ditampilkan, cuma
     * penanda apakah hasilnya terpotong, supaya admin diberi tahu untuk mempersempit pencarian
     * alih-alih mengira mahasiswanya tidak ada. Lihat mahasiswaResultsTruncated() dan
     * form.blade.php (yang men-take() hasilnya ke $mahasiswaSearchLimit sebelum dirender).
     */
    #[Computed]
    public function mahasiswaResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        return Mahasiswa::query()
            ->whereNull('id_user')
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->mahasiswaSearch}%")
                    ->orWhere('nim', 'like', "%{$this->mahasiswaSearch}%");
            })
            ->orderBy('nama')
            ->limit($this->mahasiswaSearchLimit + 1)
            ->get(['id', 'nama', 'nim']);
    }

    public function mahasiswaResultsTruncated(): bool
    {
        return $this->mahasiswaResults->count() > $this->mahasiswaSearchLimit;
    }

    #[Computed]
    public function selectedMahasiswa(): ?Mahasiswa
    {
        return $this->id_mahasiswa ? Mahasiswa::find($this->id_mahasiswa) : null;
    }

    #[Computed]
    public function dosenOptions()
    {
        return Dosen::whereNull('id_user')->orderBy('nama')->get(['id', 'nama', 'kode_dosen']);
    }

    #[Computed]
    public function spatieRoleOptions()
    {
        return Role::orderBy('name')->get(['id', 'code', 'name']);
    }

    public function selectMahasiswa(int $id): void
    {
        $mahasiswa = Mahasiswa::whereNull('id_user')->findOrFail($id);

        $this->id_mahasiswa = $mahasiswa->id;
        $this->mahasiswaSearch = '';
        $this->name = $mahasiswa->nama ?: $this->name;
        $this->email = $mahasiswa->email ?: $this->email;
        $this->phone = $mahasiswa->handphone ?: $this->phone;
        $this->address = $mahasiswa->alamat ?: $this->address;
        $this->zip = $mahasiswa->kode_pos ?: $this->zip;
    }

    public function clearMahasiswa(): void
    {
        $this->id_mahasiswa = null;
    }

    public function updatedIdDosen(): void
    {
        if (! $this->id_dosen) {
            return;
        }

        $dosen = Dosen::whereNull('id_user')->find($this->id_dosen);

        if (! $dosen) {
            return;
        }

        $this->name = $dosen->nama ?: $this->name;
        $this->email = $dosen->email ?: $this->email;
        $this->phone = $dosen->no_hp ?: $this->phone;
        $this->address = $dosen->alamat ?: $this->address;
        $this->zip = $dosen->kode_pos ?: $this->zip;
    }

    /**
     * Rule sama persis dengan UserController::store/update.
     */
    protected function rules(): array
    {
        $uniqueEmail = Rule::unique('users', 'email');

        if ($this->penggunaId) {
            $uniqueEmail = $uniqueEmail->ignore($this->penggunaId);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $uniqueEmail],
            'role' => ['required', 'string', Rule::in(['admin', 'dosen', 'mahasiswa'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
        ];

        if ($this->penggunaId) {
            $rules['password'] = ['nullable', 'string', 'min:8'];

            return $rules;
        }

        $rules['password'] = ['required', 'string', 'min:8'];
        $rules['id_mahasiswa'] = ['nullable', 'integer', 'exists:mahasiswa,id'];
        $rules['id_dosen'] = ['nullable', 'integer', 'exists:dosen,id'];

        if ($this->role === 'admin') {
            $rules['spatieRoleId'] = ['required', 'integer', 'exists:roles,id'];
        }

        return $rules;
    }

    public function save()
    {
        $validated = $this->validate();

        foreach (['phone', 'address', 'city', 'state', 'zip', 'country'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->penggunaId) {
            if (empty($validated['password'])) {
                unset($validated['password']);
            } else {
                $validated['password'] = Hash::make($validated['password']);
            }

            unset($validated['id_mahasiswa'], $validated['id_dosen']);

            $pengguna = User::findOrFail($this->penggunaId);

            // Begitu status diubah jadi Aktif, anggap emailnya sudah terverifikasi (admin yang
            // mengaktifkan akun sudah memvalidasi identitasnya secara manual) — sama seperti
            // aturan saat create di bawah. Jangan timpa timestamp yang sudah ada (mis. dari
            // verifikasi asli lewat email), dan jangan pernah di-unset lagi kalau admin
            // menonaktifkan akun ini nanti; nonaktif bukan berarti emailnya jadi belum
            // terverifikasi.
            if ($validated['status'] === 'active' && ! $pengguna->email_verified_at) {
                $validated['email_verified_at'] = now();
            }

            $pengguna->update($validated);

            // Menonaktifkan akun harus berlaku SEKETIKA, bukan cuma mencegah login baru — token
            // API/sesi web yang sudah terlanjur diterbitkan sebelum ini tetap valid sampai
            // kedaluwarsa sendiri kalau tidak dicabut di sini. Dipanggil tanpa syarat setiap kali
            // status tersimpan 'inactive' (bukan hanya saat transisi dari active) supaya tidak ada
            // celah kalau ada token/sesi baru sempat terbit sebelum baris ini jalan.
            if ($validated['status'] === 'inactive') {
                $pengguna->revokeAccess();
            }

            session()->flash('status', 'Data pengguna berhasil diperbarui.');

            return redirect()->route('admin.pengguna.show', $this->penggunaId);
        }

        if ($validated['role'] === 'mahasiswa' && ! $this->id_mahasiswa) {
            $this->addError('id_mahasiswa', 'Pilih mahasiswa terlebih dahulu.');

            return;
        }

        if ($validated['role'] === 'dosen' && ! $this->id_dosen) {
            $this->addError('id_dosen', 'Pilih dosen terlebih dahulu.');

            return;
        }

        if ($this->id_mahasiswa) {
            $mahasiswa = Mahasiswa::find($this->id_mahasiswa);
            if (! $mahasiswa || $mahasiswa->id_user) {
                $this->addError('id_mahasiswa', 'Mahasiswa ini sudah memiliki user.');

                return;
            }

            if ($validated['role'] === 'mahasiswa') {
                if (! $mahasiswa->nim) {
                    $this->addError('id_mahasiswa', 'NIM mahasiswa tidak ditemukan, username tidak dapat dibuat.');

                    return;
                }
                if (User::where('username', $mahasiswa->nim)->exists()) {
                    $this->addError('id_mahasiswa', 'Username dari NIM mahasiswa sudah digunakan.');

                    return;
                }
                $validated['username'] = $mahasiswa->nim;
            }
        }

        if ($this->id_dosen) {
            $dosen = Dosen::find($this->id_dosen);
            if (! $dosen || $dosen->id_user) {
                $this->addError('id_dosen', 'Dosen ini sudah memiliki user.');

                return;
            }

            if ($validated['role'] === 'dosen') {
                if (! $dosen->kode_dosen) {
                    $this->addError('id_dosen', 'Kode dosen tidak ditemukan, username tidak dapat dibuat.');

                    return;
                }
                if (User::where('username', $dosen->kode_dosen)->exists()) {
                    $this->addError('id_dosen', 'Username dari kode dosen sudah digunakan.');

                    return;
                }
                $validated['username'] = $dosen->kode_dosen;
            }
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = $validated['status'] ?? 'active';
        // Sama seperti pada update di atas: hanya status Aktif yang otomatis dianggap
        // terverifikasi. Akun yang dibuat Tidak Aktif menunggu verifikasi normal (mis. lewat
        // alur aktivasi) sebelum email_verified_at terisi.
        $validated['email_verified_at'] = $validated['status'] === 'active' ? now() : null;
        unset($validated['id_mahasiswa'], $validated['id_dosen'], $validated['spatieRoleId']);

        DB::beginTransaction();
        try {
            $pengguna = User::create($validated);

            if ($validated['role'] === 'mahasiswa' && $this->id_mahasiswa) {
                Mahasiswa::find($this->id_mahasiswa)?->update(['id_user' => $pengguna->id]);
            }

            if ($validated['role'] === 'dosen' && $this->id_dosen) {
                Dosen::find($this->id_dosen)?->update(['id_user' => $pengguna->id]);
            }

            // Assign role Spatie di sini (bukan menyalin permission satu-satu) supaya permission
            // modulnya otomatis mengikuti apa pun yang ada di role_has_permissions saat ini —
            // termasuk kalau daftar permission role tsb berubah di kemudian hari lewat PermissionSeeder
            // atau halaman Role.
            if ($validated['role'] === 'admin' && $this->spatieRoleId) {
                $pengguna->assignRole(Role::findOrFail($this->spatieRoleId));
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        session()->flash('status', 'Data pengguna berhasil dibuat.');

        return redirect()->route('admin.pengguna.show', $pengguna->id);
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.pengguna.form')->extends('layouts.web');
    }
}
