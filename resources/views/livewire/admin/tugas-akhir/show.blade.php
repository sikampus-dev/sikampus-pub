@php
    $statusLabel = fn (?string $s) => match ($s) {
        'draft' => 'Draft',
        'submitted' => 'Terkirim',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'returned' => 'Dikembalikan',
        default => '—',
    };
    $keputusanLabel = fn (string $k) => match ($k) {
        'acc' => 'Disetujui (acc)',
        'returned' => 'Dikembalikan',
        'declined' => 'Ditolak',
        default => $k,
    };
    $ta = $this->tugasAkhir;
@endphp

@section('title', 'Detail Tugas Akhir — ' . config('app.name'))
@section('header_title', 'Detail Tugas Akhir')
@section('header_subtitle', $ta->mahasiswa?->nama)
@section('header_icon', 'graduation-cap')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Tugas Akhir', 'route' => route('admin.akademik.tugas-akhir')],
        ['label' => $ta->mahasiswa?->nama ?? 'Detail'],
    ]])
@endsection

<div>
    @if (session('status'))
        <div class="mb-4 flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="mb-6 border-b border-neutral-200">
        <nav class="-mb-px flex flex-wrap gap-6">
            @foreach ([['key' => 'detail', 'label' => 'Detail'], ['key' => 'pembimbing', 'label' => 'Pembimbing'], ['key' => 'sidang', 'label' => 'Ujian Sidang']] as $tab)
                <button
                    type="button"
                    wire:click="setTab('{{ $tab['key'] }}')"
                    class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition {{ $activeTab === $tab['key'] ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    @if ($activeTab === 'detail')
        <div class="space-y-6">
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h2 class="mb-4 text-base font-semibold text-neutral-900">Mahasiswa</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Nama</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">NIM</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->nim ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Email</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">No. HP</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->no_hp ?? $ta->mahasiswa?->handphone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Program studi</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->prodi?->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Semester masuk</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->semester_masuk?->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Status akademik</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->status_akademik?->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Kelompok Kelas</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $ta->mahasiswa?->kelompok_kelas?->nama ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <h2 class="text-base font-semibold text-neutral-900">Tugas akhir</h2>
                    <button
                        type="button"
                        wire:click="openStatusModal"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-neutral-800 shadow-border transition hover:bg-neutral-50"
                    >
                        <i data-lucide="gavel" class="h-4 w-4 text-sky-600" aria-hidden="true"></i>
                        Keputusan pengajuan
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Semester tugas akhir</p>
                        <p class="text-sm font-semibold text-neutral-900">
                            {{ $ta->semester?->nama ?? '—' }}{{ $ta->semester?->kode ? " ({$ta->semester->kode})" : '' }}
                            @if ($ta->semester?->is_active)
                                <span class="ml-2 text-xs font-normal text-emerald-600">semester aktif</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Status</p>
                        <p class="text-sm font-semibold text-neutral-900">{{ $statusLabel($ta->status) }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Judul</p>
                        <p class="whitespace-pre-wrap text-sm font-semibold text-neutral-900">{{ $ta->judul ?: '—' }}</p>
                    </div>
                    @if ($ta->deskripsi)
                        <div>
                            <p class="mb-1 text-xs text-neutral-500">Deskripsi</p>
                            <p class="whitespace-pre-wrap text-sm text-neutral-800">{{ $ta->deskripsi }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="mb-1 text-xs text-neutral-500">Berkas</p>
                        @if ($ta->file)
                            <a href="{{ asset('storage/'.ltrim($ta->file, '/')) }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-sky-600 hover:underline">
                                Buka / unduh berkas
                            </a>
                        @else
                            <p class="text-sm text-neutral-600">—</p>
                        @endif
                    </div>

                    @if ($ta->statusLogs->isNotEmpty())
                        <div class="border-t border-neutral-100 pt-4">
                            <p class="mb-2 text-xs text-neutral-500">Riwayat keputusan</p>
                            <div class="overflow-x-auto rounded-lg border border-neutral-100">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                                        <tr>
                                            <th class="px-3 py-2">Waktu</th>
                                            <th class="px-3 py-2">Keputusan</th>
                                            <th class="px-3 py-2">Oleh</th>
                                            <th class="px-3 py-2">Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-100">
                                        @foreach ($ta->statusLogs as $log)
                                            <tr>
                                                <td class="whitespace-nowrap px-3 py-2 text-neutral-700">{{ $log->created_at?->format('d M Y H:i') }}</td>
                                                <td class="px-3 py-2 font-medium text-neutral-900">{{ $keputusanLabel($log->status) }}</td>
                                                <td class="px-3 py-2 text-neutral-600">{{ $log->user?->name ?? $log->user?->email ?? '—' }}</td>
                                                <td class="max-w-xs px-3 py-2 text-neutral-600">
                                                    @if ($log->keterangan)
                                                        <span class="whitespace-pre-wrap">{{ $log->keterangan }}</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'pembimbing')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-neutral-900">Pembimbing</h2>
                <button
                    type="button"
                    wire:click="openPembimbingModal"
                    class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
                >
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Tambah Pembimbing
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Dosen</th>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">NIDN</th>
                            <th class="px-4 py-3">Tanggal penugasan</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($ta->pembimbing as $row)
                            <tr wire:key="pembimbing-{{ $row->id }}">
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $row->dosen?->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $row->dosen?->kode_dosen ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $row->dosen?->nidn ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $row->tanggal_penugasan?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" wire:click="openPembimbingModal({{ $row->id }})" class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900" title="Ubah">
                                            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" wire:click="confirmDeletePembimbing({{ $row->id }})" class="inline-flex items-center justify-center rounded-lg p-2 text-rose-500 transition hover:bg-rose-50 hover:text-rose-700" title="Hapus">
                                            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-neutral-500">Belum ada pembimbing. Gunakan tombol di atas untuk menambahkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($activeTab === 'sidang')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-neutral-900">Ringkasan ujian sidang</h2>
                <button
                    type="button"
                    wire:click="openSidangModal"
                    class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
                >
                    <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                    Buat Ujian Sidang
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Semester</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Tanggal daftar</th>
                            <th class="px-4 py-3">Mulai ujian</th>
                            <th class="px-4 py-3">Selesai ujian</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($ta->ujianSidang as $sidang)
                            <tr wire:key="sidang-{{ $sidang->id }}">
                                <td class="px-4 py-3 font-medium text-neutral-900">
                                    {{ $sidang->semester?->nama ?? '—' }}
                                    @if ($sidang->semester?->kode)
                                        <span class="text-neutral-500">({{ $sidang->semester->kode }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-neutral-600">{{ $statusLabel($sidang->status) }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $sidang->tanggal_daftar?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $sidang->tanggal_ujian_mulai?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $sidang->tanggal_ujian_selesai?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.akademik.tugas-akhir.ujian-sidang', [$ta->id, $sidang->id]) }}" class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900" title="Lihat detail">
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada data ujian sidang. Gunakan tombol di atas untuk menambahkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Modal: keputusan pengajuan --}}
    @if ($showStatusModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Keputusan pengajuan</h3>
                <p class="mt-1 text-sm text-neutral-600">Pilih salah satu. Status pada tugas akhir akan disesuaikan: disetujui, dikembalikan untuk perbaikan, atau ditolak.</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Keputusan *</label>
                        <select wire:model="keputusan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border">
                            <option value="">Pilih keputusan</option>
                            <option value="acc">Disetujui (acc)</option>
                            <option value="returned">Dikembalikan (returned)</option>
                            <option value="declined">Ditolak (declined)</option>
                        </select>
                        @error('keputusan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Keterangan (opsional)</label>
                        <textarea wire:model="keteranganStatus" rows="3" placeholder="Catatan untuk mahasiswa atau admin…" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                        @error('keteranganStatus') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" wire:click="closeStatusModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="saveStatus" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: tambah/ubah pembimbing --}}
    @if ($showPembimbingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">{{ $editingPembimbingId ? 'Ubah' : 'Tambah' }} Pembimbing</h3>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Dosen *</label>
                        <x-searchable-select
                            model="pembimbingDosenId"
                            :options="$this->dosenOptions"
                            optionLabel="label"
                            placeholder="— Cari nama atau kode dosen —"
                        />
                        @error('pembimbingDosenId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal penugasan</label>
                        <input type="date" wire:model="pembimbingTanggal" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                        @error('pembimbingTanggal') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" wire:click="closePembimbingModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="savePembimbing" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: konfirmasi hapus pembimbing --}}
    @if ($confirmingPembimbingDeleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus pembimbing?</h3>
                <p class="mt-2 text-sm text-neutral-600">Pembimbing ini akan dicopot dari tugas akhir.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeletePembimbing" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="deletePembimbing" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">Hapus</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: buat ujian sidang --}}
    @if ($showSidangModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Buat Ujian Sidang</h3>
                <p class="mt-1 text-sm text-neutral-600">Pilih semester dan opsional jadwal ujian. Setelah tersimpan, Anda dapat menambahkan dosen penguji.</p>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Semester *</label>
                        <x-searchable-select
                            model="sidangSemesterId"
                            :options="$this->semesterOptions"
                            optionLabel="label"
                            placeholder="— Cari atau pilih semester —"
                        />
                        @error('sidangSemesterId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal &amp; jam mulai (opsional)</label>
                            <input type="datetime-local" wire:model="sidangTanggalMulai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            @error('sidangTanggalMulai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal &amp; jam selesai (opsional)</label>
                            <input type="datetime-local" wire:model="sidangTanggalSelesai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            @error('sidangTanggalSelesai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" wire:click="closeSidangModal" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">Batal</button>
                    <button type="button" wire:click="saveSidang" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
