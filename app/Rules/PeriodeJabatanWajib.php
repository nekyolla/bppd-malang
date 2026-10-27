<?php

namespace App\Rules;

use App\Models\Jabatan;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Periode jabatan wajib diisi bila jabatan terpilih bertanda memiliki periode (PF-25).
 * Implicit agar tetap dijalankan saat nilainya kosong.
 */
class PeriodeJabatanWajib implements DataAwareRule, ValidationRule
{
    public bool $implicit = true;

    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) && Jabatan::berperiode($this->data['jabatan_id'] ?? null)) {
            $fail('Pilih periode jabatan.');
        }
    }
}
