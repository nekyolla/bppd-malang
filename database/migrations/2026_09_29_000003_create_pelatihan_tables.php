<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penyelenggaraan pelatihan dan kelasnya (ARCHITECTURE §5.4).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pelatihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judul_pelatihan_id')->constrained('judul_pelatihan')->restrictOnDelete();
            $table->year('tahun_anggaran');
            $table->unsignedTinyInteger('batch_ke');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('tipe_lokasi', ['bbpd', 'luar']);
            $table->string('keterangan_lokasi')->nullable();
            $table->enum('status', ['draft', 'dibuka', 'berjalan', 'selesai'])->default('draft')->index();
            $table->timestamps();

            $table->unique(['judul_pelatihan_id', 'tahun_anggaran', 'batch_ke']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pelatihan ADD CONSTRAINT pelatihan_tanggal_check CHECK (tanggal_selesai >= tanggal_mulai)');
        }

        Schema::create('kelas_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelatihan_id')->constrained('pelatihan')->cascadeOnDelete();
            $table->string('nama_kelas', 10);
            $table->timestamps();

            $table->unique(['pelatihan_id', 'nama_kelas']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas_pelatihan');
        Schema::dropIfExists('pelatihan');
    }
};
