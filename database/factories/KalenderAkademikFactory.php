<?php

namespace Database\Factories;

use App\Models\KalenderAkademik;
use Illuminate\Database\Eloquent\Factories\Factory;

class KalenderAkademikFactory extends Factory
{
    public function definition(): array
    {
        $mulai = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'nama' => fake()->sentence(3),
            'kategori' => fake()->randomElement(array_keys(KalenderAkademik::KATEGORI_OPTIONS)),
            'id_semester' => null,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => (clone $mulai)->modify('+7 days'),
            'deskripsi' => null,
        ];
    }
}
