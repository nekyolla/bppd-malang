<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * Palet panel (DESIGN_SYSTEM §2.1). Biru diturunkan dari biru tua lambang Kemendagri
 * (±#003366, di antara 800–900); abu kebiruan agar mode gelap terasa biru tua, bukan hitam.
 */
class Warna
{
    /**
     * Warna Filament untuk panel admin dan komponen di luar panel (form registrasi).
     *
     * @return array<string, array<int, string>>
     */
    public static function semua(): array
    {
        return [
            'primary' => self::BIRU,
            'gray' => self::ABU,
            'success' => Color::Emerald,
            'warning' => Color::Amber,
            'danger' => Color::Rose,
            'info' => Color::Sky,
        ];
    }

    public const BIRU = [
        50 => '#eff5fc',
        100 => '#dce8f7',
        200 => '#bed3ef',
        300 => '#91b4e2',
        400 => '#5e8dd0',
        500 => '#3a6cbc',
        600 => '#2554a0',
        700 => '#1d4483',
        800 => '#16386c',
        900 => '#0c2d5a',
        950 => '#071c3a',
    ];

    public const ABU = [
        50 => '#f7f9fc',
        100 => '#eef2f7',
        200 => '#e1e7ef',
        300 => '#c9d2de',
        400 => '#94a1b4',
        500 => '#64748b',
        600 => '#475569',
        700 => '#334155',
        800 => '#1c2940',
        900 => '#121d31',
        950 => '#0a1324',
    ];
}
