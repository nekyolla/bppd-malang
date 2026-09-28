<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tampilan mengikuti DESIGN_SYSTEM §2.5.
 */
enum StatusPresensi: string implements HasColor, HasLabel
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hadir => 'success',
            self::Izin => 'warning',
            self::Sakit => 'info',
            self::Alpa => 'danger',
        };
    }

    /**
     * Singkatan untuk tombol input dan kolom rekap per tanggal.
     */
    public function singkatan(): string
    {
        return match ($this) {
            self::Hadir => 'H',
            self::Izin => 'I',
            self::Sakit => 'S',
            self::Alpa => 'A',
        };
    }
}
