@section('title', 'Biodata — ' . config('app.name'))
@section('header_title', 'Biodata')
@section('header_subtitle', 'Data diri lengkap Anda yang tercatat di sistem akademik.')

{{-- Tombol di bawah ini tautan biasa (tanpa wire:*), jadi aman diletakkan di @section('page_actions')
     — section itu dirender layout di luar subtree yang di-hydrate Livewire, sehingga hanya konten
     statis yang boleh tinggal di sana (lihat catatan di livewire/mahasiswa/krs/index.blade.php). --}}
@section('page_actions')
    <a
        href="{{ route('mahasiswa.biodata.edit') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-sky-700"
    >
        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>
        Edit Biodata
    </a>
@endsection

@php
    $mhs = $this->mahasiswa;
    $textOrDash = fn ($v) => $v !== null && trim((string) $v) !== '' ? $v : '—';
    $refNama = fn ($ref) => $ref?->nama ?? '—';
    $tanggal = fn ($d) => $d ? $d->translatedFormat('d F Y') : '—';

    $sections = [
        'Identitas' => [
            ['NIM', $mhs->nim], ['Nama', $mhs->nama], ['Email', $mhs->email],
            ['Jenis kelamin', match ($mhs->jenis_kelamin) { 'L' => 'Laki-laki', 'P' => 'Perempuan', default => null }],
            ['Tempat lahir', $mhs->id_tempat_lahir], ['Tanggal lahir', $tanggal($mhs->tanggal_lahir)],
            ['No. KTP', $mhs->no_ktp], ['No. WhatsApp', $mhs->no_wa], ['Handphone', $mhs->handphone],
        ],
        'Alamat & wilayah' => [
            ['Alamat', $mhs->alamat], ['RT / RW', ($mhs->rt ?: '—').' / '.($mhs->rw ?: '—')],
            ['Dusun', $mhs->dusun], ['Kelurahan', $mhs->kelurahan], ['Kecamatan', $mhs->id_kecamatan],
            ['Kota/Kabupaten', $refNama($mhs->kota)], ['Provinsi', $refNama($mhs->provinsi)],
            ['Negara', $refNama($mhs->negara)], ['Kode pos', $mhs->kode_pos],
        ],
        'Akademik' => [
            ['Program studi', $mhs->prodi ? $mhs->prodi->nama.($mhs->prodi->jenjang?->nama ? ' · '.$mhs->prodi->jenjang->nama : '') : null],
            ['Status akademik', $refNama($mhs->status_akademik)],
            ['Semester masuk', $mhs->semester_masuk ? trim(($mhs->semester_masuk->kode ?: '').' — '.$mhs->semester_masuk->nama, ' —') : null],
            ['Kelas mahasiswa', $refNama($mhs->kelompok_kelas)], ['Jalur masuk', $refNama($mhs->jalur_masuk)],
            ['Jenis daftar', $refNama($mhs->jenis_daftar)], ['Mulai semester', $mhs->mulai_semester],
            ['SKS diakui', $mhs->sks_diakui],
        ],
        'Sekolah asal & lainnya' => [
            ['Sekolah asal', $mhs->sekolah_asal], ['NIS', $mhs->nis], ['NISN', $mhs->nisn],
            ['NPWP', $mhs->npwp], ['Penerima KPS', $mhs->penerima_kps], ['No. KPS', $mhs->no_kps],
        ],
        'Orang tua / wali' => [
            ['Nama ayah', $mhs->ayah], ['NIK ayah', $mhs->nik_ayah], ['Tgl lahir ayah', $tanggal($mhs->tgl_lahir_ayah)],
            ['Pendidikan ayah', $refNama($mhs->pendidikan_ayah)], ['Pekerjaan ayah', $refNama($mhs->pekerjaan_ayah)], ['Penghasilan ayah', $refNama($mhs->penghasilan_ayah)],
            ['Nama ibu', $mhs->ibu], ['NIK ibu', $mhs->nik_ibu], ['Tgl lahir ibu', $tanggal($mhs->tgl_lahir_ibu)],
            ['Pendidikan ibu', $refNama($mhs->pendidikan_ibu)], ['Pekerjaan ibu', $refNama($mhs->pekerjaan_ibu)], ['Penghasilan ibu', $refNama($mhs->penghasilan_ibu)],
            ['Nama wali', $mhs->wali], ['NIK wali', $mhs->nik_wali], ['Tgl lahir wali', $tanggal($mhs->tgl_lahir_wali)],
            ['Pendidikan wali', $refNama($mhs->pendidikan_wali)], ['Pekerjaan wali', $refNama($mhs->pekerjaan_wali)], ['Penghasilan wali', $refNama($mhs->penghasilan_wali)],
        ],
    ];
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="flex flex-wrap items-center gap-5">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-neutral-100">
                @if ($mhs->foto)
                    <img src="{{ asset('storage/'.ltrim($mhs->foto, '/')) }}" alt="{{ $mhs->nama }}" class="h-full w-full object-cover" />
                @else
                    <i data-lucide="user" class="h-9 w-9 text-neutral-400" aria-hidden="true"></i>
                @endif
            </div>
            <div class="min-w-0">
                <p class="font-mono text-sm text-neutral-500">{{ $textOrDash($mhs->nim) }}</p>
                <h2 class="text-lg font-semibold text-neutral-900">{{ $textOrDash($mhs->nama) }}</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    {{ $mhs->prodi?->nama ?? '—' }}{{ $mhs->prodi?->jenjang?->nama ? ' · '.$mhs->prodi->jenjang->nama : '' }}
                </p>
            </div>
        </div>
    </div>

    @foreach ($sections as $title => $fields)
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h3 class="mb-4 text-sm font-semibold text-neutral-900">{{ $title }}</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($fields as [$label, $value])
                    <div>
                        <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase">{{ $label }}</p>
                        <p class="mt-1 text-sm text-neutral-900">{{ $textOrDash($value) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <p class="px-1 text-xs text-neutral-500">
        Data akademik serta NISN, NPWP, dan KPS dikelola oleh bagian administrasi. Hubungi
        administrasi bila ada yang perlu diperbaiki.
    </p>
</div>
