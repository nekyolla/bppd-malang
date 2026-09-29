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
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
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
            ->maxSize(RegistrasiService::MAKS_UKURAN_BERKAS_KB)
            ->storeFiles(false);

        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('Pelatihan')
                        ->schema([
                            Radio::make('pelatihan_id')
                                ->label('Pelatihan yang Anda ikuti')
                                ->helperText('Sesuai surat pemanggilan dari BBPD Malang.')
                                ->options(fn (): array => $this->pelatihanDibuka()->mapWithKeys(fn (Pelatihan $p): array => [$p->id => $p->nama_tampilan])->all())
                                ->descriptions(fn (): array => $this->pelatihanDibuka()->mapWithKeys(fn (Pelatihan $p): array => [
                                    $p->id => FormatTanggal::rentang($p->tanggal_mulai, $p->tanggal_selesai).' · '.($p->keterangan_lokasi ?? $p->tipe_lokasi->getLabel()),
                                ])->all())
                                ->required(),
                        ]),
                    Step::make('Identitas')
                        ->schema([
                            TextInput::make('nik')
                                ->label('NIK')
                                ->helperText('16 angka, sesuai KTP.')
                                ->required()
                                ->length(16)
                                ->regex('/^\d{16}$/')
                                ->inputMode('numeric')
                                ->autocomplete('off'),
                            TextInput::make('nama_lengkap')
                                ->label('Nama lengkap')
                                ->helperText('Sesuai KTP, tanpa gelar.')
                                ->required()
                                ->maxLength(255),
                            ToggleButtons::make('jenis_kelamin')
                                ->label('Jenis kelamin')
                                ->options(JenisKelamin::class)
                                ->required()
                                ->inline(),
                            TextInput::make('tempat_lahir')
                                ->label('Tempat lahir')
                                ->required()
                                ->maxLength(255),
                            DatePicker::make('tanggal_lahir')
                                ->label('Tanggal lahir')
                                ->required()
                                ->maxDate(now()->subYears(17)),
                            Select::make('agama')
                                ->label('Agama')
                                ->options(array_combine(Peserta::AGAMA, Peserta::AGAMA))
                                ->required(),
                            Textarea::make('alamat_domisili')
                                ->label('Alamat domisili')
                                ->required()
                                ->rows(2)
                                ->maxLength(1000),
                            TextInput::make('no_hp')
                                ->label('Nomor HP (WhatsApp)')
                                ->placeholder('08xxxxxxxxxx')
                                ->required()
                                ->tel()
                                ->maxLength(20),
                            TextInput::make('email')
                                ->label('Email')
                                ->helperText('Boleh dikosongkan.')
                                ->email()
                                ->maxLength(255),
                            Select::make('jenjang_pendidikan')
                                ->label('Pendidikan terakhir')
                                ->options(array_combine(Peserta::JENJANG_PENDIDIKAN, Peserta::JENJANG_PENDIDIKAN))
                                ->required(),
                            TextInput::make('jurusan_pendidikan')
                                ->label('Jurusan')
                                ->helperText('Boleh dikosongkan.')
                                ->maxLength(255),
                        ]),
                    Step::make('Jabatan & Desa')
                        ->schema([
                            Select::make('jabatan_id')
                                ->label('Jabatan')
                                ->options(fn (): array => Jabatan::aktif()->orderBy('urutan')->orderBy('nama_jabatan')->pluck('nama_jabatan', 'id')->all())
                                ->required()
                                ->searchable(),
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
                            Textarea::make('alamat_kantor_desa')
                                ->label('Alamat kantor desa')
                                ->required()
                                ->rows(2)
                                ->maxLength(1000),
                        ]),
                    Step::make('Pajak & Sumber Dana')
                        ->schema([
                            TextInput::make('npwp')
                                ->label('NPWP')
                                ->helperText('15 atau 16 angka. Boleh dikosongkan jika tidak punya.')
                                ->inputMode('numeric')
                                ->maxLength(25),
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
                        ->description('Setiap berkas maksimal 2 MB.')
                        ->schema([
                            $berkas('ktp', 'Scan KTP', ['application/pdf'], 'Format PDF.'),
                            $berkas('foto', 'Pas foto 4x3', ['image/jpeg'], 'Format JPG, latar polos.'),
                            $berkas('surat_tugas', 'Surat tugas dari desa', ['application/pdf'], 'Format PDF.'),
                        ]),
                    Step::make('Periksa & Kirim')
                        ->schema([
                            Section::make('Ringkasan')
                                ->schema([
                                    Text::make(fn (Get $get): string => 'Pelatihan: '.($this->pelatihanDibuka()->firstWhere('id', $get('pelatihan_id'))?->nama_tampilan ?? '–')),
                                    Text::make(fn (Get $get): string => 'Nama: '.($get('nama_lengkap') ?: '–')),
                                    Text::make(fn (Get $get): string => 'NIK: '.($get('nik') ?: '–')),
                                    Text::make(fn (Get $get): string => 'Desa: '.(Desa::find($get('desa_id'))?->nama ?? '–')),
                                    Text::make(fn (Get $get): string => 'Nomor HP: '.($get('no_hp') ?: '–')),
                                ]),
                            Checkbox::make('pernyataan')
                                ->label('Saya menyatakan data yang saya isi benar dan sesuai KTP serta surat tugas.')
                                ->accepted()
                                ->dehydrated(false),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit" size="lg">Kirim pendaftaran</x-filament::button>'))),
            ]);
    }

    public function daftar(): void
    {
        $data = $this->form->getState();

        $this->jalankanService(fn () => app(RegistrasiService::class)->daftar($data));

        $this->terkirim = true;
        $this->data = [];
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
