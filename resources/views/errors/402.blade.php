@extends('layouts.web')

@section('title', 'Layanan Dihentikan Sementara — ' . config('app.name'))

@section('content')
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
    <div class="mx-auto max-w-md rounded-2xl bg-white p-8 text-center shadow-border">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
            <i data-lucide="credit-card" class="h-6 w-6" aria-hidden="true"></i>
        </div>
        <h1 class="text-lg font-semibold text-neutral-900">Layanan Sedang Dihentikan Sementara</h1>
        <p class="mt-2 text-sm leading-relaxed text-neutral-600">
            {{ $exception->getMessage() ?: 'Langganan Sikampus Cloud untuk kampus Anda sudah berakhir dan belum diperpanjang.' }}
        </p>
        {{--
            Tanpa tombol "Coba Lagi" (beda dari 503.blade.php) -- ini bukan pemeliharaan
            sementara yang selesai dengan sendirinya, mengulang tidak akan mengubah apa pun.
            Satu-satunya jalan keluar ada di LUAR tenant ini (akun Sikampus Platform), jadi
            tidak ada tautan aksi yang bisa ditawarkan dari sini.
        --}}
    </div>
</div>
@endsection
