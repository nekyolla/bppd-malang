<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hanya akun internal yang login (PF-01) dan role keuangan dihapus (PF-04).
 * Peserta kini disimpan di tabel `peserta`, bukan `users`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('users')->whereNull('username')->exists()) {
            throw new RuntimeException('Masih ada akun tanpa username (akun peserta lama). Hapus atau beri username terlebih dahulu.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropColumn('nik');
            $table->string('username', 50)->nullable(false)->change();
        });

        DB::table(config('permission.table_names.roles'))->whereIn('name', ['keuangan', 'peserta'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->change();
            $table->char('nik', 16)->nullable()->unique()->after('username');
        });
    }
};
