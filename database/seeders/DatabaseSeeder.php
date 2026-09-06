<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Data referensi yang dibutuhkan SETIAP instalasi: agama, jenis kuliah, status akademik,
 * permission, dan seterusnya. Aman dijalankan di produksi.
 *
 * AKUN PENGGUNA SENGAJA TIDAK ADA DI SINI. Sampai sebelumnya seeder ini memanggil UserSeeder,
 * PmbUserSeeder, dan AssignRoleSeeder, yang membuat admin@gmail.com dan admin@pmb.com dengan
 * password tetap "Admin123!@#" — dan AssignRoleSeeder memberi yang pertama role Superadmin.
 * Repo ini publik dan kredensial itu ikut terbawa ke dalam setiap zip rilis, sehingga siapa pun
 * bisa membacanya lalu mencobanya di instalasi kampus mana pun. Karena `migrate --seed` adalah
 * langkah normal saat memasang (dan yang dijalankan deploy engine Sikampus Cloud), setiap
 * instalasi otomatis membawa pintu masuk itu.
 *
 * Akun admin pertama kini dibuat lewat `php artisan sikampus:create-admin` dengan kredensial
 * yang ditentukan pemasang. Untuk kebutuhan pengembangan lokal, akun contoh tersedia di
 * Database\Seeders\DemoAccountsSeeder yang harus dipanggil secara sadar dan menolak berjalan
 * di luar environment local.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(AgamaSeeder::class);
        $this->call(JenisKuliahSeeder::class);
        $this->call(StatusAkademikSeeder::class);
        $this->call(JenisKeluarSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(JenisNilaiSeeder::class);
        $this->call(JnsMatkulSeeder::class);
        $this->call(PekerjaanSeeder::class);
        $this->call(PendidikanSeeder::class);
        $this->call(PenghasilanSeeder::class);
        $this->call(JalurMasukSeeder::class);
        $this->call(JenisDaftarSeeder::class);
    }
}
