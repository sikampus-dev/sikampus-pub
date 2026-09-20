@section('title', 'KRS — ' . config('app.name'))
@section('header_title', 'KRS')
@section('header_subtitle', 'Kelola Kartu Rencana Studi mahasiswa')
@section('header_icon', 'clipboard-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'KRS'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.akademik.krs.template') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
        Download Template
    </a>
    <a
        href="{{ route('admin.akademik.krs.import') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="upload" class="h-4 w-4" aria-hidden="true"></i>
        Import KRS
    </a>
    <a
        href="{{ route('admin.akademik.krs.create') }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah KRS
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
                    placeholder="Cari nama atau NIM mahasiswa..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Prodi</label>
                    <x-searchable-select
                        model="filterProdi"
                        :options="$this->prodiOptions"
                        placeholder="Semua Prodi"
                        :live="true"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
                    <x-searchable-select
                        model="filterSemester"
                        :options="$this->semesterOptions"
                        placeholder="Semua Semester"
                        :live="true"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Status Pengajuan</label>
                    <x-searchable-select
                        model="filterStatusPengajuan"
                        :options="$this->statusPengajuanOptions()"
                        placeholder="Semua"
                        :live="true"
                    />
                </div>
            </div>
        </div>

        @if ($belumMengajukanError !== '')
            <div class="flex gap-3 border-b border-neutral-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-amber-600" aria-hidden="true"></i>
                <span>{{ $belumMengajukanError }}</span>
            </div>
        @endif

        {{-- wire:target menyebut nama properti filter/pencarian secara eksplisit, bukan dibiarkan
             kosong: kalau kosong, wire:loading ikut menyala untuk request LAIN dari komponen ini
             (mis. pagination), padahal yang diminta cuma indikator untuk filter dan pencarian. --}}
        <div
            wire:loading.flex
            wire:target="search, filterProdi, filterSemester, filterStatusPengajuan"
            class="items-center gap-2 border-b border-neutral-200 bg-neutral-50 px-4 py-2 text-xs font-medium text-neutral-500"
        >
            <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin" aria-hidden="true"></i>
            Memuat data...
        </div>

        <div
            class="overflow-x-auto"
            wire:loading.class="opacity-50 pointer-events-none"
            wire:target="search, filterProdi, filterSemester, filterStatusPengajuan"
        >
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Kelas Mahasiswa</th>
                        <th class="px-4 py-3">Dosen Wali</th>
                        <th class="px-4 py-3 text-center">SKS Diajukan</th>
                        <th class="px-4 py-3 text-center">SKS Di-acc</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($krsList as $row)
                        <tr wire:key="krs-mhs-{{ $row['id_mahasiswa'] }}">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $row['nim'] }}</td>
                            <td class="px-4 py-3 text-neutral-900">{{ $row['nama'] }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $row['prodi_nama'] }}
                                @if ($row['jenjang_kode'])
                                    <span class="text-neutral-400">- {{ $row['jenjang_kode'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $row['kelompok_kelas_nama'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $row['dosen_wali'] }}</td>
                            <td class="px-4 py-3 text-center font-medium text-neutral-900">{{ $row['sks_diajukan'] }}</td>
                            <td class="px-4 py-3 text-center font-medium text-neutral-900">{{ $row['sks_diacc'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.akademik.krs.show', $row['id_mahasiswa']) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Lihat Detail"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-neutral-500">Belum ada data KRS.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $krsList->links() }}
        </div>
    </div>
</div>
