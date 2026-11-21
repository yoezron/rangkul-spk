<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instrumen yang dikirim konselor di tengah percakapan untuk diisi anggota.
     *
     * `salinan` menyimpan instrumen persis saat dikirim, supaya anggota mengisi
     * versi yang sama dengan yang dipilih konselor. `isian` (terenkripsi)
     * berbentuk sama dengan jawaban_asesmen.isian.
     */
    public function up(): void
    {
        Schema::create('kiriman_instrumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan')->cascadeOnDelete();
            $table->foreignId('instrumen_id')->nullable()->constrained('instrumen')->nullOnDelete();
            $table->foreignId('dikirim_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_instrumen');
            $table->longText('salinan');
            $table->text('pengantar')->nullable();
            $table->longText('isian')->nullable();
            $table->boolean('kritis')->default(false);
            $table->timestamp('diisi_pada')->nullable();
            $table->timestamps();

            $table->index(['permintaan_id', 'diisi_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiriman_instrumen');
    }
};
