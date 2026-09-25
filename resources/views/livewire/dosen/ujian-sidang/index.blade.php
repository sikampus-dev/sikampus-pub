@section('title', 'Ujian Sidang — ' . config('app.name'))
@section('header_title', 'Ujian sidang')
@section('header_subtitle', 'Daftar ujian sidang yang Anda ikuti sebagai dosen penguji (bukan sebagai pembimbing tugas akhir).')

<div class="space-y-4">
    <div class="flex justify-end">
        <div class="w-full sm:w-64">
            <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
            <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
        </div>
    </div>

    @php
        $rows = $this->rows;
        $labelSidang = fn (?string $s) => match ($s) {
            'draft' => 'Draft',
            'submitted' => 'Terkirim',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'returned' => 'Dikembalikan',
            default => $s ?? '—',
        };
        $labelPenguji = fn (?string $s) => match ($s) {
            'draft' => 'Draft',
            'submitted' => 'Terkirim',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => $s ?? '—',
        };
    @endphp

    <div class="rounded-2xl bg-white shadow-border">
        <div class="border-b border-neutral-200 px-6 py-3">
            <h2 class="text-sm font-semibold text-neutral-800">Penugasan sebagai penguji</h2>
            <p class="mt-0.5 text-xs text-neutral-500">{{ count($rows) }} entri</p>
        </div>

        @if (empty($rows))
            <div class="p-10 text-center">
                <i data-lucide="gavel" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
                <p class="font-medium text-neutral-600">Tidak ada data untuk filter ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <th class="px-6 py-3">Mahasiswa</th>
                            <th class="px-6 py-3">Prodi</th>
                            <th class="px-6 py-3">Judul tugas akhir</th>
                            <th class="px-6 py-3">Semester sidang</th>
                            <th class="px-6 py-3">Peran</th>
                            <th class="px-6 py-3">Status sidang</th>
                            <th class="px-6 py-3">Waktu</th>
                            <th class="px-6 py-3">Status penilaian</th>
                            <th class="px-6 py-3">Nilai</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @foreach ($rows as $row)
                            @php $u = $row->ujianSidang; $ta = $u?->tugasAkhir; $m = $ta?->mahasiswa; @endphp
                            <tr wire:key="penguji-{{ $row->id }}" class="hover:bg-neutral-50/70">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-neutral-900">{{ $m?->nama ?? '—' }}</div>
                                    <div class="text-xs text-neutral-500">{{ $m?->nim ?? '—' }}</div>
                                </td>
                                <td class="px-6 py-4 text-neutral-600">
                                    {{ $m?->prodi?->nama ?? '—' }}
                                    @if ($m?->prodi?->kode)
                                        <span class="text-neutral-500">({{ $m->prodi->kode }})</span>
                                    @endif
                                </td>
                                <td class="max-w-[220px] px-6 py-4 text-neutral-800">{{ $ta?->judul ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-neutral-600">
                                    {{ $u?->semester?->nama ?? '—' }}
                                    @if ($u?->semester?->kode)
                                        <span class="block text-xs text-neutral-500">{{ $u->semester->kode }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($row->is_ketua)
                                        <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900">Ketua</span>
                                    @else
                                        <span class="text-neutral-600">Anggota</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-neutral-600">{{ $labelSidang($u?->status) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-neutral-600">{{ $u?->tanggal_ujian_mulai?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-neutral-600">{{ $labelPenguji($row->status) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-neutral-600">{{ $row->nilai ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-center">
                                    <a href="{{ route('dosen.ujian-sidang.show', $row->id) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                                        <i data-lucide="eye" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                        Lihat detail
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
