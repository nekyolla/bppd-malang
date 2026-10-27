<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat dibuat manual di luar sistem; admin cukup mengunggah PDF-nya, tanpa nomor
 * dan tanggal terbit (PF-27).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sertifikat_peserta', function (Blueprint $table) {
            $table->dropUnique(['nomor_sertifikat']);
            $table->dropColumn(['nomor_sertifikat', 'tanggal_terbit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sertifikat_peserta', function (Blueprint $table) {
            $table->string('nomor_sertifikat')->nullable()->unique()->after('pelatihan_peserta_id');
            $table->date('tanggal_terbit')->nullable()->after('nomor_sertifikat');
        });
    }
};
