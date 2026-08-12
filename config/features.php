<?php

return [
    /*
     * Per-doctor duration override + schedule exceptions (Jadwal Khusus).
     * Set to true once management approves.
     */
    'flexible_scheduling' => true,

    /*
     * Tombol Reset Petty Cash (hard-delete jejak transaksi & riwayat review) —
     * alat bersih-bersih data demo.
     *
     * ⚠️ MATI KECUALI DINYALAKAN EKSPLISIT. Jangan disimpulkan dari APP_ENV:
     * PRODUKSI KAMI BERJALAN DENGAN `APP_ENV=local` (temuan 2026-08-06, belum
     * dibereskan karena mengubahnya bisa menggeser perilaku lain). Menebak
     * "local berarti dev" akan MENYALAKAN penghapusan permanen data uang di
     * produksi — persis kebalikan dari maksudnya.
     *
     * Aturan umumnya: fitur destruktif memakai daftar-putih (harus dinyalakan),
     * bukan daftar-hitam (mati kalau kebetulan terdeteksi produksi). Lingkungan
     * yang salah label lalu jadi aman, bukan terbuka.
     *
     * Dinyalakan di dev lewat .env: PETTY_CASH_RESET_ENABLED=true
     */
    'petty_cash_reset' => (bool) env('PETTY_CASH_RESET_ENABLED', false),
];
