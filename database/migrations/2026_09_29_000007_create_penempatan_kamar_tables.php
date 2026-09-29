<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemakaian kamar per pelatihan dan penghuninya (ARCHITECTURE §5.6, §7.3).
 * Validasi gender, kapasitas, dan bentrok tanggal ada di AsramaService.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('penggunaan_kamar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelatihan_id')->constrained('pelatihan')->restrictOnDelete();
            $table->foreignId('kamar_asrama_id')->constrained('kamar_asrama')->restrictOnDelete();
            // Dikunci otomatis oleh penghuni pertama, kecuali PASUTRI (CLAUDE.md aturan 5).
            $table->enum('tipe', ['L', 'P', 'PASUTRI']);
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['pelatihan_id', 'kamar_asrama_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE penggunaan_kamar ADD CONSTRAINT penggunaan_kamar_pasutri_check CHECK (tipe <> 'PASUTRI' OR disetujui_oleh IS NOT NULL)");
        }

        Schema::create('asrama_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelatihan_peserta_id')->unique()->constrained('pelatihan_peserta')->restrictOnDelete();
            $table->foreignId('penggunaan_kamar_id')->constrained('penggunaan_kamar')->restrictOnDelete();
            $table->foreignId('ditempatkan_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asrama_peserta');
        Schema::dropIfExists('penggunaan_kamar');
    }
};
