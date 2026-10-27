<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presensi dan nilai tidak dicatat di sistem (PF-22, PF-26): presensi diisi di kertas
 * memakai daftar hadir cetak per hari.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('presensi');
        Schema::dropIfExists('lembar_presensi');

        Schema::table('pelatihan_peserta', function (Blueprint $table) {
            $table->dropColumn(['nilai_pretest', 'nilai_posttest']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pelatihan_peserta', function (Blueprint $table) {
            $table->decimal('nilai_pretest', 5, 2)->nullable()->after('diverifikasi_pada');
            $table->decimal('nilai_posttest', 5, 2)->nullable()->after('nilai_pretest');
        });

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
            $table->foreignId('pelatihan_peserta_id')->constrained('pelatihan_peserta')->restrictOnDelete();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa']);
            $table->timestamps();

            $table->unique(['lembar_presensi_id', 'pelatihan_peserta_id']);
        });
    }
};
