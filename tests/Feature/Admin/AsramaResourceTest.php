<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\Asrama\Pages\CreateAsrama;
use App\Filament\Admin\Resources\Asrama\Pages\EditAsrama;
use App\Filament\Admin\Resources\Asrama\Pages\ListAsrama;
use App\Filament\Admin\Resources\Asrama\RelationManagers\KamarRelationManager;
use App\Models\Asrama;
use App\Models\KamarAsrama;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->peran(Peran::Superadmin)->create());
    $this->asrama = Asrama::factory()->create(['nama_asrama' => 'Anggrek']);
    $this->kamar = fn () => Livewire::test(KamarRelationManager::class, ['ownerRecord' => $this->asrama, 'pageClass' => EditAsrama::class]);
});

it('hanya superadmin yang dapat mengelola asrama', function () {
    $this->get('/admin/asrama')->assertOk();
    $this->get("/admin/asrama/{$this->asrama->id}/edit")->assertOk();
    $this->actingAs(User::factory()->peran(Peran::Admin)->create())->get('/admin/asrama')->assertForbidden();
});

it('membuat asrama lalu langsung ke halaman kamar', function () {
    Livewire::test(CreateAsrama::class)
        ->fillForm(['nama_asrama' => 'Melati'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/asrama/'.Asrama::where('nama_asrama', 'Melati')->value('id').'/edit');
});

it('menampilkan jumlah kamar dan tempat tidur aktif', function () {
    KamarAsrama::factory()->for($this->asrama)->count(2)->create(['kapasitas' => 4]);
    KamarAsrama::factory()->for($this->asrama)->nonaktif()->create(['kapasitas' => 4]);

    Livewire::test(ListAsrama::class)
        ->assertCanSeeTableRecords([$this->asrama])
        ->assertTableColumnStateSet('kamar_aktif', 2, $this->asrama)
        ->assertTableColumnStateSet('kapasitas_aktif', 8, $this->asrama);
});

it('menambah kamar dengan nomor unik per asrama', function () {
    ($this->kamar)()
        ->callAction(TestAction::make('create')->table(), data: ['no_kamar' => '01', 'kapasitas' => 4])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('create')->table(), data: ['no_kamar' => '01', 'kapasitas' => 2])
        ->assertHasActionErrors(['no_kamar' => 'unique']);

    KamarAsrama::factory()->for(Asrama::factory())->create(['no_kamar' => '02']);

    ($this->kamar)()
        ->callAction(TestAction::make('create')->table(), data: ['no_kamar' => '02', 'kapasitas' => 2])
        ->assertHasNoActionErrors();

    expect($this->asrama->kamar()->pluck('no_kamar')->sort()->values()->all())->toBe(['01', '02']);
});

it('membatasi kapasitas kamar 1 sampai 20', function (int $kapasitas) {
    ($this->kamar)()
        ->callAction(TestAction::make('create')->table(), data: ['no_kamar' => '09', 'kapasitas' => $kapasitas])
        ->assertHasActionErrors(['kapasitas']);
})->with([0, 21]);

it('menonaktifkan kamar tanpa aksi hapus', function () {
    $kamar = KamarAsrama::factory()->for($this->asrama)->create();

    ($this->kamar)()
        ->assertActionDoesNotExist(TestAction::make('delete')->table($kamar))
        ->callAction(TestAction::make('nonaktifkan')->table($kamar));

    expect($kamar->fresh()->is_aktif)->toBeFalse();
});

it('menonaktifkan asrama dari halaman ubah', function () {
    Livewire::test(EditAsrama::class, ['record' => $this->asrama->getRouteKey()])
        ->callAction('nonaktifkan');

    expect($this->asrama->fresh()->is_aktif)->toBeFalse();
});
