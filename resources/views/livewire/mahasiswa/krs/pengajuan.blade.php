@section('title', 'Pengajuan KRS — ' . config('app.name'))
@section('header_title', 'Pengajuan KRS')

@php
    $finance = $this->financeCheck;
    $periode = $this->periodeKrsCheck;
    $data = $this->filteredData;
    $formatIdr = fn (float $v) => 'Rp'.number_format($v, 0, ',', '.');
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @error('selectedKelas')
        <div class="rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold">{{ $message }}</p>
            @if (session('prasyarat_violations'))
                <ul class="mt-2 list-disc space-y-1 pl-4">
                    @foreach (session('prasyarat_violations') as $v)
                        <li><span class="font-medium">{{ $v['matkul_diajukan'] }}</span>: belum memenuhi prasyarat {{ $v['kode_matkul_prasyarat'] ? $v['kode_matkul_prasyarat'].' — ' : '' }}{{ $v['matkul_prasyarat'] }} (wajib nilai minimal C)</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @enderror

    @if ($finance['allowed'] && ($finance['allowed_via_keringanan_biaya'] ?? false))
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
            <div class="flex gap-3">
                <i data-lucide="check-circle-2" class="h-8 w-8 shrink-0 text-sky-600" aria-hidden="true"></i>
                <div class="min-w-0 space-y-2">
                    <h3 class="text-base font-semibold text-sky-900">Akses pengajuan KRS dari keringanan biaya</h3>
                    <p class="text-sm text-sky-950/90">
                        Anda memiliki pengajuan keringanan biaya yang <strong>disetujui</strong> untuk semester ini, sehingga dapat mengisi KRS meskipun persentase pembayaran terhadap tagihan berlaku belum mencapai syarat minimal.
                    </p>
                    @if ($finance['persentase_minimum_required'] !== null)
                        <ul class="list-inside list-disc text-sm text-sky-950/85">
                            <li>Persentase pembayaran saat ini: <strong>{{ $finance['persentase_pembayaran'] }}%</strong></li>
                            <li>Persyaratan minimal (biasanya): <strong>{{ $finance['persentase_minimum_required'] }}%</strong>{{ $finance['aturan']['nama'] ?? null ? ' — '.$finance['aturan']['nama'] : '' }}</li>
                        </ul>
                    @endif
                    <p class="pt-1 text-sm">
                        <a href="{{ route('mahasiswa.keringanan-biaya') }}" class="font-semibold text-sky-700 underline-offset-2 hover:underline">Lihat keringanan biaya</a>
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if (! $finance['allowed'] && $finance['persentase_minimum_required'] !== null)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <div class="flex gap-3">
                <i data-lucide="shield-alert" class="h-8 w-8 shrink-0 text-amber-600" aria-hidden="true"></i>
                <div class="min-w-0 space-y-2">
                    <h3 class="text-base font-semibold text-amber-900">Pengajuan KRS belum dapat dilakukan</h3>
                    <p class="text-sm text-amber-950/90">
                        Persyaratan administratif keuangan belum terpenuhi. Pembayaran tagihan yang sudah berlaku (berdasarkan tanggal tagihan) belum mencapai persentase minimal yang ditetapkan untuk akses pengajuan KRS.
                    </p>
                    <ul class="list-inside list-disc text-sm text-amber-950/85">
                        <li>Persentase pembayaran saat ini: <strong>{{ $finance['persentase_pembayaran'] }}%</strong> (pembayaran disetujui terhadap total tagihan berlaku)</li>
                        <li>Persentase minimal yang disyaratkan: <strong>{{ $finance['persentase_minimum_required'] }}%</strong>{{ $finance['aturan']['nama'] ?? null ? ' — '.$finance['aturan']['nama'] : '' }}</li>
                        <li>Total tagihan berlaku: {{ $formatIdr($finance['total_tagihan_berlaku']) }} · Sudah terbayar (disetujui): {{ $formatIdr($finance['total_terbayar_disetujui']) }}</li>
                    </ul>
                    <p class="pt-1 text-sm">
                        <a href="{{ route('mahasiswa.tagihan') }}" class="font-semibold text-sky-700 underline-offset-2 hover:underline">Lihat tagihan saya</a>
                    </p>
                </div>
            </div>
        </div>
    @endif

    @unless ($periode['allowed'])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <div class="flex gap-3">
                <i data-lucide="calendar-off" class="h-8 w-8 shrink-0 text-amber-600" aria-hidden="true"></i>
                <div class="min-w-0 space-y-1">
                    <h3 class="text-base font-semibold text-amber-900">Pengajuan KRS sedang tidak dibuka</h3>
                    <p class="text-sm text-amber-950/90">{{ $periode['alasan'] ?? 'Periode pengisian KRS sedang tidak aktif.' }}</p>
                </div>
            </div>
        </div>
    @endunless

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-neutral-500">Total Mata Kuliah Dipilih</div>
                <div class="text-2xl font-bold text-neutral-900">{{ count($selectedKelas) }} mata kuliah</div>
            </div>
            <div class="text-right">
                <div class="text-sm text-neutral-500">Total SKS</div>
                <div class="text-2xl font-bold text-sky-600">{{ $this->totalSks }} SKS</div>
            </div>
        </div>
    </div>

    <input
        type="text"
        wire:model.live.debounce.400ms="search"
        placeholder="Cari mata kuliah, dosen, atau ruangan..."
        class="w-full rounded-lg px-4 py-2.5 text-sm shadow-border outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
    />

    @if (count($this->data) === 0)
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="book-open" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Tidak ada kelas tersedia</p>
            <p class="mt-1 text-sm text-neutral-500">Tidak ada kelas pada semester aktif yang sesuai dengan prodi, angkatan, dan kelas mahasiswa Anda.</p>
        </div>
    @elseif (count($data) === 0)
        <div class="rounded-2xl bg-white p-10 text-center shadow-border">
            <i data-lucide="alert-circle" class="mx-auto h-10 w-10 text-neutral-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium text-neutral-700">Tidak ada hasil pencarian</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-border">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="w-10 px-4 py-3">Pilih</th>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Mata Kuliah</th>
                            <th class="w-16 px-4 py-3 text-center">SKS</th>
                            <th class="px-4 py-3">Dosen</th>
                            <th class="w-24 px-4 py-3">Status</th>
                            <th class="w-24 px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($data as $item)
                            @php
                                $isSelected = in_array($item['id_kelas'], $selectedKelas, true);
                                $isDisabled = $item['sudah_dipilih'];
                            @endphp
                            <tr wire:key="kelas-{{ $item['id_kelas'] }}" class="{{ $isSelected ? 'bg-sky-50/50' : ($isDisabled ? 'bg-neutral-50/50 text-neutral-500' : '') }}">
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        @checked($isSelected)
                                        @disabled($isDisabled || ! $this->canSubmitNewKrs)
                                        wire:click="toggleKelas({{ $item['id_kelas'] }})"
                                        class="h-4 w-4 rounded border-neutral-300 text-sky-600 focus:ring-2 focus:ring-sky-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-sky-600">{{ $item['matkul']->kode ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-neutral-900">{{ $item['matkul']->nama ?? '-' }}</span>
                                    @if ($item['kode_kelas'])
                                        <span class="ml-1 text-xs text-neutral-500">({{ $item['kode_kelas'] }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center font-medium text-neutral-700">{{ $item['matkul']->sks ?? 0 }}</td>
                                <td class="px-4 py-3 text-neutral-700">{{ $item['dosen']->nama ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($item['sudah_dipilih'] && $item['krs_status'] === 'acc')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                            <i data-lucide="check-circle-2" class="h-3 w-3" aria-hidden="true"></i>
                                            Disetujui
                                        </span>
                                    @elseif ($item['sudah_dipilih'] && $item['krs_status'] === 'pending')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-700">Pending</span>
                                    @elseif ($isSelected)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700">Dipilih</span>
                                    @else
                                        <span class="text-neutral-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($item['sudah_dipilih'] && $item['krs_status'] === 'pending' && $item['id_krs'])
                                        <button
                                            type="button"
                                            wire:click="confirmCancel({{ $item['id_krs'] }})"
                                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                                        >
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                            Hapus
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($this->newSelectionsCount > 0 && $this->canSubmitNewKrs)
        <div class="flex justify-end">
            <button
                type="button"
                wire:click="submit"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:opacity-50"
            >
                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                Ajukan KRS ({{ $this->newSelectionsCount }} mata kuliah)
            </button>
        </div>
    @endif

    @if ($confirmingCancelId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Batalkan pengajuan KRS?</h3>
                <p class="mt-2 text-sm text-neutral-600">Tindakan ini tidak dapat dibatalkan.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelCancel" class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50">Batal</button>
                    <button type="button" wire:click="cancelKrs" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700">Ya, batalkan</button>
                </div>
            </div>
        </div>
    @endif
</div>
