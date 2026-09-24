<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Services\AkunService;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Registrasi mandiri peserta dengan NIK (FR-AUTH-01). Hanya aktif di panel peserta.
 */
class Register extends BaseRegister
{
    /**
     * Batas registrasi per IP per menit. Bawaan Filament (2) terlalu ketat
     * untuk peserta yang mendaftar bersama dari satu jaringan kantor/balai.
     */
    public const MAKS_REGISTRASI_PER_IP = 20;

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null)
    {
        parent::rateLimit(self::MAKS_REGISTRASI_PER_IP, $decaySeconds, $method ?? 'register', $component);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNikFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getNikFormComponent(): Component
    {
        return TextInput::make('nik')
            ->label('NIK')
            ->helperText('16 digit sesuai KTP. NIK dipakai sebagai username untuk masuk.')
            ->required()
            ->regex('/^\d{16}$/')
            ->maxLength(16)
            ->inputMode('numeric')
            ->autocomplete('username')
            ->unique(User::class, 'nik')
            ->validationMessages([
                'regex' => 'NIK harus 16 digit angka.',
                'unique' => 'NIK ini sudah terdaftar. Silakan masuk, atau hubungi panitia jika Anda lupa kata sandi.',
            ])
            ->autofocus();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email (opsional)')
            ->email()
            ->nullable()
            ->maxLength(255)
            ->unique(User::class, 'email')
            ->validationMessages([
                'unique' => 'Email ini sudah dipakai akun lain.',
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        return app(AkunService::class)->daftarPeserta($data);
    }
}
