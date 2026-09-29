<?php

namespace Database\Factories;

use App\Enums\StatusPendaftaran;
use App\Models\Pelatihan;
use App\Models\PelatihanPeserta;
use App\Models\Peserta;
use App\Models\SumberDana;
use App\Models\User;
use App\Support\IsianPeserta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PelatihanPeserta>
 */
class PelatihanPesertaFactory extends Factory
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
            'peserta_id' => Peserta::factory(),
            // Sama dengan isian form registrasi untuk peserta ini (tidak ada perbedaan).
            'data_isian' => fn (array $attributes) => IsianPeserta::dariPeserta(Peserta::findOrFail($attributes['peserta_id'])),
            'file_surat_tugas' => null,
            'sumber_dana_id' => SumberDana::factory(),
            'sumber_dana_keterangan' => null,
        ];
    }

    /**
     * Snapshot diisi dari data peserta, seperti PendaftaranService::verifikasi.
     */
    public function terverifikasi(): static
    {
        return $this->state([
            'status' => StatusPendaftaran::Terverifikasi,
            'jabatan_id_saat_pelatihan' => fn (array $attributes) => Peserta::findOrFail($attributes['peserta_id'])->jabatan_id,
            'desa_id_saat_pelatihan' => fn (array $attributes) => Peserta::findOrFail($attributes['peserta_id'])->desa_id,
            'status_ptkp_id_saat_pelatihan' => fn (array $attributes) => Peserta::findOrFail($attributes['peserta_id'])->status_ptkp_id,
            'waktu_pelantikan_saat_pelatihan' => fn (array $attributes) => Peserta::findOrFail($attributes['peserta_id'])->waktu_pelantikan,
            'diverifikasi_oleh' => User::factory(),
            'diverifikasi_pada' => now(),
        ]);
    }

    public function selesai(): static
    {
        return $this->terverifikasi()->state(['status' => StatusPendaftaran::Selesai]);
    }

    public function batal(): static
    {
        return $this->state([
            'status' => StatusPendaftaran::Batal,
            'alasan_batal' => 'Berhalangan hadir',
            'dibatalkan_oleh' => User::factory(),
            'dibatalkan_pada' => now(),
        ]);
    }
}
