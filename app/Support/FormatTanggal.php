<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Format tanggal bahasa Indonesia (DESIGN_SYSTEM.md §4).
 */
class FormatTanggal
{
    /**
     * 5 Oktober 2026
     */
    public static function tanggal(CarbonInterface $tanggal): string
    {
        return $tanggal->locale('id')->translatedFormat('j F Y');
    }

    /**
     * Ringkas jika bulan/tahun sama: "5–8 Oktober 2026", "30 Oktober – 2 November 2026".
     */
    public static function rentang(CarbonInterface $mulai, CarbonInterface $selesai): string
    {
        if ($mulai->isSameDay($selesai)) {
            return self::tanggal($mulai);
        }

        if ($mulai->isSameMonth($selesai)) {
            return $mulai->format('j').'–'.self::tanggal($selesai);
        }

        if ($mulai->isSameYear($selesai)) {
            return $mulai->locale('id')->translatedFormat('j F').' – '.self::tanggal($selesai);
        }

        return self::tanggal($mulai).' – '.self::tanggal($selesai);
    }
}
