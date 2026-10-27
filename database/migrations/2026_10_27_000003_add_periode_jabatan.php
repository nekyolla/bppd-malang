<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode jabatan (ke-1/ke-2) untuk jabatan berperiode seperti kepala desa (PF-25).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jabatan', function (Blueprint $table) {
            $table->boolean('memiliki_periode')->default(false)->after('urutan');
        });

        Schema::table('peserta', function (Blueprint $table) {
            $table->unsignedTinyInteger('periode_jabatan')->nullable()->after('jabatan_id');
        });

        Schema::table('pelatihan_peserta', function (Blueprint $table) {
            $table->unsignedTinyInteger('periode_jabatan_saat_pelatihan')->nullable()->after('jabatan_id_saat_pelatihan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pelatihan_peserta', function (Blueprint $table) {
            $table->dropColumn('periode_jabatan_saat_pelatihan');
        });

        Schema::table('peserta', function (Blueprint $table) {
            $table->dropColumn('periode_jabatan');
        });

        Schema::table('jabatan', function (Blueprint $table) {
            $table->dropColumn('memiliki_periode');
        });
    }
};
