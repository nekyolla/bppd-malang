<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot tanggal pelantikan agar tahun menjabat di riwayat pelatihan
 * tidak berubah saat peserta dilantik ulang (CLAUDE.md aturan 6).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pelatihan_peserta', function (Blueprint $table) {
            $table->date('waktu_pelantikan_saat_pelatihan')->nullable()->after('status_ptkp_id_saat_pelatihan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pelatihan_peserta', function (Blueprint $table) {
            $table->dropColumn('waktu_pelantikan_saat_pelatihan');
        });
    }
};
