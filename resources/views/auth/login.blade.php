@extends('layouts.web')

@section('title', 'Masuk — ' . config('app.name'))

@section('content')
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
    <div class="flex w-full max-w-4xl flex-col items-center gap-10 md:flex-row md:items-center md:justify-center md:gap-16 bg-white p-8 rounded-lg">
        <div class="flex flex-col items-center text-center md:w-2/5 md:items-center md:text-center">
            @if ($logoPerguruanTinggiSrc)
                <img
                    src="{{ $logoPerguruanTinggiSrc }}"
                    alt="{{ $namaPerguruanTinggi !== '' ? $namaPerguruanTinggi : config('app.name') }}"
                    class="mb-4 h-48 w-48 rounded-2xl bg-white object-contain"
                />
            @else
                <div class="mb-4 flex h-28 w-28 items-center justify-center rounded-2xl bg-neutral-900 text-white shadow-lg shadow-neutral-900/10">
                    <i data-lucide="graduation-cap" class="h-16 w-16" aria-hidden="true"></i>
                </div>
            @endif
            <p class="text-[1.75rem] font-bold text-neutral-500">{{ $namaPerguruanTinggi !== '' ? $namaPerguruanTinggi : config('app.name') }}</p>
            <p>{{ config('app.name') }}</p>
        </div>

        <div class="w-full max-w-md md:w-3/5">
            <div class="rounded-2xl bg-white p-8 shadow-border">

                <div class="mb-6 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">Masuk</h1>
                    <p class="mt-2 text-sm text-neutral-600">Masuk dengan akun admin, dosen, atau mahasiswa Anda.</p>
                </div>

                @if (session('error'))
                    <div class="mb-6 flex gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-red-600" aria-hidden="true"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <form method="post" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="login" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-neutral-700">
                            <i data-lucide="user" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                            Email atau username
                        </label>
                        <input
                            id="login"
                            name="login"
                            type="text"
                            value="{{ old('login') }}"
                            required
                            autocomplete="username"
                            autofocus
                            class="w-full rounded-lg bg-white px-3 py-2.5 text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('login') ring-2 ring-red-500 @enderror"
                            placeholder="nama@institusi.ac.id atau NIM/kode dosen"
                        />
                        @error('login')
                            <p class="mt-1.5 flex items-center gap-1 text-sm text-red-600">
                                <i data-lucide="alert-circle" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 flex items-center gap-2 text-sm font-medium text-neutral-700">
                            <i data-lucide="lock" class="h-4 w-4 text-neutral-500" aria-hidden="true"></i>
                            Kata sandi
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                class="w-full rounded-lg bg-white px-3 py-2.5 pr-10 text-neutral-900 shadow-border outline-none ring-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('password') ring-2 ring-red-500 @enderror"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                id="toggle-password"
                                aria-label="Tampilkan kata sandi"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 transition hover:text-neutral-600"
                            >
                                <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                <i data-lucide="eye-off" class="hidden h-4 w-4" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-600">
                            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/10" {{ old('remember') ? 'checked' : '' }} />
                            Ingat saya
                        </label>
                        <a href="{{ route('forgot-password') }}" class="text-sm font-semibold text-neutral-600 transition hover:text-neutral-900">
                            Lupa password?
                        </a>
                    </div>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2"
                    >
                        <i data-lucide="log-in" class="h-4 w-4" aria-hidden="true"></i>
                        Masuk
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-neutral-500">
                Belum aktivasi akun Anda?
                <a href="{{ route('aktivasi') }}" class="font-semibold text-neutral-900 hover:underline">Aktivasi akun di sini</a>
            </p>
        </div>
    </div>
</div>

<script>
    document.getElementById('toggle-password').addEventListener('click', function () {
        var input = document.getElementById('password');
        var willShow = input.type === 'password';
        input.type = willShow ? 'text' : 'password';
        this.querySelectorAll('svg, i').forEach(function (icon) {
            icon.classList.toggle('hidden');
        });
        this.setAttribute('aria-label', willShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
</script>
@endsection
