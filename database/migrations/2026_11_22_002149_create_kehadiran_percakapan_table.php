<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rentang waktu anggota atau konselor membuka halaman percakapan sebuah
     * kasus, dicatat dari polling halaman (lihat PencatatKehadiran). Dipakai
     * menghitung waktu layanan konselor untuk pembayaran.
     *
     * Hak baru `waktu_layanan.rekap` langsung diberikan ke peran komite.
     */
    public function up(): void
    {
        Schema::create('kehadiran_percakapan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sebagai', 10);
            $table->timestamp('mulai_pada');
            $table->timestamp('terakhir_pada');

            $table->index(['permintaan_id', 'user_id', 'terakhir_pada']);
            $table->index(['mulai_pada', 'terakhir_pada']);
        });

        $komite = DB::table('peran')->where('kunci', 'komite')->first();

        if ($komite !== null) {
            $hak = json_decode((string) $komite->hak, true) ?: [];

            if (! in_array('waktu_layanan.rekap', $hak, true)) {
                DB::table('peran')->where('id', $komite->id)->update(['hak' => json_encode([...$hak, 'waktu_layanan.rekap'])]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kehadiran_percakapan');

        $komite = DB::table('peran')->where('kunci', 'komite')->first();

        if ($komite !== null) {
            $hak = array_values(array_diff(json_decode((string) $komite->hak, true) ?: [], ['waktu_layanan.rekap']));
            DB::table('peran')->where('id', $komite->id)->update(['hak' => json_encode($hak)]);
        }
    }
};
