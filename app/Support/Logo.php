<?php

namespace App\Support;

/**
 * Logo instansi di `public/images/` (PF-20). Selama berkasnya belum ada, panel dan
 * form registrasi memakai teks "BBPD Malang".
 */
class Logo
{
    private const EKSTENSI = ['svg', 'png', 'webp', 'jpg'];

    /**
     * URL logo terang, atau null jika belum ada.
     */
    public static function url(): ?string
    {
        return self::cari('logo');
    }

    /**
     * URL logo untuk mode gelap; memakai logo terang jika varian gelap tidak ada.
     */
    public static function urlGelap(): ?string
    {
        return self::cari('logo-gelap') ?? self::url();
    }

    public static function favicon(): ?string
    {
        return self::cari('favicon');
    }

    private static function cari(string $nama): ?string
    {
        foreach (self::EKSTENSI as $ekstensi) {
            $path = config('bbpd.logo_folder')."/{$nama}.{$ekstensi}";

            if (is_file(public_path($path))) {
                return asset($path);
            }
        }

        return null;
    }
}
