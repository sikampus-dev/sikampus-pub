@section('title', 'Kelas — ' . config('app.name'))
@section('header_title', 'Kelas')
@section('header_subtitle', 'Data kelas kuliah per mata kuliah dan semester')
@section('header_icon', 'presentation')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Kelas'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ route('admin.akademik.kelas.template') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
        Download Template
    </a>
    <a
        href="{{ route('admin.akademik.kelas.import') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="upload" class="h-4 w-4" aria-hidden="true"></i>
        Import Kelas
    </a>
    <a
        href="{{ route('admin.akademik.kelas.create') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
    >
        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
        Tambah Kelas
    </a>
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i data-lucide="alert-circle" class="h-5 w-5 shrink-0 text-rose-600" aria-hidden="true"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Merangkum SELURUH kelas yang cocok filter/pencarian saat ini (lihat
         Kelas\Index::statistikRingkasan()), bukan cuma baris yang tampil di halaman pagination
         aktif. --}}
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900">Ringkasan</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                <span class="text-xs font-medium text-neutral-500">Jumlah Mata Kuliah</span>
                <p class="text-2xl font-bold text-neutral-900">{{ $statistik['jumlah_mata_kuliah'] }}</p>
            </div>
            <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                <span class="text-xs font-medium text-neutral-500">Total SKS</span>
                <p class="text-2xl font-bold text-neutral-900">{{ $statistik['total_sks'] }}</p>
            </div>
            <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                <span class="text-xs font-medium text-neutral-500">Total Mahasiswa</span>
                <p class="text-2xl font-bold text-neutral-900">{{ $statistik['total_mahasiswa'] }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-4 border-b border-neutral-200 p-4">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama/kode mata kuliah, prodi, atau dosen PIC..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Angkatan</label>
                    {{-- Angkatan disimpan sebagai baris Semester juga (Kelas::angkatan() -> belongsTo
                         Semester), jadi opsinya memakai $semesterOptions yang sama dengan filter
                         Semester di atas, bukan master data terpisah. --}}
                    <x-searchable-select
                        model="filterAngkatan"
                        :live="true"
                        :options="$semesterOptions"
                        optionLabel="label"
                        placeholder="Semua angkatan"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Kelas Mahasiswa</label>
                    {{-- wire:key terikat filterProdi: x-searchable-select memakai wire:ignore, jadi
                         kalau prodi berganti elemen ini harus benar-benar diganti (bukan di-patch)
                         supaya opsi kelas mahasiswa yang baru (hasil filter prodi) ikut termuat. --}}
                    <x-searchable-select
                        wire:key="filter-kelompok-kelas-select-{{ $filterProdi }}"
                        model="filterKelompokKelas"
                        :live="true"
                        :options="$kelompokKelasOptions"
                        placeholder="Semua kelas mahasiswa"
                    />
                </div>
            </div>
        </div>

        <div class="border-b border-neutral-200 px-4 py-3">
            <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                <input
                    type="checkbox"
                    wire:model.live="showTrashed"
                    class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                />
                Tampilkan kelas yang sudah dihapus
            </label>
        </div>

        {{-- wire:target menyebut nama properti filter/pencarian secara eksplisit, bukan dibiarkan
             kosong: kalau kosong, wire:loading ikut menyala untuk request LAIN dari komponen ini
             (mis. hapus satu baris, atau pindah halaman pagination), padahal yang diminta cuma
             indikator untuk filter dan pencarian. Sama seperti pola di Krs\Index/Nilai\Index. --}}
        <div
            wire:loading.flex
            wire:target="search, filterProdi, filterSemester, filterKelompokKelas, filterAngkatan, showTrashed"
            class="items-center gap-2 border-b border-neutral-200 bg-neutral-50 px-4 py-2 text-xs font-medium text-neutral-500"
        >
            <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin" aria-hidden="true"></i>
            Memuat data...
        </div>

        <div
            class="overflow-x-auto"
            wire:loading.class="opacity-50 pointer-events-none"
            wire:target="search, filterProdi, filterSemester, filterKelompokKelas, filterAngkatan, showTrashed"
        >
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Mata Kuliah</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Kel. Kelas</th>
                        <th class="px-4 py-3">Angkatan</th>
                        <th class="px-4 py-3">Sem. Kuliah</th>
                        <th class="px-4 py-3 text-center">Jml. Pertemuan</th>
                        <th class="px-4 py-3 text-center">Jml. Mahasiswa</th>
                        <th class="px-4 py-3">Dosen PIC</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($kelasList as $kelas)
                        <tr wire:key="kelas-{{ $kelas->id }}" class="{{ $kelas->trashed() ? 'bg-neutral-50 text-neutral-500' : '' }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">
                                    {{ $kelas->kurikulumMatkul?->matkul?->kode ? "{$kelas->kurikulumMatkul->matkul->kode} - " : '' }}{{ $kelas->kurikulumMatkul?->matkul?->nama ?? '—' }}
                                </div>
                                @if ($kelas->kurikulumMatkul?->matkul?->sks !== null)
                                    <div class="text-xs text-neutral-500">SKS: {{ $kelas->kurikulumMatkul->matkul->sks }}</div>
                                @endif
                                @if ($kelas->kode)
                                    <div class="text-xs text-neutral-500">Kode: {{ $kelas->kode }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $kelas->prodi ? ($kelas->prodi->jenjang?->kode ? "{$kelas->prodi->nama} ({$kelas->prodi->jenjang->kode})" : $kelas->prodi->nama) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $kelas->kelompokKelas?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $kelas->angkatan ? "{$kelas->angkatan->nama}" . ($kelas->angkatan->kode ? " ({$kelas->angkatan->kode})" : '') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-neutral-600 tabular-nums">
                                {{ $kelas->semester_kuliah_ke !== null ? "Ke-{$kelas->semester_kuliah_ke}" : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center text-neutral-600 tabular-nums">{{ $kelas->jadwal_count }}</td>
                            <td class="px-4 py-3 text-center text-neutral-600 tabular-nums">{{ $kelas->jumlah_mahasiswa }}</td>
                            <td class="px-4 py-3 text-neutral-900">{{ $kelas->dosenPic?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($kelas->trashed())
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700">
                                        Dihapus
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kelas->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600' }}">
                                        {{ $kelas->is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if ($kelas->trashed())
                                        <button
                                            type="button"
                                            wire:click="restore({{ $kelas->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-700"
                                            title="Pulihkan"
                                        >
                                            <i data-lucide="rotate-ccw" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmForceDelete({{ $kelas->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 hover:text-rose-800"
                                            title="Hapus Permanen"
                                        >
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    @else
                                        <a
                                            href="{{ route('admin.akademik.kelas.show', $kelas->id) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Lihat detail"
                                        >
                                            <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                        <a
                                            href="{{ route('admin.akademik.kelas.edit', $kelas->id) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Ubah"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $kelas->id }})"
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
                            <td colspan="10" class="px-4 py-10 text-center text-neutral-500">Belum ada data kelas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $kelasList->links() }}
        </div>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus kelas?</h3>
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

    @if ($confirmingForceDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus permanen kelas?</h3>
                <p class="mt-2 text-sm text-neutral-600">Data akan benar-benar dihapus dari database dan tidak bisa dipulihkan lagi — berbeda dari hapus biasa. Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelForceDelete"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="forceDeleteKelas"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700"
                    >
                        Hapus Permanen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
