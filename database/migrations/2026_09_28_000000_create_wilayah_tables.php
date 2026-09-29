<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provinsi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 2)->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('kab_kota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provinsi_id')->constrained('provinsi')->restrictOnDelete();
            $table->string('kode', 5)->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('kecamatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kab_kota_id')->constrained('kab_kota')->restrictOnDelete();
            $table->string('kode', 8)->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('desa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kecamatan_id')->constrained('kecamatan')->restrictOnDelete();
            $table->string('kode', 13)->unique();
            $table->string('nama');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('desa');
        Schema::dropIfExists('kecamatan');
        Schema::dropIfExists('kab_kota');
        Schema::dropIfExists('provinsi');
    }
};
