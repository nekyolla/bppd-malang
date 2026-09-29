<?php

namespace Database\Factories;

use App\Models\KelasPelatihan;
use App\Models\Pelatihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KelasPelatihan>
 */
class KelasPelatihanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pelatihan_id' => Pelatihan::factory(),
            'nama_kelas' => strtoupper(fake()->unique()->bothify('?#')),
        ];
    }
}
