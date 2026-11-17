<?php

namespace Database\Factories;

use App\Models\Provinsi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provinsi>
 */
class ProvinsiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Lewati kode yang sudah dibuat eksplisit oleh test (mis. '35'), agar tidak bentrok unik.
            'kode' => function (): string {
                do {
                    $kode = fake()->unique()->numerify('##');
                } while (Provinsi::query()->where('kode', $kode)->exists());

                return $kode;
            },
            'nama' => fake()->state(),
        ];
    }
}
