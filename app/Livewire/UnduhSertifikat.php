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
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman publik unduh sertifikat dengan NIK (PF-40). NIK tak dikenal dan NIK tanpa
 * sertifikat mendapat pesan yang sama. Batas per IP: pencarian gagal dibatasi ketat
 * (pola penebakan NIK), semua pencarian dibatasi longgar (satu kelas bisa berbagi IP).
 *
 * @property-read Schema $form Dibuat InteractsWithSchemas dari method form().
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
    #[Locked]
    public ?array $hasil = null;

    #[Locked]
    public bool $tidakDitemukan = false;

    /**
     * Honeypot: tidak terlihat oleh manusia, biasanya diisi bot.
     */
    public string $website = '';

    #[Locked]
    public ?string $galatUmum = null;

    public function mount(): void
    {
        $this->form->fill();

        // Dari tautan unduh yang kedaluwarsa (BerkasController::sertifikatPublik).
        $this->galatUmum = session('galatUmum');
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
                    // Sama seperti form registrasi: hanya angka, maksimal 16; spasi saat menempel dibuang.
                    ->mask(str_repeat('9', 16))
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

        // Potong jatah dulu (atomik), baru cek: permintaan serentak tidak dapat melewati batas.
        // Jatah gagal dikembalikan bila sertifikat ditemukan.
        $lewat = [
            $kunciGagal => RateLimiter::increment($kunciGagal, 3600) > config('bbpd.sertifikat_publik.maks_gagal_per_jam'),
            $kunci => RateLimiter::increment($kunci, 3600) > config('bbpd.sertifikat_publik.maks_per_jam'),
        ];

        foreach (array_keys(array_filter($lewat)) as $k) {
            $this->galatUmum = 'Terlalu banyak percobaan. Coba lagi dalam '.(int) ceil(RateLimiter::availableIn($k) / 60).' menit.';

            return;
        }

        // Bot yang mengisi honeypot mendapat pesan yang sama tanpa pencarian.
        if (filled($this->website)) {
            $this->tidakDitemukan = true;

            return;
        }

        $this->hasil = app(SertifikatService::class)->untukPublik($nik);

        if ($this->hasil === null) {
            $this->tidakDitemukan = true;

            return;
        }

        RateLimiter::decrement($kunciGagal, 3600);
    }

    /**
     * Menutup hasil dan mengosongkan NIK, agar orang berikutnya di perangkat bersama
     * tidak melihat sertifikat orang lain.
     */
    public function tutup(): void
    {
        $this->reset('hasil', 'tidakDitemukan', 'galatUmum');
        $this->form->fill();
    }

    public function render(): View
    {
        return view('livewire.unduh-sertifikat');
    }
}
