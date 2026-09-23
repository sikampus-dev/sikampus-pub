@section('title', 'Kalender Akademik — ' . config('app.name'))

@php
    $statusLabel = ['aktif' => 'Aktif', 'akan_datang' => 'Akan Datang', 'selesai' => 'Selesai'];
    $statusClass = [
        'aktif' => 'bg-emerald-100 text-emerald-700',
        'akan_datang' => 'bg-sky-100 text-sky-700',
        'selesai' => 'bg-neutral-100 text-neutral-500',
    ];
    $kategoriLabel = \App\Models\KalenderAkademik::KATEGORI_OPTIONS;
@endphp

<div class="space-y-6">
    <div class="min-w-0">
        <h1 class="truncate text-2xl font-semibold tracking-tight text-neutral-900">Kalender Akademik</h1>
        <p class="mt-1 text-sm text-neutral-500">Tanggal-tanggal penting akademik untuk semester berjalan.</p>
    </div>

    @if ($this->events->isEmpty())
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="calendar-days" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Belum ada kalender akademik</p>
            <p class="mt-1 text-sm text-neutral-500">Belum ada event yang diumumkan untuk semester ini.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->events as $event)
                <div wire:key="event-{{ $event->id }}" class="rounded-2xl bg-white p-5 shadow-border">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-neutral-900">{{ $event->nama }}</h2>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass[$event->status] }}">
                                    {{ $statusLabel[$event->status] }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-neutral-500">
                                {{ $kategoriLabel[$event->kategori] ?? $event->kategori }}
                                @if ($event->semester)
                                    · {{ $event->semester->nama }} ({{ $event->semester->kode }})
                                @else
                                    · Semua semester
                                @endif
                            </p>
                            @if ($event->deskripsi)
                                <p class="mt-2 text-sm text-neutral-600">{{ $event->deskripsi }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right text-sm text-neutral-600">
                            <div>{{ $event->tanggal_mulai->translatedFormat('d M Y, H:i') }}</div>
                            <div class="text-neutral-400">s.d.</div>
                            <div>{{ $event->tanggal_selesai->translatedFormat('d M Y, H:i') }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
