<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `dibuka_kembali_pada`: terakhir kali anggota membuka kembali kasus yang
     * sudah selesai. `diingatkan_menggantung_pada`: terakhir kali komite
     * diberi tahu bahwa kasus ini tanpa aktivitas 48 jam (lihat
     * rangkul:ingatkan-kasus-menggantung).
     */
    public function up(): void
    {
        Schema::table('permintaan', function (Blueprint $table) {
            $table->timestamp('dibuka_kembali_pada')->nullable()->after('selesai_pada');
            $table->timestamp('diingatkan_menggantung_pada')->nullable()->after('dibuka_kembali_pada');
        });
    }

    public function down(): void
    {
        Schema::table('permintaan', function (Blueprint $table) {
            $table->dropColumn(['dibuka_kembali_pada', 'diingatkan_menggantung_pada']);
        });
    }
};
