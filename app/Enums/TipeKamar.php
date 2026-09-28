<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Tipe pemakaian kamar dalam satu pelatihan. Tipe L/P tidak diinput admin,
 * tetapi mengikuti penghuni pertama; hanya PASUTRI yang dipilih admin.
 * Tampilan mengikuti DESIGN_SYSTEM §2.4.
 */
enum TipeKamar: string implements HasColor, HasIcon, HasLabel
{
    case LakiLaki = 'L';
    case Perempuan = 'P';
    case Pasutri = 'PASUTRI';

    public static function dariJenisKelamin(JenisKelamin $jenisKelamin): self
    {
        return match ($jenisKelamin) {
            JenisKelamin::LakiLaki => self::LakiLaki,
            JenisKelamin::Perempuan => self::Perempuan,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LakiLaki => 'Laki-laki',
            self::Perempuan => 'Perempuan',
            self::Pasutri => 'Pasutri',
        };
    }

    /**
     * @return array<int | string, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::LakiLaki => Color::Blue,
            self::Perempuan => Color::Pink,
            self::Pasutri => Color::Violet,
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::LakiLaki, self::Perempuan => Heroicon::OutlinedUser,
            self::Pasutri => Heroicon::OutlinedUsers,
        };
    }
}
