@section('title', 'Bimbingan Akademik — ' . config('app.name'))
@section('header_title', 'Bimbingan Akademik')

@section('breadcrumb')
    <a href="{{ route('dosen.perwalian') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Daftar bimbingan akademik
    </a>
@endsection

@php
    $mhs = $this->mahasiswa;
    $textOrDash = fn ($v) => $v !== null && trim((string) $v) !== '' ? $v : '—';
    $refNama = fn ($ref) => $ref?->nama ?? '—';
    $tanggal = fn ($d) => $d ? $d->translatedFormat('d F Y') : '—';
    $waktu = fn ($d) => $d ? $d->translatedFormat('d F Y H:i') : '—';
@endphp

<div class="space-y-4">
    <div class="rounded-2xl bg-white p-5 shadow-border">
        <p class="text-sm text-neutral-500">{{ $mhs->nim }}</p>
        <h2 class="text-lg font-semibold text-neutral-900">{{ $mhs->nama }}</h2>
        <p class="mt-1 text-sm text-neutral-500">
            {{ $mhs->prodi?->nama ?? '—' }}{{ $mhs->prodi?->jenjang?->nama ? ' · '.$mhs->prodi->jenjang->nama : '' }}
        </p>
    </div>

    <div class="flex gap-1 overflow-x-auto border-b border-neutral-200">
        @foreach (['biodata' => 'Biodata', 'krs' => 'KRS', 'transkrip' => 'Transkrip', 'catatan' => 'Catatan'] as $tab => $label)
            <button
                type="button"
                wire:click="$set('activeTab', '{{ $tab }}')"
                class="border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition {{ $activeTab === $tab ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-700' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($activeTab === 'biodata')
        <div class="space-y-6">
            @php
                $sections = [
                    'Identitas' => [
                        ['NIM', $mhs->nim], ['Nama', $mhs->nama], ['Email', $mhs->email],
                        ['Jenis kelamin', $mhs->jenis_kelamin], ['Tanggal lahir', $tanggal($mhs->tanggal_lahir)],
                        ['No. KTP', $mhs->no_ktp], ['No. WA', $mhs->no_wa], ['Handphone', $mhs->handphone],
                    ],
                    'Wilayah' => [
                        ['Negara', $refNama($mhs->negara)], ['Provinsi', $refNama($mhs->provinsi)], ['Kota/Kabupaten', $refNama($mhs->kota)],
                        ['Kode pos', $mhs->kode_pos], ['RT / RW', trim(($mhs->rt ?: '—').' / '.($mhs->rw ?: '—'))],
                        ['Dusun', $mhs->dusun], ['Kelurahan', $mhs->kelurahan],
                    ],
                    'Akademik' => [
                        ['Program studi', $mhs->prodi ? $mhs->prodi->nama.($mhs->prodi->jenjang?->nama ? ' · '.$mhs->prodi->jenjang->nama : '') : null],
                        ['Status akademik', $refNama($mhs->status_akademik)],
                        ['Semester masuk', $mhs->semester_masuk ? trim(($mhs->semester_masuk->kode ?: '').' — '.$mhs->semester_masuk->nama) : null],
                        ['Kelas mahasiswa', $refNama($mhs->kelompok_kelas)], ['Jalur masuk', $refNama($mhs->jalur_masuk)],
                        ['Jenis daftar', $refNama($mhs->jenis_daftar)], ['Mulai semester', $mhs->mulai_semester], ['SKS diakui', $mhs->sks_diakui],
                    ],
                    'Sekolah asal & lainnya' => [
                        ['Sekolah asal', $mhs->sekolah_asal], ['NIS', $mhs->nis], ['NISN', $mhs->nisn], ['NPWP', $mhs->npwp],
                        ['Penerima KPS', $mhs->penerima_kps], ['No. KPS', $mhs->no_kps],
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
        </div>
    @endif

    @if ($activeTab === 'krs')
        @php $krsGroups = $this->krsBySemester; @endphp
        <div class="space-y-4">
            <p class="text-xs text-neutral-500">Dikelompokkan menurut semester.</p>
            @if (empty($krsGroups))
                <div class="rounded-2xl border border-neutral-200 bg-white py-10 text-center text-sm text-neutral-500">Belum ada KRS.</div>
            @else
                @foreach ($krsGroups as $group)
                    <div class="overflow-hidden rounded-xl bg-white shadow-border">
                        <div class="flex flex-col gap-2 border-b border-neutral-100 bg-neutral-50/90 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold tracking-wide text-neutral-500 uppercase">Semester kelas</p>
                                <p class="text-sm font-semibold text-neutral-900">{{ $group['semester']->kode }} · {{ $group['semester']->nama }}</p>
                            </div>
                            <div class="flex flex-wrap gap-3 text-sm text-neutral-600">
                                <span>SKS diajukan: <strong class="text-neutral-900">{{ $group['total_sks_diajukan'] }}</strong></span>
                                <span>SKS disetujui: <strong class="text-neutral-900">{{ $group['total_sks_diacc'] }}</strong></span>
                                <span>Mata kuliah: <strong class="text-neutral-900">{{ count($group['krs']) }}</strong></span>
                            </div>
                        </div>
                        <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                            <thead class="bg-neutral-50 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                                <tr>
                                    <th class="px-4 py-3">Kode</th>
                                    <th class="px-4 py-3">Mata kuliah</th>
                                    <th class="px-4 py-3 text-center">SKS</th>
                                    <th class="px-4 py-3">Dosen</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Disetujui</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200">
                                @foreach ($group['krs'] as $row)
                                    <tr class="hover:bg-neutral-50">
                                        <td class="px-4 py-3 font-mono text-neutral-800">{{ $row['kode'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-neutral-800">{{ $row['nama'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-center text-neutral-800">{{ $row['sks'] }}</td>
                                        <td class="px-4 py-3 text-neutral-700">{{ $row['dosen'] ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $row['approved'] ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">
                                                {{ $row['approved'] ? 'Disetujui' : 'Menunggu' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-neutral-600">{{ $row['approved_at'] ? $waktu($row['approved_at']) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif
        </div>
    @endif

    @if ($activeTab === 'transkrip')
        @php $transkrip = $this->transkrip; @endphp
        <div class="space-y-4">
            <p class="text-xs text-neutral-500">Ringkasan dari nilai sementara.</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-4 shadow-border">
                    <p class="text-xs font-semibold tracking-wide text-neutral-500 uppercase">IPK sementara</p>
                    <p class="mt-1 text-2xl font-bold text-neutral-900">{{ $transkrip['ipk'] !== null ? number_format($transkrip['ipk'], 2) : '—' }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Dari nilai final × SKS</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-border">
                    <p class="text-xs font-semibold tracking-wide text-neutral-500 uppercase">Total SKS (tercatat)</p>
                    <p class="mt-1 text-2xl font-bold text-neutral-900">{{ $transkrip['total_sks'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-border">
                    <p class="text-xs font-semibold tracking-wide text-neutral-500 uppercase">SKS masuk IPK</p>
                    <p class="mt-1 text-2xl font-bold text-neutral-900">{{ $transkrip['total_sks_dengan_nilai'] }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-border">
                <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">Semester</th>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Mata kuliah</th>
                            <th class="px-4 py-3 text-center">SKS</th>
                            <th class="px-4 py-3 text-center">Huruf</th>
                            <th class="px-4 py-3 text-center">Angka mutu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @forelse ($transkrip['mata_kuliah'] as $row)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-3 text-neutral-800">{{ $row['semester']->kode }} · {{ $row['semester']->nama }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-800">{{ $row['kode'] }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $row['nama'] }}</td>
                                <td class="px-4 py-3 text-center text-neutral-800">{{ $row['sks'] }}</td>
                                <td class="px-4 py-3 text-center font-medium text-neutral-900">{{ $row['huruf_mutu'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-center text-neutral-700">{{ $row['angka_mutu'] !== null ? number_format((float) $row['angka_mutu'], 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-neutral-500">Belum ada mata kuliah dengan nilai final.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($activeTab === 'catatan')
        <div class="space-y-4">
            @if (session('status'))
                <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <h3 class="text-sm font-semibold text-neutral-900">Catatan bimbingan</h3>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="w-full sm:w-72">
                        <label class="mb-1 block text-xs font-semibold text-neutral-700">Semester</label>
                        <x-searchable-select model="filterSemesterCatatan" :options="$this->semesterOptions" :live="true" placeholder="Semua semester" />
                    </div>
                    <button
                        type="button"
                        wire:click="openBimbinganModal"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
                    >
                        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                        Tambah catatan
                    </button>
                    <a
                        href="{{ route('dosen.perwalian.bimbingan.export', ['idMahasiswa' => $idMahasiswa, 'id_semester' => $filterSemesterCatatan !== '' ? $filterSemesterCatatan : null]) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-neutral-700 shadow-border hover:bg-neutral-50"
                    >
                        <i data-lucide="file-down" class="h-4 w-4" aria-hidden="true"></i>
                        Ekspor Excel
                    </a>
                </div>
            </div>

            @php $catatanRows = $this->catatanRows; @endphp

            <div class="overflow-hidden rounded-xl bg-white shadow-border">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                            <tr>
                                <th class="px-4 py-3">Semester</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Catatan Dosen</th>
                                <th class="px-4 py-3">Catatan Mahasiswa</th>
                                <th class="px-4 py-3">Validasi Dosen</th>
                                <th class="px-4 py-3">Validasi Mhs</th>
                                <th class="px-4 py-3">Berkas</th>
                                <th class="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse ($catatanRows as $row)
                                <tr wire:key="bimbingan-{{ $row->id }}" class="hover:bg-neutral-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-neutral-800">{{ $row->semester ? trim(($row->semester->kode ?: '').' '.$row->semester->nama) : '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-neutral-800">{{ $tanggal($row->tanggal_bimbingan) }}</td>
                                    <td class="max-w-[220px] px-4 py-3 text-neutral-600">
                                        <span class="line-clamp-3">{{ $row->catatan_dosen ?: '-' }}</span>
                                    </td>
                                    <td class="max-w-[220px] px-4 py-3 text-neutral-600">
                                        <span class="line-clamp-3">{{ $row->catatan_mhs ?: '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap text-neutral-600">{{ $waktu($row->waktu_validasi_dosen) }}</td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap text-neutral-600">{{ $waktu($row->waktu_validasi_mhs) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($row->file_url)
                                            <a href="{{ $row->file_url }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:underline">Lihat</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button" wire:click="openBimbinganModal({{ $row->id }})" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-neutral-500">Belum ada catatan untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Modal Tambah/Ubah Catatan Bimbingan --}}
        @if ($showBimbinganModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
                <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-border-lg">
                    <div class="flex items-start justify-between gap-4 border-b border-neutral-200 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">
                                {{ $editingBimbinganId ? 'Ubah catatan bimbingan akademik' : 'Tambah catatan bimbingan akademik' }}
                            </h3>
                            <p class="mt-1 text-sm text-neutral-500">
                                {{ $editingBimbinganId ? 'Perbarui data yang sudah tercatat untuk mahasiswa bimbingan Anda.' : 'Data disimpan untuk mahasiswa bimbingan Anda pada semester yang dipilih.' }}
                            </p>
                        </div>
                        <button type="button" wire:click="closeBimbinganModal" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600">
                            <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>
                        </button>
                    </div>

                    <form wire:submit="saveBimbingan" class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Semester *</label>
                                <x-searchable-select model="form_id_semester" :options="$this->semesterOptions" placeholder="Pilih semester" />
                                @error('form_id_semester') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal bimbingan</label>
                                <input type="date" wire:model="form_tanggal_bimbingan" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                                @error('form_tanggal_bimbingan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-5">
                            @if ($existingWaktuValidasiDosen)
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3">
                                    <p class="text-sm font-semibold text-emerald-900">Sudah divalidasi</p>
                                    <p class="mt-1 text-xs text-emerald-800">Validasi dosen tercatat pada {{ $existingWaktuValidasiDosen }}.</p>
                                </div>
                            @else
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-neutral-50/80 px-4 py-3 shadow-border transition hover:bg-neutral-50">
                                    <input type="checkbox" wire:model="form_langsung_validasi" class="mt-1 h-4 w-4 shrink-0 rounded border-neutral-300 text-sky-600 focus:ring-sky-500" />
                                    <span>
                                        <span class="block text-sm font-semibold text-neutral-900">Langsung validasi</span>
                                        <span class="mt-0.5 block text-xs text-neutral-600">Jika dicentang, waktu validasi dosen diisi otomatis saat menyimpan (waktu server).</span>
                                    </span>
                                </label>
                            @endif
                        </div>

                        <div class="mt-5">
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Catatan dosen</label>
                            <textarea wire:model="form_catatan_dosen" rows="8" placeholder="Tuliskan ringkasan pembimbingan, arahan akademik, atau hal yang perlu ditindaklanjuti mahasiswa…" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border"></textarea>
                            @error('form_catatan_dosen') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="mt-5">
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Lampiran (opsional)</label>
                            @if ($existingFileUrl && ! $form_hapus_file)
                                <div class="mb-2 flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5 text-sm shadow-border">
                                    <a href="{{ $existingFileUrl }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:underline">Lihat berkas saat ini</a>
                                    <button type="button" wire:click="$set('form_hapus_file', true)" class="text-xs text-red-600 hover:underline">Hapus</button>
                                </div>
                            @endif
                            <input type="file" wire:model="form_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100" />
                            <p class="mt-1.5 text-xs text-neutral-500">PDF, Word, atau gambar. Maks. 10 MB per berkas.</p>
                            @error('form_file') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            <div wire:loading wire:target="form_file" class="mt-1.5 text-xs text-neutral-500">Mengunggah…</div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3 border-t border-neutral-200 pt-4">
                            <button type="button" wire:click="closeBimbinganModal" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                                Batal
                            </button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                                {{ $editingBimbinganId ? 'Simpan perubahan' : 'Simpan catatan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
