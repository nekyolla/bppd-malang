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

    /**
     * Nama di halaman publik sertifikat (PF-40): dua huruf awal tiap kata, sisanya `*`.
     */
    public static function nama(string $nama): string
    {
        return collect(preg_split('/\s+/u', trim($nama), flags: PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $kata): string => mb_substr($kata, 0, 2).str_repeat('*', max(0, mb_strlen($kata) - 2)))
            ->implode(' ');
    }
}
