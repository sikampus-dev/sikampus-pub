@section('title', 'Edit Biodata — ' . config('app.name'))
@section('header_title', 'Edit Biodata')
@section('header_subtitle', 'Perbarui data diri dan alamat Anda.')

@section('breadcrumb')
    <a href="{{ route('mahasiswa.biodata') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700">
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali ke Biodata
    </a>
@endsection

@php
    $inputClass = 'w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border';
@endphp

<form wire:submit="save" class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-900">Identitas</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIM</label>
                <input type="text" value="{{ $nim ?: '—' }}" disabled class="w-full rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-500 shadow-border" />
                <p class="mt-1.5 text-xs text-neutral-500">NIM tidak dapat diubah sendiri.</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nama *</label>
                <input type="text" wire:model="nama" class="{{ $inputClass }} @error('nama') ring-2 ring-red-500 @enderror" />
                @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Email</label>
                <input type="email" wire:model="email" class="{{ $inputClass }} @error('email') ring-2 ring-red-500 @enderror" />
                @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenis Kelamin</label>
                <x-searchable-select
                    model="jenis_kelamin"
                    :options="['L' => 'Laki-laki', 'P' => 'Perempuan']"
                    placeholder="— Pilih jenis kelamin —"
                />
                @error('jenis_kelamin') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. WhatsApp</label>
                <input type="text" wire:model="no_wa" class="{{ $inputClass }} @error('no_wa') ring-2 ring-red-500 @enderror" />
                @error('no_wa') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Handphone</label>
                <input type="text" wire:model="handphone" class="{{ $inputClass }} @error('handphone') ring-2 ring-red-500 @enderror" />
                @error('handphone') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tempat Lahir</label>
                <input type="text" wire:model="id_tempat_lahir" class="{{ $inputClass }}" />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Lahir</label>
                <input type="date" wire:model="tanggal_lahir" class="{{ $inputClass }} @error('tanggal_lahir') ring-2 ring-red-500 @enderror" />
                @error('tanggal_lahir') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">No. KTP</label>
                <input type="text" wire:model="no_ktp" class="{{ $inputClass }} @error('no_ktp') ring-2 ring-red-500 @enderror" />
                @error('no_ktp') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-900">Asal Sekolah</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Sekolah Asal</label>
                <input type="text" wire:model="sekolah_asal" class="{{ $inputClass }} @error('sekolah_asal') ring-2 ring-red-500 @enderror" />
                @error('sekolah_asal') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIS</label>
                <input type="text" wire:model="nis" class="{{ $inputClass }} @error('nis') ring-2 ring-red-500 @enderror" />
                @error('nis') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">NISN</label>
                <input type="text" wire:model="nisn" class="{{ $inputClass }} @error('nisn') ring-2 ring-red-500 @enderror" />
                @error('nisn') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-900">Alamat &amp; Wilayah</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Alamat</label>
                <textarea wire:model="alamat" rows="3" class="{{ $inputClass }}"></textarea>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">RT</label>
                <input type="text" wire:model="rt" class="{{ $inputClass }} @error('rt') ring-2 ring-red-500 @enderror" />
                @error('rt') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">RW</label>
                <input type="text" wire:model="rw" class="{{ $inputClass }} @error('rw') ring-2 ring-red-500 @enderror" />
                @error('rw') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Dusun</label>
                <input type="text" wire:model="dusun" class="{{ $inputClass }} @error('dusun') ring-2 ring-red-500 @enderror" />
                @error('dusun') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kelurahan</label>
                <input type="text" wire:model="kelurahan" class="{{ $inputClass }} @error('kelurahan') ring-2 ring-red-500 @enderror" />
                @error('kelurahan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kecamatan</label>
                <input type="text" wire:model="id_kecamatan" class="{{ $inputClass }}" />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kode Pos</label>
                <input type="text" wire:model="kode_pos" class="{{ $inputClass }} @error('kode_pos') ring-2 ring-red-500 @enderror" />
                @error('kode_pos') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kota/Kabupaten</label>
                <x-searchable-select
                    model="id_kota"
                    :options="$kotaOptions"
                    placeholder="— Pilih kota/kabupaten —"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Provinsi</label>
                <x-searchable-select
                    model="id_provinsi"
                    :options="$provinsiOptions"
                    placeholder="— Pilih provinsi —"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Negara</label>
                <x-searchable-select
                    model="id_negara"
                    :options="$negaraOptions"
                    placeholder="— Pilih negara —"
                />
            </div>
        </div>
    </div>

    @php
        $waliGroups = [
            ['title' => 'Ayah', 'suffix' => 'ayah', 'namaLabel' => 'Nama Ayah'],
            ['title' => 'Ibu', 'suffix' => 'ibu', 'namaLabel' => 'Nama Ibu'],
            ['title' => 'Wali', 'suffix' => 'wali', 'namaLabel' => 'Nama Wali'],
        ];
    @endphp

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="text-sm font-semibold text-neutral-900">Orang Tua / Wali</h3>
        <p class="mt-1 mb-4 text-sm text-neutral-500">Isi bagian Wali bila Anda berada dalam tanggungan selain orang tua.</p>

        <div class="space-y-6">
            @foreach ($waliGroups as $group)
                @php $s = $group['suffix']; @endphp
                <div class="border-t border-neutral-100 pt-5 first:border-0 first:pt-0">
                    <h4 class="mb-4 text-xs font-semibold tracking-wide text-neutral-500 uppercase">{{ $group['title'] }}</h4>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">{{ $group['namaLabel'] }}</label>
                            <input type="text" wire:model="{{ $s }}" class="{{ $inputClass }} @error($s) ring-2 ring-red-500 @enderror" />
                            @error($s) <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">NIK</label>
                            <input type="text" wire:model="nik_{{ $s }}" class="{{ $inputClass }} @error('nik_'.$s) ring-2 ring-red-500 @enderror" />
                            @error('nik_'.$s) <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal Lahir</label>
                            <input type="date" wire:model="tgl_lahir_{{ $s }}" class="{{ $inputClass }} @error('tgl_lahir_'.$s) ring-2 ring-red-500 @enderror" />
                            @error('tgl_lahir_'.$s) <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pendidikan</label>
                            <x-searchable-select
                                :model="'id_pddk_'.$s"
                                :options="$pendidikanOptions"
                                placeholder="— Pilih pendidikan —"
                            />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Pekerjaan</label>
                            <x-searchable-select
                                :model="'id_pekerjaan_'.$s"
                                :options="$pekerjaanOptions"
                                placeholder="— Pilih pekerjaan —"
                            />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Penghasilan</label>
                            <x-searchable-select
                                :model="'id_penghasilan_'.$s"
                                :options="$penghasilanOptions"
                                placeholder="— Pilih penghasilan —"
                            />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ route('mahasiswa.biodata') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">
            Batal
        </a>
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-sky-700 disabled:opacity-50"
        >
            <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
            Simpan Biodata
        </button>
    </div>
</form>
