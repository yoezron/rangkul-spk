<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `pengaturan` — pasangan kunci-nilai yang diatur dari dashboard. Nilai
     * disimpan sebagai JSON; nilai rahasia (sandi SMTP) disimpan terenkripsi.
     *
     * `audit_log` — catatan tindakan PENGELOLA (mengubah pengaturan, menugaskan
     * peran, mengunduh rekap). Kunjungan anggota tidak pernah dicatat di sini.
     * Hanya bisa ditambah; baris lama dibuang oleh pemangkasan terjadwal.
     */
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->string('kunci')->primary();
            $table->text('nilai')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aktor');
            $table->string('aksi')->index();
            $table->string('sasaran')->nullable();
            $table->json('rincian')->nullable();
            $table->string('alamat_ip', 45)->nullable();
            $table->timestamp('dibuat_pada')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('pengaturan');
    }
};
