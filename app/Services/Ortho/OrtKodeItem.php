<?php

namespace App\Services\Ortho;

/**
 * Pembaca KODE item Omnicare (`omnicare_sales_detail.item_code`), untuk
 * modul Tracking Ortho.
 *
 * KENAPA ADA
 * ----------
 * Nama item Omnicare tidak punya pola baku — brief §6.1 menghitung 132 nama
 * berbeda hanya untuk pemasangan, dan promo lahir tiap bulan dengan nama
 * kampanye ("Payday — Selfligating Metal Braces", "MARET — Deluxe …").
 * Kodenya jauh lebih tenang: prefix hurufnya adalah KATEGORI katalog, dan
 * kategori itu sudah dipetakan + diverifikasi terhadap faktur Accurate yang
 * nyata (`app/Console/Commands/AccuratePushSample.php:40-45`,
 * `docs/arsip/accurate/mapping-akun-accurate.csv`). Kelas ini memakai ulang
 * pengurai dan pengetahuan itu, bukan mengarang ulang.
 *
 * BENTUK KODE — dan kenapa cuma PREFIX yang dipercaya
 * ----------------------------------------------------
 *   26CON178   YY + PREFIX + NNN     ← bentuk utama
 *   PARADE-001 MNEMONIK + NNN        ← promo; pengurai gagal dengan anggun
 *   (kosong)                         ← 37 item promo tanpa kode sama sekali
 *
 * Yang TIDAK stabil, terbukti dari arsip:
 *   • YY berganti tiap katalog: 25PO252 → 26PO262 (tahun katalog, bukan
 *     tahun transaksi). Jadi YY selalu wildcard.
 *   • Nomor seri bergeser tak beraturan saat renumbering (+0, +1, +10) —
 *     kode lama → baru TIDAK bisa diturunkan algoritmis.
 *   • Nama yang sama punya kode beda antar cabang: 25PM260 (Bintaro) vs
 *     25PM250 (Sunter) sama-sama "Payday — Selfligating Metal Braces".
 *   Satu-satunya yang invarian adalah PREFIX. Hanya itu yang dibaca di sini.
 *
 * Kode adalah sinyal SESUDAH aturan nama eksplisit, bukan sebelum — karena
 * 37 item tidak punya kode, dan karena OrtItemRules sudah punya pengecualian
 * yang lebih tajam ("Bracket Metal Lepas" bukan pelepasan). Urutan yang sama
 * dipakai AccuratePushSample::resolve(): nama persis → aturan nama → prefix
 * kode → cadangan.
 */
final class OrtKodeItem
{
    /** Peran ortho per prefix. Nilai 'campuran'/'promo'/'paket' berarti NAMA yang memutuskan. */
    public const KONTROL  = 'kontrol';
    public const PASANG   = 'pasang';
    public const CAMPURAN = 'campuran';   // PO: "Control Ortho Metal" ATAU "Kawat Ortho" — lihat namanya
    public const PROMO    = 'promo';      // PM/PRO: pasang HANYA kalau nama menyebut braces/behel
    public const PAKET    = 'paket';      // PK/PAK: 17 item paket; lepas HANYA kalau nama Freedom

    /**
     * Prefix → peran. Sumber: mapping-akun-accurate.csv (✅ TERVERIFIKASI ACCURATE).
     *
     * ⚠ PO BUKAN pemasangan. Komentar lama di SalesReportController.php:239
     *   ("PO/OT=pasang ortho") keliru — contoh nyata prefix PO adalah "Control
     *   Ortho Metal", "Kawat Ortho", Ligature, Open Coil Spring. PO = kontrol
     *   + aksesori. Yang pemasangan adalah OT (dan OR untuk Stellar Ceramic).
     */
    private const PERAN = [
        'CON' => self::KONTROL,
        'OT'  => self::PASANG,
        'OR'  => self::PASANG,
        'PO'  => self::CAMPURAN,
        'PM'  => self::PROMO,
        'PRO' => self::PROMO,
        'PK'  => self::PAKET,
        'PAK' => self::PAKET,
    ];

    /**
     * Kategori yang PASTI bukan ortho. Dipakai sebagai veto keras: nama apa
     * pun di bawah prefix ini tidak boleh lolos ke jaring terakhir OrtItemRules
     * hanya karena mengandung kata "ortho" (mis. "Pasta Gigi Gum Ortho").
     *
     * GP (Dentures / cetak alginate) SENGAJA tidak ada di sini — cetak rahang
     * juga dipakai ortho untuk retainer, dan brief menandainya ambigu.
     */
    private const BUKAN_ORTHO = [
        'SC', 'SCA',   // scaling
        'SA', 'PSA',   // saluran akar
        'TB', 'PB',    // tambal
        'CB', 'PC',    // pencabutan
        'OD',          // odontectomy
        'PL',          // rontgen / other standard
        'BC',          // bleaching
        'KS',          // konsultasi
        'OB', 'F',     // obat
    ];

    /**
     * Prefix huruf dari sebuah kode, atau null kalau bentuknya tidak dikenal.
     * Regex ini SAMA PERSIS dengan AccuratePushSample::resolve() — tahun
     * opsional, prefix panjang bebas (2 atau 3 huruf sama-sama ada).
     */
    public static function prefix(?string $kode): ?string
    {
        $kode = strtoupper(trim((string) $kode));
        if ($kode === '') {
            return null;
        }

        return preg_match('/^\d{0,2}([A-Z]+)\d+/', $kode, $m) ? $m[1] : null;
    }

    public static function peran(?string $kode): ?string
    {
        $p = self::prefix($kode);

        return $p !== null ? (self::PERAN[$p] ?? null) : null;
    }

    public static function bukanOrtho(?string $kode): bool
    {
        $p = self::prefix($kode);

        return $p !== null && in_array($p, self::BUKAN_ORTHO, true);
    }
}
