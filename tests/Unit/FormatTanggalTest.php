<?php

use App\Support\FormatTanggal;
use Illuminate\Support\Carbon;

it('memformat tanggal dan rentang tanggal dalam bahasa Indonesia', function (string $mulai, string $selesai, string $hasil) {
    expect(FormatTanggal::rentang(Carbon::parse($mulai), Carbon::parse($selesai)))->toBe($hasil);
})->with([
    'satu hari' => ['2026-10-05', '2026-10-05', '5 Oktober 2026'],
    'bulan sama' => ['2026-10-05', '2026-10-08', '5–8 Oktober 2026'],
    'lintas bulan' => ['2026-10-30', '2026-11-02', '30 Oktober – 2 November 2026'],
    'lintas tahun' => ['2026-12-30', '2027-01-02', '30 Desember 2026 – 2 Januari 2027'],
]);
