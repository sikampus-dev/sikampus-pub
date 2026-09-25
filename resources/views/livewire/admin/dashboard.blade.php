@section('title', 'Dashboard — ' . config('app.name'))
@section('header_title', 'Ringkasan Kampus')
@section('header_subtitle', $showAkademik && $showKeuangan
    ? 'Ringkasan aktivitas akademik dan keuangan terkini'
    : ($showKeuangan ? 'Ringkasan aktivitas keuangan terkini' : 'Ringkasan aktivitas akademik terkini'))
@section('header_icon', 'layout-dashboard')

@section('nav')
    @include('admin.partials.nav')
@endsection

<div class="space-y-6">
    @if ($showAkademik)
    <div class="grid gap-4 md:grid-cols-2">
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-pink-500 to-rose-500 p-6 text-white shadow-lg">
            <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_20%_20%,white,transparent_35%),radial-gradient(circle_at_80%_0%,white,transparent_25%)]"></div>
            <div class="relative space-y-3">
                <p class="text-5xl font-semibold leading-tight">{{ $this->krsStats['total_aktif'] }}</p>
                <p class="text-lg">Mahasiswa Aktif</p>
                @if ($this->krsStats['semester'])
                    <p class="text-xs opacity-90">Semester: {{ $this->krsStats['semester']['nama'] }}</p>
                @endif
                @php $statusLainAktif = collect($this->krsStats['status_lain'])->filter(fn ($s) => $s['jumlah'] > 0); @endphp
                @if ($statusLainAktif->isNotEmpty())
                    <p class="text-xs leading-relaxed opacity-80">
                        {{ $statusLainAktif->map(fn ($s) => "{$s['nama']} {$s['jumlah']}")->implode(' · ') }}
                    </p>
                @endif
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-lg font-semibold text-neutral-900">Tautan Singkat</p>
                    <p class="text-sm text-neutral-500">Akses cepat menuju halaman penting</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-500 shadow-border">
                    <i data-lucide="layout-grid" class="h-4 w-4" aria-hidden="true"></i>
                </span>
            </div>

            <div class="mt-4 space-y-2">
                @forelse ($this->quickLinks as $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="group flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold text-neutral-700 shadow-border transition hover:-translate-y-0.5 hover:shadow-sm"
                    >
                        {{ $link['label'] }}
                        <span class="text-sky-500 transition group-hover:translate-x-0.5">↗</span>
                    </a>
                @empty
                    <p class="text-sm text-neutral-500">Belum ada tautan tersedia.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="mb-4">
                <p class="text-lg font-semibold text-neutral-900">Antrian Tindakan</p>
                <p class="text-sm text-neutral-500">Hal-hal yang menunggu keputusan Anda di seluruh modul</p>
            </div>

            @if ($this->antrianTindakan['total'] === 0)
                <div class="flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    <i data-lucide="check-circle-2" class="h-4 w-4" aria-hidden="true"></i>
                    Tidak ada antrian tindakan saat ini.
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($this->antrianTindakan['items'] as $item)
                        @php $perluPerhatian = $item['jumlah'] > 0; @endphp
                        @if ($item['url'])
                            <a
                                href="{{ $item['url'] }}"
                                class="group flex items-center justify-between rounded-xl border px-4 py-3 text-sm transition hover:-translate-y-0.5 hover:shadow-sm {{ $perluPerhatian ? 'border-amber-200 bg-amber-50/60' : 'border-neutral-200 bg-white' }}"
                            >
                        @else
                            <div class="flex items-center justify-between rounded-xl border px-4 py-3 text-sm {{ $perluPerhatian ? 'border-amber-200 bg-amber-50/60' : 'border-neutral-200 bg-white' }}">
                        @endif
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $perluPerhatian ? 'bg-amber-100 text-amber-700' : 'bg-neutral-100 text-neutral-500' }}">
                                        <i data-lucide="{{ $item['icon'] }}" class="h-4 w-4" aria-hidden="true"></i>
                                    </span>
                                    <span class="font-medium text-neutral-700">{{ $item['label'] }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex min-w-[28px] items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums {{ $perluPerhatian ? 'bg-amber-500 text-white' : 'bg-neutral-100 text-neutral-500' }}">
                                        {{ $item['jumlah'] }}
                                    </span>
                                    @if ($item['url'])
                                        <i data-lucide="arrow-right" class="h-3.5 w-3.5 text-neutral-400 transition group-hover:translate-x-0.5" aria-hidden="true"></i>
                                    @endif
                                </div>
                        @if ($item['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="mb-4">
                <p class="text-lg font-semibold text-neutral-900">Nilai Belum Difinalisasi</p>
                <p class="text-sm text-neutral-500">
                    Kelas yang perkuliahannya sudah selesai tapi nilainya belum semua difinalisasi
                    @if ($this->nilaiBelumFinalisasi['semester'])
                        — {{ $this->nilaiBelumFinalisasi['semester']['nama'] }}
                    @endif
                </p>
            </div>

            @if ($this->nilaiBelumFinalisasi['total'] === 0)
                <div class="flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    <i data-lucide="check-circle-2" class="h-4 w-4" aria-hidden="true"></i>
                    Semua kelas yang sudah selesai perkuliahannya sudah difinalisasi nilainya.
                </div>
            @else
                @php $nilaiUrl = $this->nilaiBelumFinalisasi['url']; @endphp
                <div class="space-y-2">
                    @foreach ($this->nilaiBelumFinalisasi['items'] as $item)
                        @if ($nilaiUrl)
                            <a
                                href="{{ $nilaiUrl }}"
                                class="group flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50/60 px-4 py-3 text-sm transition hover:-translate-y-0.5 hover:shadow-sm"
                            >
                        @else
                            <div class="flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50/60 px-4 py-3 text-sm">
                        @endif
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                        <i data-lucide="file-warning" class="h-4 w-4" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-neutral-700">{{ $item['matkul'] }}</p>
                                        <p class="truncate text-xs text-neutral-500">{{ $item['prodi'] ?? '—' }} · {{ $item['jumlah_pertemuan'] }}/{{ $item['jml_pertemuan_target'] }} pertemuan</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="inline-flex items-center justify-center rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold tabular-nums text-white">
                                        {{ $item['jumlah_nilai_final'] }}/{{ $item['jumlah_mahasiswa'] }}
                                    </span>
                                    @if ($nilaiUrl)
                                        <i data-lucide="arrow-right" class="h-3.5 w-3.5 text-neutral-400 transition group-hover:translate-x-0.5" aria-hidden="true"></i>
                                    @endif
                                </div>
                        @if ($nilaiUrl)
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach

                    @if ($this->nilaiBelumFinalisasi['total'] > count($this->nilaiBelumFinalisasi['items']))
                        <p class="pt-1 text-center text-xs text-neutral-500">
                            +{{ $this->nilaiBelumFinalisasi['total'] - count($this->nilaiBelumFinalisasi['items']) }} kelas lainnya
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-neutral-900">Jumlah Mahasiswa 3 Tahun Terakhir per Prodi</h2>
            <p class="text-sm text-neutral-500">Berdasarkan semester masuk</p>
        </div>

        @if (! $this->mahasiswaPerProdi['has_data'])
            <div class="py-12 text-center">
                <p class="text-sm text-neutral-500">Tidak ada data untuk ditampilkan</p>
            </div>
        @else
            @php $chart = $this->mahasiswaPerProdi; @endphp
            <div class="flex gap-3">
                <div class="flex shrink-0 flex-col justify-between pb-[26px] text-right text-[11px] text-neutral-400" style="height: {{ $chart['chart_height'] + 26 }}px">
                    @foreach ($chart['ticks'] as $tick)
                        <span class="tabular-nums">{{ number_format($tick, 0, ',', '.') }}</span>
                    @endforeach
                </div>

                <div class="min-w-0 flex-1 overflow-x-auto pt-10">
                    <div class="relative" style="min-width: {{ count($chart['series']) * count($chart['tahun_list']) * 18 }}px">
                        <div class="absolute left-0 right-0 top-0 flex flex-col justify-between" style="height: {{ $chart['chart_height'] }}px">
                            @foreach ($chart['ticks'] as $tick)
                                <div class="border-t border-neutral-100"></div>
                            @endforeach
                        </div>

                        <div class="relative flex items-end justify-around gap-8 px-2">
                            @foreach ($chart['tahun_list'] as $tahunItem)
                                <div class="flex flex-col items-center gap-2">
                                    <div class="flex items-end gap-[2px]" style="height: {{ $chart['chart_height'] }}px">
                                        @foreach ($chart['series'] as $prodi)
                                            @php $bar = $prodi['bars'][$tahunItem['tahun']]; @endphp
                                            <div class="group relative flex w-4 flex-col items-center justify-end" style="height: {{ $chart['chart_height'] }}px">
                                                <div class="invisible absolute -top-1 z-10 -translate-y-full whitespace-nowrap rounded-lg bg-neutral-900 px-2.5 py-1.5 text-xs text-white opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100">
                                                    <span class="font-semibold tabular-nums">{{ number_format($bar['value'], 0, ',', '.') }}</span>
                                                    <span class="ml-1.5 text-neutral-300">{{ $prodi['nama_prodi'] }} · {{ $tahunItem['label'] }}</span>
                                                </div>
                                                <div class="w-full rounded-t-[4px] opacity-90 transition-opacity group-hover:opacity-100" style="height: {{ $bar['height_px'] }}px; background-color: {{ $prodi['color'] }}"></div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <span class="text-xs font-medium text-neutral-600">{{ $tahunItem['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-x-4 gap-y-2 border-t border-neutral-200 pt-4">
                @foreach ($chart['series'] as $prodi)
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $prodi['color'] }}"></span>
                        <span class="text-xs text-neutral-600">{{ $prodi['nama_prodi'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    @if ($showKeuangan)
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900">Ringkasan Keuangan</h2>
                <p class="text-sm text-neutral-500">Total tagihan, pembayaran, dan piutang berjalan</p>
            </div>
            <div class="w-full sm:w-56">
                <label class="mb-1 block text-xs font-semibold text-neutral-700">Periode</label>
                <x-searchable-select model="filterSemesterKeuangan" :options="$this->semesterOptionsKeuangan" :live="true" placeholder="Semua periode" />
            </div>
        </div>

        @php $ringkasan = $this->keuanganStats['ringkasan']; @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl bg-white p-5 shadow-border">
                <p class="text-sm text-neutral-500">Total Tagihan</p>
                <p class="mt-1 text-2xl font-semibold text-neutral-900">Rp{{ number_format($ringkasan['total_tagihan'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-border">
                <p class="text-sm text-neutral-500">Total Terbayar</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">Rp{{ number_format($ringkasan['total_terbayar'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-border">
                <p class="text-sm text-neutral-500">Piutang Berjalan</p>
                <p class="mt-1 text-2xl font-semibold text-amber-600">Rp{{ number_format($ringkasan['total_piutang'], 0, ',', '.') }}</p>
                @if ($ringkasan['total_keringanan'] > 0)
                    <p class="text-xs text-neutral-500">
                        Setelah keringanan Rp{{ number_format($ringkasan['total_keringanan'], 0, ',', '.') }}
                    </p>
                @endif
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-border">
                <p class="text-sm text-neutral-500">Menunggu Approval</p>
                <p class="mt-1 text-2xl font-semibold text-sky-600">Rp{{ number_format($ringkasan['pembayaran_menunggu_approval_total'], 0, ',', '.') }}</p>
                <p class="text-xs text-neutral-500">{{ $ringkasan['pembayaran_menunggu_approval_count'] }} pembayaran</p>
            </div>
        </div>

        @if ($ringkasan['pembayaran_menunggu_approval_count'] > 0 && \Illuminate\Support\Facades\Route::has('admin.keuangan.pembayaran'))
            <a
                href="{{ route('admin.keuangan.pembayaran') }}"
                class="group flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50/60 px-4 py-3 text-sm text-amber-800 transition hover:-translate-y-0.5 hover:shadow-sm"
            >
                <span>{{ $ringkasan['pembayaran_menunggu_approval_count'] }} pembayaran menunggu approval admin.</span>
                <i data-lucide="arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" aria-hidden="true"></i>
            </a>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <p class="text-lg font-semibold text-neutral-900">Status Tagihan</p>
                {{-- Kosakata & angkanya sengaja sama persis dengan halaman daftar tagihan: keduanya
                     memakai StatusPembayaranTagihan, bukan kolom `tagihan.status`. --}}
                <p class="mb-4 text-xs text-neutral-500">Berdasarkan pembayaran disetujui dan keringanan biaya</p>
                <div class="space-y-2">
                    <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 text-sm">
                        <span class="font-medium text-emerald-800">Lunas</span>
                        <span class="font-semibold text-emerald-800">{{ $ringkasan['jumlah_tagihan_lunas'] }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-sky-50 px-4 py-3 text-sm">
                        <span class="font-medium text-sky-900">Dibayar sebagian</span>
                        <span class="font-semibold text-sky-900">{{ $ringkasan['jumlah_tagihan_dibayar_sebagian'] }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-amber-50 px-4 py-3 text-sm">
                        <span class="font-medium text-amber-900">Belum bayar</span>
                        <span class="font-semibold text-amber-900">{{ $ringkasan['jumlah_tagihan_belum_bayar'] }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-rose-50 px-4 py-3 text-sm">
                        <span class="font-medium text-rose-800">Kedaluwarsa</span>
                        <span class="font-semibold text-rose-800">{{ $ringkasan['jumlah_tagihan_kedaluwarsa'] }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <p class="mb-4 text-lg font-semibold text-neutral-900">Tren Pembayaran 6 Bulan Terakhir</p>
                @php $tren = $this->keuanganStats['tren_bulanan']; $chartH = $this->keuanganStats['chart_height']; @endphp
                <div class="flex items-end justify-between gap-3" style="height: {{ $chartH + 24 }}px">
                    @foreach ($tren as $bulan)
                        <div class="group relative flex flex-1 flex-col items-center justify-end gap-2">
                            <div class="invisible absolute -top-1 z-10 -translate-y-full whitespace-nowrap rounded-lg bg-neutral-900 px-2.5 py-1.5 text-xs text-white opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100">
                                Rp{{ number_format($bulan['total'], 0, ',', '.') }}
                            </div>
                            <div class="w-full rounded-t-[4px] bg-emerald-500 opacity-90 transition-opacity group-hover:opacity-100" style="height: {{ $bulan['height_px'] }}px"></div>
                            <span class="text-xs text-neutral-500">{{ $bulan['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <p class="mb-4 text-lg font-semibold text-neutral-900">Akses Cepat Keuangan</p>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach ($this->keuanganQuickLinks as $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="group flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold text-neutral-700 shadow-border transition hover:-translate-y-0.5 hover:shadow-sm"
                    >
                        {{ $link['label'] }}
                        <span class="text-sky-500 transition group-hover:translate-x-0.5">↗</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
