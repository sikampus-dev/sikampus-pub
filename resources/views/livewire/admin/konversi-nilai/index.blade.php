@section('title', 'Konversi Nilai — ' . config('app.name'))
@section('header_title', 'Konversi Nilai')
@section('header_subtitle', 'Mahasiswa yang memiliki data konversi nilai beserta ringkasan jumlah mata kuliah dan SKS')
@section('header_icon', 'repeat')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Konversi Nilai'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.akademik.konversi-nilai.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah
    </a>
@endsection

<div>
    <div class="rounded-2xl bg-white shadow-border">
        <div class="grid grid-cols-1 gap-3 border-b border-neutral-200 p-4 sm:grid-cols-2">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari NIM atau nama..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-neutral-700">Prodi</label>
                <x-searchable-select
                    model="filterProdi"
                    :options="$this->prodiOptions"
                    placeholder="Semua prodi"
                    :live="true"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Mahasiswa</th>
                        <th class="px-4 py-3">Program Studi</th>
                        <th class="px-4 py-3 text-center">Jumlah MK</th>
                        <th class="px-4 py-3 text-center">Total SKS (lama)</th>
                        <th class="px-4 py-3 text-center">Total SKS (baru)</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($konversiNilaiList as $row)
                        <tr wire:key="konversi-mhs-{{ $row->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">{{ $row->nama }}</div>
                                <div class="text-xs text-neutral-500">{{ $row->nim }}</div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                @if ($row->prodi)
                                    {{ $row->prodi->kode ? $row->prodi->kode.' · ' : '' }}{{ $row->prodi->nama }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center font-medium text-neutral-900">{{ (int) $row->jumlah_matkul }}</td>
                            <td class="px-4 py-3 text-center text-neutral-700">{{ (int) $row->total_sks_lama }}</td>
                            <td class="px-4 py-3 text-center font-medium text-emerald-700">{{ (int) $row->total_sks_baru }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.akademik.konversi-nilai.show', $row->id) }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Lihat Rincian"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada mahasiswa dengan data konversi nilai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $konversiNilaiList->links() }}
        </div>
    </div>
</div>
