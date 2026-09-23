@php
    // Struktur & urutan menu meniru MahasiswaSidebar.tsx di siak-frontend
    // (../siak-frontend/components/mahasiswa/MahasiswaSidebar.tsx). Rute yang modulnya belum
    // diport ('mahasiswa.coming-soon' lewat Route::view) tetap didaftarkan supaya sidebar sudah
    // lengkap sejak awal — cukup ganti target rute saat modulnya dibangun.
    $mahasiswaNavItems = [
        ['route' => 'mahasiswa.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
        // Kalender Akademik: modul baru, tidak ada di siak-frontend — sengaja tidak mengikuti
        // urutan MahasiswaSidebar.tsx di atas.
        ['route' => 'mahasiswa.kalender-akademik', 'label' => 'Kalender Akademik', 'icon' => 'calendar-days'],
        ['label' => 'Perkuliahan', 'icon' => 'calendar', 'children' => [
            ['route' => 'mahasiswa.jadwal', 'label' => 'Jadwal'],
            ['route' => 'mahasiswa.kehadiran', 'label' => 'Kehadiran'],
        ]],
        ['label' => 'Rencana Studi', 'icon' => 'book-open', 'children' => [
            ['route' => 'mahasiswa.krs.pengajuan', 'label' => 'Pengajuan KRS'],
            ['route' => 'mahasiswa.krs', 'label' => 'KRS'],
        ]],
        ['route' => 'mahasiswa.bimbingan-akademik', 'label' => 'Perwalian', 'icon' => 'users'],
        ['label' => 'Nilai', 'icon' => 'file-text', 'children' => [
            ['route' => 'mahasiswa.nilai.semester', 'label' => 'Nilai Semester'],
            ['route' => 'mahasiswa.nilai.transkrip', 'label' => 'Transkrip'],
        ]],
        ['label' => 'Akhir Studi', 'icon' => 'book-open', 'children' => [
            ['route' => 'mahasiswa.akhir-studi.tugas-akhir', 'label' => 'Tugas Akhir'],
            ['route' => 'mahasiswa.akhir-studi.bimbingan-tugas-akhir', 'label' => 'Bimbingan Tugas Akhir'],
            ['route' => 'mahasiswa.akhir-studi.ujian-sidang', 'label' => 'Ujian Sidang'],
            ['route' => 'mahasiswa.akhir-studi.yudisium-wisuda', 'label' => 'Yudisium & Wisuda'],
        ]],
        ['label' => 'Biaya', 'icon' => 'receipt', 'children' => [
            ['route' => 'mahasiswa.tagihan', 'label' => 'Tagihan'],
            ['route' => 'mahasiswa.pembayaran', 'label' => 'Pembayaran'],
            ['route' => 'mahasiswa.keringanan-biaya', 'label' => 'Keringanan Biaya'],
        ]],
        ['route' => 'mahasiswa.survey', 'label' => 'Survey', 'icon' => 'clipboard-list'],
        ['route' => 'mahasiswa.ktm', 'label' => 'KTM', 'icon' => 'id-card'],
        ['label' => 'Akun', 'icon' => 'user', 'children' => [
            ['route' => 'mahasiswa.profil', 'label' => 'Profil'],
            ['route' => 'mahasiswa.biodata', 'label' => 'Biodata'],
        ]],
    ];

    $isChildActive = fn (array $child) => request()->routeIs($child['route'].'*');
    $isItemActive = function (array $item) use ($isChildActive) {
        if (isset($item['children'])) {
            return collect($item['children'])->contains($isChildActive);
        }

        return request()->routeIs($item['route'].'*');
    };
@endphp

@foreach ($mahasiswaNavItems as $item)
    @if (isset($item['children']))
        @php $itemActive = $isItemActive($item); @endphp
        {{-- <details> native (bukan Alpine) — sidebar dirender di setiap halaman mahasiswa,
             termasuk yang belum tentu memuat komponen Livewire, jadi tidak boleh bergantung
             padanya. --}}
        <details class="group" @if ($itemActive) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemActive ? 'bg-sky-50 text-sky-700' : 'text-neutral-700 hover:bg-neutral-100' }}">
                <span class="flex items-center gap-3">
                    <i data-lucide="{{ $item['icon'] }}" class="h-5 w-5 {{ $itemActive ? 'text-sky-600' : 'text-neutral-500' }}" aria-hidden="true"></i>
                    {{ $item['label'] }}
                </span>
                <i data-lucide="chevron-right" class="h-4 w-4 text-neutral-400 transition group-open:rotate-90" aria-hidden="true"></i>
            </summary>
            <div class="mt-1 ml-4 space-y-1 border-l-2 border-neutral-200 pl-4">
                @foreach ($item['children'] as $child)
                    <a
                        href="{{ route($child['route']) }}"
                        class="block rounded-lg px-3 py-2 text-sm transition {{ $isChildActive($child) ? 'bg-sky-50 font-medium text-sky-700' : 'text-neutral-600 hover:bg-neutral-100' }}"
                    >
                        {{ $child['label'] }}
                    </a>
                @endforeach
            </div>
        </details>
    @else
        @php $itemActive = $isItemActive($item); @endphp
        <a
            href="{{ route($item['route']) }}"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $itemActive ? 'bg-sky-50 text-sky-700' : 'text-neutral-700 hover:bg-neutral-100' }}"
        >
            <i data-lucide="{{ $item['icon'] }}" class="h-5 w-5 {{ $itemActive ? 'text-sky-600' : 'text-neutral-500' }}" aria-hidden="true"></i>
            {{ $item['label'] }}
        </a>
    @endif
@endforeach
