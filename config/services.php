<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Jembatan ke situs induk spk.or.id: masuk sekali klik (SSO) dan endpoint
    | data anggota. `sso_secret` harus sama persis dengan RANGKUL_SSO_SECRET
    | di .env spk.or.id dan sekurangnya 32 karakter; selama belum diisi,
    | Rangkul menolak semua token masuk.
    */
    'spk' => [
        'url' => env('SPK_URL', 'https://spk.or.id/'),
        'sso_secret' => env('SPK_SSO_SECRET'),
        // Nama peran di spk.or.id yang otomatis menjadi superadmin Rangkul.
        'peran_superadmin' => env('SPK_PERAN_SUPERADMIN', 'Administrator'),
    ],

    /*
    | Gerbang WhatsApp Fonnte untuk notifikasi. Token perangkat dan status
    | aktifnya diatur dari dashboard (Pengaturan → WhatsApp), bukan dari .env;
    | yang di sini hanya alamat API-nya.
    */
    'fonnte' => [
        'url' => env('FONNTE_URL', 'https://api.fonnte.com'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
