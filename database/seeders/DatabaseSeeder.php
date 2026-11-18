<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Sengaja kosong: anggota hanya tercipta lewat masuk SSO dari spk.or.id,
     * tidak ada akun bawaan maupun akun uji dengan password.
     */
    public function run(): void
    {
        //
    }
}
