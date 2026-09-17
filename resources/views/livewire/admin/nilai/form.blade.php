@section('title', ($nilaiId ? 'Ubah' : 'Input') . ' Nilai — ' . config('app.name'))
@section('header_title', ($nilaiId ? 'Ubah' : 'Input') . ' Nilai')
@section('header_icon', 'graduation-cap')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Nilai', 'route' => $backUrl],
        ['label' => $mahasiswaNama, 'route' => $detailUrl],
        ['label' => $nilaiId ? 'Ubah' : 'Input'],
    ]])
@endsection

<div>
    <div class="mb-6 rounded-2xl bg-white p-6 shadow-border">
        <h2 class="mb-3 text-sm font-semibold text-neutral-700">Informasi Mahasiswa &amp; Mata Kuliah</h2>
        <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
            <div>
                <span class="text-xs font-semibold uppercase text-neutral-500">NIM</span>
                <p class="font-semibold text-neutral-900">{{ $mahasiswaNim }}</p>
            </div>
            <div>
                <span class="text-xs font-semibold uppercase text-neutral-500">Nama</span>
                <p class="font-semibold text-neutral-900">{{ $mahasiswaNama }}</p>
            </div>
            <div>
                <span class="text-xs font-semibold uppercase text-neutral-500">Prodi</span>
                <p class="font-semibold text-neutral-900">{{ $mahasiswaProdiNama }}</p>
            </div>
            <div>
                <span class="text-xs font-semibold uppercase text-neutral-500">Mata Kuliah</span>
                <p class="font-semibold text-neutral-900">{{ $matkulLabel }}</p>
            </div>
            <div>
                <span class="text-xs font-semibold uppercase text-neutral-500">Semester</span>
                <p class="font-semibold text-neutral-900">{{ $semesterLabel }}</p>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">SKS</label>
                    <input
                        type="number" min="0" max="255"
                        wire:model="sks"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('sks') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    @error('sks') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Huruf Mutu</label>
                    <select
                        wire:model.live="huruf_mutu"
                        @if (empty($this->rentangOptions)) disabled @endif
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 disabled:cursor-not-allowed disabled:opacity-60 @error('huruf_mutu') ring-2 ring-red-500 @enderror shadow-border"
                    >
                        <option value="">— Pilih huruf mutu —</option>
                        @foreach ($this->rentangOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-neutral-500">
                        @if (empty($this->rentangOptions))
                            Prodi mahasiswa belum memiliki jenjang, atau jenjang belum punya master rentang nilai.
                        @else
                            Pilihan mengikuti master rentang nilai untuk jenjang prodi mahasiswa. Angka mutu diisi otomatis sesuai pilihan.
                        @endif
                    </p>
                    @error('huruf_mutu') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Angka Mutu</label>
                    <input
                        type="number" step="0.01" min="0" max="999.99"
                        wire:model="angka_mutu"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('angka_mutu') ring-2 ring-red-500 @enderror shadow-border"
                    />
                    <p class="mt-1.5 text-xs text-neutral-500">Diisi otomatis dari pilihan huruf mutu, tapi tetap bisa diubah manual.</p>
                    @error('angka_mutu') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-sm font-medium text-neutral-700">
                        <input type="checkbox" wire:model="is_final" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/20" />
                        Tandai sebagai Nilai Final
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-neutral-700">Keterangan Revisi (opsional)</label>
                    <textarea
                        wire:model="keterangan_revisi"
                        rows="2"
                        placeholder="Dicatat di riwayat revisi nilai bersama penyimpanan ini"
                        class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('keterangan_revisi') ring-2 ring-red-500 @enderror shadow-border"
                    ></textarea>
                    @error('keterangan_revisi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $detailUrl }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                Simpan
            </button>
        </div>
    </form>
</div>
