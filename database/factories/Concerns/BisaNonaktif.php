<?php

namespace Database\Factories\Concerns;

/**
 * State bersama untuk factory data master yang punya kolom `is_aktif`.
 */
trait BisaNonaktif
{
    public function nonaktif(): static
    {
        return $this->state(['is_aktif' => false]);
    }
}
