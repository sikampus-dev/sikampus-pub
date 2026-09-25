@section('title', 'Tugas Akhir — ' . config('app.name'))
@section('header_title', 'Tugas Akhir')
@section('header_subtitle', 'Daftar pengajuan tugas akhir Anda. Gunakan filter untuk menyaring status atau semester.')

@php
    $ctx = $this->ctx;
    $canOpenPengajuan = $ctx['eligible'];
    $statusBadgeClass = fn (?string $s) => match ($s) {
        'approved' => 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        'submitted' => 'bg-sky-50 text-sky-800 ring-sky-100',
        'rejected' => 'bg-rose-50 text-rose-800 ring-rose-100',
        'returned' => 'bg-amber-50 text-amber-900 ring-amber-100',
        default => 'bg-neutral-100 text-neutral-700 ring-neutral-200',
    };
    $statusLabel = fn (?string $s) => match ($s) {
        'draft' => 'Draft', 'submitted' => 'Terkirim', 'approved' => 'Disetujui',
        'rejected' => 'Ditolak', 'returned' => 'Dikembalikan', default => $s ?? '—',
    };
@endphp

@section('page_actions')
    @if ($canOpenPengajuan)
        <a
            href="{{ route('mahasiswa.akhir-studi.tugas-akhir.pengajuan') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
        >
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Buat pengajuan
        </a>
    @else
        <span class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-neutral-200 px-4 py-2.5 text-sm font-semibold text-neutral-500">
            <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
            Buat pengajuan
        </span>
    @endif
@endsection

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2">
        @if ($ctx['semester_aktif'])
            <div class="flex flex-col justify-center rounded-xl bg-white px-4 py-3 shadow-border">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Semester aktif</p>
                <p class="mt-1 text-base font-semibold text-neutral-900">{{ $ctx['semester_aktif']->nama }}</p>
                <p class="text-sm text-neutral-600">{{ $ctx['semester_aktif']->kode }}</p>
            </div>
        @endif

        @if (! $ctx['eligible'])
            <div class="flex flex-col justify-center rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-4">
                <div class="flex gap-3">
                    <i data-lucide="alert-circle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" aria-hidden="true"></i>
                    <div class="space-y-2">
                        <p class="font-semibold text-amber-950">Belum memenuhi syarat pengajuan baru</p>
                        <p class="text-sm text-amber-950/90">{{ $ctx['pesan_tidak_eligible'] }}</p>
                        <a href="{{ route('mahasiswa.krs.pengajuan') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-900 underline decoration-amber-700/40 underline-offset-2 hover:text-amber-950">
                            Ke halaman KRS / pengajuan
                            <i data-lucide="external-link" class="h-3.5 w-3.5" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        @elseif ($ctx['krs_ta'])
            <div class="flex flex-col justify-center rounded-xl border border-emerald-100 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-950">
                <div class="flex items-start gap-2">
                    <i data-lucide="check-circle-2" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
                    <div>
                        <p class="font-semibold">Mata kuliah TA terkontrak (disetujui)</p>
                        <p class="mt-1">
                            {{ $ctx['krs_ta']->kelas->kurikulumMatkul->matkul->kode ?? '' }} — {{ $ctx['krs_ta']->kelas->kurikulumMatkul->matkul->nama ?? '' }}
                            @if ($ctx['krs_ta']->kelas->kurikulumMatkul->matkul->sks ?? null)
                                ({{ $ctx['krs_ta']->kelas->kurikulumMatkul->matkul->sks }} SKS)
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="rounded-xl bg-white p-4 shadow-border">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <i data-lucide="filter" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
            <span class="text-sm font-semibold text-neutral-800">Filter</span>
        </div>
        <div class="flex flex-wrap gap-3">
            <div class="min-w-[160px] flex-1">
                <label class="mb-1 block text-xs font-semibold text-neutral-700">Status</label>
                <x-searchable-select model="filterStatus" :options="$this->statusOptions" :live="true" :clearable="false" />
            </div>
            <div class="min-w-[200px] flex-1">
                <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
                <x-searchable-select
                    model="filterSemester"
                    :options="$this->semesterOptions"
                    :live="true"
                    placeholder="Semua semester"
                />
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-border">
        <div class="border-b border-neutral-100 bg-neutral-50/80 px-4 py-3">
            <h2 class="text-sm font-semibold text-neutral-800">Daftar pengajuan</h2>
        </div>
        @if ($this->rows->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-neutral-600">
                Belum ada data yang cocok dengan filter.
                @if ($canOpenPengajuan)
                    <a href="{{ route('mahasiswa.akhir-studi.tugas-akhir.pengajuan') }}" class="font-semibold text-sky-700 underline hover:text-sky-900">Buat pengajuan pertama</a>
                @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-neutral-200 bg-neutral-50/50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Semester</th>
                            <th class="px-4 py-3">Judul</th>
                            <th class="px-4 py-3">Topik</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Diperbarui</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($this->rows as $row)
                            <tr wire:key="ta-{{ $row->id }}" class="hover:bg-neutral-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-neutral-800">
                                    {{ $row->semester->nama ?? '—' }}
                                    @if ($row->semester?->kode)
                                        <span class="block text-xs font-normal text-neutral-500">{{ $row->semester->kode }}</span>
                                    @endif
                                </td>
                                <td class="max-w-xs px-4 py-3 font-medium text-neutral-900">
                                    <span class="line-clamp-2">{{ $row->judul }}</span>
                                    @if ($row->judul_en)
                                        <span class="mt-0.5 line-clamp-1 block text-xs font-normal text-neutral-500">{{ $row->judul_en }}</span>
                                    @endif
                                </td>
                                <td class="max-w-[200px] px-4 py-3 text-neutral-700">
                                    <span class="line-clamp-2">{{ $row->topik ?? '—' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-neutral-700">{{ $row->is_proposal !== false ? 'Proposal' : 'Final' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($row->status) }}">
                                        {{ $statusLabel($row->status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-neutral-600">{{ $row->updated_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <a
                                        href="{{ route('mahasiswa.akhir-studi.tugas-akhir.show', $row->id) }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-neutral-700 shadow-border transition hover:bg-neutral-50"
                                        title="Lihat detail"
                                    >
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
