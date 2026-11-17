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

    /** Baris alamat & kontak kop surat (PF-39). */
    public const ALAMAT = 'Jalan Raya Langsep Nomor 7, Malang Telepon/Faksimile (0341) 564782, 572071';

    public const KONTAK = 'www.kemendagri.go.id, email: bbpmd7mlg@gmail.com';

    /** Nama aplikasi (PF-32). */
    public const APLIKASI = 'Sistem Informasi Pengembangan Kapasitas Pemerintahan Desa';
}
