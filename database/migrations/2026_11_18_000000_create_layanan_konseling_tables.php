<?php

use App\Services\Asesmen\InstrumenBawaan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel layanan konseling.
     *
     * Kolom bertipe `text` yang menyimpan cerita, jawaban asesmen, pesan, dan
     * catatan konselor diisi terenkripsi oleh model (cast `encrypted`), sehingga
     * salinan basis data yang bocor tidak dapat dibaca tanpa APP_KEY.
     * Konsekuensinya: isi tersebut tidak bisa dicari lewat SQL.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notif_surel')->default(true)->after('foto');
            $table->boolean('notif_whatsapp')->default(true)->after('notif_surel');
            $table->string('nomor_whatsapp', 30)->nullable()->after('notif_whatsapp');
        });

        Schema::create('instrumen', function (Blueprint $table) {
            $table->id();
            $table->string('kunci')->unique();
            $table->string('nama');
            $table->text('instruksi')->nullable();
            $table->json('pilihan');
            $table->string('metode', 10)->default('jumlah');
            $table->decimal('pengali', 8, 2)->default(1);
            $table->json('subskala');
            $table->json('rentang');
            $table->json('butir');
            $table->text('sumber')->nullable();
            $table->boolean('aktif')->default(false);
            $table->unsignedInteger('urutan')->default(100);
            $table->boolean('bawaan')->default(false);
            $table->timestamps();
        });

        Schema::create('permintaan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->index();
            $table->boolean('darurat')->default(false)->index();
            $table->text('alasan_darurat')->nullable();
            $table->unsignedTinyInteger('langkah')->default(1);

            $table->timestamp('persetujuan_pada')->nullable();
            $table->text('keselamatan')->nullable();
            $table->text('topik')->nullable();
            $table->text('topik_lainnya')->nullable();
            $table->text('uraian')->nullable();
            $table->text('harapan')->nullable();
            $table->string('bentuk_layanan', 20)->nullable();
            $table->text('waktu_luang')->nullable();
            $table->boolean('hindari_kampus_sama')->default(false);
            $table->string('gender_konselor', 20)->default('bebas');

            $table->foreignId('konselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditugaskan_pada')->nullable();
            $table->foreignId('ditugaskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('daring_disetujui_pada')->nullable();
            $table->foreignId('daring_disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('penutupan_diusulkan_pada')->nullable();
            $table->text('alasan_penutupan')->nullable();
            $table->timestamp('penutupan_disetujui_anggota_pada')->nullable();
            $table->timestamp('penutupan_ditinjau_pada')->nullable();
            $table->foreignId('penutupan_ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_tinjauan_penutupan')->nullable();

            $table->text('alasan_penolakan')->nullable();
            $table->text('catatan_komite')->nullable();
            $table->timestamp('diajukan_pada')->nullable()->index();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('jawaban_asesmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan')->cascadeOnDelete();
            $table->foreignId('instrumen_id')->nullable()->constrained('instrumen')->nullOnDelete();
            $table->string('nama_instrumen');
            $table->longText('isian');
            $table->boolean('kritis')->default(false);
            $table->timestamps();
        });

        Schema::create('pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan')->cascadeOnDelete();
            $table->foreignId('pengirim_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis', 10)->default('pesan');
            $table->text('isi');
            $table->timestamp('dibaca_pada')->nullable();
            $table->timestamp('diingatkan_pada')->nullable();
            $table->timestamps();

            $table->index(['permintaan_id', 'id']);
            $table->index(['dibaca_pada', 'diingatkan_pada']);
        });

        Schema::create('pertemuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan')->cascadeOnDelete();
            $table->foreignId('diusulkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('mulai_pada');
            $table->unsignedSmallInteger('durasi_menit')->default(60);
            $table->text('tautan')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status', 20)->index();
            $table->timestamp('disetujui_anggota_pada')->nullable();
            $table->timestamp('disetujui_komite_pada')->nullable();
            $table->foreignId('disetujui_komite_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan_penolakan')->nullable();
            $table->timestamps();
        });

        Schema::create('catatan_sesi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan')->cascadeOnDelete();
            $table->foreignId('pertemuan_id')->nullable()->constrained('pertemuan')->nullOnDelete();
            $table->foreignId('penulis_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rangkuman');
            $table->text('analisis')->nullable();
            $table->text('saran')->nullable();
            $table->boolean('rujukan')->default(false);
            $table->text('rujukan_ke')->nullable();
            $table->timestamps();
        });

        DB::table('peran')->insert([
            'kunci' => 'komite',
            'nama' => 'Komite Rangkul',
            'deskripsi' => 'Membaca dan menganalisis permintaan layanan, menugaskan peer konselor, memantau pendampingan, menyetujui pertemuan daring pertama dan penutupan, serta menyusun asesmen.',
            'hak' => json_encode(['dasbor.lihat', 'permintaan.kelola', 'permintaan.rekap', 'asesmen.kelola']),
            'bawaan' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (InstrumenBawaan::semua() as $instrumen) {
            DB::table('instrumen')->insert([
                'kunci' => $instrumen['kunci'],
                'nama' => $instrumen['nama'],
                'instruksi' => $instrumen['instruksi'],
                'pilihan' => json_encode($instrumen['pilihan']),
                'metode' => $instrumen['metode'],
                'pengali' => $instrumen['pengali'],
                'subskala' => json_encode((object) $instrumen['subskala']),
                'rentang' => json_encode($instrumen['rentang']),
                'butir' => json_encode($instrumen['butir']),
                'sumber' => $instrumen['sumber'],
                'aktif' => $instrumen['aktif'],
                'urutan' => $instrumen['urutan'],
                'bawaan' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('peran')->where('kunci', 'komite')->delete();

        Schema::dropIfExists('catatan_sesi');
        Schema::dropIfExists('pertemuan');
        Schema::dropIfExists('pesan');
        Schema::dropIfExists('jawaban_asesmen');
        Schema::dropIfExists('permintaan');
        Schema::dropIfExists('instrumen');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notif_surel', 'notif_whatsapp', 'nomor_whatsapp']);
        });
    }
};
