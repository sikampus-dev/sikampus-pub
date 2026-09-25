@section('title', 'RPS — ' . config('app.name'))
@section('header_title', 'Rencana Pembelajaran Semester (RPS)')
@section('header_subtitle', 'Kelola RPS untuk kelas dimana Anda menjadi dosen penanggung jawab (PIC)')

<div class="space-y-4">
    <div class="flex justify-end">
        <div class="w-full sm:w-64">
            <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
            <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
        </div>
    </div>

    @php $rows = $this->rows; @endphp

    <div class="rounded-2xl bg-white shadow-border">
        @if (empty($rows))
            <div class="p-10 text-center">
                <i data-lucide="clipboard-list" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
                <p class="font-medium text-neutral-600">Tidak ada kelas</p>
                <p class="mt-1 text-sm text-neutral-500">Anda belum menjadi dosen penanggung jawab (PIC) untuk kelas mana pun pada semester ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <th class="px-6 py-3">No</th>
                            <th class="px-6 py-3">Kode kelas</th>
                            <th class="px-6 py-3">Mata kuliah</th>
                            <th class="px-6 py-3">Program studi</th>
                            <th class="px-6 py-3">Semester</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @foreach ($rows as $idx => $kelas)
                            @php $km = $kelas->kurikulumMatkul; @endphp
                            <tr wire:key="kelas-{{ $kelas->id }}" class="hover:bg-neutral-50/70">
                                <td class="px-6 py-4 text-neutral-900">{{ $idx + 1 }}</td>
                                <td class="px-6 py-4 font-medium text-sky-700">{{ $kelas->kode }}</td>
                                <td class="min-w-[200px] px-6 py-4">
                                    <div class="font-medium text-neutral-900">{{ $km?->namaMatkulLabel() ?? '—' }}</div>
                                    <div class="text-xs text-neutral-500">{{ $km?->kodeMatkulLabel() ?? '—' }}</div>
                                </td>
                                <td class="px-6 py-4 text-neutral-600">
                                    @if ($kelas->prodi)
                                        <div>{{ $kelas->prodi->nama }}</div>
                                        @if ($kelas->prodi->jenjang)
                                            <div class="text-xs text-neutral-500">{{ $kelas->prodi->jenjang->nama }}</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-neutral-600">{{ $kelas->semester?->nama ?? '—' }}</td>
                                <td class="px-6 py-4 text-center">
                                    <a href="{{ route('dosen.rps.show', $kelas->id) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                                        <i data-lucide="clipboard-list" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                        Kelola RPS
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
