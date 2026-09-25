@section('title', 'KTM — ' . config('app.name'))
@section('header_title', 'KTM')
@section('header_subtitle', 'Kartu Tanda Mahasiswa')
@section('header_icon', 'id-card')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Mahasiswa', 'route' => route('admin.administrasi.mahasiswa')],
        ['label' => 'KTM'],
    ]])
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('ktm_error'))
        <div class="mb-4 flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i data-lucide="alert-circle" class="h-5 w-5 shrink-0 text-rose-600" aria-hidden="true"></i>
            <span>{{ session('ktm_error') }}</span>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-neutral-200">
        <nav class="-mb-px flex flex-wrap gap-6">
            @foreach ([['key' => 'data', 'label' => 'Data KTM'], ['key' => 'template', 'label' => 'Template KTM'], ['key' => 'header', 'label' => 'Pengaturan Tampilan']] as $tab)
                <button
                    type="button"
                    wire:click="setTab('{{ $tab['key'] }}')"
                    class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition {{ $activeTab === $tab['key'] ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab: Data KTM --}}
    @if ($activeTab === 'data')
        <div class="mb-4 flex justify-end">
            <a
                href="{{ route('admin.administrasi.ktm.create') }}"
                class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
            >
                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                Tambah KTM
            </a>
        </div>

        <div class="rounded-2xl bg-white shadow-border">
            <div class="space-y-4 border-b border-neutral-200 p-4">
                <div class="relative">
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Cari nama atau NIM..."
                        class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                    />
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-neutral-700">Status</label>
                        <x-searchable-select
                            model="filterStatus"
                            :live="true"
                            :options="$this->statusOptions"
                            placeholder="Semua Status"
                        />
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">NIM</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Prodi</th>
                            <th class="px-4 py-3">Nomor KTM</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($ktmList as $ktm)
                            <tr wire:key="ktm-{{ $ktm->id }}">
                                <td class="px-4 py-3 text-neutral-600">{{ $ktm->mahasiswa->nim ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $ktm->mahasiswa->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $ktm->mahasiswa->prodi->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $ktm->nomor_ktm ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($ktm->file)
                                        <a href="{{ asset('storage/'.$ktm->file) }}" target="_blank" class="text-sm font-medium text-sky-600 hover:underline">
                                            Lihat
                                        </a>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ktm->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600' }}">
                                        {{ $ktm->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button
                                            type="button"
                                            wire:click="confirmRegenerate({{ $ktm->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Buat Ulang Gambar"
                                        >
                                            <i data-lucide="refresh-cw" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                        <a
                                            href="{{ route('admin.administrasi.ktm.edit', $ktm->id) }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Ubah"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $ktm->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                            title="Hapus"
                                        >
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Belum ada data KTM.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-neutral-200 p-4">
                {{ $ktmList->links() }}
            </div>
        </div>
    @endif

    {{-- Tab: Template KTM --}}
    @if ($activeTab === 'template')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-2 text-sm font-semibold text-neutral-900">Template Gambar KTM</h3>
            <p class="mb-4 text-sm text-neutral-500">
                Template ini dipakai untuk membuat gambar KTM setiap mahasiswa secara otomatis (NIM, nama, dan prodi
                ditimpakan di atas template). Ukuran template: {{ config('ktm.template_width', 800) }}&times;{{ config('ktm.template_height', 457) }}px.
            </p>

            <div class="flex flex-col items-start gap-6 sm:flex-row">
                <div class="flex h-40 w-72 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-neutral-100">
                    @if ($templateFile)
                        <img src="{{ $templateFile->temporaryUrl() }}" alt="" class="h-full w-full object-contain" />
                    @elseif ($this->currentTemplateUrl)
                        <img src="{{ $this->currentTemplateUrl }}" alt="" class="h-full w-full object-contain" />
                    @else
                        <i data-lucide="image" class="h-10 w-10 text-neutral-400" aria-hidden="true"></i>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">
                        {{ $this->currentTemplateUrl ? 'Ganti Template' : 'Unggah Template' }}
                    </label>
                    <input
                        type="file"
                        wire:model="templateFile"
                        accept="image/png,image/jpeg,image/gif,image/webp"
                        class="block w-full text-sm text-neutral-600 file:mr-4 file:rounded-lg file:border-0 file:bg-neutral-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-neutral-800"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">JPG, PNG, GIF, atau WebP, maksimal 5MB.</p>
                    @error('templateFile') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="templateFile" class="mt-1.5 text-xs text-neutral-500">Mengunggah…</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Tab: Pengaturan Tampilan --}}
    @if ($activeTab === 'header')
        <form wire:submit="saveDisplaySettings" class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-2 text-sm font-semibold text-neutral-900">Gaya Header KTM</h3>
            <p class="mb-6 text-sm text-neutral-500">
                Mengatur tampilan judul "Kartu Tanda Mahasiswa" dan nama perguruan tinggi di bagian atas kartu.
                Perubahan hanya berlaku untuk gambar KTM yang dibuat/dibuat ulang setelah pengaturan ini disimpan.
            </p>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div class="space-y-4 rounded-xl border border-neutral-100 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Judul "Kartu Tanda Mahasiswa"</p>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Warna Teks</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="headerTitleColor" class="h-10 w-14 shrink-0 cursor-pointer rounded-lg shadow-border" />
                            <input type="text" wire:model="headerTitleColor" maxlength="7" class="w-full rounded-lg px-3 py-2.5 text-sm uppercase outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border @error('headerTitleColor') ring-2 ring-red-500 @enderror" />
                        </div>
                        @error('headerTitleColor') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ukuran Font (px)</label>
                        <input type="number" wire:model="headerTitleSize" min="8" max="120" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border @error('headerTitleSize') ring-2 ring-red-500 @enderror" />
                        @error('headerTitleSize') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-neutral-100 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Nama Perguruan Tinggi</p>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Warna Teks</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="headerUnivColor" class="h-10 w-14 shrink-0 cursor-pointer rounded-lg shadow-border" />
                            <input type="text" wire:model="headerUnivColor" maxlength="7" class="w-full rounded-lg px-3 py-2.5 text-sm uppercase outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border @error('headerUnivColor') ring-2 ring-red-500 @enderror" />
                        </div>
                        @error('headerUnivColor') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ukuran Font (px)</label>
                        <input type="number" wire:model="headerUnivSize" min="8" max="120" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border @error('headerUnivSize') ring-2 ring-red-500 @enderror" />
                        @error('headerUnivSize') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-6 max-w-xs">
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Perataan Header</label>
                <select wire:model="headerAlign" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border @error('headerAlign') ring-2 ring-red-500 @enderror">
                    <option value="left">Kiri</option>
                    <option value="center">Tengah</option>
                    <option value="right">Kanan</option>
                </select>
                @error('headerAlign') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-8 border-t border-neutral-100 pt-6">
                <h3 class="mb-2 text-sm font-semibold text-neutral-900">Ukuran Font Body</h3>
                <p class="mb-4 text-sm text-neutral-500">
                    Mengatur ukuran font baris NIM, Nama, dan Prodi mahasiswa. Nama/Prodi yang terlalu panjang akan
                    tetap turun ke baris berikutnya secara otomatis mengikuti lebar kartu.
                </p>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ukuran Font NIM (px)</label>
                        <input type="number" wire:model="bodyNimSize" min="6" max="80" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border @error('bodyNimSize') ring-2 ring-red-500 @enderror" />
                        @error('bodyNimSize') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ukuran Font Nama (px)</label>
                        <input type="number" wire:model="bodyNamaSize" min="6" max="80" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border @error('bodyNamaSize') ring-2 ring-red-500 @enderror" />
                        @error('bodyNamaSize') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ukuran Font Prodi (px)</label>
                        <input type="number" wire:model="bodyProdiSize" min="6" max="80" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border @error('bodyProdiSize') ring-2 ring-red-500 @enderror" />
                        @error('bodyProdiSize') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
                >
                    <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    @endif

    {{-- Modal: Konfirmasi Hapus --}}
    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus data KTM ini?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="delete" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Konfirmasi Regenerate --}}
    @if ($confirmingRegenerateId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Buat ulang gambar KTM?</h3>
                <p class="mt-2 text-sm text-neutral-600">Gambar KTM akan dibuat ulang dari template dan data mahasiswa saat ini.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelRegenerate" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="regenerate" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">
                        Buat Ulang
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
