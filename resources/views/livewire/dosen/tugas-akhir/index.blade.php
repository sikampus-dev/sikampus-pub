@section('title', 'Tugas Akhir — ' . config('app.name'))
@section('header_title', 'Tugas akhir')
@section('header_subtitle', 'Daftar tugas akhir yang Anda bimbing sebagai dosen pembimbing dengan judul yang sudah disetujui.')

<div class="space-y-4">
    <div class="flex justify-end">
        <div class="w-full sm:w-64">
            <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
            <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
        </div>
    </div>

    @php $rows = $this->rows; @endphp

    <div class="rounded-2xl bg-white shadow-border">
        <div class="border-b border-neutral-200 px-6 py-3">
            <h2 class="text-sm font-semibold text-neutral-800">Judul disetujui — Anda sebagai pembimbing</h2>
            <p class="mt-0.5 text-xs text-neutral-500">{{ count($rows) }} entri</p>
        </div>

        @if (empty($rows))
            <div class="p-10 text-center">
                <i data-lucide="book-open" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
                <p class="font-medium text-neutral-600">Tidak ada data untuk filter ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <th class="px-6 py-3">Mahasiswa</th>
                            <th class="px-6 py-3">Program studi</th>
                            <th class="px-6 py-3">Judul</th>
                            <th class="px-6 py-3">Semester TA</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @foreach ($rows as $row)
                            <tr wire:key="ta-{{ $row->id }}" class="hover:bg-neutral-50/70">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-neutral-900">{{ $row->mahasiswa?->nama ?? '—' }}</div>
                                    <div class="text-xs text-neutral-500">{{ $row->mahasiswa?->nim ?? '—' }}</div>
                                </td>
                                <td class="px-6 py-4 text-neutral-600">
                                    {{ $row->mahasiswa?->prodi?->nama ?? '—' }}
                                    @if ($row->mahasiswa?->prodi?->kode)
                                        <span class="text-neutral-500">({{ $row->mahasiswa->prodi->kode }})</span>
                                    @endif
                                </td>
                                <td class="max-w-md px-6 py-4 text-neutral-800">{{ $row->judul }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-neutral-600">
                                    {{ $row->semester?->nama ?? '—' }}
                                    @if ($row->semester?->kode)
                                        <span class="block text-xs text-neutral-500">{{ $row->semester->kode }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="{{ route('dosen.tugas-akhir.show', $row->id) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
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
