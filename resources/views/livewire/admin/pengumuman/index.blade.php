@section('title', 'Pengumuman — ' . config('app.name'))
@section('header_title', 'Pengumuman')
@section('header_subtitle', 'Kelola pengumuman untuk mahasiswa, dosen, staff, dan alumni')
@section('header_icon', 'megaphone')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Pengumuman'],
    ]])
@endsection

@section('page_actions')
    @if (\App\Support\PanelAccess::can(auth()->user(), 'pengumuman', 'create'))
        <a
            href="{{ route('admin.administrasi.pengumuman.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Tambah Pengumuman
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

    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-4 border-b border-neutral-200 p-4">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari judul atau isi..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Audien</label>
                    <x-searchable-select
                        model="filterAudien"
                        :live="true"
                        :options="['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen', 'staff' => 'Staff', 'alumni' => 'Alumni']"
                        placeholder="Semua audien"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Prioritas</label>
                    <x-searchable-select
                        model="filterPrioritas"
                        :live="true"
                        :options="['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi']"
                        placeholder="Semua prioritas"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Status</label>
                    <x-searchable-select
                        model="filterStatus"
                        :live="true"
                        :options="['aktif' => 'Aktif', 'akan_datang' => 'Akan Datang', 'selesai' => 'Selesai']"
                        placeholder="Semua status"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Judul</th>
                        <th class="px-4 py-3">Audien</th>
                        <th class="px-4 py-3">Prioritas</th>
                        <th class="px-4 py-3">Tanggal Mulai</th>
                        <th class="px-4 py-3">Tanggal Selesai</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($pengumumanList as $pengumuman)
                        <tr wire:key="pengumuman-{{ $pengumuman->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $pengumuman->judul }}</div>
                                <div class="mt-0.5 line-clamp-2 text-xs text-neutral-500">{{ $pengumuman->isi }}</div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pengumuman->audien ? ucfirst($pengumuman->audien) : 'Semua' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $prioritasLabel = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'][$pengumuman->prioritas] ?? '—';
                                    $prioritasClass = [
                                        'low' => 'bg-neutral-100 text-neutral-700',
                                        'medium' => 'bg-amber-100 text-amber-700',
                                        'high' => 'bg-rose-100 text-rose-700',
                                    ][$pengumuman->prioritas] ?? 'bg-neutral-100 text-neutral-500';
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $prioritasClass }}">
                                    {{ $prioritasLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pengumuman->tanggal_mulai?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pengumuman->tanggal_selesai?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'pengumuman', 'update'))
                                        <a
                                            href="{{ route('admin.administrasi.pengumuman.edit', $pengumuman->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Ubah"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'pengumuman', 'delete'))
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $pengumuman->id }})"
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
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada data pengumuman.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $pengumumanList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus pengumuman?</h3>
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
