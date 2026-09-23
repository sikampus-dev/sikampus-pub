# Panduan Admin — Kalender Akademik

Dokumen ini untuk **admin panel** (Superadmin / Akademik) yang mengelola menu **Akademik >
Kalender Akademik**. Bukan dokumentasi teknis — tidak ada kode di sini, murni cara pakai dari
sisi tampilan.

## 1. Apa itu Kalender Akademik?

Kalender Akademik adalah daftar tanggal penting akademik (mis. "Pengisian KRS Semester Ganjil
2026/2027: 1–7 September") yang bisa **otomatis membatasi** kapan mahasiswa boleh mengisi KRS dan
kapan dosen boleh mengisi nilai — bukan sekadar catatan tanggal biasa.

Setiap event yang dibuat di sini otomatis:

- Tampil di halaman **Pengajuan KRS** mahasiswa dan **Kalender Akademik** di portal mahasiswa/dosen,
  sebagai informasi.
- **Mengunci** aksi terkait di luar tanggal yang ditentukan, khusus untuk dua kategori: **Pengisian
  KRS** dan **Pengisian Nilai** (lihat bagian 4).

## 2. Lokasi menu

**Akademik > Kalender Akademik** (menu paling atas di grup Akademik, di sidebar admin).

## 3. Kategori event

| Kategori | Menggerbang sesuatu? | Efeknya |
|---|---|---|
| **Pengisian KRS** | Ya | Membatasi kapan mahasiswa bisa mengajukan KRS baru (lihat bagian 4) |
| **Pengisian Nilai** | Ya | Membatasi kapan dosen bisa mengisi/mengubah nilai komponen (lihat bagian 4) |
| **Hari Libur** | Tidak | Murni informasi, tampil di kalender mahasiswa/dosen |
| **Wisuda** | Tidak | Murni informasi |
| **Lainnya** | Tidak | Murni informasi, untuk event yang tidak masuk kategori di atas |

Kategori **Pengisian KRS** dan **Pengisian Nilai** ditandai badge oranye di daftar admin — itu
tandanya event tersebut benar-benar mengunci/membuka akses, bukan cuma pengumuman.

## 4. Bagaimana ini mengunci KRS dan Nilai

- **Pengisian KRS**: selama tanggal berjalan berada **di luar** rentang event "Pengisian KRS" untuk
  semester aktif mahasiswa tersebut, tombol pilih mata kuliah di halaman Pengajuan KRS akan
  nonaktif, dan mahasiswa melihat pesan *"Pengajuan KRS sedang tidak dibuka"* beserta alasannya
  (belum dibuka / sudah berakhir, plus tanggalnya).
- **Pengisian Nilai**: selama tanggal berjalan berada di luar rentang event "Pengisian Nilai" untuk
  semester kelas yang bersangkutan, tombol **Simpan Nilai** di halaman input nilai dosen nonaktif,
  dengan pesan serupa *"Pengisian nilai sedang tidak dibuka"*.
- **Membatalkan** KRS yang sudah pernah diajukan (belum disetujui) **tidak** ikut dikunci —
  mahasiswa tetap bisa membatalkan kapan saja, hanya pengajuan **baru** yang dibatasi.

### Beberapa tahap dalam satu kategori (mis. KRS reguler + perbaikan)

Boleh, dan **tidak perlu perlakuan khusus** — buat saja dua (atau lebih) event terpisah dengan
kategori yang sama, judul berbeda, untuk semester yang sama. Sistem otomatis menganggap kategori
tersebut "terbuka" kalau tanggal berjalan masuk ke **salah satu** dari event-event itu.

Contoh untuk KRS dua tahap:

| Nama event | Kategori | Semester | Tanggal |
|---|---|---|---|
| Pengisian KRS Reguler | Pengisian KRS | Ganjil 2026/2027 | 1–7 Sep |
| Perbaikan KRS | Pengisian KRS | Ganjil 2026/2027 | 15–20 Sep |

Hasilnya:

- **1–7 Sep** (Reguler): mahasiswa bisa mengajukan KRS baru.
- **8–14 Sep** (jeda di antara dua tahap): mahasiswa **tidak** bisa mengajukan — pesan yang
  muncul otomatis menunjuk ke tahap berikutnya ("Periode belum dibuka. Dibuka mulai 15 Sep
  2026..."), bukan seolah-olah KRS semester ini sudah selesai total.
- **15–20 Sep** (Perbaikan): mahasiswa bisa mengajukan KRS baru lagi.
- **Setelah 20 Sep**: tertutup, pesan menunjuk ke tanggal berakhirnya tahap yang paling akhir
  (Perbaikan), bukan tahap Reguler.

Pola yang sama berlaku untuk **Pengisian Nilai** kalau kampus punya tahap "input nilai" dan
"revisi/susulan nilai" terpisah.

### Kalau belum ada event untuk semester tertentu

**Defaultnya TERBUKA**, bukan terkunci. Kalau untuk semester berjalan belum ada satupun event
"Pengisian KRS"/"Pengisian Nilai" yang dibuat, mahasiswa dan dosen tetap bisa mengisi KRS/nilai
kapan saja — sama seperti sebelum fitur ini ada. Ini supaya kampus yang belum sempat mengisi
kalendernya untuk satu semester tidak mendadak terkunci total.

**Praktik yang disarankan:** buat event "Pengisian KRS" dan "Pengisian Nilai" untuk setiap semester
**sebelum** semester itu berjalan, supaya jendela waktunya benar-benar berlaku sejak awal.

### Panel admin TIDAK ikut terkunci

Superadmin/admin akademik yang mengelola KRS atau Nilai langsung lewat panel admin (menu
**Akademik > KRS** dan **Akademik > Nilai**) **selalu bisa** menambah/mengubah data kapan pun,
tidak peduli kalender akademik sedang buka atau tutup. Gerbang ini hanya berlaku untuk jalur
self-service mahasiswa (Pengajuan KRS) dan dosen (Input Nilai). Ini disengaja — untuk kasus
koreksi/susulan di luar jadwal normal, lakukan lewat panel admin.

## 5. Menambah event baru

1. Buka **Akademik > Kalender Akademik**, klik **Tambah Event**.
2. Isi **Nama** — judul yang akan dilihat mahasiswa/dosen, mis. "Pengisian KRS Semester Ganjil
   2026/2027".
3. Pilih **Kategori** (lihat tabel di bagian 3).
4. Pilih **Semester**:
   - Pilih semester tertentu kalau event ini hanya berlaku untuk semester itu (umum untuk KRS
     dan Nilai).
   - **Kosongkan** ("Semua semester (global)") untuk event yang tidak terikat satu semester,
     misalnya libur nasional atau wisuda.
5. Isi **Tanggal Mulai** dan **Tanggal Selesai** (tanggal + jam). Tanggal selesai tidak boleh
   sebelum tanggal mulai.
6. (Opsional) Isi **Deskripsi** untuk catatan tambahan yang ikut tampil ke mahasiswa/dosen.
7. Klik **Simpan**.

## 6. Mengubah atau menghapus event

- Klik ikon pensil pada baris event untuk mengubah.
- Klik ikon tempat sampah untuk menghapus — akan muncul konfirmasi sebelum benar-benar terhapus.
- Mengubah tanggal event yang sedang aktif **langsung berefek** ke mahasiswa/dosen saat itu juga
  (tidak perlu proses tambahan) — kalau memperpanjang periode KRS, begitu disimpan, mahasiswa
  langsung bisa mengajukan lagi.

## 7. Mencari dan memfilter

Halaman daftar punya tiga filter yang bisa dikombinasikan:

- **Kategori** — mis. hanya tampilkan event "Pengisian KRS".
- **Semester** — hanya event untuk semester tertentu (event global tidak akan muncul kalau
  difilter ke semester tertentu, karena memang tidak terikat semester mana pun).
- **Status** — **Aktif** (sedang berjalan sekarang), **Akan Datang**, atau **Selesai**.

Kolom pencarian mencari di **Nama** dan **Deskripsi**.

## 8. Siapa yang bisa mengelola

Menu ini memakai permission **"manage kalender akademik"**, dimiliki otomatis oleh role
**Superadmin** dan **Akademik**. Kalau ada staf lain (mis. admin prodi) yang perlu akses ini
tapi role-nya tidak termasuk dua itu, berikan permission tersebut langsung ke user yang
bersangkutan lewat **Pengaturan > Pengguna > (pilih user) > tab Permission**.

## 9. Pertanyaan umum

**Mahasiswa bilang tidak bisa mengajukan KRS padahal semesternya sudah aktif.**
Cek apakah sudah ada event "Pengisian KRS" untuk semester tersebut, dan apakah tanggal berjalan
ada di dalam rentang tanggalnya. Kalau periode sudah lewat, mahasiswa akan melihat kapan periode
itu berakhir di pesan errornya — perpanjang tanggal selesainya kalau memang perlu dibuka lagi.

**Dosen tidak bisa menyimpan nilai.**
Sama seperti KRS — cek event "Pengisian Nilai" untuk semester kelas tersebut. Kalau perlu
koreksi nilai di luar jadwal, gunakan panel admin (**Akademik > Nilai**), yang tidak terkena
gerbang ini.

**Perlu buka KRS/Nilai lebih awal dari rencana.**
Ubah **Tanggal Mulai** event yang bersangkutan ke waktu sekarang (atau lebih awal), simpan —
langsung berlaku, tidak perlu menunggu apa pun.

**Apakah menghapus semester akan ikut menghapus event kalendernya?**
Semester yang masih punya event kalender akademik terkait **tidak bisa dihapus** — sistem akan
menolak dan meminta event kalendernya dibereskan dulu (dipindah semester lain atau dihapus).
