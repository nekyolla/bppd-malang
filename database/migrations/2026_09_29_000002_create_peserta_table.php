<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data peserta tanpa akun, satu baris per NIK (ARCHITECTURE §5.2, PF-03).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('peserta', function (Blueprint $table) {
            $table->id();
            $table->char('nik', 16)->unique();
            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->string('agama');
            $table->text('alamat_domisili');
            $table->string('no_hp', 20);
            $table->string('email')->nullable();
            $table->string('jenjang_pendidikan');
            $table->string('jurusan_pendidikan')->nullable();
            $table->foreignId('jabatan_id')->constrained('jabatan')->restrictOnDelete();
            $table->date('waktu_pelantikan');
            $table->foreignId('desa_id')->constrained('desa')->restrictOnDelete();
            $table->text('alamat_kantor_desa');
            $table->string('npwp', 20)->nullable();
            $table->foreignId('status_ptkp_id')->constrained('status_ptkp')->restrictOnDelete();
            // Path privat. Nullable karena berkas disimpan di folder pendaftaran
            // yang id-nya baru ada setelah baris ini dibuat (ARCHITECTURE §7.0).
            $table->string('foto')->nullable();
            $table->string('file_ktp')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peserta');
    }
};
