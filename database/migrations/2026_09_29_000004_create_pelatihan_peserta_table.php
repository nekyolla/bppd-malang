<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran peserta di satu pelatihan (ARCHITECTURE §5.5).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pelatihan_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelatihan_id')->constrained('pelatihan')->restrictOnDelete();
            $table->foreignId('peserta_id')->constrained('peserta')->restrictOnDelete();
            $table->foreignId('kelas_pelatihan_id')->nullable()->constrained('kelas_pelatihan')->restrictOnDelete();

            // Isian mentah form registrasi; tidak langsung menimpa `peserta` (PF-03).
            $table->json('data_isian');
            // Path privat. Nullable karena foldernya memakai id baris ini (ARCHITECTURE §7.0).
            $table->string('file_surat_tugas')->nullable();
            $table->foreignId('sumber_dana_id')->constrained('sumber_dana')->restrictOnDelete();
            $table->string('sumber_dana_keterangan')->nullable();

            // Snapshot, diisi saat verifikasi.
            $table->foreignId('jabatan_id_saat_pelatihan')->nullable()->constrained('jabatan')->restrictOnDelete();
            $table->foreignId('desa_id_saat_pelatihan')->nullable()->constrained('desa')->restrictOnDelete();
            $table->foreignId('status_ptkp_id_saat_pelatihan')->nullable()->constrained('status_ptkp')->restrictOnDelete();

            $table->enum('status', ['terdaftar', 'terverifikasi', 'selesai', 'batal'])->default('terdaftar');
            $table->text('catatan_panitia')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('diverifikasi_pada')->nullable();

            $table->decimal('nilai_pretest', 5, 2)->nullable();
            $table->decimal('nilai_posttest', 5, 2)->nullable();

            $table->text('alasan_batal')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('dibatalkan_pada')->nullable();
            $table->foreignId('menggantikan_id')->nullable()->unique()->constrained('pelatihan_peserta')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['pelatihan_id', 'peserta_id']);
            // Statistik desa terlatih: WHERE status = 'selesai' GROUP BY desa (ARCHITECTURE §7.7).
            $table->index(['status', 'desa_id_saat_pelatihan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelatihan_peserta');
    }
};
