<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data master (ARCHITECTURE §5.2, §5.4, §5.6). Tidak ada hapus permanen:
 * data dinonaktifkan lewat `is_aktif`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan');
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('status_ptkp', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5)->unique();
            $table->string('nama');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('sumber_dana', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->boolean('butuh_keterangan')->default(false);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kategori_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kategori');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('judul_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_pelatihan_id')->constrained('kategori_pelatihan')->restrictOnDelete();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('asrama', function (Blueprint $table) {
            $table->id();
            $table->string('nama_asrama');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kamar_asrama', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asrama_id')->constrained('asrama')->restrictOnDelete();
            $table->string('no_kamar', 10);
            $table->unsignedTinyInteger('kapasitas');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['asrama_id', 'no_kamar']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kamar_asrama');
        Schema::dropIfExists('asrama');
        Schema::dropIfExists('judul_pelatihan');
        Schema::dropIfExists('kategori_pelatihan');
        Schema::dropIfExists('sumber_dana');
        Schema::dropIfExists('status_ptkp');
        Schema::dropIfExists('jabatan');
    }
};
