<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ukuran standar template KTM (px) — diset saat admin unggah template
    |--------------------------------------------------------------------------
    */
    'template_width' => (int) env('KTM_TEMPLATE_WIDTH', 800),
    'template_height' => (int) env('KTM_TEMPLATE_HEIGHT', 457),

    /*
    |--------------------------------------------------------------------------
    | Teks header pada gambar KTM (di atas, kanan)
    |--------------------------------------------------------------------------
    */
    'title' => env('KTM_TITLE_LINE', 'Kartu Tanda Mahasiswa'),
    /* Fallback bila `settings.app_univ_name` kosong */
    'university_name' => env('KTM_UNIVERSITY_NAME', 'Universitas'),

    /*
    |--------------------------------------------------------------------------
    | Font TTF untuk render teks (wajib — Intervention membutuhkan file font)
    |--------------------------------------------------------------------------
    | Jika null, KtmImageGenerator memilih path umum: resources/fonts, DejaVu
    | (Linux), atau font sistem (macOS). Set lewat .env bila perlu, mis.:
    | KTM_FONT_BOLD=/path/ke/DejaVuSans-Bold.ttf
    | KTM_FONT_REGULAR=/path/ke/DejaVuSans.ttf
    */
    'font_bold' => env('KTM_FONT_BOLD'),
    'font_regular' => env('KTM_FONT_REGULAR'),

    /*
    |--------------------------------------------------------------------------
    | Posisi teks (proporsi lebar/tinggi template 0.0 - 1.0)
    |--------------------------------------------------------------------------
    */
    'layout' => [
        /* Ukuran teks: dikalikan min(lebar, tinggi) piksel — diset agar sekitar 24–38 px @ 800×457 */
        'header_anchor_x' => 0.94,
        /* Anchor X saat perataan header diatur ke kiri (mirror dari 1 - header_anchor_x) */
        'header_anchor_x_left' => 0.06,
        'header_title_y' => 0.1,
        'header_title_size' => 0.053,
        'header_univ_y' => 0.16,
        'header_univ_size' => 0.083,
        /* Fallback bila belum diatur lewat Pengaturan > KTM > Pengaturan Header (tabel settings) */
        'header_align' => 'right',
        'header_title_color' => '000000',
        'header_univ_color' => '000000',
        /* Foto mahasiswa (kolom `mahasiswa.foto`); placeholder jika kosong — kiri, di bawah area logo */
        'photo_x' => 0.04,
        /* Di bawah logo: logo_offset_y + logo_max_height ≈ 0,28 — sesuaikan bila perlu */
        'photo_y' => 0.40,
        'photo_width_ratio' => 0.26,
        'photo_height_ratio' => 0.5,
        /* `top` | `top-center` | `center` | … — posisi crop cover foto (utamakan `top` agar kepala tidak terpotong) */
        'photo_cover_position' => 'top',
        /* Jarak horizontal dari kanan foto ke teks NIM/NAMA/PRODI (proporsi lebar) */
        'data_gap_from_photo' => 0.03,
        'data_start_y' => 0.46,
        'data_line_size' => 0.056,
        /* Jeda setelah setiap blok (NIM / NAMA / PRODI) — bukan hanya sisa baris */
        'data_line_gap' => 0.055,
        'data_text_line_height' => 1.32,
        /* Logo (settings: app_univ_logo) — kiri atas, proporsi terhadap lebar/tinggi template */
        'logo_max_height_ratio' => 0.22,
        'logo_offset_x' => 0.04,
        'logo_offset_y' => 0.04,
    ],
];
