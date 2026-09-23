<?php

namespace App\Support;

class Masking
{
    /**
     * NIK di daftar: 4 digit awal + ******** + 4 digit akhir (DESIGN_SYSTEM.md §4).
     */
    public static function nik(?string $nik): string
    {
        if (blank($nik) || strlen($nik) !== 16) {
            return (string) $nik;
        }

        return substr($nik, 0, 4).str_repeat('*', 8).substr($nik, -4);
    }
}
