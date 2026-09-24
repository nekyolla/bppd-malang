<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\User;
use App\Services\AkunService;
use Illuminate\Database\Seeder;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('bbpd.superadmin');

        if (blank($config['username']) || blank($config['password'])) {
            $this->command?->warn('SUPERADMIN_USERNAME / SUPERADMIN_PASSWORD belum diisi di .env. Akun superadmin tidak dibuat.');

            return;
        }

        if (! preg_match(AkunService::POLA_USERNAME, $config['username'])) {
            $this->command?->warn('SUPERADMIN_USERNAME harus 3–50 karakter huruf kecil, angka, titik, garis bawah, atau tanda hubung, dan memuat minimal satu huruf. Akun superadmin tidak dibuat.');

            return;
        }

        $user = User::firstOrCreate(
            ['username' => $config['username']],
            [
                'email' => $config['email'] ?: null,
                'password' => $config['password'],
                'is_aktif' => true,
            ],
        );

        $user->assignRole(Peran::Superadmin);
    }
}
