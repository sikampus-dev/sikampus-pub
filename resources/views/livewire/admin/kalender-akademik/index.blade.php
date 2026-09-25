@section('title', 'Kalender Akademik — ' . config('app.name'))
@section('header_title', 'Kalender Akademik')
@section('header_subtitle', 'Kelola tanggal-tanggal penting akademik per semester')
@section('header_icon', 'calendar-days')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Kalender Akademik'],
    ]])
@endsection

@section('page_actions')
    @if (\App\Support\PanelAccess::can(auth()->user(), 'kalender akademik', 'create'))
        <a
            href="{{ route('admin.akademik.kalender-akademik.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Tambah Event
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
                    placeholder="Cari nama atau deskripsi..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Kategori</label>
                    <x-searchable-select
                        model="filterKategori"
                        :live="true"
                        :options="$kategoriOptions"
                        placeholder="Semua kategori"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
                    <x-searchable-select
                        model="filterSemester"
                        :live="true"
                        :options="$semesterOptions"
                        placeholder="Semua semester"
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
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Tanggal Mulai</th>
                        <th class="px-4 py-3">Tanggal Selesai</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($eventList as $event)
                        <tr wire:key="kalender-akademik-{{ $event->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $event->nama }}</div>
                                @if ($event->deskripsi)
                                    <div class="mt-0.5 line-clamp-2 text-xs text-neutral-500">{{ $event->deskripsi }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $isGating = in_array($event->kategori, \App\Models\KalenderAkademik::KATEGORI_GATING, true);
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $isGating ? 'bg-amber-100 text-amber-700' : 'bg-neutral-100 text-neutral-700' }}">
                                    {{ $kategoriOptions[$event->kategori] ?? $event->kategori }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $event->semester ? "{$event->semester->nama} ({$event->semester->kode})" : 'Semua semester' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $event->tanggal_mulai?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $event->tanggal_selesai?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'kalender akademik', 'update'))
                                        <a
                                            href="{{ route('admin.akademik.kalender-akademik.edit', $event->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Ubah"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'kalender akademik', 'delete'))
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $event->id }})"
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
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada event kalender akademik.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $eventList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus event kalender akademik?</h3>
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
