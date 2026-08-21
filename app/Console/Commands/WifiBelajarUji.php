<?php

namespace App\Console\Commands;

use App\Models\AttBranchIp;
use App\Models\AttFlag;
use App\Models\AttHpBukti;
use App\Models\AttScan;
use App\Models\Setting;
use App\Services\AttendanceHpAbsen;
use App\Services\WifiBelajarSendiri;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pemutar-ulang kasus uji WiFi-belajar-sendiri (rencana §6).
 *
 * Ini BUKAN unit test: yang dipanggil adalah AttendanceHpAbsen::absen() —
 * jalur kode yang sama persis dengan yang dipakai HP karyawan, lengkap dengan
 * transaksi, penulisan att_scan/att_hp_bukti, dan penilaian flag. Yang dipalsukan
 * cuma dua: jam (Carbon::setTestNow, supaya jendela 72 jam terasa nyata) dan
 * berkas foto (tak ada gambar sungguhan; logikanya tak menyentuh isi berkas).
 *
 * Kejadiannya SALINAN PERSIS baris prod 5–12 Agu 2026 — jam, alamat, dan orang
 * yang sama. K5–K8 dan K11–K15 dikarang karena tak ada padanannya di data nyata
 * (klaim cabang keliru, CGNAT, /48 asing, saklar mati, veto, IPv4, pagar daftar).
 *
 * ⚠️ MENULIS ke DB: tiap kasus menghapus absensi di jendela ujinya lalu menyusun
 * ulang att_branch_ip. Karena itu dikunci mati untuk lingkungan produksi.
 */
class WifiBelajarUji extends Command
{
    protected $signature   = 'att:wifi-uji {--kasus= : jalankan satu kasus saja, mis. K1}';
    protected $description = 'Putar ulang kasus uji §6 WiFi belajar sendiri lewat jalur absen sungguhan';

    /** Cabang — id sama persis di dev & prod. */
    private const BINTARO   = '6fb99a92-067e-11f1-b4b5-ea16655562dc';
    private const SUNTER    = '7ac39cf8-067e-11f1-b4b5-ea16655562dc';
    private const PAMULANG  = '7ac3a4f0-067e-11f1-b4b5-ea16655562dc';
    private const KALIDERES = '6fb9a226-067e-11f1-b4b5-ea16655562dc';
    private const CINERE    = '7e2284ea-067e-11f1-b4b5-ea16655562dc';

    /**
     * Keadaan att_branch_ip Bintaro sebelum rotasi 11 Agu — dua jalur hidup,
     * masing-masing berwajah IPv4 dan IPv6 (§7 "satu WiFi dua wajah").
     */
    private const BASELINE_BINTARO = [
        [self::BINTARO, '180.252.168.224'],
        [self::BINTARO, '180.252.170.180'],
        [self::BINTARO, '2001:448a:2092:2313:1fe9:bc37:8ef7:93cd'],
        [self::BINTARO, '2001:448a:2092:134e:b0cc:8a6a:22f5:8c53'],
        [self::SUNTER,  '2001:448a:2062:208c:465d:430d:3098:7584'],
        [self::SUNTER,  '180.245.81.112'],
        [self::PAMULANG,'2001:448a:2020:27df:527:c68d:f6d7:21e9'],
    ];

    private array $userId = [];
    private array $gagal  = [];
    private int   $kasusGagal = 0;

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('DITOLAK: perkakas ini menulis data uji. Jangan pernah di produksi.');
            return 1;
        }

        // Jendela ujinya tumpang tindih dengan HARI INI. Kalau ada yang baru saja
        // mencoba absen dari HP di lingkungan ini, datanya ikut terhapus — dan
        // itu bukan hal yang boleh terjadi diam-diam.
        $bukti = AttHpBukti::whereBetween('taken_at', ['2026-08-05 00:00:00', '2026-08-14 00:00:00'])->count();
        if ($bukti > 0) {
            $this->warn("PERHATIAN: {$bukti} bukti absen 5–13 Agu di lingkungan ini akan DIHAPUS "
                . '(tiap kasus butuh baseline bersih). Batalkan dengan Ctrl-C kalau itu data percobaanmu.');
        }

        $this->petakanUser();

        $kasus = collect($this->kasus());
        if ($pilih = $this->option('kasus')) {
            $kasus = $kasus->filter(fn ($k) => strcasecmp($k['kode'], $pilih) === 0);
            if ($kasus->isEmpty()) {
                $this->error("Kasus {$pilih} tak dikenal.");
                return 1;
            }
        }

        foreach ($kasus as $k) {
            $this->jalankan($k);
        }

        Carbon::setTestNow();
        $this->pulihkan();

        $this->newLine();
        if ($this->gagal) {
            $this->error("GAGAL {$this->kasusGagal} dari " . $kasus->count() . ' kasus ('
                . count($this->gagal) . ' harapan meleset):');
            foreach ($this->gagal as $g) $this->line('  · ' . $g);
            return 1;
        }
        $this->info('LULUS — ' . $kasus->count() . ' dari ' . $kasus->count() . ' kasus §6 sesuai harapan.');
        return 0;
    }

    // ── Daftar kasus ─────────────────────────────────────────────────────────

    private function kasus(): array
    {
        $b = self::BINTARO;

        return [
            [
                'kode'  => 'K1',
                'judul' => 'Rotasi 10d5 · 11 Agu · 7 orang Bintaro dalam sejam, se-/48',
                'harap' => 'NOL flag — rotasi diserap pencocokan /48, tak ada yang perlu dipelajari',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk',  '2001:448a:2092:10d5:7614:ae78:98b6:c1f5', $b],
                    ['2026-08-11 09:34:14', 'Talitha Maritza Devi',       'masuk',  '2001:448a:2092:10d5:69e8:184:10b:8518',   $b],
                    ['2026-08-11 09:36:06', 'Firda Mareta Naskah',        'masuk',  '2001:448a:2092:10d5:b0cc:8a6a:22f5:8c53', $b],
                    ['2026-08-11 09:39:55', 'Rizka Noviyanti',            'masuk',  '2001:448a:2092:10d5:f424:5430:e974:be48', $b],
                    ['2026-08-11 09:43:34', 'Asih Wulan Sari',            'masuk',  '2001:448a:2092:10d5:2ca7:7a66:82c0:1ee7', $b],
                    ['2026-08-11 09:54:15', 'Afrina Apriliani',           'masuk',  '2001:448a:2092:10d5:cd9b:b9d6:de1e:eae',  $b],
                    ['2026-08-11 10:38:03', 'Reza Fauzi Ramadhan',        'masuk',  '2001:448a:2092:10d5:986a:57f:fa0c:e113',  $b],
                ],
                // ARTI KASUS INI BERUBAH TOTAL pada 21 Agu 2026.
                //
                // Dulu: pendaftaran otomatis memicu di orang sah ke-2 (Firda),
                // dan dua orang pertama tetap menanggung flag sebagai "harga
                // bukti yang mereka kumpulkan". Sekarang pencocokan memakai /48
                // dan 10d5 sekerabat dengan 2313/134e di baseline — jadi ORANG
                // PERTAMA pun langsung cocok.
                //
                // Inilah bukti utama pelebaran /48: kejadian nyata 11 Agu yang
                // dulu membakar tujuh orang kini tak membakar seorang pun.
                'periksa' => function (array $j) {
                    foreach ($j as $i => $r) {
                        $ke = 'orang ke-' . ($i + 1) . " ({$r['nama']})";
                        $this->wajib($r['cocok'] === true, "{$ke} harus langsung COCOK lewat kekerabatan /48");
                        $this->wajib($r['branch_id'] === self::BINTARO, "{$ke} harus terbaca di Bintaro — dapat {$r['branch_id']}");
                        $this->wajib($r['belajar']['alasan'] === 'sudah_cocok',
                            "{$ke} tak boleh masuk jalur belajar sama sekali — dapat " . $r['belajar']['alasan']);
                    }

                    // Rotasi tak lagi menambah baris: daftar WiFi berhenti membengkak.
                    $n = AttBranchIp::where('sumber', 'otomatis')->count();
                    $this->wajib($n === 0, "rotasi tak boleh melahirkan baris baru lagi — lahir {$n}");

                    $f = AttFlag::where('jenis', 'ip_asing')->where('work_date', '2026-08-11')->count();
                    $this->wajib($f === 0, "flag ip_asing 11 Agu harus NOL (dulu 2) — dapat {$f}");
                },
            ],
            [
                'kode'  => 'K2',
                'judul' => 'Jalur kedua 2777 · Firda 11/8 malam + Alpajri & Yovanka 12/8 pagi',
                'harap' => 'NOL flag di dua hari — jalur kedua pun se-/48',
                'baseline' => array_merge(self::BASELINE_BINTARO, [[self::BINTARO, '2001:448a:2092:10d5::']]),
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 21:15:20', 'Firda Mareta Naskah',        'pulang', '2001:448a:2092:2777:b0cc:8a6a:22f5:8c53', $b],
                    ['2026-08-12 07:35:41', 'Alpajri Suhendar Al Qhusni', 'masuk',  '2001:448a:2092:2777:2610:b0ab:1fa:adb7',  $b],
                    ['2026-08-12 09:19:01', 'Yovanka Rima Millennia',     'masuk',  '2001:448a:2092:2777:1893:da0b:a1c4:2660', $b],
                ],
                // Kasus ini sudah dua kali berganti arti. Mula-mula membuktikan
                // "2 orang belum cukup" (ambang 3). Lalu, sesudah ambang turun,
                // membuktikan bukti boleh MELINTASI HARI di dalam jendela 72 jam.
                // Sekarang pertanyaan itu hilang seluruhnya: jalur kedua Bintaro
                // ada di /48 yang sama, jadi tak ada bukti yang perlu dikumpulkan.
                'periksa' => function (array $j) {
                    foreach ($j as $i => $r) {
                        $this->wajib($r['cocok'] === true,
                            'orang ke-' . ($i + 1) . " ({$r['nama']}) harus langsung cocok — jalur B se-/48 dengan jalur A");
                    }
                    foreach (['2026-08-11', '2026-08-12'] as $hari) {
                        $f = AttFlag::where('jenis', 'ip_asing')->where('work_date', $hari)->count();
                        $this->wajib($f === 0, "flag ip_asing {$hari} harus NOL — dapat {$f}");
                    }
                },
            ],
            [
                'kode'  => 'K3',
                'judul' => 'Cinere 2033:12b8 · 7 Agu · 2 orang, /48 tak sekerabat',
                'harap' => 'TIDAK daftar',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-07 10:29:50', 'Talitha Maritza Devi',  'masuk',  '2001:448a:2033:12b8:2420:ce69:7a97:70df', self::CINERE],
                    ['2026-08-07 18:34:55', 'Alif Imam Fadillah',    'pulang', '2001:448a:2033:12b8:bfb9:4b0a:79f0:8955', self::CINERE],
                    ['2026-08-07 18:35:20', 'Talitha Maritza Devi',  'pulang', '2001:448a:2033:12b8:ca2:608f:84ed:54fd',  self::CINERE],
                ],
                'periksa' => fn (array $j) => $this->takAdaPendaftaran($j),
            ],
            [
                'kode'  => 'K4',
                'judul' => 'Kalideres 90b0:ebf · 1 orang (Rizka, 6–7 Agu)',
                'harap' => 'TIDAK daftar — satu orang tak bisa dibedakan dari WiFi rumahnya sendiri',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-06 20:40:54', 'Rizka Noviyanti', 'pulang', '2001:448a:90b0:ebf:1038:9c18:777b:b5c1', self::KALIDERES],
                    ['2026-08-07 09:59:17', 'Rizka Noviyanti', 'masuk',  '2001:448a:90b0:ebf:1038:9c18:777b:b5c1', self::KALIDERES],
                ],
                'periksa' => fn (array $j) => $this->takAdaPendaftaran($j),
            ],
            [
                'kode'  => 'K5',
                'judul' => 'Se-/48 Bintaro tapi tiga orang MENGAKU cabang berbeda',
                'harap' => 'ketiganya terbaca BINTARO — saksi WiFi menang atas pilihan manual',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 08:00:00', 'Firda Mareta Naskah', 'masuk', '2001:448a:2092:9911:1::', $b],
                    ['2026-08-11 08:05:00', 'Rizka Noviyanti',     'masuk', '2001:448a:2092:9911:2::', self::SUNTER],
                    ['2026-08-11 08:10:00', 'Asih Wulan Sari',     'masuk', '2001:448a:2092:9911:3::', self::CINERE],
                ],
                // Dulu kasus ini menguji "tak ada satu cabang pun yang mencapai
                // ambang". Sejak pencocokan /48, prefix karangan 9911 berada di
                // DALAM blok Bintaro — jadi pertanyaannya berubah jadi yang lebih
                // penting: kalau orang mengaku di Sunter padahal jaringannya
                // milik Bintaro, siapa yang dipercaya? Jawabannya: jaringannya.
                'periksa' => function (array $j) {
                    foreach ($j as $i => $r) {
                        $ke = 'orang ke-' . ($i + 1) . " ({$r['nama']})";
                        $this->wajib($r['cocok'] === true, "{$ke} harus cocok lewat /48 Bintaro");
                        $this->wajib($r['branch_id'] === self::BINTARO,
                            "{$ke} harus terbaca BINTARO walau memilih cabang lain — dapat {$r['branch_id']}");
                    }
                    // Klaim manual tak boleh ikut tercatat saat WiFi mengenali:
                    // yang tersimpan harus bukti, bukan pengakuan.
                    $manual = AttHpBukti::whereBetween('taken_at', ['2026-08-11 00:00:00', '2026-08-12 00:00:00'])
                        ->whereNotNull('branch_id_manual')->count();
                    $this->wajib($manual === 0, "pilihan manual tak boleh tercatat saat WiFi mengenali — dapat {$manual}");
                    $this->takAdaPendaftaran($j);
                },
            ],
            [
                'kode'  => 'K6',
                'judul' => 'Seluler CGNAT 114.10.70.167 · 3 orang Bintaro sekalipun (diperkeras)',
                'harap' => 'TIDAK PERNAH daftar — IPv4/CGNAT dipakai jutaan orang',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 08:00:00', 'Firda Mareta Naskah', 'masuk', '114.10.70.167',   $b],
                    ['2026-08-11 08:05:00', 'Rizka Noviyanti',     'masuk', '114.10.70.167',   $b],
                    ['2026-08-11 08:10:00', 'Asih Wulan Sari',     'masuk', '114.10.70.167',   $b],
                    ['2026-08-11 09:00:00', 'Firda Mareta Naskah', 'pulang','110.137.194.229', $b],
                    ['2026-08-11 09:05:00', 'Rizka Noviyanti',     'pulang','110.137.194.229', $b],
                    ['2026-08-11 09:10:00', 'Asih Wulan Sari',     'pulang','110.137.194.229', $b],
                ],
                'periksa' => function (array $j) {
                    $this->takAdaPendaftaran($j);
                    foreach ($j as $i => $r) {
                        $this->wajib($r['belajar']['alasan'] === 'bukan_ipv6', "kejadian ke-" . ($i + 1) . " harus ditolak sebagai bukan_ipv6 — dapat " . $r['belajar']['alasan']);
                    }
                },
            ],
            [
                'kode'  => 'K7',
                'judul' => '3 orang cabang sama tapi /48 ASING (dikarang — pola "cabang baru")',
                'harap' => 'TAWARKAN ke IT + notifikasi benar-benar terkirim, sekali saja',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 08:00:00', 'Firda Mareta Naskah', 'masuk', '2404:c0:20b3:82d9:1::', $b],
                    ['2026-08-11 08:05:00', 'Rizka Noviyanti',     'masuk', '2404:c0:20b3:82d9:2::', $b],
                    ['2026-08-11 08:10:00', 'Asih Wulan Sari',     'masuk', '2404:c0:20b3:82d9:3::', $b],
                ],
                'periksa' => function (array $j) {
                    $this->takAdaPendaftaran($j);
                    // Pagar /48 berdiri SENDIRI dari ambang orang: begitu orang
                    // ke-2 datang (ambang tercapai), vonisnya sudah TAWARKAN —
                    // bukan DAFTAR. Inilah yang menjaga keamanan sesudah ambang
                    // diturunkan, dan kini juga sesudah pencocokan dilebarkan.
                    $this->wajib($j[1]['belajar']['vonis'] === WifiBelajarSendiri::TAWARKAN, 'di orang ke-2 (ambang tercapai) vonis harus TAWARKAN, bukan DAFTAR — dapat ' . $j[1]['belajar']['vonis']);
                    $this->wajib($j[1]['belajar']['alasan'] === 'kerabat_48_tak_ada', 'alasannya harus pagar /48 — dapat ' . $j[1]['belajar']['alasan']);

                    $akhir = $j[2]['belajar'];
                    $this->wajib($akhir['vonis'] === WifiBelajarSendiri::TAWARKAN, 'vonis harus TAWARKAN — dapat ' . $akhir['vonis']);
                    $this->wajib($akhir['n'] === 3, 'tiga orang harus tetap terhitung (yang gagal cuma pagar /48) — dapat ' . $akhir['n']);

                    // BARU 21 Agu 2026. Sebelum ini, TAWARKAN cuma dikembalikan
                    // sebagai nilai dan TAK PERNAH sampai ke siapa pun — tak ada
                    // notifikasi, tak ada layar yang membacanya, meski docblock
                    // kelasnya menjanjikan "tawarkan sekali klik ke IT". Karena
                    // pelebaran /48 mematikan jalur DAFTAR (satu-satunya yang
                    // dulu memberi tahu IT), tanpa ini halaman WiFi Cabang akan
                    // membisu total.
                    $notif = DB::table('admin_notification')->where('type', 'wifi_tawaran')->get();
                    $harus = count(WifiBelajarSendiri::ROLE_PENINJAU);
                    $this->wajib($notif->count() === $harus,
                        "harus tepat {$harus} notifikasi (satu per role, TIDAK berulang walau dua kejadian bervonis TAWARKAN) — dapat " . $notif->count());
                    $bersebut = $notif->filter(fn ($r) => str_contains((string) $r->title, '2404:c0:20b3:82d9::'))->count();
                    $this->wajib($bersebut === $notif->count(), 'judul notifikasi harus menyebut prefixnya');
                },
            ],
            [
                'kode'  => 'K8',
                'judul' => 'Saklar dimatikan · /48 asing dipakai bertiga',
                'harap' => 'TIDAK daftar, TIDAK menawarkan, tidak ada notifikasi — fitur mati total',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '0',
                'events' => [
                    ['2026-08-11 08:00:00', 'Firda Mareta Naskah', 'masuk', '2404:c0:20b3:82d9:1::', $b],
                    ['2026-08-11 08:05:00', 'Rizka Noviyanti',     'masuk', '2404:c0:20b3:82d9:2::', $b],
                    ['2026-08-11 08:10:00', 'Asih Wulan Sari',     'masuk', '2404:c0:20b3:82d9:3::', $b],
                ],
                'periksa' => function (array $j) {
                    $this->takAdaPendaftaran($j);
                    $this->wajib($j[2]['belajar']['alasan'] === 'saklar_mati', 'alasan harus saklar_mati — dapat ' . $j[2]['belajar']['alasan']);
                    $n = DB::table('admin_notification')->whereIn('type', ['wifi_otomatis', 'wifi_tawaran'])->count();
                    $this->wajib($n === 0, "saklar mati berarti IT pun tak diganggu — dapat {$n} notifikasi");
                },
            ],
            [
                'kode'  => 'K9',
                'judul' => 'IT sudah menonaktifkan baris ini · orang bertiga datang lagi',
                'harap' => 'TIDAK cocok & TIDAK daftar ulang — veto IT menang atas kekerabatan /48',
                'baseline' => array_merge(self::BASELINE_BINTARO, [
                    [self::BINTARO, '2001:448a:2092:10d5::', 'nonaktif'],
                ]),
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk', '2001:448a:2092:10d5:7614:ae78:98b6:c1f5', $b],
                    ['2026-08-11 09:36:06', 'Firda Mareta Naskah',        'masuk', '2001:448a:2092:10d5:b0cc:8a6a:22f5:8c53', $b],
                    ['2026-08-11 09:39:55', 'Rizka Noviyanti',            'masuk', '2001:448a:2092:10d5:f424:5430:e974:be48', $b],
                ],
                // KASUS PALING GENTING SESUDAH PELEBARAN /48.
                // Baseline Bintaro berisi 2313 & 134e yang AKTIF dan se-/48
                // dengan 10d5. Tanpa pagar veto di matchBranch, kekerabatan akan
                // menghidupkan kembali prefix yang sudah dimatikan manusia —
                // diam-diam, tanpa gejala. Kasus ini yang menahannya.
                'periksa' => function (array $j) {
                    $this->takAdaPendaftaran($j);
                    foreach ($j as $i => $r) {
                        $this->wajib($r['cocok'] === false,
                            'kejadian ke-' . ($i + 1) . ' TIDAK boleh cocok — /48 sekerabat tak boleh membatalkan veto IT');
                        $this->wajib($r['belajar']['alasan'] === 'ditolak_it',
                            'kejadian ke-' . ($i + 1) . ' harus bilang ditolak_it — dapat ' . $r['belajar']['alasan']);
                    }
                    $tetapMati = AttBranchIp::where('ip', '2001:448a:2092:10d5::')->value('status');
                    $this->wajib($tetapMati === 'nonaktif', "baris harus tetap nonaktif — dapat {$tetapMati}");
                    $n = AttFlag::where('jenis', 'ip_asing')->where('work_date', '2026-08-11')->count();
                    $this->wajib($n === 3, "ketiganya harus tetap ke-flag — dapat {$n}");
                },
            ],
            [
                'kode'  => 'K10',
                'judul' => 'Notifikasi sampai ke SEMUA role peninjau, bukan cuma "IT"',
                'harap' => 'satu baris wifi_tawaran per role di ROLE_PENINJAU',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk', '2404:c0:5d10:7a1:1::', $b],
                    ['2026-08-11 09:36:06', 'Firda Mareta Naskah',        'masuk', '2404:c0:5d10:7a1:2::', $b],
                    ['2026-08-11 09:39:55', 'Rizka Noviyanti',            'masuk', '2404:c0:5d10:7a1:3::', $b],
                ],
                'periksa' => function (array $j) {
                    $this->wajib($j[1]['belajar']['vonis'] === WifiBelajarSendiri::TAWARKAN, 'prasyarat: harus menawarkan dulu — dapat ' . $j[1]['belajar']['vonis']);

                    $sasaran = DB::table('admin_notification')->where('type', 'wifi_tawaran')
                        ->pluck('target_role')->sort()->values()->all();
                    $harap = collect(WifiBelajarSendiri::ROLE_PENINJAU)->sort()->values()->all();
                    $this->wajib($sasaran === $harap,
                        'notifikasi harus ke [' . implode(', ', $harap) . '] — dapat [' . implode(', ', $sasaran) . ']');

                    // Role penerima harus benar-benar ada; salah ketik = notif
                    // yang tak pernah dibaca siapa pun, dan itu tak kelihatan.
                    foreach (WifiBelajarSendiri::ROLE_PENINJAU as $role) {
                        $ada = DB::table('group_path')->where('nama', $role)->exists();
                        $this->wajib($ada, "role \"{$role}\" tak ada di group_path — notifikasinya takkan pernah terbaca");
                    }
                },
            ],
            [
                'kode'  => 'K11',
                'judul' => 'Saklar mati TIDAK mengembalikan pencocokan ke /64',
                'harap' => 'rotasi se-/48 tetap cocok walau belajar-sendiri dimatikan',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '0',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk', '2001:448a:2092:10d5:7614:ae78:98b6:c1f5', $b],
                    ['2026-08-11 09:36:06', 'Firda Mareta Naskah',        'masuk', '2001:448a:2092:10d5:b0cc:8a6a:22f5:8c53', $b],
                ],
                // PERUBAHAN ARTI YANG HARUS DIKETAHUI IT.
                // Saklar `wifi_belajar_sendiri` mematikan PENDAFTARAN OTOMATIS,
                // bukan pencocokan. Sejak 21 Agu 2026 mematikannya TIDAK lagi
                // mengembalikan perilaku lama "cuma /64 persis yang dipercaya".
                // Kalau suatu saat IT memang butuh saklar untuk itu, itu saklar
                // yang berbeda. Dipatok di sini supaya tak ditemukan sebagai
                // kejutan waktu ada insiden.
                'periksa' => function (array $j) {
                    foreach ($j as $i => $r) {
                        $this->wajib($r['cocok'] === true,
                            'orang ke-' . ($i + 1) . ' tetap harus cocok lewat /48 meski saklar belajar mati');
                    }
                    $this->takAdaPendaftaran($j);
                },
            ],
            [
                'kode'  => 'K12',
                'judul' => 'Veto IT berlaku untuk /64 ITU saja, bukan seluruh /48',
                'harap' => '10d5 (diveto) tetap ditolak, 2777 (saudaranya) tetap cocok',
                'baseline' => array_merge(self::BASELINE_BINTARO, [
                    [self::BINTARO, '2001:448a:2092:10d5::', 'nonaktif'],
                ]),
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk',  '2001:448a:2092:10d5:7614:ae78:98b6:c1f5', $b],
                    ['2026-08-11 18:20:00', 'Alpajri Suhendar Al Qhusni', 'pulang', '2001:448a:2092:2777:2610:b0ab:1fa:adb7',  $b],
                ],
                // Menolak satu jalur tak boleh berarti menolak seluruh gedung.
                // Kalau veto menular ke se-/48, satu klik IT akan mematikan
                // cabangnya tanpa ada yang menyadari sebabnya.
                'periksa' => function (array $j) {
                    $this->wajib($j[0]['cocok'] === false, 'prefix yang diveto harus tetap ditolak');
                    $this->wajib($j[0]['belajar']['alasan'] === 'ditolak_it', 'alasannya harus ditolak_it — dapat ' . $j[0]['belajar']['alasan']);
                    $this->wajib($j[1]['cocok'] === true, 'saudara se-/48 yang TIDAK diveto harus tetap cocok');
                },
            ],
            [
                'kode'  => 'K13',
                'judul' => 'Baris nonaktif BEREKOR bukan veto — cuma bersih-bersih rotasi lama',
                'harap' => 'tetap cocok lewat /48; veto hanya kalau ip-nya persis teks prefix',
                'baseline' => array_merge(self::BASELINE_BINTARO, [
                    [self::BINTARO, '2001:448a:2092:10d5:aaaa:bbbb:cccc:dddd', 'nonaktif'],
                ]),
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk', '2001:448a:2092:10d5:7614:ae78:98b6:c1f5', $b],
                ],
                // Pembedaan ini sudah dipakai WifiBelajarSendiri::nilai() sejak
                // dulu (baris berekor = sisa gaya lama, bukan keputusan manusia).
                // matchBranch WAJIB memakai pembedaan yang sama, kalau tidak dua
                // tempat akan menjawab berbeda untuk pertanyaan yang sama — dan
                // di prod ada TUJUH baris berekor nonaktif seperti ini.
                'periksa' => function (array $j) {
                    $this->wajib($j[0]['cocok'] === true,
                        'baris nonaktif berekor TIDAK boleh dianggap veto — dapat tidak cocok');
                    $this->wajib($j[0]['belajar']['alasan'] === 'sudah_cocok',
                        'harusnya cocok lewat /48 — dapat ' . $j[0]['belajar']['alasan']);
                },
            ],
            [
                'kode'  => 'K14',
                'judul' => 'IPv4 TIDAK ikut dilebarkan — tetap cocok-penuh',
                'harap' => '180.252.169.219 (beda satu angka) harus ditolak; .218 cocok',
                'baseline' => [
                    [self::BINTARO, '180.252.169.218'],
                    [self::BINTARO, '2001:448a:2092:2313:1fe9:bc37:8ef7:93cd'],
                ],
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 08:00:00', 'Firda Mareta Naskah', 'masuk',  '180.252.169.219', $b],
                    ['2026-08-11 18:00:00', 'Firda Mareta Naskah', 'pulang', '180.252.169.218', $b],
                ],
                // IPv4 tak punya padanan /48 yang aman: melebarkannya ke /24 atau
                // /16 berarti menerima satu wilayah ISP utuh. Jalur IPv4 sengaja
                // TIDAK disentuh, dan itu harus terkunci — bukan sekadar tertulis.
                'periksa' => function (array $j) {
                    $this->wajib($j[0]['cocok'] === false, 'IPv4 tetangga TIDAK boleh cocok — pelebaran hanya untuk IPv6');
                    $this->wajib($j[1]['cocok'] === true, 'IPv4 yang persis terdaftar harus cocok');
                },
            ],
            [
                'kode'  => 'K15',
                'judul' => 'Pagar pendaftaran: /48 milik cabang lain harus tertolak',
                'harap' => 'pemilik48Lain menunjuk baris bentrok; /48 sendiri & IPv4 lolos',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [],
                // SATU-SATUNYA kasus di berkas ini yang TIDAK lewat jalur absen
                // (lihat docblock kelas). Sengaja: pagar inilah yang menjaga
                // asumsi "satu /48 = satu pelanggan Telkom", dan kalau asumsi itu
                // bocor seluruh pelebaran /48 ikut bocor. Terlalu penting untuk
                // cuma hidup di komentar.
                'periksa' => function (array $j) {
                    // /48 Bintaro didaftarkan ke Sunter → harus bentrok.
                    $bentrok = AttBranchIp::pemilik48Lain('2001:448a:2092:beef:1::', self::SUNTER);
                    $this->wajib($bentrok !== null, '/48 Bintaro yang didaftarkan ke Sunter harus tertolak');
                    $this->wajib($bentrok && $bentrok->raw_branch_id === self::BINTARO,
                        'baris bentrok harus menunjuk Bintaro sebagai pemilik sah');

                    // /48 yang sama, cabang yang sama → bukan bentrok.
                    $this->wajib(AttBranchIp::pemilik48Lain('2001:448a:2092:beef:1::', self::BINTARO) === null,
                        'menambah jalur di /48 miliknya sendiri harus boleh');

                    // /48 yang belum dimiliki siapa pun → bukan bentrok.
                    $this->wajib(AttBranchIp::pemilik48Lain('2404:c0:20b3:82d9:1::', self::SUNTER) === null,
                        '/48 yang belum dimiliki siapa pun harus boleh didaftarkan');

                    // IPv4 tak punya /48 → tak pernah bentrok lewat pintu ini.
                    $this->wajib(AttBranchIp::pemilik48Lain('180.252.168.224', self::SUNTER) === null,
                        'IPv4 tak boleh dinilai lewat pagar /48');
                },
            ],
        ];
    }

    // ── Mesin ────────────────────────────────────────────────────────────────

    private function jalankan(array $k): void
    {
        $this->newLine();
        $this->line("<fg=yellow>── {$k['kode']} · {$k['judul']}</>");
        $this->line("   harap: {$k['harap']}");

        $this->bersihkan();
        $this->pasangBaseline($k['baseline']);
        Setting::put(WifiBelajarSendiri::KUNCI_SAKLAR, $k['saklar']);

        $hp = new AttendanceHpAbsen();
        $jejak = [];

        foreach ($k['events'] as [$jam, $nama, $jenis, $ip, $cabang]) {
            Carbon::setTestNow(Carbon::parse($jam));

            $hasil = $hp->absen($this->userId[$nama], $jenis, 'uji/wifi-belajar.jpg', $ip, $cabang, null);
            $hasil['nama'] = $nama;
            $hasil['belajar'] ??= ['vonis' => 'n/a', 'alasan' => 'sudah_cocok', 'n' => 0, 'prefix' => null];
            $jejak[] = $hasil;

            $v = $hasil['belajar'];
            $tanda = $hasil['cocok'] ? '<fg=green>cocok</>'
                : ($v['vonis'] === WifiBelajarSendiri::DAFTAR ? '<fg=cyan>DAFTAR</>'
                : ($v['vonis'] === WifiBelajarSendiri::TAWARKAN ? '<fg=magenta>tawarkan</>' : 'tolak'));

            $this->line(sprintf('   %s  %-28s %-7s %-11s %s',
                substr($jam, 5, 11), $nama, $tanda, $v['alasan'], 'n=' . $v['n']));
        }

        Carbon::setTestNow();

        $sebelum = count($this->gagal);
        ($k['periksa'])($jejak);

        if (count($this->gagal) === $sebelum) {
            $this->line("   <fg=green>✓ sesuai harapan</>");
        } else {
            $this->kasusGagal++;
            foreach (array_slice($this->gagal, $sebelum) as $g) $this->line("   <fg=red>✗ {$g}</>");
        }
    }

    /** Hapus jejak absensi di jendela uji (5–13 Agu 2026) + baris WiFi. */
    private function bersihkan(): void
    {
        DB::transaction(function () {
            $dari = '2026-08-05 00:00:00';
            $sampai = '2026-08-14 00:00:00';

            $buktiIds = AttHpBukti::whereBetween('taken_at', [$dari, $sampai])->pluck('id');
            AttFlag::whereBetween('work_date', ['2026-08-05', '2026-08-13'])->delete();
            AttHpBukti::whereIn('id', $buktiIds)->delete();
            AttScan::where('source', 'mobile')->whereBetween('scanned_at', [$dari, $sampai])->delete();
            AttBranchIp::query()->delete();
            // Kalau tidak, notifikasi kasus sebelumnya menumpuk dan K7/K10
            // menghitung sasaran dari kejadian yang bukan miliknya.
            DB::table('admin_notification')->whereIn('type', ['wifi_otomatis', 'wifi_tawaran'])->delete();
        });
    }

    /**
     * Uji ini menghapus att_branch_ip berkali-kali (tiap kasus butuh baseline
     * yang terkendali), jadi lingkungan tak boleh ditinggal kosong: pasang
     * kembali jalur AKTIF yang sama dengan produksi per 12 Agu 2026, plus
     * saklar menyala. Dengan begitu dev tetap layak dipakai mencoba manual
     * sesudah uji, dan tak ada yang mengira daftarnya memang habis.
     */
    private function pulihkan(): void
    {
        $this->bersihkan();
        Setting::put(WifiBelajarSendiri::KUNCI_SAKLAR, '1');

        $aktif = [
            [self::BINTARO,  '180.252.168.224',                             'Jalur A · IPv4'],
            [self::BINTARO,  '180.252.170.180',                             'Jalur B · IPv4'],
            [self::BINTARO,  '2001:448a:2092:10d5::',                       'Jalur A · IPv6 (rotasi 11 Agu)'],
            [self::BINTARO,  '2001:448a:2092:2777::',                       'Jalur B · IPv6 (rotasi 11 Agu)'],
            [self::SUNTER,   '2001:448a:2062:208c:465d:430d:3098:7584',     'Sunter'],
            [self::SUNTER,   '180.245.81.112',                              'Sunter · IPv4'],
            [self::PAMULANG, '2001:448a:2020:27df:527:c68d:f6d7:21e9',      'Pamulang'],
            [self::KALIDERES,'2001:448a:90b0:ebf:1038:9c18:777b:b5c1',      'Kalideres'],
            [self::CINERE,   '2001:448a:2033:12b8::',                       'Cinere · jalur /64'],
        ];
        foreach ($aktif as [$branch, $ip, $label]) {
            AttBranchIp::create([
                'raw_branch_id' => $branch, 'ip' => $ip,
                'label' => $label . ' · dipulihkan att:wifi-uji',
                'status' => 'aktif', 'sumber' => 'manual',
            ]);
        }

        $this->newLine();
        $this->line('<fg=gray>   daftar WiFi dipulihkan ke ' . count($aktif) . ' jalur aktif (cerminan prod 12 Agu), saklar menyala.</>');
    }

    private function pasangBaseline(array $rows): void
    {
        foreach ($rows as $r) {
            AttBranchIp::create([
                'raw_branch_id' => $r[0],
                'ip'            => $r[1],
                'label'         => 'baseline uji',
                'status'        => $r[2] ?? 'aktif',
                // Baris nonaktif di baseline mewakili "IT sudah meninjau lalu
                // menolak", jadi sumbernya memang otomatis.
                'sumber'        => ($r[2] ?? 'aktif') === 'nonaktif' ? 'otomatis' : 'manual',
            ]);
        }
    }

    private function petakanUser(): void
    {
        $nama = [];
        foreach ($this->kasus() as $k) {
            foreach ($k['events'] as $e) $nama[$e[1]] = true;
        }
        $rows = DB::table('users')->whereIn('nama', array_keys($nama))->get(['id', 'nama']);
        foreach ($rows as $r) $this->userId[$r->nama] = $r->id;

        $hilang = array_diff(array_keys($nama), array_keys($this->userId));
        if ($hilang) {
            throw new \RuntimeException('User tak ditemukan di DB ini: ' . implode(', ', $hilang));
        }
    }

    /** Tak boleh ada baris otomatis yang lahir sepanjang kasus ini. */
    private function takAdaPendaftaran(array $jejak): void
    {
        // AKTIF saja: baris otomatis yang sudah dinonaktifkan IT (baseline K9)
        // memang boleh ada — yang dilarang adalah jalur baru yang hidup.
        $n = AttBranchIp::where('sumber', 'otomatis')->where('status', 'aktif')->count();
        $this->wajib($n === 0, "tidak boleh ada pendaftaran otomatis — lahir {$n} baris");

        foreach ($jejak as $i => $r) {
            $this->wajib($r['belajar']['vonis'] !== WifiBelajarSendiri::DAFTAR,
                'kejadian ke-' . ($i + 1) . ' tidak boleh bervonis DAFTAR');
        }
    }

    private function wajib(bool $syarat, string $pesan): void
    {
        if (! $syarat) $this->gagal[] = $pesan;
    }
}
