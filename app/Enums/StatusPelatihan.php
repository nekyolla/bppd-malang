<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status penyelenggaraan pelatihan (ARCHITECTURE §7.1).
 * Tampilan mengikuti DESIGN_SYSTEM §2.3.
 */
enum StatusPelatihan: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Dibuka = 'dibuka';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Dibuka => 'Pendaftaran dibuka',
            self::Berjalan => 'Sedang berjalan',
            self::Selesai => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::Selesai => 'gray',
            self::Dibuka => 'success',
            self::Berjalan => 'info',
        };
    }

    /**
     * Form registrasi publik menerima pendaftaran saat pelatihan dibuka
     * atau sedang berjalan (PF-02).
     */
    public function menerimaPendaftaran(): bool
    {
        return in_array($this, self::yangMenerimaPendaftaran(), true);
    }

    /**
     * @return list<self>
     */
    public static function yangMenerimaPendaftaran(): array
    {
        return [self::Dibuka, self::Berjalan];
    }

    /**
     * Status berikutnya pada alur draft → dibuka → berjalan → selesai.
     */
    public function berikutnya(): ?self
    {
        return match ($this) {
            self::Draft => self::Dibuka,
            self::Dibuka => self::Berjalan,
            self::Berjalan => self::Selesai,
            self::Selesai => null,
        };
    }
}
