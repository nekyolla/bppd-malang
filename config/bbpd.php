<?php

return [

    /*
    | Akun superadmin awal, dibuat oleh SuperadminSeeder.
    */
    'superadmin' => [
        'username' => env('SUPERADMIN_USERNAME'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    /*
    | Batas kiriman form registrasi publik per alamat IP per jam (FR-REG-11).
    | Naikkan jika banyak peserta mendaftar dari jaringan yang sama (mis. kantor desa).
    */
    'registrasi' => [
        'maks_per_jam' => (int) env('REGISTRASI_MAKS_PER_JAM', 5),
    ],

];
