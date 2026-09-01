<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Accurate Online Open API Configuration
    |--------------------------------------------------------------------------
    |
    | Otorisasi pakai metode API Token (BUKAN OAuth) karena integrasi headless
    | (cron, server-to-server). App Key + Signature Secret di-share untuk SEMUA
    | Data Usaha (didapat dari Area Developer: account.accurate.id/developer).
    |
    | Per-Data-Usaha (per cabang) punya API Token + host masing-masing — itu
    | disimpan di tabel `accurate_credentials`, BUKAN di config ini, karena:
    |   - tokennya beda tiap Data Usaha (1 token = 1 database)
    |   - host-nya dinamis & bisa berubah (HTTP 308), perlu di-cache di DB
    |
    | Tiap request data butuh 3 header:
    |   Authorization: Bearer {api_token}
    |   X-Api-Timestamp: {unix detik / ISO8601}   (toleransi ±600 detik)
    |   X-Api-Signature: base64(HMAC-SHA256(timestamp, signature_secret))
    |
    */

    // URL akun (tahap-1: resolve host via /api/api-token.do). Bukan host data usaha.
    'account_url' => env('ACCURATE_ACCOUNT_URL', 'https://account.accurate.id'),

    'credentials' => [
        // App Key — diinformasikan ke User Accurate untuk install app. Tidak
        // dipakai di header request, disimpan untuk referensi/operasional.
        'app_key'          => env('ACCURATE_APP_KEY'),
        // Signature Secret — KEY untuk HMAC X-Api-Signature. RAHASIA.
        'signature_secret' => env('ACCURATE_SIGNATURE_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Spec Accurate: maksimal 8 pemanggilan API per detik DAN maksimal 8 proses
    | paralel per API Token. Lewat itu = error. Karena sync kita sekuensial
    | (1 proses), yang relevan adalah batas 8/detik. Kita pakai 7/detik untuk
    | safety margin (min_interval = 1000/7 ≈ 143 ms antar call).
    |
    */

    'rate_limit' => [
        'max_per_second' => env('ACCURATE_MAX_PER_SECOND', 7),
        'max_parallel'   => 8, // hard limit Accurate; sync kita sekuensial
    ],

    /*
    |--------------------------------------------------------------------------
    | Timestamp & Signature
    |--------------------------------------------------------------------------
    |
    | tolerance_seconds: batas selisih timestamp vs server Accurate (spec: 600
    | detik). Server kita WAJIB sync NTP. Format yang dipakai = unix detik.
    |
    */

    'tolerance_seconds' => 600,

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | follow_redirects WAJIB true: host data usaha bisa berubah → Accurate balas
    | HTTP 308 (Permanent Redirect) ke host baru. Guzzle harus follow + keep
    | method + keep Authorization header.
    |
    */

    'timeout_seconds' => env('ACCURATE_TIMEOUT', 60),

    'retry' => [
        'attempts' => env('ACCURATE_RETRY_ATTEMPTS', 3),
        'delay_ms' => env('ACCURATE_RETRY_DELAY_MS', 2000),
    ],

    // Re-resolve host tiap N hari (spec saran: cek /api-token.do tiap 30 hari)
    'host_refresh_days' => env('ACCURATE_HOST_REFRESH_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Scope Data Usaha (fase testing: PULL-ONLY)
    |--------------------------------------------------------------------------
    |
    | 6 Data Usaha dalam scope = Holding + 5 cabang klinik. RB Las Jaya DITUNDA.
    | Mapping ke omnicare place_id ada di kolom accurate_credentials.branch_place_id
    | (di-seed via SQL). Holding tidak punya cabang Omnicare (branch_place_id NULL).
    |
    | Detail kredensial (token, host) di tabel accurate_credentials, bukan di sini.
    |
    */

    'scope_slugs' => [
        'holding',
        'sunter',
        'cinere',
        'kalideres',
        'bintaro',
        'pamulang',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rekonsiliasi — aturan penyamaan metrik Accurate vs Omnicare
    |--------------------------------------------------------------------------
    |
    | revenue_exclude_accounts: kode akun pendapatan yang TIDAK dihitung sebagai
    | "penjualan jasa" (per Finance: obat/HPP dikeluarkan dari total penjualan).
    | Default: 400302 = Pendapatan Medicine. Tambah di sini bila Finance minta.
    |
    | omnicare_exclude_types: item_type Omnicare yang dikecualikan agar setara
    | dengan nilai Accurate. Empiris (Apr 2026 Bintaro): exclude 'V' (Voucher)
    | bikin SUM(net_sell_price) Omnicare = gross item Accurate, PERSIS.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Saklar PUSH ke Accurate
    |--------------------------------------------------------------------------
    |
    | false = `accurate:push-daily` TIDAK dijadwalkan DAN menolak dijalankan
    | manual (kecuali --force). Dipakai saat Finance mapping ulang: push harian
    | menghapus & menulis ulang dokumen hari yang berubah, jadi membiarkannya
    | menyala saat mapping setengah jadi = menyiram buku Accurate tiap 03:30.
    |
    | Yang TIDAK ikut mati (disengaja): omnicare:sync (mirror tetap segar),
    | accurate:pull-glaccount (Finance butuh COA segar justru untuk mapping),
    | rop:tarik-vendor. Semuanya MEMBACA, tak menulis ke Accurate.
    |
    | ⚠️ Saat dinyalakan lagi, audit cuma melihat --days ke belakang (default
    | 14). Kalau mapping ulang makan waktu lebih lama dari itu, hari-hari yang
    | lebih tua TIDAK ikut diperbaiki sendiri — jalankan sekali dengan --days
    | yang cukup lebar.
    |
    */

    'push_enabled' => env('ACCURATE_PUSH_ENABLED', true),

    'revenue_exclude_accounts' => ['400302'],

    'omnicare_exclude_types' => ['V'],

    /*
    |--------------------------------------------------------------------------
    | Transfer internal — dikecualikan dari "pengeluaran"
    |--------------------------------------------------------------------------
    |
    | other_payment di Accurate tercampur pemindahbukuan internal (pindah dana
    | antar rekening sendiri / antar PT grup) yang BUKAN beban riil. Transaksi
    | yang description-nya mengandung salah satu keyword ini dikecualikan dari
    | pengeluaran di Laporan Keuangan, dan jumlahnya ditampilkan transparan.
    | Daftar konservatif — sisanya menunggu klasifikasi akun dari Finance.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Ambang kelengkapan beban (Laporan Keuangan / Financial Statement)
    |--------------------------------------------------------------------------
    |
    | Sebuah bulan dianggap "beban-nya lengkap" hanya kalau total beban tercatat
    | >= ratio ini dari pendapatan (dan <= 150%). Klinik realistis bebannya 50%+
    | omzet (fee dokter saja ~40%), jadi bulan dgn beban jauh di bawah ini =
    | input Finance belum tuntas → laba/net tidak dihitung (hindari surplus palsu).
    |
    */

    'expense_complete_min_ratio' => env('ACCURATE_EXPENSE_MIN_RATIO', 0.30),

    'internal_transfer_keywords' => [
        'PINBUK',
        'TOP UP',
        'TOPUP',
        'SWITCHING',
        'PEMINDAHBUKUAN',
        'TRANSFER KE 451 PT', // BI-FAST antar PT grup (rek BSI 451)
    ],
];
