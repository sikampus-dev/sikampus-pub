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
        Schema::create('kalender_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // Enum tetap hidup di App\Models\KalenderAkademik::KATEGORI_OPTIONS, bukan di kolom
            // database — supaya kategori "gating" baru (Fase 3) cukup tambah kasus di kode, tanpa
            // migrasi baru.
            $table->string('kategori');
            // Nullable: sebagian event (libur nasional, wisuda) tidak terikat satu semester.
            $table->foreignId('id_semester')->nullable()->constrained('semester')->restrictOnDelete();
            $table->timestamp('tanggal_mulai');
            $table->timestamp('tanggal_selesai');
            $table->text('deskripsi')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kalender_akademik');
    }
};
