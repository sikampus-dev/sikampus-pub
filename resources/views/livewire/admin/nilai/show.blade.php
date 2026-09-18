@section('title', 'Detail Nilai — ' . config('app.name'))
@section('header_title', 'Detail Nilai')
@section('header_subtitle', $this->mahasiswa->nama)
@section('header_icon', 'graduation-cap')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Nilai', 'route' => $backUrl],
        ['label' => $this->mahasiswa->nama],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ $backUrl }}"
        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i data-lucide="alert-circle" class="h-5 w-5 shrink-0 text-rose-600" aria-hidden="true"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900">Informasi Mahasiswa</h2>
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
                <span class="text-xs font-medium text-neutral-500">Semester Ditempuh</span>
                <p class="text-sm font-semibold text-neutral-900">
                    {{ $this->semesterDitempuh !== null ? 'Semester '.$this->semesterDitempuh : '—' }}
                </p>
            </div>
            <div>
                <span class="text-xs font-medium text-neutral-500">Semester Masuk</span>
                <p class="text-sm font-semibold text-neutral-900">
                    {{ $this->mahasiswa->semester_masuk ? $this->mahasiswa->semester_masuk->nama.' ('.$this->mahasiswa->semester_masuk->kode.')' : '—' }}
                </p>
            </div>
        </div>

        <div class="mt-6 border-t border-neutral-200 pt-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                Statistik Nilai {{ $filterSemester !== '' || $search !== '' ? '(Berdasarkan Filter)' : '' }}
            </h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg bg-neutral-50 p-4 shadow-border">
                    <span class="text-xs font-medium text-neutral-500">Total SKS</span>
                    <p class="text-2xl font-bold text-neutral-900">{{ $this->statistik['total_sks'] }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 p-4 shadow-border">
                    <span class="text-xs font-medium text-blue-600">Total Angka Mutu</span>
                    <p class="text-2xl font-bold text-blue-900">{{ number_format($this->statistik['total_angka_mutu'], 2) }}</p>
                    <p class="mt-1 text-xs text-blue-600">({{ $this->statistik['total_sks_dengan_nilai'] }} SKS dengan nilai final)</p>
                </div>
                <div class="rounded-lg bg-emerald-50 p-4 shadow-border">
                    <span class="text-xs font-medium text-emerald-600">IPK</span>
                    <p class="text-2xl font-bold text-emerald-900">{{ $this->statistik['ipk'] ?? '—' }}</p>
                    @if ($this->statistik['ipk'] !== null)
                        <p class="mt-1 text-xs text-emerald-600">Indeks Prestasi Kumulatif</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true"></i>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama atau kode mata kuliah..."
                    class="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"
                />
            </div>
            <div>
                <x-searchable-select
                    model="filterSemester"
                    :options="$this->semesterOptions"
                    placeholder="Semua Semester"
                    :live="true"
                />
            </div>
        </div>

        {{-- @php(...) satu baris tidak dikompilasi lagi di Laravel 12 — harus @php ... @endphp. --}}
        @php
            $bisaHapus = \App\Support\PanelAccess::can(auth()->user(), 'nilai', 'delete');
        @endphp

        @if ($bisaHapus)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                    <input
                        type="checkbox"
                        wire:model.live="showTrashed"
                        class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                    />
                    Tampilkan nilai yang sudah dihapus
                </label>

                @if ($selected !== [])
                    <button
                        type="button"
                        wire:click="confirmBulkDelete"
                        class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700"
                    >
                        <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                        Hapus {{ count($selected) }} nilai terpilih
                    </button>
                @endif
            </div>
        @endif

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-neutral-900">Daftar Nilai ({{ $this->krsList->count() }} mata kuliah)</h2>
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('admin.akademik.nilai.export', $mahasiswaId) }}{{ $filterSemester !== '' ? '?id_semester='.$filterSemester : '' }}"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
                >
                    <i data-lucide="download" class="h-4 w-4" aria-hidden="true"></i>
                    Export
                </a>
{{-- Cetak menawarkan dua bentuk dokumen yang isinya memang berbeda, jadi pilihannya
                     dimunculkan dulu lewat modal, bukan langsung mencetak.

                     Pakai <dialog> native + vanilla JS (pola yang sama dengan modal konfirmasi
                     logout di layouts/web.blade.php), BUKAN dropdown Alpine: x-show terbukti tidak
                     pernah mengubah display di halaman ini — elemennya tetap block walau state-nya
                     false — sementara @click-nya jalan normal, jadi menunya akan selalu terlihat. --}}
                <button
                    type="button"
                    onclick="document.getElementById('pilih-format-cetak-modal').showModal()"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
                >
                    <i data-lucide="printer" class="h-4 w-4" aria-hidden="true"></i>
                    Cetak
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        @if ($bisaHapus)
                            <th class="px-4 py-3 w-10">
                                <input
                                    type="checkbox"
                                    wire:click="toggleSelectAll"
                                    @checked($this->selectableNilaiIds() !== [] && count($selected) === count($this->selectableNilaiIds()))
                                    @disabled($this->selectableNilaiIds() === [])
                                    class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                                    title="Pilih semua"
                                />
                            </th>
                        @endif
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Mata Kuliah</th>
                        <th class="px-4 py-3 text-center">SKS</th>
                        <th class="px-4 py-3">Semester</th>
                        <th class="px-4 py-3">Nilai</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->krsList as $krs)
                        @php
                            $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;
                            // Satu KRS tidak pernah punya nilai hidup dan terhapus sekaligus (id_krs unik).
                            $nilai = $krs->nilai;
                            $nilaiTerhapus = $nilai ? null : $this->trashedNilaiMap->get($krs->id);
                            $huruf = $nilai?->huruf_mutu ? strtoupper($nilai->huruf_mutu) : null;
                            $badgeClass = match (true) {
                                $huruf === 'A' => 'bg-emerald-200 text-emerald-700',
                                $huruf === 'A-' => 'bg-emerald-100 text-emerald-700',
                                in_array($huruf, ['B+', 'B', 'B-'], true) => 'bg-blue-100 text-blue-700',
                                $huruf === 'C' => 'bg-amber-100 text-amber-700',
                                $huruf === 'D' => 'bg-orange-100 text-orange-700',
                                in_array($huruf, ['E', 'F'], true) => 'bg-rose-100 text-rose-700',
                                default => 'bg-neutral-100 text-neutral-700',
                            };
                        @endphp
                        <tr wire:key="nilai-krs-{{ $krs->id }}" class="{{ $nilaiTerhapus ? 'bg-neutral-50 text-neutral-500' : '' }}">
                            @if ($bisaHapus)
                                <td class="px-4 py-3">
                                    @if ($nilai || $nilaiTerhapus)
                                        <input
                                            type="checkbox"
                                            value="{{ $nilai?->id ?? $nilaiTerhapus->id }}"
                                            wire:model.live="selected"
                                            class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                                            title="{{ $nilaiTerhapus ? 'Pilih untuk dihapus permanen' : 'Pilih untuk dihapus' }}"
                                        />
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $matkul?->kode ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-900">{{ $matkul?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-center text-neutral-600">{{ $matkul?->sks ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $krs->kelas->semester ? $krs->kelas->semester->nama.' ('.$krs->kelas->semester->kode.')' : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($nilai)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass }}">
                                        {{ $nilai->huruf_mutu ?? '—' }} {{ $nilai->angka_mutu !== null ? '('.$nilai->angka_mutu.')' : '' }}
                                    </span>
                                    <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $nilai->is_final ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $nilai->is_final ? 'Final' : 'Belum Final' }}
                                    </span>
                                @elseif ($nilaiTerhapus)
                                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-500 line-through">
                                        {{ $nilaiTerhapus->huruf_mutu ?? '—' }} {{ $nilaiTerhapus->angka_mutu !== null ? '('.$nilaiTerhapus->angka_mutu.')' : '' }}
                                    </span>
                                    <span class="ml-1 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">
                                        Dihapus
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-700">Belum ada nilai</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if ($nilaiTerhapus)
                                        <button
                                            type="button"
                                            wire:click="restore({{ $nilaiTerhapus->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-700"
                                            title="Pulihkan nilai"
                                        >
                                            <i data-lucide="rotate-ccw" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmForceDelete({{ $nilaiTerhapus->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 hover:text-rose-800"
                                            title="Hapus permanen"
                                        >
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    @else
                                    @if (\App\Support\PanelAccess::can(auth()->user(), 'nilai', 'update'))
                                        <a
                                            href="{{ route('admin.akademik.nilai.edit', [$mahasiswaId, $krs->id]) }}{{ $returnQuery ? '?'.$returnQuery : '' }}"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                            title="{{ $nilai ? 'Ubah Nilai' : 'Input Nilai' }}"
                                        >
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    @if ($nilai && \App\Support\PanelAccess::can(auth()->user(), 'nilai', 'delete'))
                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $nilai->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                            title="Hapus nilai"
                                        >
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $bisaHapus ? 7 : 6 }}" class="px-4 py-10 text-center text-neutral-500">Belum ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($confirmDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus nilai?</h3>
                <p class="mt-2 text-sm text-neutral-600">Data komponen dan revisi terkait ikut dihapus. Tindakan ini tidak dapat dibatalkan.</p>
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

    @if ($confirmingBulkDelete)
        @php
            $ringkasan = $this->ringkasanTerpilih;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus {{ $ringkasan['hapus'] + $ringkasan['permanen'] }} nilai terpilih?</h3>
                <div class="mt-3 space-y-2 text-sm text-neutral-600">
                    @if ($ringkasan['hapus'] > 0)
                        <p>
                            <span class="font-medium text-neutral-900">{{ $ringkasan['hapus'] }} nilai</span> akan dihapus
                            beserta komponen dan revisinya, dan masih bisa dipulihkan.
                        </p>
                    @endif
                    @if ($ringkasan['permanen'] > 0)
                        <p class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800">
                            <span class="font-medium">{{ $ringkasan['permanen'] }} nilai</span> sudah dihapus sebelumnya,
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
                        Hapus {{ $ringkasan['hapus'] + $ringkasan['permanen'] }} Nilai
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmForceDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus permanen nilai?</h3>
                <p class="mt-2 text-sm text-neutral-600">Nilai beserta komponen dan revisi yang terhapus bersamanya akan benar-benar dihapus dari database dan tidak bisa dipulihkan lagi — berbeda dari hapus biasa. Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelForceDelete" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="forceDeleteNilai" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus Permanen
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Posisi lewat fixed+translate (bukan margin:auto bawaan <dialog>) karena preflight Tailwind
         me-reset margin semua elemen termasuk dialog, yang membatalkan centering otomatis browser. --}}
    <dialog id="pilih-format-cetak-modal" class="fixed top-1/2 left-1/2 m-0 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-2xl p-0 shadow-border-lg backdrop:bg-neutral-900/40">
        <div class="p-6">
            <h3 class="text-base font-semibold text-neutral-900">Cetak dokumen apa?</h3>
            <p class="mt-2 text-sm text-neutral-600">Pilih bentuk dokumen yang ingin dicetak untuk {{ $this->mahasiswa->nama ?? 'mahasiswa ini' }}.</p>

            <div class="mt-5 space-y-3">
                <a
                    href="{{ route('admin.akademik.nilai.transkrip', $mahasiswaId) }}"
                    target="_blank"
                    rel="noopener"
                    onclick="document.getElementById('pilih-format-cetak-modal').close()"
                    class="flex gap-3 rounded-xl p-4 text-left transition hover:bg-neutral-50 shadow-border"
                >
                    <i data-lucide="award" class="mt-0.5 h-5 w-5 shrink-0 text-neutral-500" aria-hidden="true"></i>
                    <span>
                        <span class="block text-sm font-medium text-neutral-900">Transkrip Nilai</span>
                        <span class="mt-1 block text-xs text-neutral-500">
                            Dokumen resmi dwibahasa dengan kop, pas foto, nomor ijazah/transkrip, dan blok tanda
                            tangan pejabat. Selalu seluruh masa studi, hanya mata kuliah bernilai final.
                        </span>
                    </span>
                </a>

                <a
                    href="{{ route('admin.akademik.nilai.cetak', $mahasiswaId) }}{{ $filterSemester !== '' ? '?id_semester='.$filterSemester : '' }}"
                    target="_blank"
                    rel="noopener"
                    onclick="document.getElementById('pilih-format-cetak-modal').close()"
                    class="flex gap-3 rounded-xl p-4 text-left transition hover:bg-neutral-50 shadow-border"
                >
                    <i data-lucide="file-text" class="mt-0.5 h-5 w-5 shrink-0 text-neutral-500" aria-hidden="true"></i>
                    <span>
                        <span class="block text-sm font-medium text-neutral-900">Laporan Nilai</span>
                        <span class="mt-1 block text-xs text-neutral-500">
                            Rekap nilai sesuai filter yang sedang aktif di layar{{ $filterSemester !== '' ? ' (semester terpilih)' : '' }},
                            termasuk mata kuliah yang belum dinilai.
                        </span>
                    </span>
                </a>
            </div>

            <div class="mt-5 flex justify-end">
                <button
                    type="button"
                    onclick="document.getElementById('pilih-format-cetak-modal').close()"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
                >
                    Batal
                </button>
            </div>
        </div>
    </dialog>

</div>
