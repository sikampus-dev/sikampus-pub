@section('title', ($ujianId ? 'Ubah' : 'Tambah') . ' Jadwal Ujian — ' . config('app.name'))
@section('header_title', ($ujianId ? 'Ubah' : 'Tambah') . ' Jadwal Ujian')
@section('header_icon', 'clipboard-list')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Jadwal Ujian', 'route' => route('admin.akademik.jadwal-ujian')],
        ['label' => $ujianId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h2 class="mb-4 text-base font-semibold text-neutral-900">Kelas</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Filter Prodi</label>
                    {{-- :live wajib: updatedFilterProdi() di Form.php dan wire:key kelas di bawah
                         baru berjalan kalau nilainya sampai ke server begitu dipilih, bukan menunggu
                         request lain — lihat catatan serupa di App\Livewire\Admin\Jadwal\Form. --}}
                    <x-searchable-select
                        model="filterProdi"
                        :live="true"
                        :options="$prodiOptions"
                        optionLabel="label"
                        placeholder="— Semua prodi —"
                    />
                    <p class="mt-1 text-xs text-neutral-500">Hanya menyaring daftar kelas di bawah, tidak disimpan.</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Filter Semester</label>
                    <x-searchable-select
                        model="filterSemester"
                        :live="true"
                        :options="$semesterOptions"
                        optionLabel="label"
                        placeholder="— Semua semester —"
                    />
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kelas *</label>
                    {{-- wire:key terikat filterProdi/filterSemester: x-searchable-select memakai
                         wire:ignore, jadi kalau filternya berganti elemen ini harus benar-benar
                         diganti (bukan di-patch) supaya opsi kelas yang baru ikut termuat. --}}
                    <x-searchable-select
                        wire:key="id-kelas-select-{{ $filterProdi }}-{{ $filterSemester }}"
                        model="id_kelas"
                        :options="$this->kelasOptions"
                        optionLabel="label"
                        placeholder="— Pilih kelas —"
                    />
                    @error('id_kelas') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-border">
            <h2 class="mb-4 text-base font-semibold text-neutral-900">Informasi Ujian</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenis Ujian *</label>
                    <x-searchable-select
                        model="jenis_ujian"
                        :options="$jenisUjianOptions"
                        :clearable="false"
                    />
                    @error('jenis_ujian') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ruangan</label>
                    <x-searchable-select
                        model="id_ruangan"
                        :options="$ruanganOptions"
                        placeholder="— Opsional —"
                    />
                    @error('id_ruangan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal &amp; Jam Mulai</label>
                    <input type="datetime-local" wire:model="tanggal_mulai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_mulai') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('tanggal_mulai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal &amp; Jam Selesai</label>
                    <input type="datetime-local" wire:model="tanggal_selesai" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal_selesai') ring-2 ring-red-500 @enderror shadow-border" />
                    @error('tanggal_selesai') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $backUrl }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
