<?php

namespace App\Services;

class AkunService
{
    /**
     * Username akun internal: huruf kecil, angka, titik, garis bawah, atau
     * tanda hubung, dan wajib memuat minimal satu huruf.
     */
    public const POLA_USERNAME = '/^(?=.*[a-z])[a-z0-9._-]{3,50}$/';
}
