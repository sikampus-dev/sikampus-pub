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
                <span class="text-xs font-medium text-neutral-500">Kelas Mahasiswa</span>
                <p class="text-sm font-semibold text-neutral-900">{{ $this->mahasiswa->kelompok_kelas->nama ?? '—' }}</p>
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
                <button
                    type="button"
                    wire:click="bukaTambahKrsModal"
                    class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
                >
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Tambah KRS
                </button>
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

            {{-- Selalu dirender, tidak disembunyikan dengan @if ($selected) — lihat catatan di
                 kolom checkbox soal wire:model yang ditunda. --}}
            @if ($this->krsList->isNotEmpty())
                <button
                    type="button"
                    wire:click="confirmBulkDelete"
                    class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700 disabled:opacity-50"
                    x-bind:disabled="!$wire.selected.length"
                    wire:loading.attr="disabled"
                >
                    <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                    Hapus KRS terpilih<span x-text="$wire.selected.length ? ' (' + $wire.selected.length + ')' : ''"></span>
                    {{-- wire:loading dipasang di <span>, bukan langsung di <i data-lucide>: lucide
                         MENGGANTI elemen <i> itu dengan <svg> saat ikon dirender, jadi atribut yang
                         menempel padanya tidak bisa diandalkan. --}}
                    <span wire:loading wire:target="confirmBulkDelete">
                        <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                    </span>
                </button>
            @endif
        </div>

        {{-- wire:target menyebut nama properti filter/toggle secara eksplisit, bukan dibiarkan
             kosong: kalau kosong, wire:loading ikut menyala untuk request LAIN dari komponen ini
             (mis. hapus satu baris), padahal yang diminta cuma indikator untuk filter. --}}
        <div
            wire:loading.flex
            wire:target="filterSemester, showTrashed"
            class="print:hidden mb-4 items-center gap-2 rounded-lg bg-neutral-50 px-4 py-2 text-xs font-medium text-neutral-500"
        >
            <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin" aria-hidden="true"></i>
            Memuat data...
        </div>

        <div
            class="overflow-x-auto"
            wire:loading.class="opacity-50 pointer-events-none"
            wire:target="filterSemester, showTrashed"
        >
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
                                {{-- wire:model TANPA .live: mencentang tidak boleh memicu request.
                                     Dengan .live, tiap klik mengirim satu request penuh (render
                                     ulang seluruh halaman), sehingga centang baru terlihat setelah
                                     bolak-balik jaringan — terasa macet di server yang jauh.
                                     Nilainya ikut terkirim saat tombol hapus diklik. --}}
                                <input
                                    type="checkbox"
                                    value="{{ $krs->id }}"
                                    wire:model="selected"
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
                                        {{-- wire:target menyertakan id barisnya: tanpa itu "restore" cocok dengan panggilan restore
                                             mana pun, sehingga spinner berputar di SEMUA baris terhapus sekaligus padahal hanya satu
                                             yang sedang diproses. --}}
                                        <button
                                            type="button"
                                            wire:click="restore({{ $krs->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="restore({{ $krs->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-700 disabled:opacity-50"
                                            title="Pulihkan KRS"
                                        >
                                            <span wire:loading.remove wire:target="restore({{ $krs->id }})" class="inline-flex">
                                                <i data-lucide="rotate-ccw" class="h-4 w-4" aria-hidden="true"></i>
                                            </span>
                                            <span wire:loading wire:target="restore({{ $krs->id }})" class="inline-flex">
                                                <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmForceDelete({{ $krs->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="confirmForceDelete({{ $krs->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 hover:text-rose-800 disabled:opacity-50"
                                            title="Hapus permanen"
                                        >
                                            <span wire:loading.remove wire:target="confirmForceDelete({{ $krs->id }})" class="inline-flex">
                                                <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                            </span>
                                            <span wire:loading wire:target="confirmForceDelete({{ $krs->id }})" class="inline-flex">
                                                <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                                            </span>
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
                                            wire:loading.attr="disabled"
                                            wire:target="confirmDelete({{ $krs->id }})"
                                            class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-50"
                                            title="Hapus"
                                        >
                                            <span wire:loading.remove wire:target="confirmDelete({{ $krs->id }})" class="inline-flex">
                                                <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                            </span>
                                            <span wire:loading wire:target="confirmDelete({{ $krs->id }})" class="inline-flex">
                                                <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                                            </span>
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

                @if ($this->bisaHapusNilai())
                    <label class="mt-4 flex items-start gap-2 rounded-lg bg-neutral-50 p-3 text-sm text-neutral-700 shadow-border">
                        <input
                            type="checkbox"
                            wire:model="hapusNilaiTerkait"
                            class="mt-0.5 size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                        />
                        <span>
                            Hapus juga nilai yang terkait
                            <span class="block text-xs text-neutral-500">Termasuk nilai yang sudah final — tanpa opsi ini, KRS dengan nilai final tidak bisa dihapus.</span>
                        </span>
                    </label>
                @endif

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelDelete"
                        wire:loading.attr="disabled"
                        wire:target="delete"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border disabled:opacity-50"
                    >
                        Batal
                    </button>
                    {{-- Penghapusan bisa memakan beberapa detik (koneksi lambat, atau batch besar
                         yang menghapus nilai/komponen/revisi satu per satu). Tanpa indikator,
                         tombolnya tampak tidak bereaksi dan gampang diklik dua kali. --}}
                    <button
                        type="button"
                        wire:click="delete"
                        wire:loading.attr="disabled"
                        wire:target="delete"
                        class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700 disabled:opacity-75"
                    >
                        <span wire:loading.remove wire:target="delete">Hapus</span>
                        <span wire:loading wire:target="delete" class="inline-flex items-center gap-2">
                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                            Menghapus...
                        </span>
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
                    <button
                        type="button"
                        wire:click="cancelForceDelete"
                        wire:loading.attr="disabled"
                        wire:target="forceDeleteKrs"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border disabled:opacity-50"
                    >
                        Batal
                    </button>
                    {{-- Penghapusan bisa memakan beberapa detik (koneksi lambat, atau batch besar
                         yang menghapus nilai/komponen/revisi satu per satu). Tanpa indikator,
                         tombolnya tampak tidak bereaksi dan gampang diklik dua kali. --}}
                    <button
                        type="button"
                        wire:click="forceDeleteKrs"
                        wire:loading.attr="disabled"
                        wire:target="forceDeleteKrs"
                        class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700 disabled:opacity-75"
                    >
                        <span wire:loading.remove wire:target="forceDeleteKrs">Hapus Permanen</span>
                        <span wire:loading wire:target="forceDeleteKrs" class="inline-flex items-center gap-2">
                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                            Menghapus...
                        </span>
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
                    <button
                        type="button"
                        wire:click="cancelBulkDelete"
                        wire:loading.attr="disabled"
                        wire:target="bulkDelete"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border disabled:opacity-50"
                    >
                        Batal
                    </button>
                    {{-- Penghapusan bisa memakan beberapa detik (koneksi lambat, atau batch besar
                         yang menghapus nilai/komponen/revisi satu per satu). Tanpa indikator,
                         tombolnya tampak tidak bereaksi dan gampang diklik dua kali. --}}
                    <button
                        type="button"
                        wire:click="bulkDelete"
                        wire:loading.attr="disabled"
                        wire:target="bulkDelete"
                        class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700 disabled:opacity-75"
                    >
                        <span wire:loading.remove wire:target="bulkDelete">Hapus {{ $ringkasan['hapus'] + $ringkasan['permanen'] }} KRS</span>
                        <span wire:loading wire:target="bulkDelete" class="inline-flex items-center gap-2">
                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                            Menghapus...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showTambahKrsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-border-lg">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">Tambah KRS — {{ $this->mahasiswa->nama }}</h3>
                    <button
                        type="button"
                        wire:click="tutupTambahKrsModal"
                        class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700"
                    >
                        <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>

                @if ($tambahKrsError !== '')
                    <div class="mb-4 flex gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
                        <span>{{ $tambahKrsError }}</span>
                    </div>
                @endif

                <div class="space-y-4">
                    @foreach ($tambahKrs as $index => $row)
                        <div class="rounded-lg bg-neutral-50 p-4 shadow-border" wire:key="tambah-krs-row-{{ $index }}">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-xs font-semibold text-neutral-700">KRS Ke-{{ $index + 1 }}</h4>
                                @if (count($tambahKrs) > 1)
                                    <button
                                        type="button"
                                        wire:click="removeTambahKrsRow({{ $index }})"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 hover:text-rose-700"
                                    >
                                        <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kelas *</label>
                                    <x-searchable-select
                                        :model="'tambahKrs.'.$index.'.id_kelas'"
                                        :options="$this->kelasOptionsTambahKrs"
                                        placeholder="— Pilih kelas —"
                                    />
                                    @error('tambahKrs.'.$index.'.id_kelas') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Status</label>
                                    <select wire:model="tambahKrs.{{ $index }}.status" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                                        <option value="pending">Pending</option>
                                        <option value="acc">Acc/Approved</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="flex justify-center">
                        <button
                            type="button"
                            wire:click="addTambahKrsRow"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                        >
                            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                            Tambah Baris
                        </button>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="tutupTambahKrsModal"
                        wire:loading.attr="disabled"
                        wire:target="simpanTambahKrs"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border disabled:opacity-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="simpanTambahKrs"
                        wire:loading.attr="disabled"
                        wire:target="simpanTambahKrs"
                        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800 disabled:opacity-75"
                    >
                        <span wire:loading.remove wire:target="simpanTambahKrs" class="inline-flex items-center gap-2">
                            <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                            Simpan
                        </span>
                        <span wire:loading wire:target="simpanTambahKrs" class="inline-flex items-center gap-2">
                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                            Menyimpan...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
