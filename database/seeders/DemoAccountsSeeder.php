<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Akun contoh untuk pengembangan lokal: admin@gmail.com dan admin@pmb.com, keduanya dengan
 * password yang tertulis apa adanya di dalam repo ini.
 *
 * DIPISAH dari DatabaseSeeder dengan sengaja — lihat docblock di sana untuk alasan lengkapnya.
 * Ringkasnya: kredensial ini publik, jadi tidak boleh pernah ikut terpasang otomatis pada
 * instalasi kampus.
 *
 * MENOLAK BERJALAN di luar environment "local". Penjagaan ini ada di sini, bukan hanya di
 * dokumentasi, karena `db:seed --class=DemoAccountsSeeder` bisa saja dijalankan orang di server
 * produksi tanpa menyadari akibatnya — dan akibatnya adalah Superadmin dengan password yang
 * bisa dibaca siapa pun di GitHub.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'DemoAccountsSeeder hanya boleh dijalankan di environment "local". '
                .'Untuk membuat akun admin pada instalasi sungguhan, gunakan: php artisan sikampus:create-admin'
            );
        }

        $this->call(UserSeeder::class);
        $this->call(PmbUserSeeder::class);
        $this->call(AssignRoleSeeder::class);
    }
}
