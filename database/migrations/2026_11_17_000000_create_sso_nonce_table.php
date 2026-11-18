<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nonce token masuk yang sudah dipakai, disimpan sampai tokennya
     * kedaluwarsa supaya satu tautan tidak bisa dipakai dua kali.
     *
     * Yang disimpan hanya sidik SHA-256 nonce, tanpa id akun: tabel ini tidak
     * boleh bisa dipakai menelusuri siapa membuka layanan psikologis dan kapan.
     * Sengaja di basis data, bukan di cache — cache yang dikosongkan atau salah
     * diatur akan diam-diam mematikan perlindungan ini.
     */
    public function up(): void
    {
        Schema::create('sso_nonce', function (Blueprint $table) {
            $table->char('sidik', 64)->primary();
            $table->unsignedBigInteger('kedaluwarsa_pada')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sso_nonce');
    }
};
