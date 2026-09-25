@section('title', 'Tambah Yudisium — ' . config('app.name'))
@section('header_title', 'Tambah Yudisium')
@section('header_icon', 'award')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Yudisium', 'route' => route('admin.akademik.yudisium')],
        ['label' => 'Tambah'],
    ]])
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-700">Pilih Mahasiswa</h3>

            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Cari Mahasiswa *</label>
            @if ($selectedMahasiswaId)
                <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5 text-sm shadow-border">
                    <span class="font-medium text-neutral-900">{{ $this->selectedMahasiswa?->nim ?? '—' }} — {{ $this->selectedMahasiswa?->nama }}</span>
                    <button type="button" wire:click="clearMahasiswa" class="text-neutral-400 transition hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>
            @else
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="mahasiswaSearch"
                        placeholder="Cari NIM atau nama mahasiswa..."
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('selectedMahasiswaId') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @if ($mahasiswaSearch !== '')
                        <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg bg-white shadow-border-lg">
                            @forelse ($this->mahasiswaResults as $m)
                                <button
                                    type="button"
                                    wire:click="selectMahasiswa({{ $m->id }})"
                                    class="block w-full px-3 py-2 text-left text-sm transition hover:bg-neutral-50"
                                >
                                    <span class="font-medium text-neutral-900">{{ $m->nim ?? '—' }}</span>
                                    <span class="text-neutral-500"> — {{ $m->nama }}</span>
                                </button>
                            @empty
                                <p class="px-3 py-2 text-sm text-neutral-500">Tidak ada hasil.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif
            @error('selectedMahasiswaId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror

            @if ($this->selectedMahasiswa)
                <div class="mt-4 rounded-lg bg-neutral-50 p-4 shadow-border">
                    <h4 class="mb-3 text-sm font-semibold text-neutral-900">Detail Mahasiswa</h4>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">Nama</p>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->nama }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">NIM</p>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->nim ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">Email</p>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->email ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">No. HP</p>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->handphone ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">Program Studi</p>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->prodi?->nama ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">Semester Masuk</p>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->semester_masuk?->nama ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">Status Akademik</p>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->status_akademik?->nama ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">Kelompok Kelas</p>
                            <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->kelompok_kelas?->nama ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-700">Informasi Yudisium</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenis Keluar *</label>
                    <x-searchable-select
                        model="id_jenis_keluar"
                        :options="$jenisKeluarOptions"
                        placeholder="— Pilih jenis keluar —"
                    />
                    @error('id_jenis_keluar') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Keluar</label>
                    <input type="date" wire:model="tgl_keluar" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    @error('tgl_keluar') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. Ijazah</label>
                    <input type="text" wire:model="no_ijazah" placeholder="Nomor Ijazah" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    @error('no_ijazah') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. SK Yudisium</label>
                    <input type="text" wire:model="no_sk_yudisium" placeholder="Nomor SK Yudisium" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    @error('no_sk_yudisium') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal SK Yudisium</label>
                    <input type="date" wire:model="tanggal_sk_yudisium" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    @error('tanggal_sk_yudisium') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">IPK</label>
                    <input type="number" step="0.01" min="0" max="4.00" wire:model="ipk" placeholder="0.00 - 4.00" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    @error('ipk') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Judul Skripsi</label>
                    <input type="text" wire:model="judul_skripsi" placeholder="Judul Skripsi" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                    @error('judul_skripsi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Keterangan</label>
                    <textarea wire:model="keterangan" rows="3" placeholder="Keterangan tambahan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                    @error('keterangan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.akademik.yudisium') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="button" wire:click="save(true)" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                Simpan dan Buat Baru
            </button>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
