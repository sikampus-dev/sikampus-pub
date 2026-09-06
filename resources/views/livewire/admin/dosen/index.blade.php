@php
    $initials = function (?string $nama) {
        return collect(explode(' ', trim((string) $nama)))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') ?: '?';
    };
@endphp

@section('title', 'Dosen — ' . config('app.name'))
@section('header_title', 'Dosen')
@section('header_subtitle', 'Data induk dosen')
@section('header_icon', 'user-round')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Dosen'],
    ]])
@endsection

@section('page_actions')
    @if (\App\Support\PanelAccess::can(auth()->user(), 'dosen', 'create'))
        <a
            href="{{ route('admin.administrasi.dosen.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Tambah Dosen
        </a>
    @endif
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{--
        Tombol Export sengaja ditaruh di dalam <div> root (bukan @section('page_actions')) —
        page_actions dirender layouts.web DI LUAR subtree yang di-morph Livewire, jadi href-nya
        tidak akan pernah ikut ter-update saat filter berubah. Di sini href dihitung ulang setiap
        render supaya file yang di-export selalu sesuai filter yang sedang dipilih.
    --}}
    <div class="mb-4 flex justify-end">
        <a
            href="{{ route('admin.administrasi.dosen.export', array_filter([
                'search' => $search !== '' ? $search : null,
            ])) }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
            Export Excel
        </a>
    </div>

    <div class="rounded-2xl bg-white shadow-border">
        <div class="flex flex-wrap items-center gap-3 border-b border-neutral-200 p-4">
            <div class="relative flex-1 min-w-[200px]">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama, email, kode, NIP, atau NIDN..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Foto</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">NIP / NIDN</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">No. HP</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($dosenList as $dosen)
                        <tr wire:key="dosen-{{ $dosen->id }}">
                            <td class="px-4 py-3">
                                @if ($dosen->foto)
                                    <img src="{{ asset('storage/'.ltrim($dosen->foto, '/')) }}" alt="{{ $dosen->nama }}" class="h-10 w-10 rounded-full object-cover ring-1 ring-neutral-200" />
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-100 text-xs font-semibold text-neutral-900 ring-1 ring-neutral-200">
                                        {{ $initials($dosen->nama) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-neutral-900">
                                {{ trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : '')) }}
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $dosen->kode_dosen ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $dosen->nip ?? $dosen->nidn ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $dosen->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $dosen->no_hp ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('admin.administrasi.dosen.show', $dosen->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Lihat"
                                    >
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'dosen', 'update'))
                                        <a
                                            href="{{ route('admin.administrasi.dosen.edit', $dosen->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Ubah"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'dosen', 'delete'))
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $dosen->id }})"
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
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Belum ada data dosen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $dosenList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus dosen?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelDelete"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="delete"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
