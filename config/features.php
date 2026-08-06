<?php

return [
    /*
     * Per-doctor duration override + schedule exceptions (Jadwal Khusus).
     * Set to true once management approves.
     */
    'flexible_scheduling' => true,

    /*
     * Tombol Reset Petty Cash (hard-delete jejak transaksi & riwayat review) —
     * alat bersih-bersih data demo. Default HANYA hidup di dev; di prod mati.
     * Override eksplisit lewat .env PETTY_CASH_RESET_ENABLED bila perlu.
     */
    'petty_cash_reset' => env('PETTY_CASH_RESET_ENABLED', env('APP_ENV') === 'local'),
];
