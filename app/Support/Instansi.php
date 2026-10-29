<?php

namespace App\Support;

/**
 * Nomenklatur resmi instansi (PF-21): tanpa singkatan. Di tempat sempit cukup "Balai Besar".
 * Nilai teknis (`TipeLokasi::Bbpd`, nama tabel/repo) tidak ikut berubah.
 */
class Instansi
{
    public const NAMA = 'Balai Besar Pengembangan Kapasitas Pemerintahan Desa';

    public const SINGKAT = 'Balai Besar';

    public const KEMENTERIAN = 'Kementerian Dalam Negeri';

    public const DIREKTORAT = 'Direktorat Jenderal Bina Pemerintahan Desa';

    public const APLIKASI = 'Sistem Informasi Pelatihan Aparatur Desa';
}
