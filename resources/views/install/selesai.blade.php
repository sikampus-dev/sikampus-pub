@extends('install.layout', ['step' => 'jalankan'])
@section('title', 'Selesai')

@section('content')
    <div class="card">
        <div class="box box-ok"><strong>Sikampus berhasil dipasang.</strong></div>

        <table>
            @foreach ($notes as $note)
                <tr><td>{{ $note }}</td></tr>
            @endforeach
        </table>

        <p style="margin-top:1.5rem">
            Masuk memakai <strong>{{ $email }}</strong> dan password yang tadi Anda tentukan.
        </p>

        <div class="actions">
            <a class="btn" href="{{ url('/') }}">Buka aplikasi</a>
        </div>
    </div>

    <div class="card">
        <h2>Halaman pemasangan sudah ditutup</h2>
        <p class="muted" style="margin-top:0">
            Alamat <code>/install</code> kini menjawab 404 dan tidak bisa dijalankan lagi. Tidak ada
            berkas yang perlu Anda hapus secara manual.
        </p>
    </div>
@endsection
