{{-- Layout wizard pemasangan, sengaja berdiri sendiri tanpa @vite: pada titik ini aset build
     memang sudah ada di dalam zip rilis, tapi installer harus tetap terbaca walau manifest Vite
     bermasalah — halaman inilah satu-satunya cara pemasang tahu apa yang salah. --}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Pemasangan') — Sikampus</title>
    <style>
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;background:#fafafa;color:#171717;line-height:1.6}
        .wrap{max-width:44rem;margin:0 auto;padding:3rem 1.5rem 5rem}
        .card{background:#fff;border:1px solid #e5e5e5;border-radius:.75rem;padding:2rem;margin-bottom:1.25rem}
        h1{font-size:1.5rem;margin:0 0 .25rem}
        h2{font-size:1.05rem;margin:0 0 1rem}
        .muted{color:#737373;font-size:.9rem;margin-top:0}
        .steps{display:flex;gap:.5rem;list-style:none;padding:0;margin:0 0 2rem;font-size:.8rem;flex-wrap:wrap}
        .steps li{padding:.35rem .75rem;border-radius:999px;background:#f0f0f0;color:#737373}
        .steps li.active{background:#171717;color:#fff}
        label{display:block;font-size:.875rem;font-weight:500;margin:.9rem 0 .35rem}
        input{width:100%;padding:.6rem .75rem;border:1px solid #d4d4d4;border-radius:.5rem;font-size:.95rem;font-family:inherit}
        input:focus{outline:2px solid #171717;outline-offset:-1px;border-color:#171717}
        .btn{display:inline-block;background:#171717;color:#fff;border:0;padding:.7rem 1.25rem;border-radius:.5rem;font-size:.9rem;font-weight:500;cursor:pointer;text-decoration:none}
        .btn:disabled{opacity:.45;cursor:not-allowed}
        .btn-ghost{background:#fff;color:#404040;border:1px solid #d4d4d4}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .err{color:#b91c1c;font-size:.85rem;margin:.35rem 0 0}
        .box{border-radius:.5rem;padding:.9rem 1.1rem;font-size:.9rem;margin-bottom:1rem}
        .box-warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
        .box-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .box-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
        table{width:100%;border-collapse:collapse;font-size:.9rem}
        td{padding:.5rem 0;border-bottom:1px solid #f0f0f0;vertical-align:top}
        td.s{width:5.5rem;font-weight:500}
        .ok{color:#047857}.no{color:#b91c1c}
        code{background:#f5f5f5;padding:.1rem .35rem;border-radius:.25rem;font-size:.85em}
        .actions{margin-top:1.75rem;display:flex;gap:.75rem;align-items:center}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Pemasangan Sikampus</h1>
    <p class="muted">Versi {{ config('sikampus.version') }}</p>

    <ol class="steps">
        @foreach (['persyaratan' => 'Persyaratan', 'database' => 'Database', 'akun' => 'Identitas & Akun', 'jalankan' => 'Pemasangan'] as $key => $label)
            <li class="{{ ($step ?? '') === $key ? 'active' : '' }}">{{ $label }}</li>
        @endforeach
    </ol>

    @yield('content')
</div>
</body>
</html>
