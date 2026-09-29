<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\Jabatan\Pages\ManageJabatan;
use App\Filament\Admin\Resources\JudulPelatihan\Pages\ManageJudulPelatihan;
use App\Filament\Admin\Resources\KategoriPelatihan\Pages\ManageKategoriPelatihan;
use App\Filament\Admin\Resources\StatusPtkp\Pages\ManageStatusPtkp;
use App\Filament\Admin\Resources\SumberDana\Pages\ManageSumberDana;
use App\Filament\Admin\Support\DataMaster;
use App\Models\Jabatan;
use App\Models\JudulPelatihan;
use App\Models\KategoriPelatihan;
use App\Models\StatusPtkp;
use App\Models\SumberDana;
use App\Models\User;
use App\Services\DataMasterService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->superadmin = User::factory()->peran(Peran::Superadmin)->create();
});

/**
 * [halaman, model, url, kolom nama unik]
 */
dataset('data master', [
    'jabatan' => [ManageJabatan::class, Jabatan::class, '/admin/jabatan', 'nama_jabatan'],
    'kategori pelatihan' => [ManageKategoriPelatihan::class, KategoriPelatihan::class, '/admin/kategori-pelatihan', 'nama_kategori'],
    'judul pelatihan' => [ManageJudulPelatihan::class, JudulPelatihan::class, '/admin/judul-pelatihan', 'judul'],
    'sumber dana' => [ManageSumberDana::class, SumberDana::class, '/admin/sumber-dana', 'nama'],
    'status PTKP' => [ManageStatusPtkp::class, StatusPtkp::class, '/admin/status-ptkp', 'kode'],
]);

/**
 * Isian form tambah yang sah untuk tiap data master.
 *
 * @return array<string, mixed>
 */
function isianDataMaster(string $model): array
{
    return match ($model) {
        Jabatan::class => ['nama_jabatan' => 'Kaur Umum', 'urutan' => 3],
        KategoriPelatihan::class => ['nama_kategori' => 'Teknis'],
        JudulPelatihan::class => ['kategori_pelatihan_id' => KategoriPelatihan::factory()->create()->id, 'judul' => 'Pelatihan BUMDes'],
        SumberDana::class => ['nama' => 'Hibah', 'butuh_keterangan' => false],
        StatusPtkp::class => ['kode' => 'K/1', 'nama' => 'Kawin, 1 tanggungan'],
    };
}

it('hanya superadmin yang dapat membuka data master', function (string $halaman, string $model, string $url) {
    $this->actingAs($this->superadmin)->get($url)->assertOk();
    $this->actingAs(User::factory()->peran(Peran::Admin)->create())->get($url)->assertForbidden();
})->with('data master');

// Dipisah per role karena Filament menyimpan navigasi panel selama satu proses test.
it('menampilkan menu Data Master untuk superadmin', function () {
    $this->actingAs($this->superadmin)->get('/admin')->assertSee('Data Master');
});

it('menyembunyikan menu Data Master dari admin', function () {
    $this->actingAs(User::factory()->peran(Peran::Admin)->create())->get('/admin')->assertDontSee('Data Master');
});

it('superadmin dapat menambah dan mengubah data master', function (string $halaman, string $model, string $url, string $kolomNama) {
    $this->actingAs($this->superadmin);
    $isian = isianDataMaster($model);

    Livewire::test($halaman)
        ->callAction('create', data: $isian)
        ->assertHasNoActionErrors();

    $record = $model::where($kolomNama, $isian[$kolomNama])->firstOrFail();
    expect($record->is_aktif)->toBeTrue();

    Livewire::test($halaman)
        ->callAction(TestAction::make('edit')->table($record), data: [$kolomNama => 'X/9'])
        ->assertHasNoActionErrors();

    expect($record->fresh()->{$kolomNama})->toBe('X/9');
})->with('data master');

it('menolak nama data master yang sudah ada', function (string $halaman, string $model, string $url, string $kolomNama) {
    $this->actingAs($this->superadmin);
    $isian = isianDataMaster($model);
    $model::factory()->create([$kolomNama => $isian[$kolomNama]]);

    Livewire::test($halaman)
        ->callAction('create', data: $isian)
        ->assertHasActionErrors([$kolomNama => 'unique']);

    expect($model::where($kolomNama, $isian[$kolomNama])->count())->toBe(1);
})->with('data master');

it('tidak menyediakan aksi hapus maupun hapus massal', function (string $halaman, string $model) {
    $this->actingAs($this->superadmin);
    $record = $model::factory()->create();

    Livewire::test($halaman)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($record))
        ->assertActionDoesNotExist(TestAction::make('delete')->table()->bulk());
})->with('data master');

it('menonaktifkan dan mengaktifkan kembali data master', function (string $halaman, string $model) {
    $this->actingAs($this->superadmin);
    $record = $model::factory()->create();

    Livewire::test($halaman)
        ->assertActionHidden(TestAction::make('aktifkan')->table($record))
        ->callAction(TestAction::make('nonaktifkan')->table($record));

    expect($record->fresh()->is_aktif)->toBeFalse();

    Livewire::test($halaman)
        ->filterTable('is_aktif', false)
        ->assertActionHidden(TestAction::make('nonaktifkan')->table($record))
        ->callAction(TestAction::make('aktifkan')->table($record));

    expect($record->fresh()->is_aktif)->toBeTrue();
})->with('data master');

it('hanya menampilkan data aktif secara default', function (string $halaman, string $model) {
    $this->actingAs($this->superadmin);
    $aktif = $model::factory()->create();
    $nonaktif = $model::factory()->nonaktif()->create();

    Livewire::test($halaman)
        ->assertCanSeeTableRecords([$aktif])
        ->assertCanNotSeeTableRecords([$nonaktif])
        ->filterTable('is_aktif', false)
        ->assertCanSeeTableRecords([$nonaktif])
        ->assertCanNotSeeTableRecords([$aktif]);
})->with('data master');

it('memilih kategori aktif, ditambah kategori nonaktif yang sedang dipakai', function () {
    $aktif = KategoriPelatihan::factory()->create();
    $nonaktif = KategoriPelatihan::factory()->nonaktif()->create();
    $lain = KategoriPelatihan::factory()->nonaktif()->create();
    $judul = JudulPelatihan::factory()->for($nonaktif)->create();
    $pilihan = DataMaster::pilihanAktif('kategori_pelatihan_id');

    expect($pilihan(KategoriPelatihan::query(), null)->pluck('id')->all())->toBe([$aktif->id])
        ->and($pilihan(KategoriPelatihan::query(), $judul)->orderBy('id')->pluck('id')->all())->toBe([$aktif->id, $nonaktif->id])
        ->and($pilihan(KategoriPelatihan::query(), $judul)->pluck('id')->all())->not->toContain($lain->id);
});

it('menolak menonaktifkan model yang bukan data master', function () {
    app(DataMasterService::class)->nonaktifkan($this->superadmin);
})->throws(InvalidArgumentException::class);
