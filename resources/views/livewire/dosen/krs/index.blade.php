@section('title', 'Persetujuan KRS — ' . config('app.name'))
@section('header_title', 'Persetujuan KRS')
@section('header_subtitle', 'Setujui pengajuan KRS mahasiswa bimbingan Anda')

<div class="space-y-4">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="w-full max-w-md">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari berdasarkan nama atau NIM..."
                    class="w-full rounded-xl bg-white py-2 pr-4 pl-10 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
        </div>
        <div class="w-full sm:w-64">
            <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
            <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
        </div>
    </div>

    @php $rows = $this->rows; @endphp

    <div class="rounded-2xl bg-white shadow-border">
        @if ($rows->isEmpty())
            <div class="p-10 text-center">
                <i data-lucide="users" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
                <p class="font-medium text-neutral-600">Tidak ada mahasiswa bimbingan</p>
                <p class="mt-1 text-sm text-neutral-500">
                    {{ $search !== '' ? 'Tidak ada mahasiswa bimbingan yang cocok dengan pencarian.' : 'Anda belum memiliki mahasiswa bimbingan.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <th class="px-6 py-3">No</th>
                            <th class="px-6 py-3">Nama</th>
                            <th class="px-6 py-3">Prodi</th>
                            <th class="px-6 py-3">Angkatan/Semester Masuk</th>
                            <th class="px-6 py-3 text-center">Jumlah Mata Kuliah</th>
                            <th class="px-6 py-3 text-center">Persentase Di-ACC</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @foreach ($rows as $idx => $mhs)
                            @php
                                $stat = $mhs->statistik_krs;
                                $persenClass = $stat['persentase_diacc'] == 100 ? 'text-emerald-600' : ($stat['persentase_diacc'] > 0 ? 'text-amber-600' : 'text-neutral-600');
                            @endphp
                            <tr wire:key="mhs-{{ $mhs->id }}" class="hover:bg-neutral-50/70">
                                <td class="px-6 py-4 text-neutral-900">{{ $rows->firstItem() + $idx }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-neutral-900">{{ $mhs->nama }}</div>
                                    <div class="mt-1 text-xs text-neutral-500">{{ $mhs->nim }}</div>
                                </td>
                                <td class="px-6 py-4 text-neutral-600">
                                    @if ($mhs->prodi)
                                        <div>{{ $mhs->prodi->nama }}</div>
                                        @if ($mhs->prodi->jenjang)
                                            <div class="text-xs text-neutral-500">{{ $mhs->prodi->jenjang->nama }}</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-neutral-600">{{ $mhs->semester_masuk?->nama ?? '—' }}</td>
                                <td class="px-6 py-4 text-center text-neutral-900">{{ $stat['total'] }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 font-semibold {{ $persenClass }}">
                                        @if ($stat['persentase_diacc'] == 100)
                                            <i data-lucide="check-circle-2" class="h-4 w-4" aria-hidden="true"></i>
                                        @endif
                                        {{ $stat['persentase_diacc'] }}%
                                    </span>
                                    <span class="text-xs text-neutral-500">({{ $stat['diacc'] }}/{{ $stat['total'] }})</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button
                                        type="button"
                                        wire:click="openKrsModal({{ $mhs->id }})"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50"
                                    >
                                        <i data-lucide="file-check" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                        Lihat KRS
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-neutral-200 px-4 py-3">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Persetujuan KRS --}}
    @if ($viewingMahasiswaId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900">Persetujuan KRS</h3>
                        <p class="mt-1 text-sm text-neutral-500">{{ $viewingMahasiswaLabel }}</p>
                    </div>
                    <button type="button" wire:click="closeKrsModal" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4">
                    @php $krsPending = $this->krsPending; $pendingIds = $this->krsPendingOnlyIds; @endphp

                    @if (empty($krsPending))
                        <div class="py-12 text-center">
                            <i data-lucide="file-check" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
                            <p class="font-medium text-neutral-600">Tidak ada KRS untuk semester ini</p>
                        </div>
                    @else
                        @if (count($pendingIds) > 0)
                            <div class="mb-4 flex items-center justify-between">
                                <button type="button" wire:click="toggleSelectAll" class="flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
                                    <i data-lucide="{{ count($selectedKrsIds) === count($pendingIds) ? 'check-square' : 'square' }}" class="h-4 w-4" aria-hidden="true"></i>
                                    {{ count($selectedKrsIds) === count($pendingIds) ? 'Batal Pilih Semua' : 'Pilih Semua' }}
                                </button>
                                <span class="text-sm text-neutral-500">
                                    {{ count($pendingIds) }} belum disetujui
                                    @if (count($selectedKrsIds) > 0)
                                        · {{ count($selectedKrsIds) }} dipilih
                                    @endif
                                </span>
                            </div>
                        @else
                            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800">
                                Semua KRS sudah disetujui
                            </div>
                        @endif

                        <div class="space-y-2">
                            @foreach ($krsPending as $krs)
                                @php $isSelected = in_array($krs['id'], $selectedKrsIds); @endphp
                                <div class="rounded-lg border p-4 {{ $krs['is_approved'] ? 'border-neutral-200 bg-neutral-50/50' : ($isSelected ? 'border-sky-500 bg-sky-50' : 'border-neutral-200 bg-white hover:border-neutral-300') }}">
                                    <div class="flex items-start gap-3">
                                        @if ($krs['is_approved'])
                                            <div class="mt-1 flex h-5 w-5 items-center justify-center">
                                                <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600" aria-hidden="true"></i>
                                            </div>
                                        @else
                                            <button type="button" wire:click="toggleKrsSelection({{ $krs['id'] }})" class="mt-1">
                                                <i data-lucide="{{ $isSelected ? 'check-square' : 'square' }}" class="h-5 w-5 {{ $isSelected ? 'text-sky-600' : 'text-neutral-400' }}" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                        <div class="flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="font-semibold text-neutral-900">{{ $krs['kode_matkul'] ?? '-' }} - {{ $krs['nama_matkul'] ?? '-' }}</h4>
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $krs['is_approved'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                    {{ $krs['is_approved'] ? 'Disetujui' : 'Belum disetujui' }}
                                                </span>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-4 text-sm text-neutral-600">
                                                <span>SKS: {{ $krs['sks'] }}</span>
                                                @if ($krs['semester'])
                                                    <span>Semester: {{ $krs['semester']->nama }}</span>
                                                @endif
                                                @if ($krs['dosen_pic'])
                                                    <span>Dosen: {{ $krs['dosen_pic']->nama }}</span>
                                                @endif
                                                @if ($krs['prodi'])
                                                    <span>Prodi: {{ $krs['prodi']->nama }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if (! empty($krsPending))
                    <div class="flex items-center justify-end gap-3 border-t border-neutral-200 px-6 py-4">
                        <button type="button" wire:click="closeKrsModal" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                            {{ count($pendingIds) > 0 ? 'Batal' : 'Tutup' }}
                        </button>
                        @if (count($pendingIds) > 0)
                            <button
                                type="button"
                                wire:click="approveSelected"
                                wire:loading.attr="disabled"
                                @if (empty($selectedKrsIds)) disabled @endif
                                class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <i data-lucide="check-circle-2" class="h-4 w-4" aria-hidden="true"></i>
                                Setujui ({{ count($selectedKrsIds) }})
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
