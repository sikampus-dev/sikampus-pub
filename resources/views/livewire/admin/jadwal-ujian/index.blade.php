@section('title', 'Jadwal Ujian — ' . config('app.name'))
@section('header_title', 'Jadwal Ujian')
@section('header_subtitle', 'Jadwal UTS/UAS/Praktikum per kelas')
@section('header_icon', 'clipboard-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Jadwal Ujian'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.akademik.jadwal-ujian.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah Jadwal Ujian
    </a>
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
                    placeholder="Cari nama/kode mata kuliah..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Prodi</label>
                    <x-searchable-select
                        model="filterProdi"
                        :live="true"
                        :options="$prodiOptions"
                        optionLabel="label"
                        placeholder="Semua prodi"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
                    <x-searchable-select
                        model="filterSemester"
                        :live="true"
                        :options="$semesterOptions"
                        optionLabel="label"
                        placeholder="Semua semester"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Kelas</label>
                    {{-- wire:key terikat filterProdi/filterSemester: x-searchable-select memakai
                         wire:ignore, jadi kalau prodi/semester berganti elemen ini harus benar-benar
                         diganti (bukan di-patch) supaya opsi kelas yang baru ikut termuat. --}}
                    <x-searchable-select
                        wire:key="filter-kelas-select-{{ $filterProdi }}-{{ $filterSemester }}"
                        model="filterKelas"
                        :live="true"
                        :options="$kelasOptions"
                        optionLabel="label"
                        placeholder="Semua kelas"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Mata Kuliah</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Ruangan</th>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($ujianList as $ujian)
                        <tr wire:key="ujian-{{ $ujian->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">
                                    {{ $ujian->kelas?->kurikulumMatkul?->matkul?->kode ? "{$ujian->kelas->kurikulumMatkul->matkul->kode} - " : '' }}{{ $ujian->kelas?->kurikulumMatkul?->matkul?->nama ?? '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $ujian->kelas?->prodi ? ($ujian->kelas->prodi->jenjang?->kode ? "{$ujian->kelas->prodi->nama} ({$ujian->kelas->prodi->jenjang->kode})" : $ujian->kelas->prodi->nama) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $ujian->semester ? "{$ujian->semester->nama} ({$ujian->semester->kode})" : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                    {{ ucfirst(strtolower($ujian->jenis_ujian)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $ujian->ruangan?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">
                                @if ($ujian->tanggal_mulai)
                                    {{ $ujian->tanggal_mulai->format('d M Y H:i') }}
                                    @if ($ujian->tanggal_selesai)
                                        – {{ $ujian->tanggal_selesai->format('d M Y H:i') }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('admin.akademik.jadwal-ujian.show', $ujian->id) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Lihat detail"
                                    >
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    <a
                                        href="{{ route('admin.akademik.jadwal-ujian.edit', $ujian->id) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
                                        class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                        title="Ubah"
                                    >
                                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="confirmDelete({{ $ujian->id }})"
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
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Belum ada data jadwal ujian.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $ujianList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus jadwal ujian?</h3>
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
