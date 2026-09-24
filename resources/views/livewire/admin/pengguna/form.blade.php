@section('title', ($penggunaId ? 'Ubah' : 'Tambah') . ' Pengguna — ' . config('app.name'))
@section('header_title', ($penggunaId ? 'Ubah' : 'Tambah') . ' Pengguna')
@section('header_icon', 'users')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Pengguna', 'route' => route('admin.pengguna.index')],
        ['label' => $penggunaId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-900">Tipe Akun</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tipe *</label>
                    <x-searchable-select
                        model="role"
                        :live="true"
                        :options="['admin' => 'Admin / Operator', 'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa']"
                        placeholder="— Pilih tipe akun —"
                    />
                    @error('role') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                    <x-searchable-select
                        model="status"
                        :clearable="false"
                        :options="['active' => 'Aktif', 'inactive' => 'Tidak Aktif']"
                    />
                </div>
            </div>

            @if (! $penggunaId && $role === 'mahasiswa')
                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pilih Mahasiswa *</label>

                    @if ($this->selectedMahasiswa)
                        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5 text-sm shadow-border">
                            <span class="font-medium text-neutral-900">{{ $this->selectedMahasiswa->nim ?? '—' }} — {{ $this->selectedMahasiswa->nama }}</span>
                            <button type="button" wire:click="clearMahasiswa" class="text-neutral-400 transition hover:text-neutral-600">
                                <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                            </button>
                        </div>
                    @else
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="mahasiswaSearch"
                                placeholder="Ketik NIM atau nama mahasiswa untuk mencari..."
                                class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('id_mahasiswa') ring-2 ring-red-500 @enderror shadow-border"
                            />
                            @if ($mahasiswaSearch !== '')
                                <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg bg-white shadow-border-lg">
                                    @forelse ($this->mahasiswaResults->take($mahasiswaSearchLimit) as $m)
                                        <button
                                            type="button"
                                            wire:click="selectMahasiswa({{ $m->id }})"
                                            class="block w-full px-3 py-2 text-left text-sm transition hover:bg-neutral-50"
                                        >
                                            <span class="font-medium text-neutral-900">{{ $m->nim ?? '—' }}</span>
                                            <span class="text-neutral-500"> — {{ $m->nama }}</span>
                                        </button>
                                    @empty
                                        <p class="px-3 py-2 text-sm text-neutral-500">Tidak ada hasil untuk "{{ $mahasiswaSearch }}". Mahasiswa yang sudah memiliki akun tidak ikut ditampilkan di sini.</p>
                                    @endforelse
                                    @if ($this->mahasiswaResultsTruncated())
                                        <p class="border-t border-neutral-100 px-3 py-2 text-xs text-neutral-400">
                                            Menampilkan {{ $mahasiswaSearchLimit }} hasil teratas. Perjelas pencarian (mis. ketik NIM lengkap) untuk menemukan mahasiswa lain.
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                    <p class="mt-1.5 text-xs text-neutral-500">Hanya menampilkan mahasiswa yang belum memiliki akun. Memilih mahasiswa akan mengisi nama, email, dan detail lain secara otomatis.</p>
                    @error('id_mahasiswa') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            @if (! $penggunaId && $role === 'admin')
                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Peran Admin (Role) *</label>
                    <x-searchable-select
                        model="spatieRoleId"
                        :live="true"
                        :options="$this->spatieRoleOptions"
                        optionLabel="name"
                        placeholder="— Pilih peran admin —"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">Menentukan menu dan permission yang otomatis dimiliki akun ini (mis. Akademik hanya dapat mengelola modul Akademik &amp; Administrasi, Keuangan hanya modul Keuangan). Bisa diubah lagi lewat tab Role setelah pengguna dibuat.</p>
                    @error('spatieRoleId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            @if (! $penggunaId && $role === 'dosen')
                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pilih Dosen *</label>
                    <x-searchable-select
                        model="id_dosen"
                        :live="true"
                        :options="$this->dosenOptions"
                        optionLabel="nama"
                        placeholder="Pilih dosen yang belum memiliki akun user"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">Hanya menampilkan dosen yang belum memiliki akun. Memilih dosen akan mengisi nama, email, dan detail lain secara otomatis.</p>
                    @error('id_dosen') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-900">Informasi Dasar</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama *</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('name') ring-2 ring-red-500 @enderror shadow-border" placeholder="Masukkan nama lengkap" />
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Email *</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('email') ring-2 ring-red-500 @enderror shadow-border" placeholder="contoh@email.com" />
                    @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Password {{ $penggunaId ? '' : '*' }}</label>
                    <input type="password" wire:model="password" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('password') ring-2 ring-red-500 @enderror shadow-border" placeholder="{{ $penggunaId ? 'Kosongkan jika tidak ingin mengubah password' : 'Minimal 8 karakter' }}" />
                    @error('password') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Telepon</label>
                    <input type="text" wire:model="phone" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" placeholder="081234567890" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-900">Alamat</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Alamat Lengkap</label>
                    <textarea wire:model="address" rows="3" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" placeholder="Masukkan alamat lengkap"></textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kota</label>
                    <input type="text" wire:model="city" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Provinsi</label>
                    <input type="text" wire:model="state" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Pos</label>
                    <input type="text" wire:model="zip" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Negara</label>
                    <input type="text" wire:model="country" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" placeholder="Indonesia" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $penggunaId ? route('admin.pengguna.show', $penggunaId) : route('admin.pengguna.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
