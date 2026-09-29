<?php

namespace Database\Factories;

use App\Enums\TipeKamar;
use App\Models\KamarAsrama;
use App\Models\Pelatihan;
use App\Models\PenggunaanKamar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PenggunaanKamar>
 */
class PenggunaanKamarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pelatihan_id' => Pelatihan::factory()->dibuka(),
            'kamar_asrama_id' => KamarAsrama::factory(),
            'tipe' => TipeKamar::LakiLaki,
            'disetujui_oleh' => null,
            'catatan' => null,
        ];
    }

    public function perempuan(): static
    {
        return $this->state(['tipe' => TipeKamar::Perempuan]);
    }

    public function pasutri(): static
    {
        return $this->state([
            'tipe' => TipeKamar::Pasutri,
            'disetujui_oleh' => User::factory(),
        ]);
    }
}
