@section('title', 'KRS — ' . config('app.name'))
@section('header_title', 'KRS')
@section('header_subtitle', 'Data KRS mahasiswa program studi Anda')

<div>
    <div class="rounded-2xl bg-white shadow-border">
        <div class="space-y-4 border-b border-neutral-200 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Periode Semester</label>
                    <x-searchable-select
                        model="filterSemester"
                        :live="true"
                        :options="$this->semesterOptions"
                        placeholder="Semua semester"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Angkatan (Semester Masuk)</label>
                    <x-searchable-select
                        model="filterAngkatan"
                        :live="true"
                        :options="$this->semesterOptions"
                        placeholder="Semua angkatan"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-neutral-700">Kelompok Kelas</label>
                    <x-searchable-select
                        model="filterKelompokKelas"
                        :live="true"
                        :options="$this->kelompokKelasOptions"
                        placeholder="Semua kelompok kelas"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">NIM</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Prodi</th>
                        <th class="px-4 py-3">Dosen Wali</th>
                        <th class="px-4 py-3 text-right">SKS Diajukan</th>
                        <th class="px-4 py-3 text-right">SKS Disetujui</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($krsList as $row)
                        <tr wire:key="krs-mhs-{{ $row['id_mahasiswa'] }}">
                            <td class="px-4 py-3 font-mono font-medium text-neutral-900">{{ $row['nim'] }}</td>
                            <td class="px-4 py-3 text-neutral-900">{{ $row['nama'] }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $row['prodi_nama'] }}
                                @if ($row['jenjang_kode'])
                                    <span class="text-neutral-400">({{ $row['jenjang_kode'] }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ $row['dosen_wali'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $row['sks_diajukan'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900">{{ $row['sks_diacc'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="openDetailModal({{ $row['id_mahasiswa'] }})"
                                    class="inline-flex items-center justify-center rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900"
                                    title="Lihat detail KRS"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Belum ada data KRS.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-neutral-200 p-4">
            {{ $krsList->links() }}
        </div>
    </div>

    {{-- Modal: Detail KRS Mahasiswa (dikelompokkan per semester) --}}
    @if ($detailMahasiswaId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4 py-8">
            <div class="flex max-h-full w-full max-w-3xl flex-col rounded-2xl bg-white shadow-border-lg">
                <div class="flex items-center justify-between gap-3 border-b border-neutral-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">Detail KRS Mahasiswa</h3>
                    <button type="button" wire:click="closeDetailModal" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4">
                    @if (! $this->detailMahasiswa)
                        <p class="py-6 text-center text-sm text-neutral-500">Mahasiswa tidak ditemukan.</p>
                    @else
                        <div class="rounded-xl bg-neutral-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Mahasiswa</p>
                            <p class="mt-1 font-semibold text-neutral-900">
                                {{ $this->detailMahasiswa->nama }}
                                <span class="font-normal text-neutral-600">({{ $this->detailMahasiswa->nim }})</span>
                            </p>
                            <p class="text-sm text-neutral-600">
                                {{ $this->detailMahasiswa->prodi?->nama ?? '—' }}
                                @if ($this->detailMahasiswa->semester_masuk?->kode)
                                    <span class="text-neutral-500">&middot; Angkatan {{ $this->detailMahasiswa->semester_masuk->kode }}</span>
                                @endif
                            </p>
                        </div>

                        @if (empty($this->detailKrsBySemester))
                            <p class="py-6 text-center text-sm text-neutral-500">Belum ada data KRS.</p>
                        @else
                            <div class="mt-4 space-y-4">
                                @foreach ($this->detailKrsBySemester as $block)
                                    <div class="overflow-hidden rounded-xl shadow-border">
                                        <div class="flex items-center justify-between bg-neutral-100 px-4 py-2">
                                            <span class="font-medium text-neutral-800">{{ $block['semester']['kode'] }} {{ $block['semester']['nama'] }}</span>
                                            <span class="text-sm text-neutral-600">SKS: {{ $block['total_sks_diacc'] }} / {{ $block['total_sks_diajukan'] }}</span>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-sm">
                                                <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                                    <tr>
                                                        <th class="px-4 py-2">Mata Kuliah</th>
                                                        <th class="px-4 py-2">Kelas</th>
                                                        <th class="px-4 py-2">Dosen</th>
                                                        <th class="px-4 py-2 text-right">SKS</th>
                                                        <th class="px-4 py-2 text-center">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-neutral-100">
                                                    @foreach ($block['krs'] as $item)
                                                        <tr wire:key="detail-krs-{{ $item['id'] }}">
                                                            <td class="px-4 py-2 text-neutral-900">
                                                                {{ $item['matkul_nama'] ?? $item['matkul_kode'] ?? '—' }}
                                                                @if ($item['matkul_kode'])
                                                                    <span class="text-neutral-500">({{ $item['matkul_kode'] }})</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2 text-neutral-600">{{ $item['kelas_nama'] ?? '—' }}</td>
                                                            <td class="px-4 py-2 text-neutral-600">{{ $item['dosen_nama'] ?? '—' }}</td>
                                                            <td class="px-4 py-2 text-right tabular-nums text-neutral-700">{{ $item['sks'] }}</td>
                                                            <td class="px-4 py-2 text-center">
                                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $item['status'] === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">
                                                                    {{ $item['status'] === 'approved' ? 'Disetujui' : 'Pending' }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
