<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Status pendaftaran peserta di satu pelatihan (ARCHITECTURE §7.1).
 * Tampilan mengikuti DESIGN_SYSTEM §2.2.
 */
enum StatusPendaftaran: string implements HasColor, HasIcon, HasLabel
{
    case Terdaftar = 'terdaftar';
    case Terverifikasi = 'terverifikasi';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Terdaftar => 'Menunggu verifikasi',
            self::Terverifikasi => 'Terverifikasi',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Terdaftar => 'warning',
            self::Terverifikasi => 'info',
            self::Selesai => 'success',
            self::Batal => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Terdaftar => Heroicon::OutlinedClock,
            self::Terverifikasi => Heroicon::OutlinedCheckBadge,
            self::Selesai => Heroicon::OutlinedAcademicCap,
            self::Batal => Heroicon::OutlinedXCircle,
        };
    }

    /**
     * Perpindahan status yang sah: terdaftar → terverifikasi → selesai,
     * dan batal hanya dari terdaftar atau terverifikasi.
     */
    public function bisaMenjadi(self $tujuan): bool
    {
        return match ($this) {
            self::Terdaftar => in_array($tujuan, [self::Terverifikasi, self::Batal], true),
            self::Terverifikasi => in_array($tujuan, [self::Selesai, self::Batal], true),
            self::Selesai, self::Batal => false,
        };
    }

    public function isAkhir(): bool
    {
        return in_array($this, [self::Selesai, self::Batal], true);
    }
}
