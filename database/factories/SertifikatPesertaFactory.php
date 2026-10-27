<?php

namespace Database\Factories;

use App\Models\PelatihanPeserta;
use App\Models\SertifikatPeserta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SertifikatPeserta>
 */
class SertifikatPesertaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pelatihan_peserta_id' => PelatihanPeserta::factory()->selesai(),
            'file_sertifikat' => function (array $attributes) {
                $pendaftaran = PelatihanPeserta::findOrFail($attributes['pelatihan_peserta_id']);

                return "sertifikat/{$pendaftaran->pelatihan_id}/{$pendaftaran->id}.pdf";
            },
            'diupload_oleh' => User::factory(),
        ];
    }
}
