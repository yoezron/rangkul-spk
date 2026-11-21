<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda pengingat WhatsApp "pesan menunggu lebih dari 2 jam" (lihat
     * rangkul:ingatkan-pesan-tertunda), terpisah dari `diingatkan_pada` yang
     * dipakai pengingat 24 jam.
     */
    public function up(): void
    {
        Schema::table('pesan', function (Blueprint $table) {
            $table->timestamp('diingatkan_whatsapp_pada')->nullable()->after('diingatkan_pada');
        });
    }

    public function down(): void
    {
        Schema::table('pesan', function (Blueprint $table) {
            $table->dropColumn('diingatkan_whatsapp_pada');
        });
    }
};
