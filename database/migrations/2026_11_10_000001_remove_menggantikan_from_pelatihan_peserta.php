<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda pengganti dihapus (PF-28): pengganti mendaftar dan diverifikasi seperti
 * peserta biasa, peserta yang tidak jadi cukup dibatalkan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pelatihan_peserta', function (Blueprint $table) {
            $table->dropForeign(['menggantikan_id']);
            $table->dropUnique(['menggantikan_id']);
            $table->dropColumn('menggantikan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pelatihan_peserta', function (Blueprint $table) {
            $table->foreignId('menggantikan_id')->nullable()->unique()->after('kelas_pelatihan_id')->constrained('pelatihan_peserta')->restrictOnDelete();
        });
    }
};
