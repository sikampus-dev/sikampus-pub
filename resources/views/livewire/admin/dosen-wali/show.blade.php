@php
    $dosen = $this->dosen;
    $namaLengkap = trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : ''));
@endphp

@section('title', 'Mahasiswa Bimbingan — ' . config('app.name'))
@section('header_title', 'Mahasiswa Bimbingan')
@section('header_subtitle', $namaLengkap . ($dosen->kode_dosen ? ' ('.$dosen->kode_dosen.')' : ''))
@section('header_icon', 'users')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Dosen', 'route' => route('admin.administrasi.dosen')],
        ['label' => 'Dosen Wali', 'route' => route('admin.administrasi.dosen-wali')],
        ['label' => $namaLengkap],
    ]])
@endsection

{{--
    Hanya link navigasi biasa yang boleh masuk ke page_actions — section itu dirender oleh
    layouts.web DI LUAR root komponen Livewire (lihat @yield('page_actions') vs @yield('content')
    di layouts/web.blade.php), jadi wire:click di sini tidak akan pernah terikat. Tombol "Tambah
    Mahasiswa Bimbingan" karena itu dipindah ke toolbar kartu di dalam <div> root di bawah.
--}}
@section('page_actions')
    <a
        href="{{ $backUrl }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="mb-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
        Prodi (Kaprodi): <span class="font-medium text-neutral-900">{{ $dosen->prodiAsKaprodi->nama ?? '—' }}</span>
        &middot;
        Kuota bimbingan akademik: <span class="font-medium text-neutral-900">{{ $dosen->kuota_bimbingan_akademik ?? 0 }}</span>
    </div>

    @if (\App\Support\PanelAccess::can(auth()->user(), 'dosen wali', 'update'))
        <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
            <button
                type="button"
                wire:click="openModal"
                class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
            >
                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                Tambah Mahasiswa Bimbingan
            </button>
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-border">
        <div class="flex flex-wrap items-center gap-3 border-b border-neutral-200 p-4">
            <div class="relative flex-1 min-w-[200px]">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari NIM atau nama mahasiswa..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
            
        </div>

        @php $bimbinganList = $this->bimbinganList; @endphp

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($bimbinganList as $item)
                        <tr wire:key="bimbingan-{{ $item->id }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $item->mahasiswa->nim ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $item->mahasiswa->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $item->mahasiswa->prodi->nama ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $item->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-700' }}">
                                    {{ $item->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('admin.administrasi.dosen-wali.riwayat', ['id' => $dosen->id, 'dosenWaliId' => $item->id]) }}{{ $riwayatQuery ? '?'.$riwayatQuery : '' }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Riwayat bimbingan"
                                    >
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'dosen wali', 'delete'))
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $item->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                            title="Hapus"
                                        >
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-neutral-500">
                                {{ $search ? 'Tidak ada hasil pencarian.' : 'Belum ada mahasiswa bimbingan.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $bimbinganList->links() }}
        </div>
    </div>

    {{-- Modal: Tambah Mahasiswa Bimbingan --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Tambah Mahasiswa Bimbingan</h3>
                    <button type="button" wire:click="closeModal" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4 p-6">
                    @if ($lastAddedLabel !== '')
                        <div class="flex gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800">
                            <i data-lucide="check-circle" class="h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true"></i>
                            <span><span class="font-medium">{{ $lastAddedLabel }}</span> berhasil ditambahkan. Pilih mahasiswa berikutnya atau tutup modal.</span>
                        </div>
                    @endif

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Mahasiswa *</label>

                        @if ($selectedMahasiswaId)
                            <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5 text-sm shadow-border">
                                <span class="font-medium text-neutral-900">{{ $selectedMahasiswaLabel }}</span>
                                <button type="button" wire:click="$set('selectedMahasiswaId', null)" class="text-neutral-400 transition hover:text-neutral-600">
                                    <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                                </button>
                            </div>
                        @else
                            <div class="relative">
                                <input
                                    type="text"
                                    x-init="$nextTick(() => $el.focus())"
                                    wire:model.live.debounce.300ms="mahasiswaSearch"
                                    placeholder="Cari NIM atau nama mahasiswa..."
                                    class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('selectedMahasiswaId') ring-2 ring-red-500 @enderror shadow-border"
                                />
                                @if ($mahasiswaSearch !== '')
                                    <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg bg-white shadow-border-lg">
                                        @forelse ($this->mahasiswaResults as $m)
                                            <button
                                                type="button"
                                                @if ($m->has_dosen_wali) disabled @else
                                                    wire:click="selectMahasiswa({{ $m->id }}, '{{ addslashes(trim(($m->nim ?? '').' - '.$m->nama)) }}')"
                                                @endif
                                                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm transition {{ $m->has_dosen_wali ? 'cursor-not-allowed bg-neutral-50 text-neutral-400' : 'hover:bg-neutral-50' }}"
                                            >
                                                <span>
                                                    <span class="font-medium {{ $m->has_dosen_wali ? '' : 'text-neutral-900' }}">{{ $m->nim ?? '—' }}</span>
                                                    <span class="text-neutral-500"> — {{ $m->nama }}</span>
                                                </span>
                                                @if ($m->has_dosen_wali)
                                                    <span class="shrink-0 text-xs text-rose-500" title="Sudah punya dosen wali">
                                                        <i data-lucide="x-circle" class="h-4 w-4" aria-hidden="true"></i>
                                                    </span>
                                                @endif
                                            </button>
                                        @empty
                                            <p class="px-3 py-2 text-sm text-neutral-500">Tidak ada hasil.</p>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @endif
                        <p class="mt-1.5 text-xs text-neutral-500">Mahasiswa yang sudah punya dosen wali tidak dapat dipilih.</p>
                        @error('selectedMahasiswaId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3 border-t border-neutral-200 pt-4">
                        <button type="button" wire:click="closeModal" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                            {{ $lastAddedLabel !== '' ? 'Selesai' : 'Batal' }}
                        </button>
                        <button type="submit" class="flex-1 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Konfirmasi Hapus --}}
    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus mahasiswa dari daftar bimbingan?</h3>
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
</div>
