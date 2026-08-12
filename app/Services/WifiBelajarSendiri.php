<?php

namespace App\Services;

use App\Models\AttBranchIp;
use App\Models\AttHpBukti;
use App\Models\AdminNotification;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * WiFi Cabang belajar sendiri — mendaftarkan prefix IPv6 baru secara otomatis
 * ketika cukup banyak orang cabang itu terbukti memakainya.
 *
 * MASALAH YANG DIOBATI (docs/rencana/rencana-wifi-belajar-sendiri.md):
 * prefix IPv6 IndiHome berputar kira-kira MINGGUAN. Tiap rotasi, semua orang
 * beraturan baku di cabang itu kena flag ip_asing sampai ada manusia yang
 * sadar lalu mendaftarkan nomor barunya. Ini masalah KEBISINGAN, bukan
 * integritas — WiFi cuma saksi, tak ada yang gagal absen. Ongkosnya: waktu
 * review Anisa, dan flag jadi terbiasa merah sehingga yang asli tenggelam.
 *
 * EMPAT SYARAT (semua wajib, §3 — sudah di-ACC, jangan dilonggarkan diam-diam):
 *   1. >= 3 orang BERBEDA memakai /64 ini
 *   2. ketiganya cabang asalnya sama dengan cabang yang dipilih
 *   3. dalam 72 jam terakhir
 *   4. /48-nya sekerabat dengan prefix cabang itu yang sudah terdaftar
 *
 * Syarat 1-3 terpenuhi tapi 4 tidak (mis. cabang baru yang belum punya prefix
 * apa pun) → JANGAN otomatis, tawarkan sekali klik ke IT.
 *
 * KENAPA HANYA IPv6: itulah satu-satunya yang berputar (IPv4 cabang bertahan
 * berhari-hari melewati rotasi, §7). Sekaligus cara membuktikan larangan
 * "jangan pernah daftarkan IP seluler" (§6) tanpa menebak-nebak operator —
 * Rizka 7 Agu absen lewat Telkomsel 114.10.70.167 yang CGNAT, dipakai jutaan
 * orang; mendaftarkannya = membuka absen dari mana pun. IPv4 tak pernah lewat
 * sini. IPv6 seluler pun tetap gugur di pagar /48.
 *
 * KENAPA SATU ORANG TAK PERNAH CUKUP: satu orang tak bisa dibedakan dari WiFi
 * rumahnya sendiri. Kasus Kalideres (Rizka, 6-7 Agu) membuktikannya — waktu itu
 * penyelesaiannya lewat memeriksa foto selfie, bukan lewat jumlah.
 */
class WifiBelajarSendiri
{
    /** Berapa orang berbeda yang harus terbukti memakai satu /64. ACC Ekky 2026-08-12. */
    public const AMBANG_ORANG = 3;

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

    // ── Vonis ────────────────────────────────────────────────────────────────
    public const DAFTAR   = 'daftar';    // empat syarat lengkap → didaftarkan
    public const TAWARKAN = 'tawarkan';  // 1-3 lengkap, /48 tak sekerabat → tanya IT
    public const TOLAK    = 'tolak';     // belum cukup bukti

    /**
     * Timbang satu alamat. MURNI — tidak menulis apa pun, aman dipanggil dari
     * perkakas uji/pengintip. Yang menulis: pelajari().
     *
     * Alasan sengaja dikembalikan sebagai kode, bukan cuma bool: tujuh kasus uji
     * §6 menuntut pembedaan "ditolak karena APA" — satu orang dan tiga orang
     * beda cabang sama-sama ditolak lewat pintu yang berbeda, dan yang
     * "3 orang tapi /48 asing" harus jadi tawaran, bukan diam.
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

        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return $tolak('bukan_ipv6');
        }

        // ::ffff:x.x.x.x — IPv4 yang menyamar sebagai IPv6. Tetap IPv4 (dan
        // CGNAT seluler bisa lewat bentuk ini), jangan sampai lolos.
        if (substr($bin, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
            return $tolak('bukan_ipv6');
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
        // 2313 lama) BUKAN veto — kalau jalur itu kembali dipakai bertiga,
        // memang sudah semestinya dikenali lagi.
        $veto = AttBranchIp::where('ip', $prefixTeks)->where('status', 'nonaktif')->first();
        if ($veto) {
            return $tolak('ditolak_it', $prefixTeks);
        }

        // ── Syarat 1-3: siapa saja yang memakai /64 ini dalam 72 jam ──────────
        [$pendukung, $n] = $this->pendukung($prefix64, $branchDipilih, $saat);

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
            'vonis' => self::DAFTAR, 'alasan' => 'lengkap',
            'prefix' => $prefixTeks, 'n' => $n, 'pendukung' => $pendukung,
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
     * Orang berbeda yang memakai /64 ini dalam jendela, yang BOLEH dihitung.
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
    private function pendukung(string $prefix64, string $branchDipilih, Carbon $saat): array
    {
        $sejak = $saat->copy()->subHours(self::JENDELA_JAM);
        $hp    = new AttendanceHpAbsen();

        // Prefix tak bisa dicocokkan di SQL: satu /64 punya tak terhingga bentuk
        // tertulis (ekor privacy extension berganti tiap sesi, '::' terkompresi,
        // huruf besar/kecil). Sama seperti AttBranchIp::matchBranch, banding
        // dilakukan BINER di PHP. Jendelanya sempit (72 jam ~ puluhan baris).
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
            $rbin = @inet_pton((string) $b->ip);
            if ($rbin === false || strlen($rbin) !== 16 || substr($rbin, 0, 8) !== $prefix64) {
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

        // Syarat 2: cabang ASAL harus cabang ini. Yang kosong (semua orang
        // fleksibel) otomatis gugur di sini juga.
        $namaOrang = \App\Models\Users::whereIn('id', $ids)
            ->where('raw_branch_id', $branchDipilih)
            ->pluck('nama', 'id');

        $flex = $this->fleksibel(array_keys($namaOrang->all()));

        $orang = [];
        foreach ($namaOrang as $uid => $nama) {
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
        $label = mb_substr(
            "Otomatis · {$hasil['n']} orang {$cabang} dalam " . self::JENDELA_JAM
            . " jam: {$depan} · /48 sekerabat — perlu ditinjau",
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
        // sadar): kafe/tetangga se-/48 yang didatangi >=3 staf bisa ikut
        // terdaftar. Ongkosnya kecil, tapi harus ADA yang melihatnya.
        //
        // Satu baris per role, BUKAN satu baris "IT" saja: notifikasi dikirim
        // ke target_role yang harus COCOK PERSIS dengan group_name pemakai, dan
        // halaman WiFi Cabang dipegang dua role terpisah — "Head IT" takkan
        // pernah melihat baris yang ditujukan ke "IT".
        foreach (self::ROLE_PENINJAU as $role) {
            AdminNotification::notifyRole($role, 'wifi_otomatis',
                "WiFi baru terdaftar otomatis — {$cabang}",
                "Jaringan {$prefix} didaftarkan sendiri oleh sistem karena {$hasil['n']} orang {$cabang} "
                . "memakainya dalam " . self::JENDELA_JAM . " jam terakhir ({$nama}), dan blok /48-nya sekerabat "
                . "dengan jalur {$cabang} yang sudah terdaftar. Tinjau di halaman WiFi Cabang — "
                . 'nonaktifkan kalau ini bukan jaringan cabang.',
                'absensi/wifi');
        }

        \App\Models\AuditLog::record('create',
            "WiFi belajar sendiri: {$prefix} terdaftar otomatis ke {$cabang} "
            . "({$hasil['n']} orang: {$nama})");

        return $baris;
    }

    /** 8 byte pertama → "2001:448a:2092:2777::" */
    private function teksPrefix(string $prefix64): string
    {
        return (string) inet_ntop($prefix64 . str_repeat("\0", 8));
    }
}
