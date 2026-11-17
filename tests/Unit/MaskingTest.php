<?php

use App\Support\Masking;

it('menyamarkan nama: dua huruf awal tiap kata, sisanya bintang (PF-40)', function (string $nama, string $samaran) {
    expect(Masking::nama($nama))->toBe($samaran);
})->with([
    ['Budi Santoso', 'Bu** Sa*****'],
    ['Al', 'Al'],
    ['Siti  Nur Aminah', 'Si** Nu* Am****'],
    ['Ñoño', 'Ño**'],
]);
