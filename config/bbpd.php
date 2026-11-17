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

    /*
    | Batas pencarian halaman publik unduh sertifikat per alamat IP per jam (PF-40). Pencarian
    | gagal dibatasi ketat (pola penebakan NIK); total pencarian longgar karena satu kelas
    | bisa mengunduh bersamaan dari jaringan yang sama.
    */
    'sertifikat_publik' => [
        'maks_gagal_per_jam' => (int) env('SERTIFIKAT_PUBLIK_MAKS_GAGAL_PER_JAM', 30),
        'maks_per_jam' => (int) env('SERTIFIKAT_PUBLIK_MAKS_PER_JAM', 120),
        'maks_unduh_per_jam' => (int) env('SERTIFIKAT_PUBLIK_MAKS_UNDUH_PER_JAM', 120),
    ],

    /*
    | Folder logo instansi di dalam public/ (App\Support\Logo). Test memakai folder
    | lain agar tidak pernah menyentuh logo asli.
    */
    'logo_folder' => 'images',

    /*
    | Alamat reverse proxy di depan aplikasi (nginx, load balancer), dipisah koma, atau "*".
    | Tanpa ini semua pengunjung tampak ber-IP proxy, sehingga batas registrasi & login per IP
    | berlaku untuk semua orang sekaligus. Kosongkan bila aplikasi tidak di belakang proxy.
    */
    'proxy_tepercaya' => env('TRUSTED_PROXIES'),

];
