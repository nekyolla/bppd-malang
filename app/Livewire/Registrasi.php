<?php

namespace App\Livewire;

use App\Enums\JenisKelamin;
use App\Filament\Concerns\MemetakanGalatService;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\KabKota;
use App\Models\Kecamatan;
use App\Models\Pelatihan;
use App\Models\Peserta;
use App\Models\Provinsi;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Services\RegistrasiService;
use App\Support\FormatTanggal;
use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Form registrasi publik tanpa login (PRD §8.2, DESIGN_SYSTEM §5.2).
 */
#[Layout('components.layouts.publik')]
#[Title('Registrasi Peserta Pelatihan')]
class Registrasi extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use MemetakanGalatService;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public bool $terkirim = false;

    /**
     * Honeypot: tidak terlihat oleh manusia, biasanya diisi bot (FR-REG-11).
     */
    public string $website = '';

    public ?string $galatUmum = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $berkas = fn (string $nama, string $label, array $tipe, string $bantuan): FileUpload => FileUpload::make($nama)
            ->label($label)
            ->helperText($bantuan)
            ->required()
            ->acceptedFileTypes($tipe)
            // Teks bawaan FilePond ("Seret & Jatuhkan…") kurang jelas di ponsel.
            ->placeholder('<span class="filepond--label-action">Pilih berkas</span> atau seret ke sini')
            ->maxSize(RegistrasiService::MAKS_UKURAN_BERKAS_KB)
            ->storeFiles(false);

        // Baris ringkasan sebelum kirim: label sebaris dengan isiannya.
        $ringkasan = fn (string $nama, string $label, Closure $isi): TextEntry => TextEntry::make("ringkasan_{$nama}")
            ->label($label)
            ->inlineLabel()
            ->state(fn (Get $get): string => filled($nilai = $isi($get)) ? $nilai : '–');

        // Galat tampil saat isian ditinggalkan, tidak menunggu tombol "Selanjutnya" (PF-12).
        $langsung = fn (Field $isian): Field => $isian
            ->live(onBlur: true)
            ->afterStateUpdated(fn (HasSchemas $livewire, Field $component) => $livewire->validateOnly($component->getStatePath()));

        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('Pelatihan')
                        ->schema([
                            Radio::make('pelatihan_id')
                                ->label('Pelatihan yang Anda ikuti')
                                ->helperText('Sesuai surat pemanggilan.')
                                ->options(fn (): array => $this->pelatihanDibuka()->mapWithKeys(fn (Pelatihan $p): array => [$p->id => $p->nama_tampilan])->all())
                                ->descriptions(fn (): array => $this->pelatihanDibuka()->mapWithKeys(fn (Pelatihan $p): array => [
                                    $p->id => FormatTanggal::rentang($p->tanggal_mulai, $p->tanggal_selesai).' · '.($p->keterangan_lokasi ?? $p->tipe_lokasi->getLabel()),
                                ])->all())
                                ->required(),
                        ]),
                    Step::make('Identitas')
                        ->schema([
                            $langsung(TextInput::make('nik')
                                ->label('NIK')
                                ->helperText('16 angka, sesuai KTP.')
                                ->required()
                                ->length(16)
                                ->regex('/^\d{16}$/')
                                ->validationMessages(['size' => 'NIK harus 16 angka.', 'regex' => 'NIK harus 16 angka.'])
                                ->mask(str_repeat('9', 16))
                                ->inputMode('numeric')
                                ->autocomplete('off')),
                            $langsung(TextInput::make('nama_lengkap')
                                ->label('Nama lengkap')
                                ->helperText('Sesuai KTP, tanpa gelar.')
                                ->required()
                                ->maxLength(255)),
                            ToggleButtons::make('jenis_kelamin')
                                ->label('Jenis kelamin')
                                ->options(JenisKelamin::class)
                                ->required()
                                ->inline(),
                            $langsung(TextInput::make('tempat_lahir')
                                ->label('Tempat lahir')
                                ->required()
                                ->maxLength(255)),
                            $langsung(DatePicker::make('tanggal_lahir')
                                ->label('Tanggal lahir')
                                ->required()
                                ->maxDate(now()->subYears(17))
                                ->validationMessages(['before_or_equal' => 'Peserta minimal berusia 17 tahun.'])),
                            Select::make('agama')
                                ->label('Agama')
                                ->options(array_combine(Peserta::AGAMA, Peserta::AGAMA))
                                ->required(),
                            $langsung(Textarea::make('alamat_domisili')
                                ->label('Alamat domisili')
                                ->required()
                                ->rows(2)
                                ->maxLength(1000)),
                            $langsung(TextInput::make('no_hp')
                                ->label('Nomor HP (WhatsApp)')
                                ->placeholder('08xxxxxxxxxx')
                                ->required()
                                ->tel()
                                ->regex('/^08\d{8,12}$/')
                                ->validationMessages(['regex' => 'Nomor HP diawali 08 dan terdiri dari 10–14 angka.'])
                                ->mask(str_repeat('9', 14))
                                ->inputMode('numeric')),
                            $langsung(TextInput::make('email')
                                ->label('Email')
                                ->helperText('Boleh dikosongkan.')
                                ->email()
                                ->maxLength(255)),
                            Select::make('jenjang_pendidikan')
                                ->label('Pendidikan terakhir')
                                ->options(array_combine(Peserta::JENJANG_PENDIDIKAN, Peserta::JENJANG_PENDIDIKAN))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state): void {
                                    if (! Peserta::jenjangKuliah($state)) {
                                        $set('jurusan_pendidikan', null);
                                    }
                                }),
                            // Hanya untuk jenjang kuliah (PF-28).
                            $langsung(TextInput::make('jurusan_pendidikan')
                                ->label('Jurusan')
                                ->placeholder('Mis. Ilmu Pemerintahan')
                                ->required()
                                ->maxLength(255)
                                ->validationMessages(['required' => 'Jurusan wajib diisi untuk pendidikan D1–S3.'])
                                ->visible(fn (Get $get): bool => Peserta::jenjangKuliah($get('jenjang_pendidikan')))),
                        ]),
                    Step::make('Jabatan & Desa')
                        ->schema([
                            Select::make('jabatan_id')
                                ->label('Jabatan')
                                ->options(fn (): array => Jabatan::aktif()->orderBy('urutan')->orderBy('nama_jabatan')->pluck('nama_jabatan', 'id')->all())
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('periode_jabatan', null)),
                            // Hanya untuk jabatan berperiode, mis. kepala desa (PF-25).
                            Select::make('periode_jabatan')
                                ->label('Periode jabatan')
                                ->options(Peserta::PERIODE_JABATAN)
                                ->required()
                                ->validationMessages(['required' => 'Pilih periode jabatan.'])
                                ->visible(fn (Get $get): bool => Jabatan::berperiode($get('jabatan_id'))),
                            DatePicker::make('waktu_pelantikan')
                                ->label('Tanggal pelantikan')
                                ->required()
                                ->maxDate(now())
                                ->live()
                                ->helperText(fn (?string $state): string => $state
                                    ? 'Tahun menjabat saat ini: tahun ke-'.Peserta::hitungTahunMenjabat(Carbon::parse($state), now()->year).'.'
                                    : 'Tanggal pelantikan pada jabatan saat ini.'),
                            Select::make('provinsi_id')
                                ->label('Provinsi')
                                ->options(fn (): array => Provinsi::query()->orderBy('nama')->pluck('nama', 'id')->all())
                                ->required()
                                ->searchable()
                                ->live()
                                ->dehydrated(false)
                                ->afterStateUpdated(fn (Set $set) => self::kosongkan($set, ['kab_kota_id', 'kecamatan_id', 'desa_id'])),
                            Select::make('kab_kota_id')
                                ->label('Kabupaten/kota')
                                ->options(fn (Get $get): array => KabKota::query()->where('provinsi_id', $get('provinsi_id'))->orderBy('nama')->pluck('nama', 'id')->all())
                                ->disabled(fn (Get $get): bool => blank($get('provinsi_id')))
                                ->required()
                                ->searchable()
                                ->live()
                                ->dehydrated(false)
                                ->afterStateUpdated(fn (Set $set) => self::kosongkan($set, ['kecamatan_id', 'desa_id'])),
                            Select::make('kecamatan_id')
                                ->label('Kecamatan')
                                ->options(fn (Get $get): array => Kecamatan::query()->where('kab_kota_id', $get('kab_kota_id'))->orderBy('nama')->pluck('nama', 'id')->all())
                                ->disabled(fn (Get $get): bool => blank($get('kab_kota_id')))
                                ->required()
                                ->searchable()
                                ->live()
                                ->dehydrated(false)
                                ->afterStateUpdated(fn (Set $set) => self::kosongkan($set, ['desa_id'])),
                            Select::make('desa_id')
                                ->label('Desa')
                                ->options(fn (Get $get): array => Desa::query()->where('kecamatan_id', $get('kecamatan_id'))->orderBy('nama')->pluck('nama', 'id')->all())
                                ->disabled(fn (Get $get): bool => blank($get('kecamatan_id')))
                                ->required()
                                ->searchable(),
                            $langsung(Textarea::make('alamat_kantor_desa')
                                ->label('Alamat kantor desa')
                                ->required()
                                ->rows(2)
                                ->maxLength(1000)),
                        ]),
                    Step::make('Pajak & Sumber Dana')
                        ->schema([
                            $langsung(TextInput::make('npwp')
                                ->label('NPWP')
                                ->helperText('15–16 angka tanpa titik. Kosongkan jika tidak punya.')
                                ->regex('/^(\d{15}|\d{16})$/')
                                ->validationMessages(['regex' => 'NPWP terdiri dari 15 atau 16 angka.'])
                                ->mask(str_repeat('9', 16))
                                ->inputMode('numeric')),
                            Select::make('status_ptkp_id')
                                ->label('Status PTKP')
                                ->helperText('TK = tidak kawin, K = kawin; angka = jumlah tanggungan.')
                                ->options(fn (): array => StatusPtkp::aktif()->orderBy('id')->get()->mapWithKeys(fn (StatusPtkp $s): array => [$s->id => "{$s->kode} — {$s->nama}"])->all())
                                ->required(),
                            Radio::make('sumber_dana_id')
                                ->label('Sumber dana keikutsertaan')
                                ->options(fn (): array => SumberDana::aktif()->orderBy('id')->pluck('nama', 'id')->all())
                                ->required()
                                ->live(),
                            TextInput::make('sumber_dana_keterangan')
                                ->label('Keterangan sumber dana')
                                ->maxLength(255)
                                ->visible(fn (Get $get): bool => $this->butuhKeterangan($get('sumber_dana_id')))
                                ->required(fn (Get $get): bool => $this->butuhKeterangan($get('sumber_dana_id'))),
                        ]),
                    Step::make('Berkas')
                        ->schema([
                            $berkas('ktp', 'Scan KTP', ['application/pdf', 'image/jpeg'], 'PDF atau JPG, maksimal 2 MB.'),
                            $berkas('foto', 'Pas foto 3x4', ['image/jpeg'], 'JPG latar polos, maksimal 2 MB.'),
                            $berkas('surat_tugas', 'Surat tugas dari desa', ['application/pdf'], 'PDF, maksimal 2 MB.'),
                        ]),
                    Step::make('Periksa & Kirim')
                        ->schema([
                            Section::make('Ringkasan')
                                ->compact()
                                ->extraAttributes(['class' => 'fi-ringkasan'])
                                ->schema([
                                    $ringkasan('pelatihan', 'Pelatihan', fn (Get $get): ?string => $this->pelatihanDibuka()->firstWhere('id', $get('pelatihan_id'))?->nama_tampilan),
                                    $ringkasan('nama', 'Nama', fn (Get $get): ?string => $get('nama_lengkap')),
                                    $ringkasan('nik', 'NIK', fn (Get $get): ?string => $get('nik')),
                                    $ringkasan('desa', 'Desa', fn (Get $get): ?string => Desa::find($get('desa_id'))?->nama),
                                    $ringkasan('no_hp', 'Nomor HP', fn (Get $get): ?string => $get('no_hp')),
                                ]),
                            Checkbox::make('pernyataan')
                                ->label('Data yang saya isi benar dan sesuai KTP serta surat tugas.')
                                ->accepted()
                                ->dehydrated(false),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit" size="lg">Kirim pendaftaran</x-filament::button>'))),
            ]);
    }

    /**
     * Pesan khusus isian ikut dipakai saat validasi per isian (`validateOnly`, PF-12);
     * Filament hanya menyertakannya saat seluruh form divalidasi.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return $this->form->getValidationMessages();
    }

    public function daftar(): void
    {
        $this->galatUmum = null;
        $kunci = $this->kunciBatasKiriman();

        if (RateLimiter::tooManyAttempts($kunci, config('bbpd.registrasi.maks_per_jam'))) {
            $this->galatUmum = 'Terlalu banyak pendaftaran dari perangkat ini. Coba lagi dalam 1 jam.';

            return;
        }

        $data = $this->form->getState();
        RateLimiter::hit($kunci, 3600);

        // Bot yang mengisi honeypot melihat halaman sukses, tetapi tidak ada yang disimpan.
        if (filled($this->website)) {
            $this->selesai();

            return;
        }

        $this->jalankanService(fn () => app(RegistrasiService::class)->daftar($data));

        $this->selesai();
    }

    private function selesai(): void
    {
        $this->terkirim = true;
        $this->data = [];
        $this->website = '';
    }

    private function kunciBatasKiriman(): string
    {
        return 'registrasi:'.request()->ip();
    }

    public function render(): View
    {
        return view('livewire.registrasi', [
            'adaPelatihan' => $this->pelatihanDibuka()->isNotEmpty(),
        ]);
    }

    /**
     * @return Collection<int, Pelatihan>
     */
    private function pelatihanDibuka(): Collection
    {
        return once(fn () => Pelatihan::menerimaPendaftaran()
            ->with('judulPelatihan')
            ->orderBy('tanggal_mulai')
            ->get());
    }

    /**
     * Pilihan wilayah di bawahnya dikosongkan saat wilayah induk berubah.
     *
     * @param  list<string>  $kolom
     */
    private static function kosongkan(Set $set, array $kolom): void
    {
        foreach ($kolom as $nama) {
            $set($nama, null);
        }
    }

    private function butuhKeterangan(mixed $sumberDanaId): bool
    {
        return filled($sumberDanaId)
            && (bool) SumberDana::query()->whereKey($sumberDanaId)->value('butuh_keterangan');
    }
}
