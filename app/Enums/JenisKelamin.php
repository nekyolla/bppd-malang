<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JenisKelamin: string implements HasLabel
{
    case LakiLaki = 'L';
    case Perempuan = 'P';

    public function getLabel(): string
    {
        return match ($this) {
            self::LakiLaki => 'Laki-laki',
            self::Perempuan => 'Perempuan',
        };
    }
}
