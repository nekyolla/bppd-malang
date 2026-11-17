<?php

namespace App\Livewire;

use App\Services\SertifikatService;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman publik unduh sertifikat dengan NIK (PF-40). NIK tak dikenal dan NIK tanpa
 * sertifikat mendapat pesan yang sama. Batas per IP: pencarian gagal dibatasi ketat
 * (pola penebakan NIK), semua pencarian dibatasi longgar (satu kelas bisa berbagi IP).
 */
#[Layout('components.layouts.publik')]
#[Title('Unduh Sertifikat')]
class UnduhSertifikat extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array{nama: string, daftar: list<array{pelatihan: string, tanggal: string, nomor: ?string, url: string}>}|null
     */
    public ?array $hasil = null;

    public bool $tidakDitemukan = false;

    /**
     * Honeypot: tidak terlihat oleh manusia, biasanya diisi bot.
     */
    public string $website = '';

    public ?string $galatUmum = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('nik')
                    ->label('NIK')
                    ->helperText('16 angka, sesuai KTP.')
                    ->required()
                    ->regex('/^\d{16}$/')
                    ->validationMessages(['regex' => 'NIK harus 16 angka.'])
                    ->inputMode('numeric')
                    ->autocomplete('off'),
            ]);
    }

    public function cari(): void
    {
        $this->galatUmum = null;
        $this->hasil = null;
        $this->tidakDitemukan = false;

        // NIK yang ditempel dari dokumen sering berisi spasi atau tanda hubung.
        $this->data['nik'] = preg_replace('/\D/', '', (string) ($this->data['nik'] ?? ''));
        $nik = $this->form->getState()['nik'];

        $kunci = 'sertifikat-publik:'.request()->ip();
        $kunciGagal = 'sertifikat-publik-gagal:'.request()->ip();

        foreach ([$kunciGagal => config('bbpd.sertifikat_publik.maks_gagal_per_jam'), $kunci => config('bbpd.sertifikat_publik.maks_per_jam')] as $k => $maks) {
            if (RateLimiter::tooManyAttempts($k, $maks)) {
                $this->galatUmum = 'Terlalu banyak percobaan. Coba lagi dalam '.(int) ceil(RateLimiter::availableIn($k) / 60).' menit.';

                return;
            }
        }

        RateLimiter::hit($kunci, 3600);

        // Bot yang mengisi honeypot mendapat pesan yang sama tanpa pencarian.
        if (filled($this->website)) {
            $this->tidakDitemukan = true;

            return;
        }

        $this->hasil = app(SertifikatService::class)->untukPublik($nik);

        if ($this->hasil === null) {
            RateLimiter::hit($kunciGagal, 3600);
            $this->tidakDitemukan = true;
        }
    }

    public function render(): View
    {
        return view('livewire.unduh-sertifikat');
    }
}
