<?php

namespace Database\Factories;

use App\Enums\JenisKelamin;
use App\Enums\TipeKamar;
use App\Models\AsramaPeserta;
use App\Models\PelatihanPeserta;
use App\Models\PenggunaanKamar;
use App\Models\Peserta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AsramaPeserta>
 */
class AsramaPesertaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'penggunaan_kamar_id' => PenggunaanKamar::factory(),
            // Peserta terverifikasi di pelatihan yang sama, dengan gender sesuai tipe kamar.
            'pelatihan_peserta_id' => function (array $attributes) {
                $penggunaan = PenggunaanKamar::findOrFail($attributes['penggunaan_kamar_id']);
                $peserta = $penggunaan->tipe === TipeKamar::Pasutri
                    ? Peserta::factory()
                    : Peserta::factory()->state(['jenis_kelamin' => JenisKelamin::from($penggunaan->tipe->value)]);

                return PelatihanPeserta::factory()->terverifikasi()->for($peserta)->create([
                    'pelatihan_id' => $penggunaan->pelatihan_id,
                ])->id;
            },
            'ditempatkan_oleh' => User::factory(),
        ];
    }
}
