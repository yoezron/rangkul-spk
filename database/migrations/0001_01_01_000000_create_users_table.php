<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anggota dikenali lewat `spk_id` (id akun di spk.or.id), bukan email:
     * admin situs induk dapat mengganti email seketika. Kolom lain hanyalah
     * salinan dari endpoint data anggota, diperbarui tiap kali diperiksa.
     *
     * Sengaja tanpa kolom password, remember_token, dan tabel reset password:
     * Rangkul tidak punya login lokal maupun pendaftaran.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('spk_id')->unique();
            $table->string('nama');
            $table->string('email')->nullable();
            $table->string('telp')->nullable();
            $table->string('gender')->nullable();
            $table->string('kampus')->nullable();
            $table->string('wilayah')->nullable();
            $table->string('peran')->nullable();
            $table->string('nomor_anggota')->nullable();
            $table->string('foto', 2048)->nullable();
            $table->timestamp('disinkronkan_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
