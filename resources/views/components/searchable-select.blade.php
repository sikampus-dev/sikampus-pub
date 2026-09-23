@props([
    'model',
    'options' => [],
    'optionValue' => 'id',
    'optionLabel' => 'nama',
    'placeholder' => '— Pilih —',
    'live' => false,
    'clearable' => true, // false = tidak ada opsi kosong (dipakai untuk field yang selalu punya nilai default, mis. status)
])

@php
    // $options bisa berupa Collection Eloquent (pakai optionValue/optionLabel sebagai key) atau
    // array asosiatif statis ['value' => 'Label', ...] — dua-duanya diseragamkan jadi $items.
    $items = $options instanceof \Illuminate\Support\Collection
        ? $options->mapWithKeys(fn ($o) => [$o->{$optionValue} => $o->{$optionLabel}])
        : collect($options);

    // $wire.entangle() dibangun manual (bukan Blade @entangle) supaya modifier .live bisa dirangkai
    // langsung — lihat rencana implementasi untuk alasan wire:ignore + entangle dipilih dibanding
    // wire:model langsung (Tom Select memanipulasi DOM sibling yang tidak dikenali morph Livewire).
    $entangleExpr = "\$wire.entangle('{$model}')".($live ? '.live' : '');
@endphp

{{--
    wire:ignore: setelah render pertama, Livewire tidak lagi menyentuh subtree ini sama sekali — ini
    yang mencegah Tom Select ke-duplikasi/rusak saat Livewire morph ulang halaman (mis. klik pagination,
    ganti filter lain). Sinkronisasi nilai murni lewat Alpine + $wire.entangle, bukan wire:model.
--}}
<div
    {{ $attributes->merge(['class' => 'relative w-full']) }}
    wire:ignore
    x-data="{
        value: {!! $entangleExpr !!},
        ts: null,
        init() {
            this.ts = new TomSelect(this.$refs.select, {
                create: false,
                allowEmptyOption: {{ $clearable ? 'true' : 'false' }},
                onChange: (val) => {
                    this.value = val;
                    // Tanpa ini, class `input-active` (dipasang TomSelect selama kontrol fokus)
                    // tetap menempel setelah opsi dipilih — dan `.ts-wrapper.single.input-active
                    // .ts-control > .item` di app.css sengaja menyembunyikan label terpilih selama
                    // class itu ada (supaya teks pencarian tidak tertimpa label lama). Blur di sini
                    // melepas fokus begitu opsi dipilih (sama seperti <select> native), sehingga
                    // class itu langsung hilang dan label langsung terlihat tanpa perlu klik di luar.
                    this.ts.blur();
                },
            });
            // TomSelect membaca .value dari <select> saat inisialisasi, tapi tidak ada <option>
            // yang ditandai selected berdasarkan nilai entangled — tanpa baris ini, field yang
            // sudah terisi (mis. buka form edit) akan tampil kosong walau properti Livewire-nya benar.
            if (this.value) {
                this.ts.setValue(this.value, true);
            }
            this.$watch('value', (val) => {
                if (this.ts && this.ts.getValue() !== (val ?? '')) {
                    this.ts.setValue(val ?? '', true);
                }
            });
        },
        destroy() { this.ts?.destroy(); },
    }"
>
    <select x-ref="select">
        @if ($clearable)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($items as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>

    {{-- Indikator caret, penanda visual bahwa ini dropdown (bukan input teks biasa) — elemen
         sendiri di luar markup yang dikelola Tom Select, jadi aman dari wire:ignore/re-init. --}}
    <i
        data-lucide="chevron-down"
        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
        aria-hidden="true"
    ></i>
</div>
