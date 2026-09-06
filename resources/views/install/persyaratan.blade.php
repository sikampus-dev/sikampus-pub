@extends('install.layout', ['step' => 'persyaratan'])
@section('title', 'Persyaratan')

@section('content')
    <div class="card">
        <h2>Persyaratan server</h2>

        @unless ($passes)
            <div class="box box-err">
                Sebagian syarat belum terpenuhi. Pemasangan tidak bisa dilanjutkan sampai semuanya hijau —
                melanjutkan hanya akan menghasilkan aplikasi yang gagal di tengah jalan.
            </div>
        @endunless

        <table>
            @foreach ($checks as $check)
                <tr>
                    <td class="s {{ $check['ok'] ? 'ok' : 'no' }}">{{ $check['ok'] ? 'OK' : 'BELUM' }}</td>
                    <td>
                        {{ $check['label'] }}
                        <div class="muted">{{ $check['detail'] }}</div>
                    </td>
                </tr>
            @endforeach
        </table>

        <div class="actions">
            @if ($passes)
                <a class="btn" href="{{ route('install.database') }}">Lanjut</a>
            @else
                <button class="btn" disabled>Lanjut</button>
                <a class="btn btn-ghost" href="{{ route('install.index') }}">Periksa ulang</a>
            @endif
        </div>
    </div>

    <div class="card">
        <h2>Satu hal di luar jangkauan pemeriksaan ini</h2>
        <p class="muted" style="margin-top:0">
            Pastikan document root hosting mengarah ke direktori <code>public/</code>, bukan ke akar
            aplikasi. Kalau tidak, berkas <code>.env</code> dan seluruh kode bisa diunduh siapa pun
            lewat browser. Pengaturan ini ada di panel hosting Anda dan tidak bisa diperbaiki dari sini.
        </p>
    </div>
@endsection
