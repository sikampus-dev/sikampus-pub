@section('title', 'Plugin — ' . config('app.name'))
@section('header_title', 'Plugin')
@section('header_subtitle', 'Instal & kelola plugin aplikasi')
@section('header_icon', 'puzzle')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Pengaturan'],
        ['label' => 'Sistem'],
        ['label' => 'Plugin'],
    ]])
@endsection

<div class="space-y-6">
    @if (session('status'))
        <div
            class="flex gap-3 whitespace-pre-line rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            <i data-lucide="circle-check" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div
            class="flex gap-3 whitespace-pre-line rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-900"
            role="alert"
        >
            <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($uploadsAllowed)
        <div class="rounded-2xl bg-white p-8 shadow-border">
            <div class="mb-6">
                <h2 class="text-xl font-semibold tracking-tight text-neutral-900">Instal Plugin Baru</h2>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                    Unggah berkas <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-sm text-neutral-800">.zip</code> plugin
                    berisi <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-sm text-neutral-800">plugin.json</code>.
                    Plugin tidak otomatis aktif setelah diinstal — aktifkan dari daftar di bawah setelah Anda
                    memastikan sumbernya terpercaya. Menginstal plugin setara dengan menjalankan kode PHP
                    baru di aplikasi ini.
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="pluginZip" class="mb-2 block text-sm font-medium text-neutral-700">Berkas ZIP plugin</label>
                    <input
                        id="pluginZip"
                        type="file"
                        wire:model="pluginZip"
                        accept=".zip"
                        class="block w-full rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-900 shadow-border outline-none ring-neutral-900 transition file:mr-4 file:rounded-md file:border-0 file:bg-neutral-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-neutral-800 hover:file:bg-neutral-200 focus:border-neutral-900 focus:bg-white focus:ring-2 focus:ring-neutral-900/10 @error('pluginZip') ring-2 ring-red-500 @enderror"
                    >
                    <div wire:loading wire:target="pluginZip" class="mt-2 flex items-center gap-1.5 text-xs text-neutral-500">
                        <i data-lucide="loader-circle" class="h-3.5 w-3.5 animate-spin" aria-hidden="true"></i>
                        Mengunggah berkas...
                    </div>
                    @error('pluginZip')
                        <p class="mt-2 flex items-center gap-1 text-sm text-red-600">
                            <i data-lucide="alert-circle" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button
                        type="button"
                        wire:click="install"
                        wire:loading.attr="disabled"
                        wire:target="install"
                        class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2 disabled:opacity-60"
                    >
                        <i data-lucide="upload-cloud" class="h-4 w-4" aria-hidden="true"></i>
                        <span wire:loading.remove wire:target="install">Instal Plugin</span>
                        <span wire:loading wire:target="install">Menginstal...</span>
                    </button>
                </div>
            </div>
        </div>
    @else
        {{-- Tenant Sikampus Cloud: plugin dipasang oleh portal, bukan diunggah di sini. Lihat
             PluginInstaller::uploadsAllowed() untuk alasannya. --}}
        <div class="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50/60 p-6 text-sm text-amber-900">
            <i data-lucide="shield-check" class="h-5 w-5 shrink-0 text-amber-600" aria-hidden="true"></i>
            <div>
                <p class="font-semibold">Pemasangan plugin dikelola oleh Sikampus Cloud</p>
                <p class="mt-1 leading-relaxed">
                    Demi keamanan server bersama, instalasi Cloud tidak dapat memasang plugin dari berkas ZIP.
                    Hubungi tim Sikampus untuk memasang plugin. Plugin yang sudah terpasang tetap dapat
                    diaktifkan, dinonaktifkan, dan dihapus dari daftar di bawah.
                </p>
            </div>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-8 shadow-border">
        <h2 class="mb-6 text-xl font-semibold tracking-tight text-neutral-900">Plugin Terinstal</h2>

        @if ($this->plugins->isEmpty())
            <p class="text-sm text-neutral-500">Belum ada plugin yang diinstal.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs font-medium uppercase tracking-wide text-neutral-500">
                            <th class="py-2 pr-4">Nama</th>
                            <th class="py-2 pr-4">Versi</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Migrasi terakhir</th>
                            <th class="py-2 pr-0 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($this->plugins as $plugin)
                            <tr wire:key="plugin-{{ $plugin->id }}">
                                <td class="py-3 pr-4">
                                    <div class="font-medium text-neutral-900">{{ $plugin->name }}</div>
                                    <div class="text-xs text-neutral-500">{{ $plugin->slug }}</div>
                                </td>
                                <td class="py-3 pr-4 text-neutral-600">{{ $plugin->version }}</td>
                                <td class="py-3 pr-4">
                                    @if ($plugin->enabled)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Aktif</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-600">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-neutral-600">
                                    {{ $plugin->last_migrated_at?->translatedFormat('d M Y H:i') ?? '—' }}
                                </td>
                                <td class="py-3 pr-0">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($plugin->migrations_relative_path)
                                            <button
                                                type="button"
                                                wire:click="migrate('{{ $plugin->slug }}')"
                                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-neutral-600 shadow-border transition hover:bg-neutral-50"
                                            >
                                                <i data-lucide="database" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                                Migrasi
                                            </button>
                                        @endif

                                        @if ($plugin->settingsUrl())
                                            <a
                                                href="{{ $plugin->settingsUrl() }}"
                                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-neutral-600 shadow-border transition hover:bg-neutral-50"
                                            >
                                                <i data-lucide="settings" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                                Pengaturan
                                            </a>
                                        @endif

                                        @if ($plugin->enabled)
                                            <button
                                                type="button"
                                                wire:click="disable('{{ $plugin->slug }}')"
                                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-neutral-600 shadow-border transition hover:bg-neutral-50"
                                            >
                                                Nonaktifkan
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="enable('{{ $plugin->slug }}')"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-neutral-800"
                                            >
                                                Aktifkan
                                            </button>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="confirmDelete('{{ $plugin->slug }}')"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 shadow-border transition hover:bg-red-50"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($slugToDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-border-lg">
                <h3 class="text-base font-semibold text-neutral-900">Hapus plugin?</h3>
                <p class="mt-2 text-sm text-neutral-600">
                    Ketik <strong>{{ $slugToDelete }}</strong> untuk konfirmasi. Migration yang sudah
                    dijalankan tidak akan di-rollback otomatis, dan tindakan ini tidak dapat dibatalkan.
                </p>
                <input
                    type="text"
                    wire:model="confirmSlugInput"
                    placeholder="{{ $slugToDelete }}"
                    autocomplete="off"
                    autocapitalize="off"
                    autocorrect="off"
                    spellcheck="false"
                    class="mt-4 block w-full rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-900 shadow-border outline-none focus:ring-2 focus:ring-red-500"
                >
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="cancelDelete"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="destroy"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700"
                    >
                        Hapus Permanen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
