@section('title', 'Dashboard — ' . config('app.name'))
@section('header_title', 'Dashboard')
@section('header_subtitle', 'Selamat datang, ' . $this->namaLengkapDosen)

<div class="space-y-6">
    @if (count($this->quickLinks) > 0)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($this->quickLinks as $link)
                @php
                    $colorClasses = [
                        'sky' => 'bg-sky-100 text-sky-600',
                        'emerald' => 'bg-emerald-100 text-emerald-600',
                        'pink' => 'bg-pink-100 text-pink-600',
                        'amber' => 'bg-amber-100 text-amber-600',
                    ][$link['color']] ?? 'bg-neutral-100 text-neutral-600';
                @endphp
                <a href="{{ $link['url'] }}" class="rounded-2xl bg-white p-6 shadow-border transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $colorClasses }}">
                            <i data-lucide="{{ $link['icon'] }}" class="h-6 w-6" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm text-neutral-500">{{ $link['section'] }}</p>
                            <p class="truncate text-base font-semibold text-neutral-900">{{ $link['label'] }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-lg font-semibold text-neutral-900">Informasi Dosen</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-neutral-500">Status:</span>
                    <span class="font-semibold text-neutral-900">Aktif</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-500">Email:</span>
                    <span class="font-semibold text-neutral-900">{{ auth()->user()->email ?? '—' }}</span>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-lg font-semibold text-neutral-900">Status Persetujuan KRS</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-neutral-500">Total SKS diajukan:</span>
                    <span class="font-semibold text-neutral-900">{{ $this->krsStats['diajukan'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-500">SKS disetujui:</span>
                    <span class="font-semibold text-emerald-700">{{ $this->krsStats['disetujui'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-500">SKS belum disetujui:</span>
                    <span class="font-semibold text-amber-700">{{ $this->krsStats['belum_disetujui'] }}</span>
                </div>
            </div>
            @if (\Illuminate\Support\Facades\Route::has('dosen.krs'))
                <a
                    href="{{ route('dosen.krs') }}"
                    class="mt-4 inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm text-neutral-500 shadow-border hover:bg-neutral-100 hover:text-neutral-700"
                >
                    Lihat semua pengajuan KRS
                    <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
                </a>
            @endif
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-neutral-900">Jadwal Mengajar Minggu Ini</h3>
            @if (\Illuminate\Support\Facades\Route::has('dosen.jadwal'))
                <a
                    href="{{ route('dosen.jadwal') }}"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm text-neutral-500 shadow-border hover:bg-neutral-100 hover:text-neutral-700"
                >
                    Lihat jadwal lengkap
                    <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
                </a>
            @endif
        </div>

        @if (count($this->jadwalMingguIni) === 0)
            <p class="text-sm text-neutral-500">Belum ada jadwal mengajar minggu ini.</p>
        @else
            @php
                $hariLabel = ['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu', 'minggu' => 'Minggu'];
            @endphp
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-neutral-600">
                            <th class="px-3 py-2.5 font-semibold">Hari</th>
                            <th class="px-3 py-2.5 font-semibold">Waktu</th>
                            <th class="px-3 py-2.5 font-semibold">Mata Kuliah</th>
                            <th class="px-3 py-2.5 font-semibold">Kelas</th>
                            <th class="px-3 py-2.5 font-semibold">Ruangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($this->jadwalMingguIni as $item)
                            <tr class="hover:bg-neutral-50/70">
                                <td class="px-3 py-2.5 text-neutral-700">{{ $hariLabel[strtolower((string) $item['hari'])] ?? $item['hari'] ?? '-' }}</td>
                                <td class="px-3 py-2.5 font-medium text-neutral-800">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-neutral-600">{{ $item['tanggal']->translatedFormat('d F Y') }}</span>
                                        <span>{{ $item['jam_mulai'] ?? '-' }} – {{ $item['jam_selesai'] ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-neutral-800">
                                    <span class="font-medium">{{ $item['kode_matkul'] ?? '-' }}</span> - {{ $item['nama_matkul'] }}
                                </td>
                                <td class="px-3 py-2.5 text-neutral-700">{{ $item['nama_kelas'] ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-neutral-700">{{ $item['nama_ruangan'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
