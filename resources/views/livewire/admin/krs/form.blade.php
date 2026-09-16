@section('title', ($krsId ? 'Ubah' : 'Tambah') . ' KRS — ' . config('app.name'))
@section('header_title', ($krsId ? 'Ubah' : 'Tambah') . ' KRS')
@section('header_icon', 'clipboard-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'KRS', 'route' => $backUrl],
        ...($krsId ? [['label' => $mahasiswaNama ?: 'Detail', 'route' => $cancelUrl]] : []),
        ['label' => $krsId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    @if ($submitError !== '')
        <div class="mb-4 flex gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
            <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
            <span>{{ $submitError }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        @if ($krsId)
            {{-- Mode edit: satu baris krs, info mahasiswa read-only --}}
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h2 class="mb-4 text-sm font-semibold text-neutral-700">Detail Mahasiswa</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <span class="text-xs font-medium text-neutral-500">NIM</span>
                        <p class="text-sm font-semibold text-neutral-900">{{ $mahasiswaNim }}</p>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-neutral-500">Nama</span>
                        <p class="text-sm font-semibold text-neutral-900">{{ $mahasiswaNama }}</p>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-neutral-500">Prodi</span>
                        <p class="text-sm font-semibold text-neutral-900">{{ $mahasiswaProdiNama }}</p>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-neutral-500">Dosen Wali</span>
                        <p class="text-sm font-semibold text-neutral-900">{{ $mahasiswaDosenWali }}</p>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-neutral-500">Semester Masuk</span>
                        <p class="text-sm font-semibold text-neutral-900">{{ $mahasiswaSemesterMasuk }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h2 class="mb-4 text-sm font-semibold text-neutral-700">Edit KRS</h2>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kelas *</label>
                        <x-searchable-select
                            model="editIdKelas"
                            :options="$this->kelasOptions"
                            placeholder="— Pilih kelas —"
                        />
                        @error('editIdKelas') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                        <select wire:model="editStatus" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                            <option value="pending">Pending</option>
                            <option value="acc">Acc/Approved</option>
                        </select>
                        @error('editStatus') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 rounded-lg bg-neutral-50 p-4 shadow-border">
                    <h3 class="mb-2 text-xs font-semibold text-neutral-700">Kelas Saat Ini</h3>
                    <div class="space-y-1 text-sm">
                        <p><span class="font-medium text-neutral-500">Mata Kuliah:</span> <span class="text-neutral-900">{{ $currentMatkulLabel }}</span></p>
                        <p><span class="font-medium text-neutral-500">Semester:</span> <span class="text-neutral-900">{{ $currentSemesterLabel }}</span></p>
                        <p><span class="font-medium text-neutral-500">SKS:</span> <span class="text-neutral-900">{{ $currentSks ?? '—' }}</span></p>
                    </div>
                </div>
            </div>
        @else
            {{-- Mode create: cari mahasiswa, lalu bisa tambah beberapa kelas sekaligus --}}
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h2 class="mb-4 text-sm font-semibold text-neutral-700">Pilih Mahasiswa</h2>

                @if (! $selectedMahasiswaId)
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="mahasiswaSearch"
                            placeholder="Cari berdasarkan NIM atau nama..."
                            class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                        />
                        @if ($mahasiswaSearch !== '')
                            <div class="mt-1 max-h-56 overflow-y-auto rounded-lg bg-white shadow-border-lg">
                                @forelse ($this->mahasiswaSearchResults as $mhs)
                                    <button
                                        type="button"
                                        wire:click="selectMahasiswaOption({{ $mhs->id }})"
                                        class="block w-full px-3 py-2 text-left text-sm transition hover:bg-neutral-100"
                                    >
                                        <span class="font-medium text-neutral-900">{{ $mhs->nim }}</span>
                                        <span class="text-neutral-600"> - {{ $mhs->nama }}</span>
                                    </button>
                                @empty
                                    <p class="px-3 py-2 text-sm text-neutral-500">Tidak ada hasil.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                    @error('selectedMahasiswaId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                @else
                    <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-700">Detail Mahasiswa</h3>
                            <button type="button" wire:click="clearSelectedMahasiswa" class="text-xs font-medium text-neutral-500 transition hover:text-neutral-900">
                                Ganti
                            </button>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <span class="text-xs font-medium text-neutral-500">NIM</span>
                                <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->nim }}</p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-neutral-500">Nama</span>
                                <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->nama }}</p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-neutral-500">Prodi</span>
                                <p class="text-sm font-semibold text-neutral-900">
                                    {{ $this->selectedMahasiswa->prodi->nama ?? '—' }}
                                    {{ $this->selectedMahasiswa->prodi->kode ? '('.$this->selectedMahasiswa->prodi->kode.')' : '' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-neutral-500">Kelas Mahasiswa</span>
                                <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswa->kelompok_kelas->nama ?? '—' }}</p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-neutral-500">Dosen Wali</span>
                                <p class="text-sm font-semibold text-neutral-900">{{ $this->selectedMahasiswaDosenWali }}</p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-neutral-500">Semester Masuk</span>
                                <p class="text-sm font-semibold text-neutral-900">
                                    {{ $this->selectedMahasiswa->semester_masuk ? $this->selectedMahasiswa->semester_masuk->nama.' ('.$this->selectedMahasiswa->semester_masuk->kode.')' : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if ($selectedMahasiswaId)
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-neutral-700">Daftar KRS</h2>
                    @foreach ($krs as $index => $row)
                        <div class="rounded-2xl bg-white p-6 shadow-border" wire:key="krs-row-{{ $index }}">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-neutral-700">KRS Ke-{{ $index + 1 }}</h3>
                                @if (count($krs) > 1)
                                    <button
                                        type="button"
                                        wire:click="removeRow({{ $index }})"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                    >
                                        <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kelas *</label>
                                    <x-searchable-select
                                        :model="'krs.'.$index.'.id_kelas'"
                                        :options="$this->kelasOptions"
                                        placeholder="— Pilih kelas —"
                                    />
                                    @error('krs.'.$index.'.id_kelas') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                                    <select wire:model="krs.{{ $index }}.status" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                                        <option value="pending">Pending</option>
                                        <option value="acc">Acc/Approved</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="flex justify-center">
                        <button
                            type="button"
                            wire:click="addRow"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                        >
                            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                            Tambah KRS
                        </button>
                    </div>
                </div>
            @endif
        @endif

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $cancelUrl }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            @if ($krsId || $selectedMahasiswaId)
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                    <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                    Simpan
                </button>
            @endif
        </div>
    </form>
</div>
