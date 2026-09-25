@section('title', 'Kehadiran — ' . config('app.name'))
@section('header_title', 'Kehadiran')
@section('header_subtitle', 'Daftar pertemuan per kelas yang Anda ampu — pilih pertemuan untuk mengisi kehadiran mahasiswa.')

<div class="space-y-4">
    <div class="flex justify-end">
        <div class="w-full sm:w-64">
            <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
            <x-searchable-select model="filterSemester" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
        </div>
    </div>

    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @php $rows = $this->rows; @endphp

    @if (empty($rows))
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="clipboard-list" class="mx-auto mb-4 h-10 w-10 text-neutral-300" aria-hidden="true"></i>
            <p class="font-medium text-neutral-600">Belum ada perkuliahan</p>
            <p class="mt-1 text-sm text-neutral-500">Belum ada perkuliahan yang dibuat untuk semester ini.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($rows as $row)
                @php $kelas = $row['kelas']; $km = $kelas->kurikulumMatkul; @endphp
                <div class="rounded-2xl bg-white p-6 shadow-border">
                    <div class="mb-4">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="rounded-lg bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $km?->kodeMatkulLabel() ?? '-' }}</span>
                                <span class="text-xs text-neutral-500">{{ $km?->sksLabel() ?? 0 }} SKS</span>
                            </div>
                            <button type="button" wire:click="openRekapModal({{ $kelas->id }})" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-border hover:bg-neutral-50">
                                <i data-lucide="eye" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                Lihat rekap
                            </button>
                        </div>
                        <h3 class="mb-2 text-lg font-semibold text-neutral-900">{{ $km?->namaMatkulLabel() ?? '-' }}</h3>
                        @if ($kelas->prodi)
                            <div class="flex items-center gap-2 text-xs text-neutral-600">
                                <i data-lucide="graduation-cap" class="h-3.5 w-3.5 text-neutral-400" aria-hidden="true"></i>
                                <span>{{ $kelas->prodi->nama }}{{ $kelas->prodi->jenjang ? ' (' . $kelas->prodi->jenjang->nama . ')' : '' }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="space-y-2">
                        @foreach ($row['perkuliahan'] as $item)
                            <a href="{{ route('dosen.kehadiran.detail', $item['id']) }}" class="group block rounded-xl px-4 py-4 shadow-border transition hover:border-sky-300 hover:shadow-md">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1">
                                        <span class="mb-2 inline-block rounded-lg bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">Pertemuan {{ $item['pertemuan_ke'] }}</span>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2 text-sm text-neutral-600">
                                                <i data-lucide="calendar" class="h-4 w-4 text-neutral-400" aria-hidden="true"></i>
                                                <span>{{ $item['tanggal'] ? \Illuminate\Support\Carbon::parse($item['tanggal'])->translatedFormat('l, d F Y') : '—' }}</span>
                                            </div>
                                            <div class="flex items-center gap-4 text-sm text-neutral-700">
                                                <div class="flex items-center gap-2">
                                                    <i data-lucide="users" class="h-4 w-4 text-neutral-400" aria-hidden="true"></i>
                                                    <span><span class="font-medium">{{ $item['jumlah_hadir'] }}</span><span class="text-neutral-500"> / {{ $row['jumlah_mahasiswa'] }}</span></span>
                                                </div>
                                                <span class="text-neutral-500">Mahasiswa hadir</span>
                                            </div>
                                        </div>
                                    </div>
                                    <i data-lucide="arrow-right" class="h-5 w-5 shrink-0 text-neutral-400 transition group-hover:text-sky-600" aria-hidden="true"></i>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($showRekapModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-neutral-900/40 p-4">
            <div class="my-8 w-full max-w-7xl rounded-2xl bg-white shadow-border-lg">
                <div class="sticky top-0 z-10 flex items-center justify-between rounded-t-2xl border-b border-neutral-200 bg-white px-6 py-4">
                    <h3 class="text-lg font-semibold text-neutral-900">Laporan Kehadiran Mahasiswa</h3>
                    <button type="button" wire:click="closeRekapModal" class="rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                        <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="p-6">
                    @php $rekap = $this->rekap; @endphp
                    @if ($rekap)
                        @include('livewire.dosen.kehadiran.partials.rekap-table', ['rekap' => $rekap])
                    @else
                        <p class="py-8 text-center text-neutral-500">Data rekap tidak tersedia.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
