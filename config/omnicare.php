<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Omnicare (Lokadok) Open API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials issued privately oleh Omnicare admin ke klinik. Username =
    | subdomain klinik, password = passkey yang dishare. Auth dikirim via JSON
    | body, BUKAN header (per spec Lokadok).
    |
    */

    'base_url' => env('OMNICARE_BASE_URL', 'https://api.lokadok.xyz'),

    'credentials' => [
        'username' => env('OMNICARE_USERNAME'),
        'password' => env('OMNICARE_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Spec Lokadok: sales endpoints (sales_detail, product_sales) punya
    | cooldown 10 detik antar call. Other endpoints tidak ada cooldown.
    | Kita pakai 11 detik untuk safety margin.
    |
    */

    'cooldown_ms' => env('OMNICARE_COOLDOWN_MS', 11000),

    'sales_endpoints' => [
        '/get/sales_detail',
        '/get/product_sales',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    */

    'timeout_seconds' => env('OMNICARE_TIMEOUT', 60),

    'retry' => [
        'attempts' => env('OMNICARE_RETRY_ATTEMPTS', 3),
        'delay_ms' => env('OMNICARE_RETRY_DELAY_MS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Branch Scope
    |--------------------------------------------------------------------------
    |
    | List place_id yang akan di-sync. ABG (31163) di-exclude karena verified
    | placeholder (0 doctors, 0 sales saat smoke test 2026-05-20).
    |
    | Untuk pilot mode (sync 1 cabang saja), pakai flag --place=<id> di
    | Artisan command, bukan ubah config ini.
    |
    */

    'active_branches' => [
        '29957', // Bintaro (parent)
        '29958', // Sunter
        '29959', // Kalideres
        '30439', // Cinere
        '30941', // Pamulang
    ],

    'excluded_branches' => [
        '31163', // SWEETTREATS ABG — verified placeholder
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    |
    | days_overlap: berapa hari ke belakang yang di-pull tiap nightly sync
    | (untuk catch late entries). Default 3 hari = aman untuk staff yang
    | input H+1/H+2 pagi.
    |
    | backfill_months: depth historical data untuk first-time backfill.
    |
    */

    'sync' => [
        'days_overlap' => env('OMNICARE_SYNC_DAYS', 3),
        'backfill_months' => env('OMNICARE_BACKFILL_MONTHS', 3),
        'page_size' => 100, // Max per Lokadok spec
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Settings
    |--------------------------------------------------------------------------
    |
    | match_window_days: range tanggal toleransi untuk match reservation ↔
    | encounter. ±3 hari = akomodasi reschedule kecil tanpa terlalu loose.
    |
    */

    'reconcile' => [
        'match_window_days' => env('OMNICARE_MATCH_WINDOW', 3),
        'lookback_days'     => env('OMNICARE_RECONCILE_LOOKBACK', 30),
    ],
];
