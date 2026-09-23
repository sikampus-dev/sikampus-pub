# Sikampus

**Sikampus** adalah aplikasi Sistem Informasi Akademik (SIAK) berbasis Laravel 12 untuk
perguruan tinggi, dengan modul **PMB (Penerimaan Mahasiswa Baru)** yang terintegrasi di
dalamnya. Repo ini (`sikampus-pub`) adalah produk self-hosted yang bisa dipasang dan dikelola
sendiri oleh kampus — lewat klon Git maupun lewat unduhan rilis siap pakai — tanpa bergantung
pada Sikampus Cloud.

## 1. Gambaran Sekilas

Sikampus terutama berupa REST API yang dikonsumsi oleh aplikasi frontend terpisah (portal
mahasiswa, dosen, dan panel admin utama — lihat `FRONTEND_URL` di `.env`), dilengkapi:

- **Wizard pemasangan web** (`/install`) — pemasangan instalasi baru tanpa akses shell:
  cek persyaratan server → konfigurasi database → identitas & akun superadmin pertama →
  eksekusi migrasi. Otomatis nonaktif (404) begitu instalasi selesai.
- **Panel superadmin web** (Blade + Livewire, di balik `/dashboard`) — konfigurasi environment,
  pemicu migrasi database (**Pengaturan > Migrasi**), cek & jalankan pembaruan versi
  (**Pengaturan > Sistem > Cek Pembaruan**), serta pengujian upload file.
- **Wizard pembaruan otomatis** (`/pembaruan`) — memperbarui instalasi ke versi rilis terbaru
  lewat dua jalur (arsip rilis siap pakai, atau `git pull` + `composer` + `npm`), dengan
  rollback otomatis kalau penukaran berkas gagal.
- **Perintah CLI cadangan** (`php artisan sikampus:update`) — melanjutkan pembaruan yang
  tertunda kalau wizard web terhenti di tengah jalan (mis. server membatasi waktu eksekusi).

Cakupan domain akademik yang didukung antara lain:

- **Manajemen akademik** — Fakultas, Program Studi, Kurikulum, Mata Kuliah, Kelas, Jadwal, KRS
  (Kartu Rencana Studi), dan penilaian (Nilai, Revisi Nilai, Konversi Nilai).
- **RPS (Rencana Pembelajaran Semester)** — pemetaan CPL, CPMK, Sub-CPMK, dan rencana
  pembelajaran per dosen/kelas.
- **Keuangan** — tagihan, rincian tagihan, pembayaran, komponen biaya, struktur biaya, dan
  keringanan biaya.
- **Tugas akhir** — alur Tugas Akhir → Ujian Sidang → Yudisium → Wisuda.
- **PMB (Penerimaan Mahasiswa Baru)** — modul pendaftaran mahasiswa baru yang berdiri sendiri
  (auth & sesi terpisah dari sistem utama), dengan prefix rute `/pmb`.
- **Autentikasi & otorisasi berlapis** — role & permission berbasis [Spatie Permission] untuk
  akses panel/modul, kolom `role` legacy untuk tipe akun (admin, dosen, mahasiswa), serta
  scoping data per fakultas/program studi untuk admin (lihat `SPATIE_PERMISSION_SETUP.md`).
- **Integrasi sistem eksternal** — endpoint khusus (mis. untuk Siska) yang diakses via API key
  (`PARTNER_API_KEYS`), bukan token Sanctum biasa.

> Catatan keamanan: instalasi baru **tidak lagi** memiliki akun admin bawaan
> (`admin@gmail.com`). Akun superadmin pertama dibuat lewat wizard `/install`, atau lewat
> `php artisan sikampus:create-admin` untuk pemasangan manual.

## 2. Stack yang Digunakan

**Backend**

- [PHP](https://www.php.net/) ^8.2
- [Laravel](https://laravel.com/) ^12.0
- [Laravel Sanctum](https://laravel.com/docs/sanctum) — autentikasi API (SPA cookie & bearer token)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) — role & permission
- [Livewire](https://livewire.laravel.com/) ^4.3 — panel superadmin & wizard pemasangan/pembaruan
- [PestPHP](https://pestphp.com/) — testing framework
- [Laravel Pint](https://laravel.com/docs/pint) — code style / formatter
- [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/) — import/export Excel
- [DomPDF](https://github.com/dompdf/dompdf) — generate PDF
- [Intervention Image](https://image.intervention.io/) — pemrosesan gambar (mis. KTM mahasiswa)

**Frontend (panel Blade minimal)**

- [Vite](https://vitejs.dev/) ^7
- [Tailwind CSS](https://tailwindcss.com/) ^4
- [Tom Select](https://tom-select.js.org/) — dropdown/select interaktif
- [Sonner](https://sonner.emilkowal.ski/) — notifikasi toast

**Database & Infrastruktur**

- MySQL (database utama & database testing `siak_testing`)
- Redis (opsional, untuk cache/queue)
- Queue driver: database
- Session driver: database

> Catatan: Aplikasi ini murni backend/API untuk pengguna akhir. Untuk antarmuka mahasiswa/dosen
> digunakan aplikasi frontend terpisah yang mengonsumsi API ini (lihat konfigurasi
> `FRONTEND_URL`); yang berbasis Blade di repo ini khusus untuk superadmin (pemasangan,
> pembaruan, konfigurasi, migrasi).

## 3. Cara Instal Melalui GitHub (klon Git — untuk pengembangan)

Jalur ini cocok untuk pengembang yang ingin menjalankan/mengubah kode sumber langsung, karena
`composer install`/`npm install` dijalankan sendiri.

### Prasyarat

- PHP >= 8.2 beserta ekstensi yang dibutuhkan Laravel
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) & npm
- MySQL
- (Opsional) Redis

### Langkah instalasi

```bash
git clone git@github.com:sikampus-dev/sikampus-pub.git
cd sikampus-pub
```

```bash
composer install
npm install
```

```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan konfigurasi di file `.env`, minimal:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — koneksi database utama
- `FRONTEND_URL` — URL aplikasi frontend (default `http://localhost:3000`)

```bash
php artisan migrate
```

Buat akun superadmin pertama (jangan pernah mengandalkan seeder akun — repo publik ini sengaja
tidak menyertakannya):

```bash
php artisan sikampus:create-admin --name="Nama Anda" --email=admin@contoh.id
```

Jalankan aplikasi (server + queue worker + log tail + Vite, sekaligus):

```bash
composer dev
```

Aplikasi akan berjalan di `http://localhost:8000` (default `php artisan serve`).

## 4. Cara Instal Melalui Download Source Code (rilis siap pakai)

Jalur ini untuk kampus/pemasang yang **tidak** ingin menjalankan Composer/npm di server —
artefak rilis di [GitHub Releases](https://github.com/sikampus-dev/sikampus-pub/releases) sudah
berisi `vendor/` dan `public/build/` hasil kompilasi, dan pemasangannya dipandu lewat wizard web
`/install` (tanpa perlu akses shell sama sekali).

1. Buka halaman [Releases](https://github.com/sikampus-dev/sikampus-pub/releases) repository ini,
   lalu unduh berkas zip rilis terbaru (mis. `sikampus-v1.1.1.zip`) beserta `.sha256`-nya untuk
   verifikasi integritas unduhan.
2. Ekstrak isi zip ke document root server (arahkan virtual host/domain ke folder `public/`
   hasil ekstrak).
3. Pastikan folder `storage/` dan `bootstrap/cache/` dapat ditulis oleh web server.
4. Buka `https://domain-anda/install` di browser, lalu ikuti wizard empat langkah:
   - **Persyaratan** — pengecekan ekstensi PHP, versi, dan permission folder.
   - **Database** — koneksi ke MySQL (wizard menulis `.env` secara otomatis, termasuk
     `APP_KEY`).
   - **Identitas & akun** — nama aplikasi serta akun superadmin pertama.
   - **Pemasangan** — menjalankan migrasi dan menandai instalasi selesai
     (`storage/app/installed.lock`).
5. Selesai — `/install` otomatis menjawab 404 setelah instalasi ditandai selesai, dan Anda bisa
   masuk lewat halaman login menggunakan akun yang baru dibuat.

> Alternatif manual: kalau lebih suka menjalankan lewat shell, langkah 3 dan 4 (`cp .env.example
> .env` → `php artisan key:generate` → sesuaikan `.env` → `php artisan migrate` →
> `php artisan sikampus:create-admin`) bisa dijalankan seperti pada jalur klon Git di atas —
> zip rilis sudah menyertakan `vendor/` sehingga `composer install`/`npm install` tidak
> diperlukan.

## Memperbarui Instalasi

- **Lewat panel** — **Pengaturan > Sistem > Cek Pembaruan** menampilkan versi terpasang vs versi
  rilis terbaru beserta changelog; wizard **`/pembaruan`** memasang versi baru langkah demi
  langkah (unduh → verifikasi checksum → tukar berkas → migrasi → selesai), dengan rollback
  otomatis kalau ada langkah yang gagal.
- **Lewat CLI (jalan darurat)** — kalau wizard web terhenti karena batas waktu eksekusi server:

  ```bash
  php artisan sikampus:update
  ```

- Menjalankan pembaruan (bukan sekadar mengecek) menuntut **license key** yang terverifikasi ke
  Sikampus Platform. Pengecekan versi baru sendiri **tidak** dibatasi lisensi — instalasi tanpa
  key tetap diberi tahu kalau ada versi lebih baru.
- Versi aplikasi berasal dari berkas [`VERSION`](VERSION) di root project — satu sumber
  kebenaran untuk kedua cara instalasi di atas.

## Menjalankan Test

Test menggunakan Pest dan berjalan terhadap database MySQL bernama `siak_testing` (bukan
SQLite). Pastikan database tersebut sudah dibuat sebelum menjalankan test.

```bash
composer test
```

atau

```bash
php artisan test
php artisan test --filter=NamaTest
vendor/bin/pest tests/Feature/KrsPengajuanApprovalTest.php
```

## Format / Lint Kode

```bash
vendor/bin/pint          # perbaiki otomatis
vendor/bin/pint --test   # cek saja, tanpa mengubah file
```

## Membangun Artefak Rilis (untuk maintainer)

```bash
./scripts/build-release.sh          # dari HEAD
./scripts/build-release.sh v1.1.1   # dari sebuah tag
```

Menghasilkan `dist/<nama-rilis>.zip` (berisi `vendor/` + `public/build/` yang sudah jadi),
`.sha256`, dan manifest sha256 per berkas — dibangun dari `git archive`, bukan direktori kerja,
sehingga `.env` dan berkas lokal lain tidak mungkin ikut terbawa.
