@extends('install.layout', ['step' => 'database'])
@section('title', 'Database')

@section('content')
    <div class="card">
        <h2>Koneksi database</h2>
        <p class="muted" style="margin-top:0">
            Buat database MySQL kosong lewat panel hosting Anda, lalu isikan datanya di bawah.
            Koneksinya diuji sebelum apa pun ditulis.
        </p>

        <form method="post" action="{{ route('install.database.store') }}">
            @csrf
            <div class="row">
                <div>
                    <label for="db_host">Host</label>
                    <input id="db_host" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required>
                    @error('db_host')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="db_port">Port</label>
                    <input id="db_port" name="db_port" value="{{ old('db_port', '3306') }}" required>
                    @error('db_port')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>

            <label for="db_database">Nama database</label>
            <input id="db_database" name="db_database" value="{{ old('db_database') }}" required>
            @error('db_database')<p class="err">{{ $message }}</p>@enderror

            <div class="row">
                <div>
                    <label for="db_username">Username</label>
                    <input id="db_username" name="db_username" value="{{ old('db_username') }}" required>
                    @error('db_username')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    {{-- Sengaja tanpa old(): mengembalikan password ke form berarti menyimpannya
                         di sesi lalu merendernya kembali ke HTML. --}}
                    <label for="db_password">Password</label>
                    <input id="db_password" name="db_password" type="password" autocomplete="off">
                    @error('db_password')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="actions">
                <button class="btn" type="submit">Uji koneksi &amp; lanjut</button>
                <a class="btn btn-ghost" href="{{ route('install.index') }}">Kembali</a>
            </div>
        </form>
    </div>
@endsection
