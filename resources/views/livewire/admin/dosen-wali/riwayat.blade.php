@php
    $dosenWali = $this->dosenWali;
    $mahasiswa = $dosenWali->mahasiswa;
    $dosen = $dosenWali->dosen;
@endphp

@section('title', 'Riwayat Bimbingan Akademik — ' . config('app.name'))
@section('header_title', 'Riwayat Bimbingan Akademik')
@section('header_subtitle', ($mahasiswa->nama ?? '—') . ($mahasiswa->nim ? ' (NIM '.$mahasiswa->nim.')' : ''))
@section('header_icon', 'notebook-pen')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Administrasi'],
        ['label' => 'Dosen', 'route' => route('admin.administrasi.dosen')],
        ['label' => 'Dosen Wali', 'route' => route('admin.administrasi.dosen-wali')],
        ['label' => $dosen->nama ?? '—', 'route' => route('admin.administrasi.dosen-wali.show', $dosenWali->id_dosen)],
        ['label' => 'Riwayat Bimbingan'],
    ]])
@endsection

@section('page_actions')
    <a
        href="{{ $backUrl }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div>
    <div class="mb-6 rounded-2xl bg-white p-4 shadow-border">
        <p class="font-semibold text-neutral-900">
            Dosen: {{ $dosen->nama ?? '—' }}{{ $dosen->kode_dosen ? ' ('.$dosen->kode_dosen.')' : '' }}
        </p>
        <p class="mt-1 text-sm text-neutral-600">
            Mahasiswa: {{ $mahasiswa->nama ?? '—' }}{{ $mahasiswa->nim ? ' · NIM '.$mahasiswa->nim : '' }}
        </p>
        @if ($mahasiswa->prodi?->nama)
            <p class="mt-0.5 text-sm text-neutral-600">Prodi: {{ $mahasiswa->prodi->nama }}</p>
        @endif
    </div>

    <div class="rounded-2xl bg-white shadow-border">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 p-4">
            <h3 class="text-sm font-semibold text-neutral-900">Catatan Bimbingan</h3>
            <div class="w-full sm:w-64">
                <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
                <x-searchable-select
                    model="filterSemester"
                    :live="true"
                    :options="$this->semesterOptions->mapWithKeys(fn ($s) => [$s->id => $s->nama.($s->is_active ? ' (Aktif)' : '')])->all()"
                    placeholder="Semua semester"
                />
            </div>
        </div>

        @php $rows = $this->bimbinganRows; @endphp

        @if ($rows->isEmpty())
            <div class="py-12 text-center text-neutral-500">Belum ada catatan bimbingan.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Semester</th>
                            <th class="px-4 py-3">Tanggal Bimbingan</th>
                            <th class="px-4 py-3">Catatan Dosen</th>
                            <th class="px-4 py-3">Catatan Mahasiswa</th>
                            <th class="px-4 py-3">Validasi Dosen</th>
                            <th class="px-4 py-3">Validasi Mahasiswa</th>
                            <th class="px-4 py-3">Berkas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($rows as $row)
                            <tr wire:key="riwayat-{{ $row->id }}">
                                <td class="px-4 py-3 text-neutral-600">{{ $row->semester ? $row->semester->nama.' ('.$row->semester->kode.')' : '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $row->tanggal_bimbingan?->translatedFormat('d F Y') ?? '—' }}</td>
                                <td class="px-4 py-3 max-w-xs whitespace-pre-wrap text-neutral-600">{{ $row->catatan_dosen ?? '—' }}</td>
                                <td class="px-4 py-3 max-w-xs whitespace-pre-wrap text-neutral-600">{{ $row->catatan_mhs ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $row->waktu_validasi_dosen?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $row->waktu_validasi_mhs?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($row->file_url)
                                        <a href="{{ $row->file_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-medium text-sky-600 hover:underline">
                                            <i data-lucide="paperclip" class="h-4 w-4" aria-hidden="true"></i>
                                            Lihat
                                        </a>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
