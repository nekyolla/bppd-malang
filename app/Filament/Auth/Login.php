<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Login memakai NIK + kata sandi (FR-AUTH-02), dipakai panel admin dan peserta.
 */
class Login extends BaseLogin
{
    /**
     * Batas percobaan per NIK + IP per menit (FR-AUTH-03).
     */
    public const MAKS_PERCOBAAN_PER_NIK = 5;

    /**
     * Batas percobaan per IP per menit. Dibuat longgar karena peserta di
     * lokasi pelatihan memakai jaringan yang sama (satu IP publik).
     */
    public const MAKS_PERCOBAAN_PER_IP = 60;

    public function authenticate(): ?LoginResponse
    {
        $key = $this->getNikRateLimitKey();

        if (RateLimiter::tooManyAttempts($key, self::MAKS_PERCOBAAN_PER_NIK)) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                RateLimiter::availableIn($key),
            ))?->send();

            return null;
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            RateLimiter::hit($key, 60);

            throw $exception;
        }

        if ($response !== null) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null)
    {
        parent::rateLimit(self::MAKS_PERCOBAAN_PER_IP, $decaySeconds, $method ?? 'authenticate', $component);
    }

    protected function getNikRateLimitKey(): string
    {
        return 'login-nik:'.sha1(($this->data['nik'] ?? '').'|'.request()->ip());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNikFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getNikFormComponent(): Component
    {
        return TextInput::make('nik')
            ->label('NIK')
            ->helperText('16 digit sesuai KTP.')
            ->required()
            ->regex('/^\d{16}$/')
            ->validationMessages([
                'regex' => 'NIK harus 16 digit angka.',
            ])
            ->maxLength(16)
            ->inputMode('numeric')
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->hint(null)
            ->helperText('Lupa kata sandi? Hubungi panitia BBPD untuk mengatur ulang.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'nik' => $data['nik'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.nik' => 'NIK atau kata sandi salah.',
        ]);
    }
}
