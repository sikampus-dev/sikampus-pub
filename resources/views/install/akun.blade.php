@extends('install.layout', ['step' => 'akun'])
@section('title', 'Identitas & Akun')

@section('content')
    <div class="card">
        <h2>Identitas perguruan tinggi &amp; akun admin</h2>
        <p class="muted" style="margin-top:0">
            Akun ini yang Anda pakai untuk masuk pertama kali. Sikampus tidak memasang akun bawaan
            apa pun, jadi hanya akun inilah yang akan bisa membuka aplikasi.
        </p>

        <form method="post" action="{{ route('install.account.store') }}">
            @csrf
            <label for="institution_name">Nama perguruan tinggi</label>
            <input id="institution_name" name="institution_name" value="{{ old('institution_name') }}" required>
            @error('institution_name')<p class="err">{{ $message }}</p>@enderror

            <div class="row">
                <div>
                    <label for="admin_name">Nama admin</label>
                    <input id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required>
                    @error('admin_name')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="admin_email">Email admin</label>
                    <input id="admin_email" name="admin_email" type="email" value="{{ old('admin_email') }}" required>
                    @error('admin_email')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="admin_password">Password</label>
                    <input id="admin_password" name="admin_password" type="password" minlength="8" autocomplete="new-password" required>
                    <p class="muted" style="margin:.35rem 0 0;font-size:.8rem">Minimal 8 karakter.</p>
                    @error('admin_password')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="admin_password_confirmation">Ulangi password</label>
                    <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                </div>
            </div>

            <div class="actions">
                <button class="btn" type="submit">Lanjut</button>
                <a class="btn btn-ghost" href="{{ route('install.database') }}">Kembali</a>
            </div>
        </form>
    </div>
@endsection
