@section('title', 'Detail KRS — ' . config('app.name'))
@section('header_title', 'Detail KRS')
@section('header_subtitle', $this->mahasiswa->nama)
@section('header_icon', 'clipboard-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'KRS', 'route' => $backUrl],
        ['label' => $this->mahasiswa->nama],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ $backUrl }}"
        class="print:hidden inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div class="space-y-6">
    @if (session('status'))
        <div class="print:hidden flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="print:hidden flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i data-lucide="alert-circle" class="h-5 w-5 shrink-0 text-rose-600" aria-hidden="true"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900">Detail Mahasiswa</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div>
                <span class="text-xs font-medium text-neutral-500">NIM</span>
                <p class="text-sm font-semibold text-neutral-900">{{ $this->mahasiswa->nim }}</p>
            </div>
            <div>
                <span class="text-xs font-medium text-neutral-500">Nama</span>
                <p class="text-sm font-semibold text-neutral-900">{{ $this->mahasiswa->nama }}</p>
            </div>
            <div>
                <span class="text-xs font-medium text-neutral-500">Prodi</span>
                <p class="text-sm font-semibold text-neutral-900">
                    {{ $this->mahasiswa->prodi->nama ?? '—' }}
                    {{ $this->mahasiswa->prodi->kode ? '('.$this->mahasiswa->prodi->kode.')' : '' }}
                </p>
            </div>
            <div>
                <span class="text-xs font-medium text-neutral-500">Dosen Wali</span>
                <p class="text-sm font-semibold text-neutral-900">{{ $this->dosenWali }}</p>
            </div>
            <div>
                <span class="text-xs font-medium text-neutral-500">Semester Masuk</span>
                <p class="text-sm font-semibold text-neutral-900">
                    {{ $this->mahasiswa->semester_masuk ? $this->mahasiswa->semester_masuk->nama.' ('.$this->mahasiswa->semester_masuk->kode.')' : '—' }}
                </p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900">Ringkasan</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                <span class="text-xs font-medium text-neutral-500">Total KRS</span>
                <p class="text-2xl font-bold text-neutral-900">{{ $this->summary['total_krs'] }}</p>
            </div>
            <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                <span class="text-xs font-medium text-neutral-500">SKS Diajukan</span>
                <p class="text-2xl font-bold text-neutral-900">{{ $this->summary['sks_diajukan'] }}</p>
            </div>
            <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                <span class="text-xs font-medium text-neutral-500">SKS Di-acc</span>
                <p class="text-2xl font-bold text-neutral-900">{{ $this->summary['sks_diacc'] }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-neutral-900">Daftar KRS</h2>
            <div class="print:hidden flex items-center gap-3">
                <div class="w-56">
                    <x-searchable-select
                        model="filterSemester"
                        :options="$this->semesterOptions"
                        placeholder="Semua Semester"
                        :live="true"
                    />
                </div>
                <a
                    href="{{ route('admin.akademik.krs.cetak', $mahasiswaId) }}{{ $filterSemester !== '' ? '?id_semester='.$filterSemester : '' }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
                >
                    <i data-lucide="printer" class="h-4 w-4" aria-hidden="true"></i>
                    Cetak
                </a>
            </div>
        </div>

        <div class="print:hidden mb-4 flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                <input
                    type="checkbox"
                    wire:model.live="showTrashed"
                    class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                />
                Tampilkan KRS yang sudah dihapus
            </label>

            @if ($selected !== [])
                <button
                    type="button"
                    wire:click="confirmBulkDelete"
                    class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700"
                >
                    <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                    Hapus {{ count($selected) }} KRS terpilih
                </button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="print:hidden px-4 py-3 w-10">
                            <input
                                type="checkbox"
                                wire:click="toggleSelectAll"
                                @checked($this->krsList->isNotEmpty() && count($selected) === $this->krsList->count())
                                @disabled($this->krsList->isEmpty())
                                class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                                title="Pilih semua"
                            />
                        </th>
                        <th class="px-4 py-3">Mata Kuliah</th>
                        <th class="px-4 py-3">Kode Kelas</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3 text-center">SKS</th>
                        <th class="px-4 py-3">Dosen Pengampu</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="print:hidden px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->krsList as $krs)
                        @php
                            $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;
                            $isApproved = $krs->approved_at !== null;
                        @endphp
                        <tr wire:key="krs-{{ $krs->id }}" class="{{ $krs->trashed() ? 'bg-neutral-50 text-neutral-500' : '' }}">
                            <td class="print:hidden px-4 py-3">
                                <input
                                    type="checkbox"
                                    value="{{ $krs->id }}"
                                    wire:model.live="selected"
                                    class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                                    title="{{ $krs->trashed() ? 'Pilih untuk dihapus permanen' : 'Pilih untuk dihapus' }}"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900">
                                    {{ $matkul?->kode ? $matkul->kode.' - ' : '' }}{{ $matkul?->nama ?? '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ filled($krs->kelas?->kode) ? $krs->kelas->kode : '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $krs->kelas?->semester ? $krs->kelas->semester->nama.' ('.$krs->kelas->semester->kode.')' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center text-neutral-600">{{ $matkul->sks ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $krs->kelas?->dosenPic?->nama ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($krs->trashed())
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700">
                                        Dihapus
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $isApproved ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $isApproved ? 'Aktif' : 'Pending' }}
                                    </span>
                                @endif
                            </td>
                            <td class="print:hidden px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if ($krs->trashed())
                                        <button
                                            type="button"
                                            wire:click="restore({{ $krs->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-700"
                                            title="Pulihkan KRS"
                                        >
                                            <i data-lucide="rotate-ccw" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmForceDelete({{ $krs->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 hover:text-rose-800"
                                            title="Hapus permanen"
                                        >
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    @else
                                        <a
                                            href="{{ route('admin.akademik.krs.edit', $krs->id) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="Ubah"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $krs->id }})"
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
                            <td colspan="8" class="px-4 py-10 text-center text-neutral-500">Belum ada data KRS.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($confirmDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus KRS?</h3>
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

    @if ($confirmForceDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus permanen KRS?</h3>
                <p class="mt-2 text-sm text-neutral-600">KRS beserta sisa nilai, komponen, dan revisinya akan benar-benar dihapus dari database dan tidak bisa dipulihkan lagi — berbeda dari hapus biasa. Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelForceDelete" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="forceDeleteKrs" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus Permanen
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmingBulkDelete)
        @php
            $ringkasan = $this->ringkasanTerpilih;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus {{ $ringkasan['hapus'] + $ringkasan['permanen'] }} KRS terpilih?</h3>
                <div class="mt-3 space-y-2 text-sm text-neutral-600">
                    @if ($ringkasan['hapus'] > 0)
                        <p>
                            <span class="font-medium text-neutral-900">{{ $ringkasan['hapus'] }} KRS</span> akan dihapus
                            beserta nilai yang belum final, dan masih bisa dipulihkan. KRS yang sudah punya nilai final
                            akan dilewati.
                        </p>
                    @endif
                    @if ($ringkasan['permanen'] > 0)
                        <p class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800">
                            <span class="font-medium">{{ $ringkasan['permanen'] }} KRS</span> sudah dihapus sebelumnya,
                            jadi akan dihapus <span class="font-medium">permanen</span> dari database dan tidak bisa
                            dipulihkan lagi.
                        </p>
                    @endif
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelBulkDelete" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="bulkDelete" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus {{ $ringkasan['hapus'] + $ringkasan['permanen'] }} KRS
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
