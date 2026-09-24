<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Login memakai username + kata sandi (FR-AUTH-02), dipakai panel admin dan peserta.
 * Isian username dicocokkan ke kolom `username` (akun internal) atau `nik` (peserta).
 */
class Login extends BaseLogin
{
    /**
     * Batas percobaan per username + IP per menit (FR-AUTH-03).
     */
    public const MAKS_PERCOBAAN_PER_USERNAME = 5;

    /**
     * Batas percobaan per IP per menit. Dibuat longgar karena peserta di
     * lokasi pelatihan memakai jaringan yang sama (satu IP publik).
     */
    public const MAKS_PERCOBAAN_PER_IP = 60;

    public function authenticate(): ?LoginResponse
    {
        $key = $this->getUsernameRateLimitKey();

        if (RateLimiter::tooManyAttempts($key, self::MAKS_PERCOBAAN_PER_USERNAME)) {
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

    protected function getUsernameRateLimitKey(): string
    {
        return 'login-username:'.sha1(static::normalisasiUsername($this->data['username'] ?? '').'|'.request()->ip());
    }

    protected static function normalisasiUsername(?string $username): string
    {
        return Str::lower(trim((string) $username));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getUsernameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->placeholder(fn (): ?string => Filament::getCurrentPanel()?->getId() === 'peserta'
                ? 'Masukkan NIK 16 digit'
                : null)
            ->required()
            ->maxLength(50)
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
     * @return array<string|int, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $username = static::normalisasiUsername($data['username']);

        return [
            fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('username', $username)
                ->orWhere('nik', $username)),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => 'Username atau kata sandi salah.',
        ]);
    }
}
