<?php

namespace App\Services\Ortho;

/**
 * Aturan membaca item ortho dari cermin rekam medis (omnicare_sales_detail).
 *
 * ════════════════════════════════════════════════════════════════════════════
 * ⚠ SELURUH pengetahuan tentang nama item ortho hidup DI SINI, dan tidak boleh
 *   bocor ke tempat lain. Kalau ada `LIKE '%bracket%'` muncul di controller,
 *   command, atau kueri mana pun, itu bug — pindahkan ke sini.
 * ════════════════════════════════════════════════════════════════════════════
 *
 * JEBAKAN YANG JADI ALASAN KELAS INI ADA
 * --------------------------------------
 * Dua nama item ini beda arti sejauh langit dan bumi, dan keduanya mengandung
 * kata "Bracket" + "Lepas":
 *
 *   "Lepas Bracket"       → perawatan SELESAI. Behel dicopot untuk selamanya.
 *   "Bracket Metal Lepas" → bracket COPOT sendiri lalu dipasang ulang.
 *                           Perawatan justru masih berjalan. (1.396 + 175 baris)
 *
 * Mencocokkan dengan `str_contains($nama, 'lepas')` akan menandai ribuan pasien
 * selesai di hari pertama. Pembedanya adalah POSISI: "Lepas" sebagai kata
 * PEMBUKA berarti tindakan melepas; "Lepas" di belakang berarti sifat barang
 * yang copot. Karena itu pola akhir perawatan DIJANGKARKAN di awal string
 * (`^lepas\s+bracket`) dan tidak pernah memakai pencarian bebas.
 *
 * DUA BAHASA, BUKAN SATU
 * ----------------------
 * Katalog Omnicare mayoritas berbahasa INGGRIS — contoh di brief semuanya
 * begitu: "Control Braces Metal", "Opening Cinere - Metal Braces",
 * "Signature Metal Braces". Versi pertama aturan ini ditulis sepenuhnya dengan
 * kosakata Indonesia, dan akibatnya 9 dari 14 nama contoh di brief TIDAK
 * terbaca sebagai ortho sama sekali — termasuk jaring terakhir yang justru ada
 * untuk mencegah kehilangan. Kata `braces` dan `control` karena itu wajib ada
 * di setiap pola, berdampingan dengan padanan Indonesianya.
 *
 * Yang SENGAJA tidak dilakukan:
 *   • POLA_PASANG tidak dilonggarkan jadi "apa pun yang mengandung Metal
 *     Braces". "Control Braces Metal" juga mengandungnya; melonggarkannya
 *     membuat kunjungan kontrol terbaca sebagai pemasangan dan merusak
 *     tanggal pasang setiap pasien. Nama kampanye promo ("Opening Cinere -
 *     Metal Braces") cukup dikenali sebagai ORTHO lewat jaring terakhir;
 *     penentuan tanggal pasang adalah tugas penyemai lewat aturan posisi
 *     brief §6.1 ("transaksi ortho PERTAMA yang bukan Control/Kawat/Lepas").
 *   • Kata kerja pelepasan versi Inggris (Remove, Debonding) TIDAK dikarang —
 *     tidak ada buktinya di brief. Kalau ternyata ada di katalog nyata,
 *     akibatnya perawatan tidak pernah ditandai selesai: kegagalan yang AMAN,
 *     kebalikan dari salah menandai selesai. `--daftar-item` akan
 *     memperlihatkannya sebagai baris `kontrol` yang jelas-jelas pelepasan.
 *
 * CARA MEMVERIFIKASI SEBELUM PERCAYA
 * ----------------------------------
 * Aturan di bawah disusun dari brief konsultan, BUKAN dari katalog item
 * sungguhan — cermin Omnicare kosong di lingkungan pengembangan. Sebelum
 * `ort:pull-visits` dipercaya menulis apa pun di produksi, jalankan:
 *
 *     php artisan ort:pull-visits --daftar-item
 *
 * Perintah itu tidak menulis apa pun. Ia mencetak setiap nama item yang benar-
 * benar ada beserta klasifikasinya, supaya salah-baca ketahuan oleh mata
 * manusia sebelum menyentuh satu baris pun.
 */
final class OrtItemRules
{
    /** Hasil klasifikasi. */
    public const PASANG   = 'pasang';    // pemasangan behel — awal perawatan
    public const LEPAS    = 'lepas';     // pelepasan behel — akhir perawatan
    public const KONTROL  = 'kontrol';   // kunjungan kontrol rutin
    public const PENYERTA = 'penyerta';  // barang/aksesori yang menyertai kunjungan

    /**
     * Akhir perawatan. DIJANGKARKAN DI AWAL — ini satu-satunya hal yang
     * memisahkannya dari "Bracket Metal Lepas". Jangan pernah dilonggarkan
     * jadi pencarian bebas.
     */
    private const POLA_LEPAS = '/^\s*(lepas|pelepasan|buka)\s+(bracket|behel|braket|braces)\b/iu';

    /**
     * Pengecualian tegas. Diperiksa DULUAN, sebelum pola apa pun. Ini jaring
     * pengaman berlapis: seandainya POLA_LEPAS suatu hari dilonggarkan oleh
     * orang yang tidak membaca komentar di atas, daftar ini masih menahannya.
     */
    private const BUKAN_AKHIR_PERAWATAN = [
        '/(bracket|braces)\s+(metal|ceramic|keramik|sapphire|stellar|self\s*ligating)\s+lepas/iu',
        '/(bracket|braces)\s+lepas\s+(pasang|ulang)/iu',
        '/pasang\s+ulang\s+(bracket|braces)/iu',
    ];

    /**
     * Paket lepas behel — "Freedom 1/2/3" (Rp 3,9–5,9 jt: lepas behel atas &
     * bawah + scaling + retainer). Ini akhir perawatan, sama seperti "Lepas
     * Bracket", dan keduanya masih dipakai berdampingan.
     *
     * Dikenali lewat NAMA, bukan kode. Prefix PK/PAK (Pendapatan Paket)
     * memuat 17 item paket, dan repo tidak punya bukti isi paket selain
     * Freedom — memakai prefix berarti menandai selesai paket apa pun. Salah
     * menandai selesai adalah risiko nomor satu modul ini. Nama "Freedom" khas,
     * dan pemilik produk memastikan semua variannya berarti lepas behel; jadi
     * "Freedom 4" nanti ikut terbaca benar tanpa perubahan kode.
     */
    private const POLA_PAKET_LEPAS = '/^\s*freedom\b/iu';

    /** Awal perawatan — kata kerja eksplisit di depan. */
    private const POLA_PASANG = [
        '/^\s*pasang\s+(bracket|behel|braket|braces|ortho)\b/iu',
        '/^\s*(bracket|behel|braket|braces)\s+(atas|bawah|rahang)\b/iu',
        '/^\s*pemasangan\s+(bracket|behel|braket|braces|ortho)\b/iu',
    ];

    /**
     * Produk braces bernama — cara katalog Omnicare menamai PEMASANGAN:
     * "Signature Metal Braces", "Payday — Selfligating Metal Braces",
     * "Stellar Ceramic Braces Spesialis", "NUME NEW YEAR - METAL BRACES".
     * Tidak ada kata kerja; yang ada jenis bracket + kata braces/behel.
     *
     * Wajib menuntut KEDUANYA (jenis + braces), supaya "Kawat Braces" atau
     * "Ortho Brush" tidak ikut. Diperiksa SESUDAH POLA_KONTROL — "Control
     * Braces Metal" juga memuat keduanya — dan SEBELUM POLA_PENYERTA, karena
     * "Deluxe Metal Braces SP Free Orthokit" adalah pemasangan yang kebetulan
     * menyebut orthokit; versi lama membacanya sebagai aksesori.
     */
    private const POLA_PRODUK_BRACES = [
        '/\b(metal|ceramic|keramik|sapphire|stellar|self\s*ligating|selfligating|clear|damon)\b.*\b(braces|behel)\b/iu',
        '/\b(braces|behel)\b.*\b(metal|ceramic|keramik|sapphire|stellar|self\s*ligating|selfligating|clear|damon)\b/iu',
    ];

    /** Kunjungan kontrol rutin. */
    private const POLA_KONTROL = [
        // Lampiran A brief menuliskannya harfiah: "diawali Control +
        // mengandung Braces atau Ortho". Katalog Omnicare mayoritas Inggris
        // ("Control Braces Metal"), jadi `control` dan `braces` WAJIB ada di
        // sini — bukan cuma `kontrol` dan `bracket`.
        '/\b(kontrol|control)\s+(ortho|orto|behel|bracket|braket|braces)\b/iu',
        '/^\s*(kontrol|control)\b.*\b(braces|bracket|ortho|orto|behel)\b/iu',
        '/\b(ortho|orto)\s+(kontrol|control)\b/iu',
        '/^\s*(kontrol|control)\s+(rutin\s+)?(ortho|orto|behel)\b/iu',
        '/\badjustment\s+(bracket|behel|braces|ortho)\b/iu',
        // Nyata di katalog (26CON192, 22 qty/bulan) — tanpa kata braces/ortho.
        '/\b(kontrol|control)\s+self\s*ligating\b/iu',
        // Nyata (CHADEN-001): "Control" di akhir, bukan di depan.
        '/\bchange\s+dentist\s+control\b/iu',
    ];

    /**
     * Barang penyerta — sering muncul di tanggal yang SAMA dengan kontrol.
     * Tidak pernah dihitung sebagai kunjungan terpisah (lihat dedup per
     * tanggal di OrtVisitMatcher/ort:pull-visits).
     */
    private const POLA_PENYERTA = [
        '/^\s*kawat\b/iu',
        '/\borthokit\b/iu',
        '/\bortho\s*brush\b/iu',
        '/\bmolar\s+(bracket|tube)\b/iu',
        '/\bpower\s*(chain|o)\b/iu',
        '/\bmini\s*screw\b/iu',
        '/\bortho\s*wax\b/iu',
        '/\bretainer\b/iu',
        // Nyata di katalog prefix PO (118 item — kelompok terbanyak):
        '/\bligature\b/iu',
        '/\bcoil\s*spring\b/iu',
        '/\belastic\b/iu',
        '/\bbuc+al\s+tube\b/iu',
        '/\binterdental\b/iu',
        '/\bwax\b/iu',
    ];

    /**
     * Barang retail yang namanya kebetulan menyebut "ortho". Tanpa veto ini,
     * "Pasta Gigi Gum Ortho" lolos jaring terakhir dan menjadi KUNJUNGAN
     * KONTROL HANTU. Diperiksa sebelum jaring terakhir, sesudah semua pola.
     */
    private const POLA_RETAIL = '/\b(pasta\s+gigi|sikat\s+gigi|toothpaste|toothbrush|tongue\s+cleaner|dr\.?\s*brush|mouthwash|floss)\b/iu';

    /**
     * Penanda "ini urusan ortho" untuk nama yang tidak tertangkap pola mana
     * pun. Dipakai sebagai jaring terakhir supaya kunjungan tidak hilang cuma
     * karena namanya tidak lazim — hasilnya KONTROL, pilihan paling aman:
     * salah menghitung satu kunjungan jauh lebih ringan daripada salah
     * menandai perawatan selesai.
     */
    private const PETUNJUK_ORTHO = '/\b(ortho|orto|behel|bracket|braket|braces)\b/iu';

    /**
     * Klasifikasikan satu baris item. Mengembalikan null kalau bukan urusan
     * ortho sama sekali.
     *
     * $itemCode opsional: kalau ada, dipakai sebagai VETO keras (kategori yang
     * pasti bukan ortho) dan sebagai sinyal sesudah aturan nama eksplisit.
     * Pemanggil lama yang cuma mengoper nama tetap jalan seperti sebelumnya.
     *
     * URUTAN PEMERIKSAAN TIDAK BOLEH DIUBAH:
     *   0. veto kode         — scaling/tambal/cabut/... tak pernah jadi ortho
     *   1. pengecualian akhir-perawatan   (paling berbahaya kalau salah)
     *   2. paket lepas (Freedom)
     *   3. akhir perawatan
     *   4. awal perawatan (kata kerja eksplisit)
     *   5. kontrol eksplisit
     *   6. produk braces bernama → pasang   (sesudah kontrol, sebelum penyerta)
     *   7. penyerta
     *   8. prefix kode                       (CON / OT / OR / PO / PM / PRO)
     *   9. veto retail, lalu jaring terakhir
     */
    public function klasifikasi(?string $itemName, ?string $itemExtra = null, ?string $itemCode = null): ?string
    {
        $nama = trim((string) $itemName . ' ' . (string) $itemExtra);
        if ($nama === '') {
            return null;
        }

        // 0. Kategori katalog yang pasti bukan ortho — tervalidasi Accurate.
        if (OrtKodeItem::bukanOrtho($itemCode)) {
            return null;
        }

        // 1.
        foreach (self::BUKAN_AKHIR_PERAWATAN as $pola) {
            if (preg_match($pola, $nama)) {
                return $this->cocokSalahSatu(self::POLA_PENYERTA, $nama) ? self::PENYERTA : self::KONTROL;
            }
        }

        // 2–3.
        if (preg_match(self::POLA_PAKET_LEPAS, $nama) || preg_match(self::POLA_LEPAS, $nama)) {
            return self::LEPAS;
        }

        // 4–7.
        if ($this->cocokSalahSatu(self::POLA_PASANG, $nama)) {
            return self::PASANG;
        }
        if ($this->cocokSalahSatu(self::POLA_KONTROL, $nama)) {
            return self::KONTROL;
        }
        if ($this->cocokSalahSatu(self::POLA_PRODUK_BRACES, $nama)) {
            return self::PASANG;
        }
        if ($this->cocokSalahSatu(self::POLA_PENYERTA, $nama)) {
            return self::PENYERTA;
        }

        // 8. Prefix kode — untuk nama yang tak tertangkap pola mana pun.
        switch (OrtKodeItem::peran($itemCode)) {
            case OrtKodeItem::KONTROL:
                return self::KONTROL;
            case OrtKodeItem::PASANG:
                return self::PASANG;
            case OrtKodeItem::CAMPURAN:
                // PO: kontrol kalau namanya bilang begitu, selebihnya aksesori.
                return preg_match('/\b(kontrol|control)\b/iu', $nama) ? self::KONTROL : self::PENYERTA;
            case OrtKodeItem::PROMO:
                // PM/PRO: promo apa saja (scaling, whitening...). Pasang hanya
                // kalau namanya memang produk behel.
                return preg_match(self::PETUNJUK_ORTHO, $nama) ? self::PASANG : null;
            case OrtKodeItem::PAKET:
                // PK/PAK yang BUKAN Freedom (sudah ditangani di langkah 2):
                // paket lain — tidak disimpulkan apa-apa.
                return null;
        }

        // 9. Barang retail ber-"ortho" bukan kunjungan.
        if (preg_match(self::POLA_RETAIL, $nama)) {
            return null;
        }

        return preg_match(self::PETUNJUK_ORTHO, $nama) ? self::KONTROL : null;
    }

    public function adalahOrtho(?string $itemName, ?string $itemExtra = null): bool
    {
        return $this->klasifikasi($itemName, $itemExtra) !== null;
    }

    /** Apakah item ini menandai perawatan selesai (behel dilepas untuk selamanya)? */
    public function isLepasBracket(?string $itemName, ?string $itemExtra = null): bool
    {
        return $this->klasifikasi($itemName, $itemExtra) === self::LEPAS;
    }

    public function isPasang(?string $itemName, ?string $itemExtra = null): bool
    {
        return $this->klasifikasi($itemName, $itemExtra) === self::PASANG;
    }

    public function isKontrol(?string $itemName, ?string $itemExtra = null): bool
    {
        return $this->klasifikasi($itemName, $itemExtra) === self::KONTROL;
    }

    public function isPenyerta(?string $itemName, ?string $itemExtra = null): bool
    {
        return $this->klasifikasi($itemName, $itemExtra) === self::PENYERTA;
    }

    /**
     * Fixture pembukti — pasangan nama item dan klasifikasi yang DIHARAPKAN.
     *
     * Dipakai `ort:pull-visits --uji-aturan`. Ditaruh di kelas ini, bukan di
     * berkas uji terpisah, supaya siapa pun yang mengubah polanya di atas
     * melihat langsung apa yang akan pecah.
     */
    /**
     * Jenis bracket dari nama item — untuk kolom `ort_case.bracket_type`.
     *
     * Dipakai penyemai (`ort:seed-cases`). Ada DI SINI, bukan di penyemai,
     * karena ini pengetahuan tentang nama item: berkas ini satu-satunya tempat
     * pola nama boleh hidup (lihat catatan kepala kelas).
     *
     * Berguna bukan cuma untuk item pemasangan — nama kunjungan kontrol pun
     * menyebutkannya ("Control Metal Braces" → Metal), jadi pasien yang
     * tanggal pasangnya harus diperkirakan tetap bisa diketahui jenis
     * bracketnya.
     *
     * URUTAN PEMERIKSAAN PENTING: "Payday — Selfligating Metal Braces" memuat
     * `metal` DAN `self ligating`. Yang lebih khusus diperiksa lebih dulu,
     * kalau tidak semuanya jatuh jadi "Metal".
     */
    public static function jenisBracket(?string $nama): ?string
    {
        $n = (string) $nama;

        return match (true) {
            (bool) preg_match('/\bdamon\b/iu', $n)                        => 'Damon Quantum',
            (bool) preg_match('/\bself\s*ligating|selfligating\b/iu', $n) => 'Self Ligating',
            (bool) preg_match('/\bsapphire\b/iu', $n)                     => 'Sapphire',
            (bool) preg_match('/\b(ceramic|keramik)\b/iu', $n)            => 'Ceramic',
            (bool) preg_match('/\bmetal\b/iu', $n)                        => 'Metal',
            default                                                        => null,
        };
    }

    /**
     * Rahang yang dipasangi — `ort_case.bracket_arch` (atas|bawah|keduanya).
     *
     * Dikembalikan null kalau nama item tidak menyebutnya sama sekali, yang
     * merupakan mayoritas katalog. null lebih jujur daripada menebak
     * "keduanya": kolom kosong terbaca sebagai "tidak tahu", tebakan terbaca
     * sebagai fakta.
     */
    public static function rahang(?string $nama): ?string
    {
        $n = (string) $nama;

        if (preg_match('/\b(2|dua)\s*rahang\b|\bkeduanya\b|\batas\b.*\bbawah\b|\bbawah\b.*\batas\b|\bupper\b.*\blower\b/iu', $n)) {
            return 'keduanya';
        }
        if (preg_match('/\b(atas|upper)\b/iu', $n)) {
            return 'atas';
        }
        if (preg_match('/\b(bawah|lower)\b/iu', $n)) {
            return 'bawah';
        }

        return null;
    }

    public static function fixture(): array
    {
        return [
            // ── Akhir perawatan (yang SEHARUSNYA menutup kasus) ──
            ['Lepas Bracket',                       self::LEPAS],
            ['Lepas Bracket Atas',                  self::LEPAS],
            ['Lepas Behel Rahang Bawah',            self::LEPAS],
            ['Pelepasan Bracket',                   self::LEPAS],

            // ── ⚠ JANGAN tertukar: bracket copot, perawatan jalan terus ──
            ['Bracket Metal Lepas',                 self::KONTROL],
            ['Bracket Ceramic Lepas',               self::KONTROL],
            ['Bracket Self Ligating Lepas',         self::KONTROL],
            ['Pasang Ulang Bracket',                self::KONTROL],

            // ── Awal perawatan ──
            ['Pasang Bracket Metal',                self::PASANG],
            ['Pasang Behel Atas',                   self::PASANG],
            ['Pemasangan Bracket Ceramic',          self::PASANG],

            // ── Kontrol rutin ──
            ['Kontrol Ortho',                       self::KONTROL],
            ['Kontrol Behel Bulanan',               self::KONTROL],
            ['Ortho Kontrol',                       self::KONTROL],

            // ── Penyerta: tidak pernah jadi kunjungan terpisah ──
            ['Kawat Ortho 0.016',                   self::PENYERTA],
            ['Orthokit',                            self::PENYERTA],
            ['Ortho Brush',                         self::PENYERTA],
            ['Molar Bracket',                       self::PENYERTA],
            ['Power Chain',                         self::PENYERTA],
            ['Retainer Atas',                       self::PENYERTA],

            // ── Bahasa INGGRIS — bentuk yang dipakai katalog Omnicare ──
            //    Semua nama di blok ini diambil APA ADANYA dari brief
            //    konsultan (Lampiran A & §6.1), termasuk salah ketik
            //    "Siganture" yang memang ada di sana. Sebelum `braces` dan
            //    `control` ditambahkan, SEMBILAN dari nama-nama ini terbaca
            //    "bukan ortho" — termasuk oleh jaring terakhir.
            ['Control Braces Metal',                self::KONTROL],
            ['Control Braces Ceramic',              self::KONTROL],
            ['Control Braces Sapphire',             self::KONTROL],
            ['Control Braces Self Ligating',        self::KONTROL],
            ['Control Braces Stellar',              self::KONTROL],
            ['Control Ortho',                       self::KONTROL],

            //    Nama kampanye promo = PEMASANGAN (POLA_PRODUK_BRACES: jenis
            //    bracket + kata braces). Ini sinyal tanggal pasang untuk
            //    penyemai — jauh lebih baik daripada menebak dari posisi.
            ['Opening Cinere - Metal Braces',       self::PASANG],
            ['Signature Metal Braces (px an Vabbya)', self::PASANG],
            ['Cimulang Oktober - Siganture Metal Braces Gp', self::PASANG],

            //    Jebakan yang sama, ejaan Inggris. Ini HARUS tetap kontrol.
            ['Braces Metal Lepas',                  self::KONTROL],
            ['Lepas Braces',                        self::LEPAS],
            ['Pasang Braces Metal',                 self::PASANG],

            // ── Bukan urusan ortho sama sekali ──
            ['Scaling',                             null],
            ['Tambal Komposit',                     null],
            ['Cabut Gigi Susu',                     null],
            ['Konsultasi Dokter Umum',              null],
            ['Metal Crown',                         null],   // 'metal' saja bukan ortho

            // ── NAMA NYATA dari arsip produksi, dengan KODE ──
            //    Sumber: docs/arsip/laporan-lama/audit-sales-mar-2026.md dan
            //    docs/arsip/accurate/mapping-akun-accurate.csv (tervalidasi
            //    terhadap faktur Accurate). Elemen ketiga = item_code.
            //    Sebelum blok ini ada, 8 dari 11 nama berkode salah dibaca.
            ['Freedom 2',                                    self::LEPAS,    '26PK167'],   // yang diminta
            ['Freedom 1',                                    self::LEPAS,    '26PK167'],
            ['Freedom 3',                                    self::LEPAS,    null],        // belum pernah terlihat di data
            ['Control Metal Braces',                         self::KONTROL,  '26CON178'],  // item terbanyak se-Bintaro
            ['Control Metal Braces Spesialis',               self::KONTROL,  '26CON185'],
            ['Control Self Ligating',                        self::KONTROL,  '26CON192'],  // dulu: BUKAN ORTHO
            ['Control Ortho Metal',                          self::KONTROL,  '26PO001'],
            ['Change Dentist Control',                       self::KONTROL,  'CHADEN-001'],
            ['Payday — Selfligating Metal Braces',           self::PASANG,   '25PM260'],   // dulu: kontrol
            ['WAR PROMO 8.8 — Signature Metal Braces',       self::PASANG,   '25PM145'],
            ['MARET — Deluxe Metal Braces SP Free Orthokit', self::PASANG,   null],        // dulu: PENYERTA (orthokit menang)
            ['Pemasangan ortho metal - drg fri',             self::PASANG,   '25PM016'],   // dulu: kontrol
            ['Stellar Ceramic Braces Spesialis',             self::PASANG,   '26OR001'],
            ['NUME NEW YEAR - METAL BRACES',                 self::PASANG,   'NUME-001'],  // kode gaya lain → pengurai gagal anggun
            ['Ligature',                                     self::PENYERTA, '26PO262'],   // dulu: BUKAN ORTHO
            ['Open Coil Spring',                             self::PENYERTA, '26PO265'],
            ['Kawat Self Ligating',                          self::PENYERTA, '26PO226'],
            ['Elastic 5/16',                                 self::PENYERTA, 'ELASTI-001'],
            ['Bucal Tube Baru',                              self::PENYERTA, null],
            ['Tempat Retainer',                              self::PENYERTA, 'TR'],
            // Veto kode: kategori pasti bukan ortho, walau namanya apa pun.
            ['Scaling kls 3',                                null,           '26SC264'],
            ['Perawatan Saluran Akar Posterior 1 Saluran',   null,           '25SA250'],
            ['Pencabutan Gigi Permanen',                     null,           '26CB070'],
            // Kunjungan hantu: retail ber-"ortho" TIDAK boleh jadi kontrol.
            ['Pasta Gigi Gum Ortho',                         null,           'PGGO'],
            // Paket PK yang BUKAN Freedom tidak boleh menutup perawatan.
            ['Paket Scaling Keluarga',                       null,           '26PK150'],
            // Promo non-behel (PM) bukan ortho.
            ['APRIL — WHITENING',                            null,           '26PM300'],
        ];
    }

    private function cocokSalahSatu(array $polaList, string $nama): bool
    {
        foreach ($polaList as $pola) {
            if (preg_match($pola, $nama)) {
                return true;
            }
        }

        return false;
    }
}
