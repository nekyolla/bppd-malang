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

    /**
     * Lambang sebagai data URI untuk dokumen cetak (dompdf tidak mengambil berkas lewat URL).
     * Hanya PNG/JPG; null jika belum ada.
     */
    public static function lambangDataUri(): ?string
    {
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg'] as $ekstensi => $mime) {
            $path = public_path(config('bbpd.logo_folder')."/lambang.{$ekstensi}");

            if (is_file($path)) {
                return "data:{$mime};base64,".base64_encode((string) file_get_contents($path));
            }
        }

        return null;
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
