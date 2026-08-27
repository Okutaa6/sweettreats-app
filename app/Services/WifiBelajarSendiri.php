<?php

namespace App\Services;

use App\Models\AttBranchIp;
use App\Models\AttHpBukti;
use App\Models\AdminNotification;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * WiFi Cabang belajar sendiri — mendaftarkan jaringan cabang secara otomatis
 * ketika cukup banyak orang cabang itu terbukti memakainya.
 *
 * DUA JALUR, jangan tertukar:
 *   · IPv6 → didaftarkan sebagai PREFIX /64, dipagari kekerabatan /48
 *   · IPv4 → didaftarkan COCOK-PENUH, tanpa pagar blok (sejak 27 Agu 2026)
 *
 * ⚠️ Docblock ini pernah berbunyi "IPv4 tak pernah lewat sini", dan kalimat itu
 * bertahan berbulan-bulan sesudah asumsinya patah — itulah yang membuat
 * Kalideres nol baris IPv4 tanpa ada yang menyadarinya. Kalau salah satu jalur
 * berubah lagi, ubah kalimat ini di baris yang sama dengan kodenya.
 *
 * MASALAH YANG DIOBATI (docs/rencana/rencana-wifi-belajar-sendiri.md):
 * prefix IPv6 IndiHome berputar kira-kira MINGGUAN. Tiap rotasi, semua orang
 * beraturan baku di cabang itu kena flag ip_asing sampai ada manusia yang
 * sadar lalu mendaftarkan nomor barunya. Ini masalah KEBISINGAN, bukan
 * integritas — WiFi cuma saksi, tak ada yang gagal absen. Ongkosnya: waktu
 * review Anisa, dan flag jadi terbiasa merah sehingga yang asli tenggelam.
 *
 * EMPAT SYARAT JALUR IPv6 (semua wajib, §3 — sudah di-ACC, jangan
 * dilonggarkan diam-diam). Empat syarat jalur IPv4 ada di nilaiIpv4():
 *   1. >= AMBANG_ORANG orang BERBEDA memakai /64 ini (2 sejak 18 Agu 2026;
 *      dulu 3 — alasan lengkap ada di konstantanya)
 *   2. semuanya cabang EFEKTIFnya sama dengan cabang yang dipilih
 *      (penempatan/rolling menang atas cabang asal — lihat pendukung())
 *   3. dalam 72 jam terakhir
 *   4. /48-nya sekerabat dengan prefix cabang itu yang sudah terdaftar
 *
 * Syarat 1-3 terpenuhi tapi 4 tidak (mis. cabang baru yang belum punya prefix
 * apa pun) → JANGAN otomatis, tawarkan sekali klik ke IT.
 *
 * ⚠️ SEJAK 27 AGU 2026 — IPv4 IKUT DIPELAJARI, TAPI COCOK-PENUH.
 * (docs/rencana/rencana-wifi-ipv4-belajar.md)
 *
 * Dulu IPv4 sengaja tak pernah lewat sini, dengan alasan "IPv4 cabang bertahan
 * berhari-hari melewati rotasi, jadi cukup didaftarkan manual sekali". Alasan
 * itu benar; yang tak diantisipasi: sebuah cabang bisa TAK PERNAH KEBAGIAN
 * pendaftaran manual itu sama sekali. Kalideres punya NOL baris IPv4 sampai
 * 23 Agu 2026 — 13 absen dalam 3 minggu harus pilih cabang manual, dan tak ada
 * yang berteriak. Cinere juga nol. Jatuh di antara dua jalur: belajar-sendiri
 * melewatinya, pencocokan /48 cuma untuk IPv6.
 *
 * Jalur IPv4 memakai AMBANG DAN JENDELA YANG SAMA (2 orang / 72 jam) dan saksi
 * yang dinilai dengan pendukung() yang sama, tapi hasilnya baris COCOK-PENUH,
 * bukan prefix. Nol pelebaran: 180.252.169.219 tetap ditolak walau .218
 * terdaftar (dikunci K14).
 *
 * ⚠️ JANGAN MELEBARKAN IPv4 ke /24 atau apa pun. Bukan cuma berisiko — TAK AKAN
 * MENOLONG: alamat satu cabang melompat antar blok yang tak berkerabat sama
 * sekali (Kalideres memakai 36.77.251/252.x DAN 180.242.129–131.x dalam periode
 * yang sama). Tak ada satu /24 pun yang menampung keduanya.
 *
 * ⚠️ LARANGAN LAMA "jangan daftarkan IP seluler" TIDAK dicabut — cara
 * membuktikannya yang berubah. Dulu dibuktikan dari BENTUK alamat (v4 = curiga).
 * Itu terbukti keliru: absen ber-IPv4 di Kalideres sempat disimpulkan "orang
 * pakai data seluler", lalu Ekky bertanya langsung ke Cici — dia memakai WiFi
 * klinik, dan datanya membenarkan Cici (36.77.252.85 dipakai EMPAT orang
 * berbeda dalam 5 hari, semuanya memilih Kalideres). Sekarang dibuktikan dari
 * PERILAKU: data seluler pribadi memberi tiap orang alamat sendiri, jadi satu
 * alamat yang dipakai >= AMBANG_ORANG orang cabang yang sama adalah jaringan
 * bersama. Bukti yang sah = berapa orang berbeda memakainya, bukan bloknya.
 *
 * SISA RISIKO YANG DITERIMA SADAR: kalau CGNAT seluler benar-benar memberi
 * alamat yang sama kepada dua staf cabang yang sama dalam 72 jam, alamat itu
 * akan terdaftar. Karena IPv4 tak punya pagar kekerabatan /48 yang menjaminnya,
 * tiap baris IPv4 otomatis WAJIB berisik: notifikasi IT + label "perlu
 * ditinjau" + AuditLog (lihat perluTinjau()). Terdaftar tapi kelihatan.
 *
 * KENAPA SATU ORANG TAK PERNAH CUKUP: satu orang tak bisa dibedakan dari WiFi
 * rumahnya sendiri. Kasus Kalideres (Rizka, 6-7 Agu) membuktikannya — waktu itu
 * penyelesaiannya lewat memeriksa foto selfie, bukan lewat jumlah.
 *
 * ⚠️ SEJAK 21 AGU 2026 — VONIS "DAFTAR" PRAKTIS TAK PERNAH TERBIT LAGI.
 * AttBranchIp::matchBranch kini mencocokkan /48, bukan /64. Akibatnya /64 baru
 * yang /48-nya sekerabat sudah cocok dengan sendirinya, sehingga
 * AttendanceHpAbsen tak pernah lagi memanggil pelajari() untuk kasus itu — dan
 * syarat 4 (kekerabatan /48) memang syarat yang sama yang membuat pencocokan
 * berhasil. Jalur DAFTAR tersubsumsi: yang dulu menunggu 2 saksi dalam 72 jam
 * sekarang dikenali seketika.
 *
 * Kode DAFTAR/daftarkan() SENGAJA DIPERTAHANKAN — kalau pelebaran /48 suatu
 * saat harus dibatalkan, mekanismenya harus masih utuh. Yang hidup sehari-hari
 * sekarang adalah TAWARKAN: /48 yang asing sama sekali, dipakai ramai-ramai.
 */
class WifiBelajarSendiri
{
    /**
     * Berapa orang berbeda yang harus terbukti memakai satu /64.
     *
     * DITURUNKAN 3 → 2 pada 2026-08-18 (ACC Ekky), setelah angka 3 terbukti
     * TAK TERJANGKAU bagi sebagian cabang. Calon saksi = staf asal ber-aturan
     * `baku`; orang fleksibel/ABG memang tak dihitung (lihat pendukung()):
     *
     *     Pamulang  2 orang → ambang 3 mustahil sendirian
     *     Cinere    3 orang → butuh 100% staf, dalam 72 jam
     *     Kalideres 4 orang → butuh 3 dari 4
     *     Bintaro/Sunter 5  → lega
     *
     * Jadi untuk tiga dari lima cabang, ambangnya sama atau lebih tinggi dari
     * jumlah orangnya — mesin dirancang gagal di sana, dan sisanya jatuh ke
     * tangan manusia. Terbukti 18 Agu: Telkom memutar prefix di semua cabang,
     * 16 orang ke-flag dalam sehari; tiga jaringan berhasil mendaftar sendiri
     * tapi DUA mentok di 2-dari-3 dan harus didaftarkan tangan
     * (Kalideres `90b0:58`, Bintaro jalur-2 `2092:270e`).
     *
     * ⚠️ YANG MENJAGA KEAMANAN BUKAN ANGKA INI, tapi pagar kekerabatan /48 —
     * tiap cabang punya blok Telkom sendiri, jadi /64 baru di dalam blok cabang
     * itu hampir pasti gedung yang sama. Dua orang di kafe tak akan pernah
     * lolos: blok kafe bukan blok klinik. Pagar itu TIDAK diturunkan.
     *
     * KENAPA TIDAK 1: lihat catatan "satu orang tak pernah cukup" di atas —
     * satu orang tak bisa dibedakan dari WiFi rumahnya sendiri. Batas itu tetap.
     */
    public const AMBANG_ORANG = 2;

    /** Seberapa jauh ke belakang bukti dihitung. Rotasi mingguan, jadi 72 jam
     *  cukup lebar untuk menangkap satu gelombang tanpa memungut jaringan basi. */
    public const JENDELA_JAM = 72;

    /** Saklar mati — sys_setting, bisa dimatikan dari halaman WiFi Cabang (§3). */
    public const KUNCI_SAKLAR = 'wifi_belajar_sendiri';

    /**
     * Siapa yang diberi tahu saat sebuah jaringan mendaftar sendiri. Harus
     * COCOK PERSIS dengan group_name (kolom group_path.nama) — notifikasi
     * ber-target_role tidak mengenal hierarki, "Head IT" bukan bagian dari
     * "IT". Samakan dengan guard AbsensiBranchIpController: yang diberi tahu
     * harus yang bisa membuka halamannya.
     */
    public const ROLE_PENINJAU = ['Head IT', 'IT'];

    /**
     * Alasan di balik vonis DAFTAR. Dibedakan karena KONSEKUENSINYA berbeda:
     * yang IPv6 dijamin kekerabatan /48 (jalur cabang yang sudah dikenal, nomor
     * baru) sehingga tak menambah apa-apa untuk ditinjau; yang IPv4 cuma
     * dijamin perilaku saksi, jadi selalu perlu mata manusia. Lihat perluTinjau().
     */
    public const LENGKAP_V6 = 'lengkap';
    public const LENGKAP_V4 = 'lengkap_ipv4';

    // ── Vonis ────────────────────────────────────────────────────────────────
    public const DAFTAR   = 'daftar';    // empat syarat lengkap → didaftarkan
    public const TAWARKAN = 'tawarkan';  // 1-3 lengkap, /48 tak sekerabat → tanya IT
    public const TOLAK    = 'tolak';     // belum cukup bukti

    /**
     * Timbang satu alamat. MURNI — tidak menulis apa pun, aman dipanggil dari
     * perkakas uji/pengintip. Yang menulis: pelajari().
     *
     * Alasan sengaja dikembalikan sebagai kode, bukan cuma bool: kasus uji §6
     * menuntut pembedaan "ditolak karena APA" — kurang orang dan orang-cukup-
     * tapi-beda-cabang sama-sama ditolak lewat pintu yang berbeda, dan yang
     * "orang cukup tapi /48 asing" harus jadi tawaran, bukan diam.
     *
     * @return array{vonis:string, alasan:string, prefix:?string, n:int, pendukung:string[]}
     */
    public function nilai(string $ip, ?string $branchDipilih, ?Carbon $saat = null): array
    {
        $saat = $saat ? $saat->copy() : Carbon::now();
        $tolak = fn (string $alasan, ?string $prefix = null, int $n = 0, array $p = []) =>
            ['vonis' => self::TOLAK, 'alasan' => $alasan, 'prefix' => $prefix, 'n' => $n, 'pendukung' => $p];

        if (! static::aktif()) {
            return $tolak('saklar_mati');
        }

        // Tanpa cabang pilihan tak ada yang bisa dikaitkan — syarat 2 & 4 kehilangan acuan.
        if (! $branchDipilih) {
            return $tolak('tanpa_cabang');
        }

        // IPv4 — termasuk ::ffff:x.x.x.x yang menyamar sebagai IPv6 — dinilai
        // COCOK-PENUH lewat jalurnya sendiri, tak pernah lewat prefix. Yang
        // menyamar wajib ikut jalur ini: kalau tidak, "/48" dari ::ffff:0.0.0.0
        // akan menampung seluruh internet v4 dalam satu blok.
        if (($v4 = AttBranchIp::teksIpv4($ip)) !== null) {
            return $this->nilaiIpv4($v4, $branchDipilih, $saat);
        }

        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return $tolak('alamat_tak_sah');
        }

        $prefix64 = substr($bin, 0, 8);
        $prefix48 = substr($bin, 0, 6);
        $prefixTeks = $this->teksPrefix($prefix64);

        // Sudah dikenal (mungkin baru didaftarkan orang lain sedetik lalu) — diam.
        if (AttBranchIp::matchBranch($ip)) {
            return $tolak('sudah_cocok', $prefixTeks);
        }

        // Baris untuk prefix INI pernah ada lalu dinonaktifkan = IT sudah
        // melihatnya dan bilang tidak. Jangan pernah menawar ulang keputusan
        // itu, dan jangan pula mengaku "DAFTAR" padahal UNIQUE(ip) membuat
        // penulisannya tak berefek apa-apa. Pemeriksaannya sengaja seketat
        // teks prefix: baris berekor yang dinonaktifkan karena rotasi (mis.
        // 2313 lama) BUKAN veto — kalau jalur itu kembali dipakai ramai-ramai,
        // memang sudah semestinya dikenali lagi.
        if (AttBranchIp::where('ip', $prefixTeks)->where('status', 'nonaktif')->exists()) {
            return $tolak('ditolak_it', $prefixTeks);
        }

        // ── Syarat 1-3: siapa saja yang memakai /64 ini dalam 72 jam ──────────
        [$pendukung, $n] = $this->pendukung(
            fn (string $ipBukti): bool => static::sePrefix64($ipBukti, $prefix64),
            $branchDipilih,
            $saat
        );

        if ($n < self::AMBANG_ORANG) {
            return $tolak('orang_kurang', $prefixTeks, $n, $pendukung);
        }

        // ── Syarat 4: pagar kekerabatan /48 ──────────────────────────────────
        // Semua prefix satu cabang tetap di /48 yang sama melewati rotasi, dan
        // tiap cabang punya /48 berbeda (§2). Jadi kekerabatan /48 = bukti kuat
        // "gedung yang sama, nomor baru". Ini yang mencegah 3 karyawan yang
        // kebetulan absen bareng dari tempat lain (mis. pelatihan) ikut
        // terdaftar sebagai cabang.
        if (! $this->sekerabat48($prefix48, $branchDipilih)) {
            return [
                'vonis' => self::TAWARKAN, 'alasan' => 'kerabat_48_tak_ada',
                'prefix' => $prefixTeks, 'n' => $n, 'pendukung' => $pendukung,
            ];
        }

        return [
            'vonis' => self::DAFTAR, 'alasan' => self::LENGKAP_V6,
            'prefix' => $prefixTeks, 'n' => $n, 'pendukung' => $pendukung,
        ];
    }

    /**
     * Jalur IPv4 — COCOK-PENUH, nol pelebaran (§3 & §4 rencana).
     *
     * Empat syarat, urutannya sengaja: yang paling murah & paling menentukan
     * dulu, supaya jalur ini (dilewati tiap absen ber-IP tak dikenal) tak
     * menembak kueri saksi untuk alamat yang toh sudah punya baris.
     *
     *   1. >= AMBANG_ORANG orang BERBEDA memakai alamat ini dalam jendela
     *   2. semuanya memilih cabang yang SAMA — dan cabang efektif mereka
     *      memang cabang itu (pendukung() yang sama dengan IPv6)
     *   3. alamat ini TAK PERNAH dipakai memilih cabang lain sepanjang sejarah
     *   4. alamat ini BELUM terdaftar untuk cabang mana pun
     *
     * Syarat 4 diperiksa lebih dulu karena baris yang sudah ada juga membawa
     * keputusan manusia: baris NONAKTIF ber-alamat persis = IT sudah melihat
     * lalu bilang tidak, dan itu tak boleh ditawar ulang. Ini pembedaan yang
     * sama dengan veto IPv6 — bedanya IPv4 tak punya "baris berekor", satu
     * alamat cuma punya satu bentuk tertulis, jadi tak ada yang perlu dibedakan.
     */
    private function nilaiIpv4(string $alamat, string $branchDipilih, Carbon $saat): array
    {
        $tolak = fn (string $alasan, int $n = 0, array $p = []) =>
            ['vonis' => self::TOLAK, 'alasan' => $alasan, 'prefix' => $alamat, 'n' => $n, 'pendukung' => $p];

        // ── Syarat 4 ─────────────────────────────────────────────────────────
        // UNIQUE(ip) → paling banyak satu baris. Aktif = sudah dikenal (diam);
        // nonaktif = veto IT (jangan pernah didaftarkan ulang diam-diam).
        $baris = AttBranchIp::where('ip', $alamat)->first();
        if ($baris) {
            return $tolak($baris->status === 'aktif' ? 'sudah_cocok' : 'ditolak_it');
        }

        // ── Pagar tambahan: alamatnya harus benar-benar alamat INTERNET ──────
        //
        // Ditemukan saat audit 27 Agu. Kalau suatu saat aplikasi berada di
        // belakang proxy yang TrustProxies-nya belum dipasang, request()->ip()
        // berhenti menjadi alamat karyawan dan berubah jadi alamat proxy —
        // SATU alamat, SAMA untuk semua orang, sering privat (10.x) atau
        // 127.0.0.1. Dua orang cabang mana pun akan langsung memenuhi ambang,
        // alamat itu terdaftar, lalu SELURUH absen di semua cabang terbaca
        // sebagai cabang itu — dan flag ip_asing berhenti berbunyi justru saat
        // ia paling dibutuhkan. Kegagalan senyap yang sempurna.
        //
        // Ini BUKAN menebak identitas jaringan dari bentuk alamat (§5 rencana
        // melarang itu, dan benar). Ini fakta struktural: alamat privat,
        // loopback, link-local, atau ruang bersama CGNAT TIDAK BISA menjadi
        // alamat internet publik sebuah cabang dilihat dari server. Kalau ia
        // muncul, yang rusak rantai proxy-nya, bukan WiFi cabangnya.
        //
        // Sengaja hanya menahan pendaftaran OTOMATIS. Manusia tetap boleh
        // mendaftarkan apa pun lewat halaman WiFi Cabang — mis. 127.0.0.1 di
        // lingkungan uji; ia tahu apa yang sedang dikerjakannya, mesin tidak.
        //
        // Jalur IPv6 tak membutuhkan pagar ini: ULA (fc00::/7) dan link-local
        // (fe80::/10) tak akan pernah sekerabat /48 dengan blok Telkom cabang,
        // jadi syarat 4 di sana sudah menahannya lebih dulu.
        if (! static::ipv4Publik($alamat)) {
            return $tolak('alamat_tak_publik');
        }

        // ── Syarat 1-2 ───────────────────────────────────────────────────────
        [$pendukung, $n] = $this->pendukung(
            fn (string $ipBukti): bool => AttBranchIp::teksIpv4($ipBukti) === $alamat,
            $branchDipilih,
            $saat
        );

        if ($n < self::AMBANG_ORANG) {
            return $tolak('orang_kurang', $n, $pendukung);
        }

        // ── Syarat 3 ─────────────────────────────────────────────────────────
        // SEPANJANG SEJARAH, bukan cuma di dalam jendela. Alamat yang bulan lalu
        // membaca Sunter lalu minggu ini membaca Cinere bukan jaringan cabang —
        // itu alamat yang berpindah tangan (rotasi ISP, hotspot, kantor bersama),
        // dan mendaftarkannya berarti membaca cabang yang salah diam-diam.
        if ($this->pernahCabangLain($alamat, $branchDipilih)) {
            return $tolak('pernah_cabang_lain', $n, $pendukung);
        }

        return [
            'vonis' => self::DAFTAR, 'alasan' => self::LENGKAP_V4,
            'prefix' => $alamat, 'n' => $n, 'pendukung' => $pendukung,
        ];
    }

    /**
     * Timbang lalu DAFTARKAN kalau memang layak. Dipanggil dari jalur absen —
     * pemicunya harus saat ABSEN, bukan saat review (§5): 11 Agu tujuh orang
     * datang berurutan dalam sejam; auto-daftar di orang ketiga membuat orang
     * ke-4 sampai ke-7 langsung cocok dan tak pernah ke-flag. Kalau pemicunya
     * di sapuan harian 06:20 besoknya, semuanya terlanjur merah.
     *
     * Tak pernah melempar: absen tak boleh gagal gara-gara fitur kenyamanan.
     *
     * @return array vonis yang sama seperti nilai(), plus 'baris' bila mendaftar
     */
    public function pelajari(string $ip, ?string $branchDipilih, ?Carbon $saat = null): array
    {
        try {
            $hasil = $this->nilai($ip, $branchDipilih, $saat);

            // TAWARKAN kini vonis hidup satu-satunya sehari-hari — kirimkan ke
            // IT, jangan cuma dikembalikan sebagai nilai yang tak dibaca siapa pun.
            if ($hasil['vonis'] === self::TAWARKAN) {
                $this->tawarkan($hasil, $branchDipilih, $saat ? $saat->copy() : Carbon::now());
                return $hasil;
            }

            if ($hasil['vonis'] !== self::DAFTAR) {
                return $hasil;
            }

            $hasil['baris'] = $this->daftarkan($hasil['prefix'], $branchDipilih, $hasil);
            return $hasil;
        } catch (\Throwable $e) {
            report($e);
            return ['vonis' => self::TOLAK, 'alasan' => 'galat', 'prefix' => null, 'n' => 0, 'pendukung' => []];
        }
    }

    /** Fitur menyala? Default MENYALA — dimatikan dari halaman WiFi Cabang. */
    public static function aktif(): bool
    {
        return Setting::get(self::KUNCI_SAKLAR, '1') === '1';
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Orang berbeda yang memakai alamat/prefix ini dalam jendela, yang BOLEH
     * dihitung. SATU implementasi untuk dua jalur: IPv6 melewatkan pencocok
     * per-/64, IPv4 melewatkan pencocok cocok-penuh. Syarat siapa-yang-boleh-
     * jadi-saksi (cabang efektif, bukan fleksibel) sengaja identik — untuk IPv4
     * justru lebih penting, karena di sana tak ada pagar kekerabatan /48 yang
     * menyaring "dua orang di kafe".
     *
     * Yang TIDAK dihitung:
     *  • orang berjadwal FLEKSIBEL — §6. Bukan karena buktinya palsu, tapi
     *    karena cabang asal mereka memang kosong di sistem, jadi syarat 2 tak
     *    bisa diperiksa sama sekali. Mereka juga orang yang paling sering absen
     *    dari cabang lain (Talitha tercatat di Cinere, Pamulang, dan Bintaro
     *    dalam sepekan) — saksi paling lemah untuk "jaringan ini milik cabang X".
     *  • orang yang cabang ASALNYA bukan cabang ini (syarat 2)
     *  • bukti yang cabangnya sendiri bukan cabang ini — mis. Rizka yang asalnya
     *    Bintaro tapi absen memilih Kalideres: tak boleh menguatkan Bintaro
     *    (bukti bilang dia di tempat lain) maupun Kalideres (asalnya beda).
     *
     * @return array{0:string[], 1:int} [nama-nama pendukung, jumlah orang]
     */
    private function pendukung(callable $alamatSama, string $branchDipilih, Carbon $saat): array
    {
        $sejak = $saat->copy()->subHours(self::JENDELA_JAM);
        $hp    = new AttendanceHpAbsen();

        // Alamat tak bisa dicocokkan di SQL: satu /64 punya tak terhingga bentuk
        // tertulis (ekor privacy extension berganti tiap sesi, '::' terkompresi,
        // huruf besar/kecil), dan satu IPv4 bisa datang sebagai ::ffff:a.b.c.d.
        // Sama seperti AttBranchIp::matchBranch, banding dilakukan BINER di PHP.
        // Jendelanya sempit (72 jam ~ puluhan baris).
        $rows = AttHpBukti::query()
            ->where('taken_at', '>=', $sejak)
            ->where('taken_at', '<=', $saat)
            ->whereNotNull('user_id')
            ->orderBy('taken_at')
            ->get(['id', 'user_id', 'ip', 'branch_id_cocok', 'branch_id_manual', 'taken_at']);

        // Saring dulu yang alamat & cabang-buktinya cocok, ambil kejadian
        // PERTAMA tiap orang. Baru sesudah itu tanya DB sekali untuk semuanya —
        // jalur ini dilewati tiap absen yang IP-nya tak dikenal, jadi jangan
        // menembak satu query per baris.
        $calon = [];
        foreach ($rows as $b) {
            if (isset($calon[$b->user_id])) {
                continue;
            }
            if (! $alamatSama((string) $b->ip)) {
                continue;
            }
            // Bukti ini mengaku di cabang mana? Rizka yang asalnya Bintaro tapi
            // absen memilih Kalideres tak boleh menguatkan Bintaro.
            if (($b->branch_id_cocok ?: $b->branch_id_manual) !== $branchDipilih) {
                continue;
            }
            $calon[$b->user_id] = $hp->workDate(Carbon::parse($b->taken_at));
        }

        if (! $calon) {
            return [[], 0];
        }

        $ids = array_keys($calon);

        // ── Syarat 2: cabang orang ini harus cabang yang dipilih ─────────────
        //
        // Yang dipakai cabang EFEKTIF — penempatan (rolling) menang atas cabang
        // asal — bukan `raw_branch_id` mentah.
        //
        // KENAPA: orang yang SEDANG DITEMPATKAN di suatu cabang justru saksi
        // paling sahih bahwa jaringan itu milik cabang tersebut — dia memang
        // ditugaskan ke sana, tertulis, dengan tanggal dan jejak konfirmasi.
        // Membandingkan cabang asal membuat dia selamanya tak terhitung, dan
        // itu bukan kehati-hatian melainkan kebutaan.
        //
        // Ongkosnya nyata dan terukur: 13 Agu 14.49-14.51 empat orang absen
        // dari /64 baru Pamulang — Irma (fleksibel, memang gugur), Ramdan
        // (ditempatkan di Pamulang, asalnya Kalideres), Mutiara & Alviona
        // (asal Pamulang). Terhitung dua, ambangnya tiga. Padahal Pamulang
        // cuma punya DUA staf asal, jadi tanpa Ramdan cabang itu tak akan
        // pernah bisa mendaftarkan jaringannya sendiri — selamanya. Kalideres
        // (satu staf asal) lebih parah lagi.
        //
        // Penempatan ditimbang pada TANGGAL bukti masing-masing, bukan hari
        // ini: jendelanya 72 jam, dan penempatan bisa mulai/berakhir di
        // tengahnya. Memakai "hari ini" akan menilai bukti kemarin dengan
        // penugasan yang belum berlaku saat itu.
        //
        // Cabang asal yang KOSONG (orang fleksibel/ABG) tetap gugur di sini —
        // null tak akan pernah sama dengan $branchDipilih.
        $baris = \App\Models\Users::whereIn('id', $ids)->get(['id', 'nama', 'raw_branch_id']);

        // Semua baris rolling milik para calon, ditimbang di PHP supaya tak
        // menembak satu query per orang (jalur ini dilewati tiap absen).
        $rolling = \App\Models\UserPosition::query()
            ->whereIn('user_id', $ids)
            ->where('kind', 'rolling')
            ->where('is_primary', 1)
            ->whereNotNull('confirmed_at')
            ->get(['user_id', 'raw_branch_id', 'effective_from', 'effective_to'])
            ->groupBy('user_id');

        $cocokCabang = [];
        foreach ($baris as $u) {
            $tanggal = $calon[$u->id];
            $cabang  = $u->raw_branch_id;

            foreach ($rolling->get($u->id, []) as $p) {
                $dari = $p->effective_from?->toDateString();
                $sampai = $p->effective_to?->toDateString();
                if ($dari && $dari > $tanggal) continue;
                if ($sampai && $sampai < $tanggal) continue;
                $cabang = $p->raw_branch_id;   // penempatan menang
                break;
            }

            if ($cabang === $branchDipilih) {
                $cocokCabang[$u->id] = $u->nama;
            }
        }

        $flex = $this->fleksibel(array_keys($cocokCabang));

        $orang = [];
        foreach ($cocokCabang as $uid => $nama) {
            if ($flex($uid, $calon[$uid])) {
                continue;
            }
            $orang[$uid] = $nama ?: $uid;
        }

        return [array_values($orang), count($orang)];
    }

    /**
     * Pemeriksa "berjadwal fleksibel di tanggal ini", dimuat sekali untuk
     * banyak orang. Sepadan dengan AttendanceHpAbsen::isFleksibel, tapi tanpa
     * satu query per orang.
     *
     * @return callable(string,string):bool
     */
    private function fleksibel(array $ids): callable
    {
        if (! $ids) {
            return fn ($u, $d) => false;
        }

        $aturan = \Illuminate\Support\Facades\DB::table('att_user_rule as ur')
            ->join('att_work_rule as wr', 'wr.id', '=', 'ur.rule_id')
            ->whereIn('ur.user_id', $ids)
            ->where('wr.tipe', 'fleksibel')
            ->get(['ur.user_id', 'ur.effective_from', 'ur.effective_to'])
            ->groupBy('user_id');

        return function (string $userId, string $tanggal) use ($aturan): bool {
            foreach ($aturan->get($userId, []) as $r) {
                if ($r->effective_from <= $tanggal
                    && ($r->effective_to === null || $r->effective_to >= $tanggal)) {
                    return true;
                }
            }
            return false;
        };
    }

    /** Cabang ini punya prefix IPv6 aktif yang /48-nya sama? */
    private function sekerabat48(string $prefix48, string $branchDipilih): bool
    {
        $rows = AttBranchIp::where('raw_branch_id', $branchDipilih)
            ->where('status', 'aktif')->get(['ip']);

        foreach ($rows as $row) {
            $rbin = @inet_pton((string) $row->ip);
            if ($rbin !== false && strlen($rbin) === 16 && substr($rbin, 0, 6) === $prefix48) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tulis barisnya. Disimpan sebagai prefix bersih (2001:448a:2092:2777::),
     * bukan alamat satu HP berekor seperti baris lama — pencocokan cuma membaca
     * 8 byte pertama jadi hasilnya sama, tapi barisnya terbaca sebagai apa
     * adanya: sebuah JALUR, bukan sebuah perangkat.
     */
    private function daftarkan(string $prefix, string $branchDipilih, array $hasil): ?AttBranchIp
    {
        $cabang = optional(\App\Models\RawBranch::find($branchDipilih))->name ?? $branchDipilih;
        $nama   = implode(', ', array_slice($hasil['pendukung'], 0, 5));

        // label = varchar(191). Nama panjang ("Alpajri Suhendar Al Qhusni") cepat
        // menembusnya, dan kolom penuh TIDAK boleh menjatuhkan absen orangnya —
        // jadi: nama depan saja, lalu dipotong keras sebagai jaring pengaman.
        // Keterangan utuh tetap ada di notifikasi IT & AuditLog.
        $depan = implode(', ', array_map(
            fn ($n) => strtok(trim($n), ' '),
            array_slice($hasil['pendukung'], 0, 4)
        ));
        // Label harus menyebut APA yang menjamin baris ini, karena penjaminnya
        // berbeda: IPv6 dijamin kekerabatan /48 (jalur cabang yang sudah
        // dikenal), IPv4 cuma dijamin perilaku saksi. Yang kedua perlu mata
        // manusia; yang pertama tidak (§6 rencana).
        $v4     = AttBranchIp::teksIpv4($prefix) !== null;
        $tinjau = static::perluTinjau($prefix, $branchDipilih, AttBranchIp::all());
        $dasar  = $v4 ? 'IPv4 cocok-penuh' : ($tinjau ? 'blok /48 baru' : '/48 sekerabat');
        $ekor   = " · {$dasar}" . ($tinjau ? ' — PERLU DITINJAU' : ' — disahkan');
        $label = mb_substr(
            "Otomatis · {$hasil['n']} orang {$cabang} dalam " . self::JENDELA_JAM
            . " jam: {$depan}{$ekor}",
            0, 191
        );

        try {
            $baris = AttBranchIp::firstOrCreate(
                ['ip' => $prefix],
                [
                    'raw_branch_id' => $branchDipilih,
                    'label'         => $label,
                    'status'        => 'aktif',
                    'created_by'    => null,   // tak ada manusia di balik baris ini
                    'sumber'        => 'otomatis',
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Dua orang absen di detik yang sama, dua-duanya jadi "orang ketiga"
            // → insert kedua nabrak UNIQUE(ip). Yang pertama menang; baca ulang.
            if (($e->errorInfo[0] ?? null) !== '23000') {
                throw $e;
            }
            return AttBranchIp::where('ip', $prefix)->first();
        }

        if (! $baris->wasRecentlyCreated) {
            return $baris;
        }

        // Beri tahu IT tiap kali ini terjadi (§3, sisa risiko yang diterima
        // sadar): jaringan yang didatangi AMBANG_ORANG staf cabang yang sama —
        // kafe se-/48, atau satu alamat CGNAT yang kebetulan dibagi — bisa ikut
        // terdaftar. Ongkosnya kecil, tapi harus ADA yang melihatnya. Untuk
        // IPv4 ini BUKAN sekadar kesopanan: notifikasi + label adalah SATU-
        // SATUNYA pertahanan yang tersisa, karena tak ada pagar kekerabatan.
        //
        // Satu baris per role, BUKAN satu baris "IT" saja: notifikasi dikirim
        // ke target_role yang harus COCOK PERSIS dengan group_name pemakai, dan
        // halaman WiFi Cabang dipegang dua role terpisah — "Head IT" takkan
        // pernah melihat baris yang ditujukan ke "IT".
        $penjamin = $v4
            ? 'Alamat IPv4 ini didaftarkan COCOK-PENUH — tetangganya yang cuma beda satu angka tetap '
              . 'ditolak. Tak ada kekerabatan blok yang menjaminnya; yang menjaminnya cuma perilaku: '
              . "{$hasil['n']} orang berbeda tak akan berbagi satu alamat kalau itu data seluler pribadi."
            : "Blok /48-nya sekerabat dengan jalur {$cabang} yang sudah terdaftar, jadi jaringan ini "
              . 'praktis sudah dikenali dengan sendirinya.';

        $ajakan = $tinjau
            ? ' PERLU DITINJAU di halaman WiFi Cabang — nonaktifkan kalau ini bukan jaringan cabang.'
            : ' Tinjau di halaman WiFi Cabang kalau sempat; nonaktifkan kalau ini bukan jaringan cabang.';

        foreach (self::ROLE_PENINJAU as $role) {
            AdminNotification::notifyRole($role, 'wifi_otomatis',
                "WiFi baru terdaftar otomatis — {$cabang}",
                "Jaringan {$prefix} didaftarkan sendiri oleh sistem karena {$hasil['n']} orang {$cabang} "
                . 'memakainya dalam ' . self::JENDELA_JAM . " jam terakhir ({$nama}). {$penjamin}{$ajakan}",
                'absensi/wifi');
        }

        \App\Models\AuditLog::record('create',
            "WiFi belajar sendiri: {$prefix} terdaftar otomatis ke {$cabang} "
            . "({$hasil['n']} orang: {$nama})");

        return $baris;
    }

    /**
     * Beri tahu IT bahwa ada jaringan ASING yang dipakai ramai-ramai.
     *
     * Sejak pencocokan melebar ke /48 (AttBranchIp::matchBranch, 21 Agu 2026),
     * vonis DAFTAR praktis tak pernah terbit lagi — lihat catatan di docblock
     * kelas. Yang tersisa justru kasus yang MEMANG butuh manusia: /48 yang tak
     * dikenal sama sekali, dipakai >= AMBANG_ORANG staf cabang itu.
     *
     * Dulu vonis ini cuma dikembalikan sebagai nilai dan tak pernah sampai ke
     * siapa pun — tak ada notifikasi, tak ada layar yang membacanya, meski
     * docblock kelas menjanjikan "tawarkan sekali klik ke IT". Dibiarkan
     * begitu, pelebaran /48 akan membuat halaman WiFi Cabang membisu total.
     *
     * Sekali per prefix per jendela: satu jaringan dipakai belasan kali sehari,
     * dan notifikasi berulang justru membuat IT berhenti membacanya.
     */
    private function tawarkan(array $hasil, string $branchDipilih, Carbon $saat): void
    {
        $prefix = (string) $hasil['prefix'];
        $cabang = optional(\App\Models\RawBranch::find($branchDipilih))->name ?? $branchDipilih;

        $sudah = AdminNotification::where('type', 'wifi_tawaran')
            ->where('title', 'like', '%' . $prefix . '%')
            ->where('created_at', '>=', $saat->copy()->subHours(self::JENDELA_JAM))
            ->exists();
        if ($sudah) {
            return;
        }

        $nama = implode(', ', array_slice($hasil['pendukung'], 0, 5));

        foreach (self::ROLE_PENINJAU as $role) {
            AdminNotification::notifyRole($role, 'wifi_tawaran',
                "WiFi asing dipakai ramai — {$prefix} ({$cabang})",
                "{$hasil['n']} orang {$cabang} memakai jaringan {$prefix} dalam " . self::JENDELA_JAM
                . " jam terakhir ({$nama}), tapi blok /48-nya TIDAK sekerabat dengan jalur {$cabang} "
                . 'mana pun yang sudah terdaftar. Sistem sengaja tidak mendaftarkannya sendiri. '
                . 'Kalau ini memang jalur baru cabang, daftarkan di halaman WiFi Cabang; kalau bukan '
                . '(rumah, kafe, atau seluler), biarkan saja.',
                'absensi/wifi');
        }
    }

    /**
     * §6 — baris otomatis ini perlu mata manusia, atau cukup disahkan?
     *
     * IPv4: SELALU perlu. Tak ada kekerabatan yang menjaminnya — cuma perilaku
     * saksi, dan perilaku bisa menipu (CGNAT yang kebetulan dibagi dua staf
     * cabang yang sama). Itu sisa risiko yang diterima sadar, dan syarat
     * menerimanya adalah baris itu KELIHATAN.
     *
     * IPv6: perlu HANYA kalau /48-nya benar-benar baru — yaitu belum ada baris
     * yang DITARUH MANUSIA di /48 itu untuk cabang yang sama. Yang se-/48
     * dengan jalur yang sudah disahkan manusia tak menambah apa-apa untuk
     * ditinjau: sejak pencocokan /48 (21 Agu 2026) alamat seperti itu sudah
     * cocok dengan sendirinya, terdaftar atau tidak. Menandainya "perlu
     * ditinjau" cuma membuat halaman WiFi Cabang berteriak untuk sesuatu yang
     * bukan keputusan — dan halaman yang berteriak untuk hal sepele akan
     * berhenti dibaca. Kelima baris otomatis di prod (id 20, 21, 22, 25, 26)
     * semuanya jenis ini.
     *
     * Baris ber-sumber otomatis TIDAK boleh menjamin baris otomatis lain:
     * kalau boleh, satu baris keliru akan mengesahkan seluruh /48 di
     * belakangnya tanpa seorang pun pernah melihatnya.
     *
     * ⚠️ SATU aturan untuk DUA tempat — label saat menulis (daftarkan()) DAN
     * daftar tinjauan di halaman WiFi Cabang. Pelajaran mahal dari veto /48:
     * pembedaan yang sama pernah ditulis di dua tempat lalu menyimpang.
     *
     * @param iterable<AttBranchIp> $semua seluruh isi att_branch_ip
     */
    public static function perluTinjau(string $ip, ?string $branchId, iterable $semua): bool
    {
        if (AttBranchIp::teksIpv4($ip) !== null) {
            return true;
        }

        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return true;   // tak terbaca = jangan ditebak, minta manusia
        }
        $p48 = substr($bin, 0, 6);

        foreach ($semua as $row) {
            if ($row->sumber === 'otomatis' || $row->status !== 'aktif') {
                continue;
            }
            if ((string) $row->raw_branch_id !== (string) $branchId) {
                continue;
            }
            $rbin = @inet_pton((string) $row->ip);
            if ($rbin !== false && strlen($rbin) === 16 && substr($rbin, 0, 6) === $p48) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alamat ini pernah dipakai memilih cabang LAIN, kapan pun? (§3 syarat 3)
     *
     * Tanpa batas waktu — sengaja. Jendela 72 jam menjawab "siapa yang di sana
     * sekarang"; pertanyaan ini lain: "alamat ini pernah jadi milik siapa lagi".
     * Alamat yang bulan lalu terbaca Sunter tak boleh diam-diam jadi Cinere —
     * itu ciri alamat yang berpindah tangan (rotasi ISP, hotspot, kantor
     * bersama), bukan ciri jaringan cabang.
     *
     * Kedua bentuk tulisan diperiksa: bukti lama bisa tersimpan sebagai
     * ::ffff:a.b.c.d sementara yang dinilai sekarang bentuk bertitik.
     */
    private function pernahCabangLain(string $alamat, string $branchDipilih): bool
    {
        $rows = AttHpBukti::query()
            ->whereIn('ip', [$alamat, '::ffff:' . $alamat])
            ->get(['branch_id_cocok', 'branch_id_manual']);

        foreach ($rows as $b) {
            $cabang = $b->branch_id_cocok ?: $b->branch_id_manual;
            if ($cabang !== null && (string) $cabang !== $branchDipilih) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alamat IPv4 ini alamat internet yang wajar, bukan alamat dalam?
     *
     * Menolak: privat (10/8, 172.16/12, 192.168/16) · loopback (127/8) ·
     * link-local (169.254/16) · 0.0.0.0/8 · ruang cadangan (240/4, termasuk
     * 255.255.255.255) — semuanya lewat filter bawaan PHP — DAN 100.64/10,
     * ruang bersama CGNAT (RFC 6598) yang tak ikut terjaring filter itu tapi
     * sama saja artinya: kalau ia sampai ke server, yang salah rantai proxy.
     */
    private static function ipv4Publik(string $alamat): bool
    {
        $bin = @inet_pton($alamat);
        if ($bin === false || strlen($bin) !== 4) {
            return false;
        }

        // 100.64.0.0/10 — tak dicakup FILTER_FLAG_NO_RES_RANGE.
        $oktet = array_values(unpack('C4', $bin));
        if ($oktet[0] === 100 && $oktet[1] >= 64 && $oktet[1] <= 127) {
            return false;
        }

        return filter_var(
            $alamat,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /** Bukti ini berada di /64 yang sama? Banding BINER — lihat pendukung(). */
    private static function sePrefix64(string $ip, string $prefix64): bool
    {
        $bin = @inet_pton($ip);

        return $bin !== false && strlen($bin) === 16 && substr($bin, 0, 8) === $prefix64;
    }

    /** 8 byte pertama → "2001:448a:2092:2777::" */
    private function teksPrefix(string $prefix64): string
    {
        return (string) inet_ntop($prefix64 . str_repeat("\0", 8));
    }
}
