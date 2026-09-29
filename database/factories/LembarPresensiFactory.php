<?php

namespace Database\Factories;

use App\Models\KelasPelatihan;
use App\Models\LembarPresensi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LembarPresensi>
 */
class LembarPresensiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kelas_pelatihan_id' => KelasPelatihan::factory(),
            'tanggal' => fn (array $attributes) => KelasPelatihan::findOrFail($attributes['kelas_pelatihan_id'])
                ->pelatihan->tanggal_mulai->toDateString(),
            'file_scan' => null,
            'diinput_oleh' => User::factory(),
        ];
    }
}
