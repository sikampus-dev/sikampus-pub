@php
    $kelas = $this->kelas;
    $matkulLabel = trim(($kelas->kurikulumMatkul?->matkul?->kode ? "{$kelas->kurikulumMatkul->matkul->kode} — " : '') . ($kelas->kurikulumMatkul?->matkul?->nama ?? 'Kelas'));
@endphp

@section('title', $matkulLabel . ' — ' . config('app.name'))
@section('header_title', $matkulLabel)
@section('header_subtitle', 'Detail kelas kuliah')
@section('header_icon', 'presentation')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Kelas', 'route' => route('admin.akademik.kelas')],
        ['label' => 'Detail'],
    ]])
@endsection

{{-- Tombol aksi sengaja berada di dalam badan komponen, bukan di section page_actions:
     layouts.web me-render page_actions di luar root <div> komponen, sehingga wire:click di sana
     tidak pernah terikat Livewire (tombol tampil tapi diam saat diklik). --}}
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        <a
            href="{{ $backUrl }}"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Kembali
        </a>
        <a
            href="{{ route('admin.akademik.kelas.edit', $kelasId) }}{{ $returnQuery ? '?' . $returnQuery : '' }}"
            class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800"
        >
            <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
            Ubah
        </a>
        <button
            type="button"
            wire:click="confirmDelete"
            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-rose-600 shadow-border transition hover:bg-rose-50"
        >
            <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
            Hapus
        </button>
    </div>

    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-semibold text-neutral-900">Informasi Kelas</h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kelas->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600' }}">
                {{ $kelas->is_active ? 'Aktif' : 'Tidak Aktif' }}
            </span>
        </div>
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kode Kelas</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $kelas->kode ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kurikulum</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $kelas->kurikulumMatkul?->kurikulum?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Program Studi</dt>
                <dd class="mt-0.5 text-neutral-900">
                    {{ $kelas->prodi ? ($kelas->prodi->jenjang?->kode ? "{$kelas->prodi->nama} ({$kelas->prodi->jenjang->kode})" : $kelas->prodi->nama) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kelas Mahasiswa</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $kelas->kelompokKelas?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester Berjalan</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $kelas->semester?->nama ?? '—' }} @if ($kelas->semester?->kode) ({{ $kelas->semester->kode }}) @endif</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Angkatan</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $kelas->angkatan?->nama ?? '—' }} @if ($kelas->angkatan?->kode) ({{ $kelas->angkatan->kode }}) @endif</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester Kuliah Ke</dt>
                <dd class="mt-0.5 tabular-nums text-neutral-900">{{ $kelas->semester_kuliah_ke !== null ? "Ke-{$kelas->semester_kuliah_ke}" : '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Jumlah Pertemuan</dt>
                <dd class="mt-0.5 tabular-nums text-neutral-900">{{ $kelas->jml_pertemuan ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Jadwal Mingguan</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $kelas->is_mingguan ? 'Ya' : 'Tidak' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Kuota</dt>
                <dd class="mt-0.5 tabular-nums text-neutral-900">{{ $kelas->kuota ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Dosen Penanggung Jawab</dt>
                <dd class="mt-0.5 text-neutral-900">{{ $kelas->dosenPic?->nama ?? '—' }} @if ($kelas->dosenPic?->kode_dosen) ({{ $kelas->dosenPic->kode_dosen }}) @endif</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Mahasiswa Terdaftar (KRS)</dt>
                <dd class="mt-0.5 tabular-nums font-semibold text-neutral-900">{{ $this->jumlahMahasiswa }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500">Perkuliahan Tercatat</dt>
                <dd class="mt-0.5 tabular-nums text-neutral-900">{{ $this->jumlahPerkuliahan }}</dd>
            </div>
        </dl>

        @if ($kelas->kelasDosen->isNotEmpty())
            <div class="mt-6 border-t border-neutral-200 pt-4">
                <h3 class="mb-2 text-sm font-semibold text-neutral-900">Tim Pengajar</h3>
                <ul class="list-inside list-disc space-y-1 text-sm text-neutral-700">
                    @foreach ($kelas->kelasDosen as $kd)
                        <li>
                            {{ $kd->dosen?->nama ?? '—' }}
                            @if ($kd->dosen?->kode_dosen) ({{ $kd->dosen->kode_dosen }}) @endif
                            @if ($kd->is_pic) <span class="text-xs font-medium text-neutral-500">— PIC</span> @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-semibold text-neutral-900">Jadwal</h2>
            @if (count($selectedJadwalIds) > 0)
                <button
                    type="button"
                    wire:click="confirmBulkDelete"
                    class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700"
                >
                    <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                    Hapus Terpilih ({{ count($selectedJadwalIds) }})
                </button>
            @endif
        </div>
        @if ($this->jadwalList->isEmpty())
            <p class="text-sm text-neutral-500">Belum ada jadwal.</p>
        @else
            <div class="overflow-x-auto rounded-lg shadow-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="w-10 px-4 py-3">
                                <input
                                    type="checkbox"
                                    wire:click="toggleAllJadwal"
                                    @checked(count($selectedJadwalIds) > 0 && count($selectedJadwalIds) === $this->jadwalList->count())
                                    class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                                    title="Centang semua"
                                />
                            </th>
                            <th class="px-4 py-3">Hari</th>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Ruangan</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Dosen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($this->jadwalList as $jadwal)
                            <tr wire:key="jadwal-{{ $jadwal->id }}">
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedJadwalIds"
                                        value="{{ $jadwal->id }}"
                                        class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10"
                                    />
                                </td>
                                <td class="px-4 py-3 text-neutral-900">{{ ucfirst($jadwal->hari ?? '—') }}</td>
                                <td class="px-4 py-3 tabular-nums text-neutral-900 whitespace-nowrap">
                                    {{ $jadwal->jam_mulai && $jadwal->jam_selesai ? "{$jadwal->jam_mulai} – {$jadwal->jam_selesai}" : ($jadwal->jam_mulai ?? $jadwal->jam_selesai ?? '—') }}
                                </td>
                                <td class="px-4 py-3 text-neutral-900">{{ $jadwal->ruangan?->nama ?? $jadwal->ruangan?->kode ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-900">{{ $jadwal->jenisKuliah?->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-900">
                                    {{ $jadwal->dosen->map(fn ($d) => $d->dosen?->nama)->filter()->join(', ') ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($confirmingDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus kelas?</h3>
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

    @if ($confirmingBulkDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus {{ count($selectedJadwalIds) }} jadwal terpilih?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelBulkDelete" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        Batal
                    </button>
                    <button type="button" wire:click="bulkDeleteJadwal" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
