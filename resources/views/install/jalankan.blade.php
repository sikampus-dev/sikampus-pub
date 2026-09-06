@extends('install.layout', ['step' => 'jalankan'])
@section('title', 'Pemasangan')

@section('content')
    <div class="card">
        <h2>Periksa sekali lagi</h2>

        @if (! empty($error))
            <div class="box box-err">
                <strong>Pemasangan gagal.</strong><br>{{ $error }}
            </div>
        @endif

        <table>
            <tr><td class="s">Database</td><td>{{ $database['db_username'] }}@{{ $database['db_host'] }}:{{ $database['db_port'] }}/{{ $database['db_database'] }}</td></tr>
            <tr><td class="s">Perguruan tinggi</td><td>{{ $account['institution_name'] }}</td></tr>
            <tr><td class="s">Admin</td><td>{{ $account['admin_name'] }} &lt;{{ $account['admin_email'] }}&gt;</td></tr>
        </table>

        <div class="box box-warn" style="margin-top:1.25rem">
            Menekan tombol di bawah akan membuat seluruh tabel dan memuat data referensi ke database
            tersebut. Proses ini bisa memakan waktu satu sampai dua menit — jangan menutup halaman.
        </div>

        <form method="post" action="{{ route('install.execute') }}">
            @csrf
            <div class="actions">
                <button class="btn" type="submit">Pasang sekarang</button>
                <a class="btn btn-ghost" href="{{ route('install.account') }}">Kembali</a>
            </div>
        </form>
    </div>
@endsection
