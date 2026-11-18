<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Peran di dalam Rangkul dan penugasannya kepada pengguna.
     *
     * Berbeda dari kolom `users.peran` (salinan peran di spk.or.id): tabel ini
     * mengatur siapa boleh berbuat apa di Rangkul. Superadmin tidak ada di
     * sini — ia mengikuti peran Administrator di spk.or.id.
     */
    public function up(): void
    {
        Schema::create('peran', function (Blueprint $table) {
            $table->id();
            $table->string('kunci')->unique();
            $table->string('nama');
            $table->string('deskripsi', 500)->nullable();
            $table->json('hak');
            $table->boolean('bawaan')->default(false);
            $table->timestamps();
        });

        Schema::create('peran_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('peran_id')->constrained('peran')->cascadeOnDelete();
            $table->foreignId('ditugaskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'peran_id']);
        });

        DB::table('peran')->insert([
            [
                'kunci' => 'administrator',
                'nama' => 'Administrator Rangkul',
                'deskripsi' => 'Mengelola layanan sehari-hari: buka/tutup layanan, halaman depan, kontak, dan penugasan peer konselor.',
                'hak' => json_encode(['dasbor.lihat', 'pengaturan.umum', 'pengaturan.halaman', 'pengguna.lihat', 'pengguna.tugaskan', 'audit.lihat', 'konseling.tangani']),
                'bawaan' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kunci' => 'peer_konselor',
                'nama' => 'Peer Konselor',
                'deskripsi' => 'Anggota atau pengurus SPK yang ditugaskan mendampingi sesama anggota.',
                'hak' => json_encode(['konseling.tangani']),
                'bawaan' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peran_user');
        Schema::dropIfExists('peran');
    }
};
