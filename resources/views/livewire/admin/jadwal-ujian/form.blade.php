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

    {{-- Bentrok dengan jadwal ujian yang sudah DIHAPUS. Unique `ujian_unique` tidak menyertakan
         deleted_at, jadi baris terhapus tetap menduduki kombinasinya — dan admin tidak punya
         halaman mana pun untuk melihat baris itu. Karena itu ditawarkan langsung di sini. --}}
    @if ($this->duplikatTerhapus)
        @php($dupe = $this->duplikatTerhapus)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Jadwal ujian yang terhapus memakai kombinasi ini</h3>
                <p class="mt-2 text-sm text-neutral-600">
                    Kombinasi kelas, semester, dan jenis ujian ini masih dipakai oleh jadwal yang sudah
                    dihapus, sehingga jadwal baru tidak bisa disimpan sebelum jadwal lama itu diputuskan.
                </p>

                <dl class="mt-4 space-y-1.5 rounded-xl bg-neutral-50 p-4 text-sm">
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-neutral-500">Mata kuliah</dt>
                        <dd class="text-neutral-800">
                            {{ trim(($dupe->kelas?->kurikulumMatkul?->matkul?->kode ? $dupe->kelas->kurikulumMatkul->matkul->kode.' - ' : '').($dupe->kelas?->kurikulumMatkul?->matkul?->nama ?? 'Kelas')) }}
                            @if ($dupe->kelas?->kelompokKelas?->nama)
                                <span class="text-neutral-500">· {{ $dupe->kelas->kelompokKelas->nama }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-neutral-500">Jenis ujian</dt>
                        <dd class="text-neutral-800">{{ $dupe->jenis_ujian }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-neutral-500">Semester</dt>
                        <dd class="text-neutral-800">{{ $dupe->semester?->nama ?? '—' }} {{ $dupe->semester?->kode ? '('.$dupe->semester->kode.')' : '' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-neutral-500">Ruangan lama</dt>
                        <dd class="text-neutral-800">{{ $dupe->ruangan?->nama ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-neutral-500">Jadwal lama</dt>
                        <dd class="text-neutral-800">
                            {{ $dupe->tanggal_mulai?->format('d/m/Y H:i') ?? '—' }}
                            @if ($dupe->tanggal_selesai) &ndash; {{ $dupe->tanggal_selesai->format('d/m/Y H:i') }} @endif
                        </dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-neutral-500">Dihapus</dt>
                        <dd class="text-neutral-800">
                            {{ $dupe->deleted_at?->format('d/m/Y H:i') ?? '—' }}
                            @if ($dupe->deleted_by)
                                <span class="text-neutral-500">oleh {{ $dupe->deleted_by }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <span class="font-medium">Memulihkan akan memakai tanggal dan ruangan lama di atas</span> —
                    isian yang baru Anda ketik di form tidak ikut tersimpan. Kalau yang Anda inginkan justru
                    isian baru itu, pilih hapus permanen.
                </p>

                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        wire:click="batalkanDuplikat"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="hapusPermanenDuplikat"
                        wire:confirm="Hapus permanen jadwal ujian lama itu? Tindakan ini tidak bisa dibatalkan."
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700"
                    >
                        Hapus permanen &amp; simpan baru
                    </button>
                    <button
                        type="button"
                        wire:click="pulihkanDuplikat"
                        class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800"
                    >
                        Pulihkan jadwal lama
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
