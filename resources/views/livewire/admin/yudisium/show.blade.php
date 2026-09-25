@php
    $y = $this->yudisium;
    $formatIpk = function ($ipk) {
        if ($ipk === null) {
            return '—';
        }

        return number_format((float) $ipk, 2);
    };
@endphp

@section('title', 'Detail Yudisium — ' . config('app.name'))
@section('header_title', 'Detail Yudisium')
@section('header_subtitle', $y->mahasiswa?->nama)
@section('header_icon', 'award')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Yudisium', 'route' => route('admin.akademik.yudisium')],
        ['label' => $y->mahasiswa?->nama ?? 'Detail'],
    ]])
@endsection

<div class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Informasi Mahasiswa</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p class="mb-1 text-xs text-neutral-500">Nama</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->mahasiswa?->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">NIM</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->mahasiswa?->nim ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Email</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->mahasiswa?->email ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">No. HP</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->mahasiswa?->handphone ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Program Studi</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->mahasiswa?->prodi?->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Semester Masuk</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->mahasiswa?->semester_masuk?->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Status Akademik</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->mahasiswa?->status_akademik?->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Kelompok Kelas</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->mahasiswa?->kelompok_kelas?->nama ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-4 text-base font-semibold text-neutral-900">Informasi Yudisium</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p class="mb-1 text-xs text-neutral-500">Jenis Keluar</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->jenis_keluar?->nama ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Tanggal Keluar</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->tgl_keluar ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">IPK</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $formatIpk($y->ipk) }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">No. Ijazah</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->no_ijazah ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">No. SK Yudisium</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->no_sk_yudisium ?? '—' }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs text-neutral-500">Tanggal SK Yudisium</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->tanggal_sk_yudisium ?? '—' }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="mb-1 text-xs text-neutral-500">Judul Skripsi</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->judul_skripsi ?? '—' }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="mb-1 text-xs text-neutral-500">Keterangan</p>
                <p class="text-sm font-semibold text-neutral-900">{{ $y->keterangan ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>
