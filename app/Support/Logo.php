<?php

namespace App\Support;

/**
 * Lambang instansi di `public/images/` (PF-20). Nama instansi di sebelahnya ditulis sebagai
 * teks HTML (lihat `components/merek`) agar terbaca di mode terang & gelap. Tanpa berkas,
 * merek cukup berupa teks.
 */
class Logo
{
    private const EKSTENSI = ['svg', 'png', 'webp', 'jpg'];

    /**
     * URL lambang, atau null jika belum ada.
     */
    public static function lambang(): ?string
    {
        return self::cari('lambang');
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
