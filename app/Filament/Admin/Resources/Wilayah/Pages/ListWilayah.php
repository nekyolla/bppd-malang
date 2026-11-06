<?php

namespace App\Filament\Admin\Resources\Wilayah\Pages;

use App\Filament\Admin\Resources\Wilayah\WilayahResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListWilayah extends ListRecords
{
    protected static string $resource = WilayahResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Desa di 18 provinsi wilayah kerja, sesuai kode Kemendagri. Hanya dapat dilihat; perubahan data dilakukan lewat pembaruan aplikasi.';
    }
}
