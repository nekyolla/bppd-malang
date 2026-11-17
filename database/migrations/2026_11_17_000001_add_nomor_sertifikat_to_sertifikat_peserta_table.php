<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor sertifikat diisi admin saat mengunggah (opsional) untuk export data peserta (PF-37).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sertifikat_peserta', function (Blueprint $table) {
            $table->string('nomor_sertifikat', 100)->nullable()->unique()->after('pelatihan_peserta_id');
        });
    }

    public function down(): void
    {
        Schema::table('sertifikat_peserta', function (Blueprint $table) {
            $table->dropUnique(['nomor_sertifikat']);
            $table->dropColumn('nomor_sertifikat');
        });
    }
};
