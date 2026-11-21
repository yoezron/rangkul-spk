<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private array $tabel = ['permintaan', 'pertemuan', 'kiriman_instrumen'];

    /**
     * Kunci acak untuk tautan kasus, pertemuan, dan kuesioner (lihat
     * PunyaKunciTautan), menggantikan ID angka di URL. Baris lama diisi kunci
     * baru, sehingga tautan lama berbasis angka tidak berlaku lagi.
     */
    public function up(): void
    {
        foreach ($this->tabel as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->string('kunci_tautan', 16)->nullable()->after('id');
            });

            $terpakai = [];

            foreach (DB::table($tabel)->whereNull('kunci_tautan')->pluck('id') as $id) {
                do {
                    $kunci = Str::random(12);
                } while (isset($terpakai[strtolower($kunci)]));

                $terpakai[strtolower($kunci)] = true;
                DB::table($tabel)->where('id', $id)->update(['kunci_tautan' => $kunci]);
            }

            Schema::table($tabel, function (Blueprint $table) {
                $table->unique('kunci_tautan');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabel as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropUnique(['kunci_tautan']);
                $table->dropColumn('kunci_tautan');
            });
        }
    }
};
