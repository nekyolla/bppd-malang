<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presensi harian per kelas (ARCHITECTURE §5.5, §7.6).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lembar_presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_pelatihan_id')->constrained('kelas_pelatihan')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('file_scan')->nullable();
            $table->foreignId('diinput_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['kelas_pelatihan_id', 'tanggal']);
        });

        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembar_presensi_id')->constrained('lembar_presensi')->cascadeOnDelete();
            // Pembatalan peserta tidak menghapus presensi (CLAUDE.md aturan 7).
            $table->foreignId('pelatihan_peserta_id')->constrained('pelatihan_peserta')->restrictOnDelete();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa']);
            $table->timestamps();

            $table->unique(['lembar_presensi_id', 'pelatihan_peserta_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi');
        Schema::dropIfExists('lembar_presensi');
    }
};
