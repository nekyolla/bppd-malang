<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat PDF per pendaftaran (ARCHITECTURE §5.5, FR-SRT).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sertifikat_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelatihan_peserta_id')->unique()->constrained('pelatihan_peserta')->restrictOnDelete();
            $table->string('nomor_sertifikat')->unique();
            $table->date('tanggal_terbit');
            $table->string('file_sertifikat');
            $table->foreignId('diupload_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sertifikat_peserta');
    }
};
