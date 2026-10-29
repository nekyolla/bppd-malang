<?php

/*
| Hanya kunci yang diubah dari bawaan Livewire; sisanya digabung dari config package.
*/

return [

    /*
    | Unggahan sementara (sebelum form dikirim) terbuka untuk publik lewat /daftar.
    | Bawaan Livewire menerima berkas apa pun sampai 12 MB, 60 kali per menit per IP,
    | sehingga disk bisa dipenuhi. Di sistem ini semua unggahan PDF/JPG maks 2 MB (PF-08).
    */
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:2048', 'mimetypes:application/pdf,image/jpeg'],
        'directory' => null,
        'middleware' => 'throttle:20,1',
        'preview_mimes' => ['jpg', 'jpeg'],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
