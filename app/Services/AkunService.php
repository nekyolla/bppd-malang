<?php

namespace App\Services;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

class AkunService
{
    /**
     * Username akun internal: huruf kecil, angka, titik, garis bawah, atau
     * tanda hubung, dan wajib memuat huruf agar tidak bentrok dengan NIK peserta.
     */
    public const POLA_USERNAME = '/^(?=.*[a-z])[a-z0-9._-]{3,50}$/';

    /**
     * Registrasi mandiri peserta (FR-AUTH-01).
     *
     * @param  array{nik: string, email?: ?string, password: string}  $data
     */
    public function daftarPeserta(#[SensitiveParameter] array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'nik' => $data['nik'],
                'email' => filled($data['email'] ?? null) ? $data['email'] : null,
                'password' => $data['password'],
                'is_aktif' => true,
            ]);

            $user->assignRole(Peran::Peserta);

            return $user;
        });
    }
}
