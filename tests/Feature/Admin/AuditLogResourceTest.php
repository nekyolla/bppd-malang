<?php

use App\Enums\Peran;
use App\Enums\StatusPendaftaran;
use App\Filament\Admin\Resources\AuditLog\AuditLogResource;
use App\Filament\Admin\Resources\AuditLog\Pages\ListAuditLog;
use App\Filament\Admin\Resources\AuditLog\Pages\ViewAuditLog;
use App\Models\KelasPelatihan;
use App\Models\PelatihanPeserta;
use App\Models\User;
use App\Services\AkunService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->superadmin = User::factory()->peran(Peran::Superadmin)->create(['username' => 'kepala']);
    $this->actingAs($this->superadmin);
});

it('hanya dapat dibuka superadmin', function () {
    $this->get('/admin/audit-log')->assertOk();

    $this->actingAs(User::factory()->peran(Peran::Admin)->create())->get('/admin/audit-log')->assertForbidden();
});

it('menampilkan siapa, kapan, aksi, objek, dan nilai lama → baru', function () {
    $pendaftaran = PelatihanPeserta::factory()->create();
    $pendaftaran->forceFill(['status' => StatusPendaftaran::Terverifikasi])->save();
    $log = Activity::query()->where('subject_id', $pendaftaran->id)->where('event', 'updated')->sole();

    Livewire::test(ListAuditLog::class)
        ->assertCanSeeTableRecords([$log])
        ->assertSee('kepala')
        ->assertSee("Pendaftaran #{$pendaftaran->id}")
        ->assertActionExists(TestAction::make('view')->table($log))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($log));

    Livewire::test(ViewAuditLog::class, ['record' => $log->getKey()])
        ->assertSee('status')
        ->assertSee('terdaftar')
        ->assertSee('terverifikasi')
        ->assertActionDoesNotExist('kembali');
});

it('mencatat perubahan akun tanpa hash kata sandi', function () {
    $admin = User::factory()->peran(Peran::Admin)->create();

    app(AkunService::class)->aturUlangKataSandi($admin, 'rahasia-baru-123');
    $admin->update(['email' => 'admin.baru@bbpd.test']);

    $logAkun = Activity::query()->where('subject_type', $admin->getMorphClass())->where('subject_id', $admin->id)->get();

    expect($logAkun->pluck('event')->all())->toContain('atur-ulang-sandi', 'updated')
        ->and(json_encode($logAkun->pluck('attribute_changes')))->not->toContain('password')
        ->and(AuditLogResource::perubahan($logAkun->firstWhere('event', 'updated'))[0]['kolom'])->toBe('email');
});

it('menampilkan catatan unduhan beserta nama berkasnya', function () {
    $pendaftaran = PelatihanPeserta::factory()->create();
    $log = activity('unduhan')->performedOn($pendaftaran)->causedBy($this->superadmin)->event('unduh')->log('Mengunduh daftar-hadir.pdf');

    Livewire::test(ListAuditLog::class)
        ->filterTable('event', 'unduh')
        ->assertCanSeeTableRecords([$log])
        ->assertSee('Mengunduh daftar-hadir.pdf');
});

it('menampilkan nama kolom yang mudah dibaca', function () {
    $pendaftaran = PelatihanPeserta::factory()->terverifikasi()->create();
    $kelas = KelasPelatihan::factory()->for($pendaftaran->pelatihan)->create();
    $pendaftaran->forceFill(['kelas_pelatihan_id' => $kelas->id])->save();
    $log = Activity::query()->where('subject_id', $pendaftaran->id)->where('event', 'updated')->latest('id')->first();

    expect(AuditLogResource::perubahan($log)[0]['kolom'])->toBe('kelas pelatihan')
        ->and(AuditLogResource::objek($log))->toBe("Pendaftaran #{$pendaftaran->id}");
});
