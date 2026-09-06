<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Buat akun superadmin pertama untuk sebuah instalasi.
 *
 * Menggantikan Database\Seeders\UserSeeder sebagai cara instalasi mendapatkan admin pertamanya.
 * Seeder itu memakai kredensial tetap yang tertulis di repo publik; perintah ini menerima
 * kredensial dari pemasang, sehingga tidak ada dua instalasi yang berbagi password.
 *
 * Dipakai oleh tiga jalur: pemasangan manual lewat shell, wizard installer web, dan deploy
 * engine Sikampus Cloud yang menjalankannya di dalam direktori tenant.
 *
 * Password TIDAK PERNAH ditampilkan kembali oleh perintah ini — pada pemakaian lewat Process
 * dari sistem lain, keluaran perintah lazim ikut tercatat di log dan ditampilkan ke layar.
 *
 * Password juga bisa diberikan lewat environment SIKAMPUS_ADMIN_PASSWORD, dan itulah cara yang
 * HARUS dipakai saat perintah ini dijalankan oleh sistem lain. Argumen proses (--password=...)
 * masuk ke argv yang bisa dibaca semua user lokal lewat /proc — masalah yang sama persis dengan
 * menempelkan token ke URL git, dan sudah jadi catatan tersendiri di deploy engine Sikampus.
 */
class CreateAdmin extends Command
{
    protected $signature = 'sikampus:create-admin
        {--name= : Nama lengkap}
        {--email= : Alamat email untuk login}
        {--password= : Password (minimal 8 karakter)}
        {--force : Perbarui password kalau email sudah terdaftar}';

    protected $description = 'Buat (atau perbarui) akun superadmin untuk instalasi ini';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nama lengkap');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password')
            ?: (string) getenv('SIKAMPUS_ADMIN_PASSWORD')
            ?: $this->secret('Password');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'password.min' => 'Password minimal 8 karakter.',
            'email.email' => 'Format email tidak valid.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing && ! $this->option('force')) {
            $this->error("Email {$email} sudah terdaftar. Gunakan --force untuk memperbarui passwordnya.");

            return self::FAILURE;
        }

        $user = $existing ?: new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->status = 'active';
        $user->save();

        // Role Spatie adalah sumber kebenaran untuk akses panel (kolom users.role hanya
        // menyatakan tipe akun) — tanpa langkah ini akun bisa login tapi tidak bisa membuka
        // satu modul pun.
        $role = Role::where('code', 'superadmin')->orWhere('name', 'Superadmin')->first()
            ?: Role::create(['name' => 'Superadmin', 'guard_name' => 'web', 'code' => 'superadmin']);

        $user->syncRoles([$role]);

        $this->info(($existing ? 'Akun diperbarui' : 'Akun superadmin dibuat').": {$email}");

        return self::SUCCESS;
    }
}
