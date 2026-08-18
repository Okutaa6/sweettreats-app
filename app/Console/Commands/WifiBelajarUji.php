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
 * yang sama. Tiga kasus terakhir dikarang karena tak ada padanannya di data
 * nyata (orang beda cabang, /48 asing, saklar mati).
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
                'harap' => 'DAFTAR di orang sah ke-2 (Firda 09:36) → flag ip_asing tinggal 2',
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
                'periksa' => function (array $j) {
                    // ⚠️ INI penjaga §6 sekarang: Talitha datang KEDUA dan
                    // fleksibel. Kalau ia ikut terhitung, hitungannya jadi 2 dan
                    // pendaftaran memicu di kejadiannya — jadi assertion ini
                    // yang membuktikan orang fleksibel tak menguatkan apa pun.
                    $this->wajib($j[1]['belajar']['n'] === 1, 'saat Talitha (fleksibel) absen hitungan harus tetap 1 — dapat ' . $j[1]['belajar']['n']);
                    $this->wajib($j[1]['belajar']['vonis'] !== WifiBelajarSendiri::DAFTAR, 'Talitha fleksibel TIDAK boleh memicu pendaftaran — dapat ' . $j[1]['belajar']['vonis']);

                    // Orang sah ke-2 adalah Firda (Alpajri #1, Talitha tak dihitung).
                    $this->wajib($j[2]['belajar']['n'] === 2, 'saat Firda absen hitungannya harus 2 — dapat ' . $j[2]['belajar']['n']);
                    $this->wajib($j[2]['belajar']['vonis'] === WifiBelajarSendiri::DAFTAR, 'Firda (orang sah ke-2) harus memicu pendaftaran — dapat ' . $j[2]['belajar']['vonis'] . '/' . $j[2]['belajar']['alasan']);
                    $this->wajib($j[2]['belajar']['prefix'] === '2001:448a:2092:10d5::', 'yang didaftarkan harus prefix bersih 2001:448a:2092:10d5:: — dapat ' . $j[2]['belajar']['prefix']);
                    // Sesudah terdaftar, rekan yang datang berikutnya langsung cocok.
                    foreach ([3, 4, 5, 6] as $i) {
                        $this->wajib($j[$i]['cocok'] === true, "orang ke-" . ($i + 1) . " ({$j[$i]['nama']}) harusnya sudah COCOK, tak ke-flag lagi");
                    }
                    $n = AttFlag::where('jenis', 'ip_asing')->where('work_date', '2026-08-11')->count();
                    $this->wajib($n === 2, "flag ip_asing 11 Agu harus tinggal 2 (Alpajri, Firda) — dapat {$n}");
                },
            ],
            [
                'kode'  => 'K2',
                'judul' => 'Jalur kedua 2777 · Firda 11/8 malam + Alpajri & Yovanka 12/8 pagi',
                'harap' => 'DAFTAR di Alpajri (orang sah ke-2) — bukti melintasi HARI, jendela 72 jam',
                'baseline' => array_merge(self::BASELINE_BINTARO, [[self::BINTARO, '2001:448a:2092:10d5::']]),
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 21:15:20', 'Firda Mareta Naskah',        'pulang', '2001:448a:2092:2777:b0cc:8a6a:22f5:8c53', $b],
                    ['2026-08-12 07:35:41', 'Alpajri Suhendar Al Qhusni', 'masuk',  '2001:448a:2092:2777:2610:b0ab:1fa:adb7',  $b],
                    ['2026-08-12 09:19:01', 'Yovanka Rima Millennia',     'masuk',  '2001:448a:2092:2777:1893:da0b:a1c4:2660', $b],
                ],
                // Sejak ambang turun jadi 2 (18 Agu), kasus ini berubah arti:
                // dulu ia membuktikan "2 orang belum cukup", sekarang ia
                // membuktikan bukti boleh MELINTASI HARI selama masih di dalam
                // jendela 72 jam — Firda malam 11/8, Alpajri pagi 12/8.
                // Penjaga "fleksibel tak dihitung" pindah ke K1.
                'periksa' => function (array $j) {
                    $this->wajib($j[0]['belajar']['n'] === 1, 'Firda sendirian harus terhitung 1 — dapat ' . $j[0]['belajar']['n']);
                    $this->wajib($j[0]['belajar']['vonis'] !== WifiBelajarSendiri::DAFTAR, 'satu orang tak boleh cukup — dapat ' . $j[0]['belajar']['vonis']);

                    $this->wajib($j[1]['belajar']['vonis'] === WifiBelajarSendiri::DAFTAR, 'Alpajri (hari BERIKUTNYA) harus memicu pendaftaran — dapat ' . $j[1]['belajar']['vonis'] . '/' . $j[1]['belajar']['alasan']);
                    $this->wajib($j[1]['belajar']['n'] === 2, 'hitungannya harus 2 — dapat ' . $j[1]['belajar']['n']);
                    $this->wajib($j[1]['belajar']['prefix'] === '2001:448a:2092:2777::', 'prefix yang didaftarkan harus 2777:: — dapat ' . $j[1]['belajar']['prefix']);

                    // Yovanka datang sesudahnya → jaringannya sudah dikenal.
                    $this->wajib($j[2]['cocok'] === true, 'Yovanka harusnya sudah COCOK, tak ke-flag lagi');
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
                'judul' => '3 orang di satu jaringan tapi BEDA cabang (dikarang)',
                'harap' => 'TIDAK daftar — tak ada satu cabang pun yang mencapai 3',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 08:00:00', 'Firda Mareta Naskah', 'masuk', '2001:448a:2092:9911:1::', $b],
                    ['2026-08-11 08:05:00', 'Rizka Noviyanti',     'masuk', '2001:448a:2092:9911:2::', self::SUNTER],
                    ['2026-08-11 08:10:00', 'Asih Wulan Sari',     'masuk', '2001:448a:2092:9911:3::', self::CINERE],
                ],
                'periksa' => fn (array $j) => $this->takAdaPendaftaran($j),
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
                'harap' => 'TAWARKAN ke IT, bukan daftar otomatis (§3)',
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
                    // diturunkan, jadi assertion ini yang paling penting di sini.
                    $this->wajib($j[1]['belajar']['vonis'] === WifiBelajarSendiri::TAWARKAN, 'di orang ke-2 (ambang tercapai) vonis harus TAWARKAN, bukan DAFTAR — dapat ' . $j[1]['belajar']['vonis']);
                    $this->wajib($j[1]['belajar']['alasan'] === 'kerabat_48_tak_ada', 'alasannya harus pagar /48 — dapat ' . $j[1]['belajar']['alasan']);

                    $akhir = $j[2]['belajar'];
                    $this->wajib($akhir['vonis'] === WifiBelajarSendiri::TAWARKAN, 'vonis harus TAWARKAN — dapat ' . $akhir['vonis']);
                    $this->wajib($akhir['n'] === 3, 'tiga orang harus tetap terhitung (yang gagal cuma pagar /48) — dapat ' . $akhir['n']);
                },
            ],
            [
                'kode'  => 'K8',
                'judul' => 'Saklar dimatikan · ulangi K1 yang tadinya lulus',
                'harap' => 'TIDAK daftar — fitur mati total',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '0',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk', '2001:448a:2092:10d5:7614:ae78:98b6:c1f5', $b],
                    ['2026-08-11 09:36:06', 'Firda Mareta Naskah',        'masuk', '2001:448a:2092:10d5:b0cc:8a6a:22f5:8c53', $b],
                    ['2026-08-11 09:39:55', 'Rizka Noviyanti',            'masuk', '2001:448a:2092:10d5:f424:5430:e974:be48', $b],
                ],
                'periksa' => function (array $j) {
                    $this->takAdaPendaftaran($j);
                    $this->wajib($j[2]['belajar']['alasan'] === 'saklar_mati', 'alasan harus saklar_mati — dapat ' . $j[2]['belajar']['alasan']);
                },
            ],
            [
                'kode'  => 'K9',
                'judul' => 'IT sudah menonaktifkan baris ini · orang bertiga datang lagi',
                'harap' => 'TIDAK daftar ulang, dan vonisnya jujur bilang ditolak_it',
                'baseline' => array_merge(self::BASELINE_BINTARO, [
                    [self::BINTARO, '2001:448a:2092:10d5::', 'nonaktif'],
                ]),
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk', '2001:448a:2092:10d5:7614:ae78:98b6:c1f5', $b],
                    ['2026-08-11 09:36:06', 'Firda Mareta Naskah',        'masuk', '2001:448a:2092:10d5:b0cc:8a6a:22f5:8c53', $b],
                    ['2026-08-11 09:39:55', 'Rizka Noviyanti',            'masuk', '2001:448a:2092:10d5:f424:5430:e974:be48', $b],
                ],
                'periksa' => function (array $j) {
                    $this->takAdaPendaftaran($j);
                    foreach ($j as $i => $r) {
                        $this->wajib($r['belajar']['alasan'] === 'ditolak_it',
                            'kejadian ke-' . ($i + 1) . ' harus bilang ditolak_it — dapat ' . $r['belajar']['alasan']);
                    }
                    // Veto IT tak boleh diam-diam dihidupkan lagi.
                    $tetapMati = AttBranchIp::where('ip', '2001:448a:2092:10d5::')->value('status');
                    $this->wajib($tetapMati === 'nonaktif', "baris harus tetap nonaktif — dapat {$tetapMati}");
                    // Semua orang tetap ke-flag: jaringannya memang tak diakui.
                    $n = AttFlag::where('jenis', 'ip_asing')->where('work_date', '2026-08-11')->count();
                    $this->wajib($n === 3, "ketiganya harus tetap ke-flag — dapat {$n}");
                },
            ],
            [
                'kode'  => 'K10',
                'judul' => 'Notifikasi sampai ke SEMUA role peninjau, bukan cuma "IT"',
                'harap' => 'satu baris notifikasi per role di ROLE_PENINJAU',
                'baseline' => self::BASELINE_BINTARO,
                'saklar' => '1',
                'events' => [
                    ['2026-08-11 07:42:32', 'Alpajri Suhendar Al Qhusni', 'masuk', '2001:448a:2092:10d5:7614:ae78:98b6:c1f5', $b],
                    ['2026-08-11 09:36:06', 'Firda Mareta Naskah',        'masuk', '2001:448a:2092:10d5:b0cc:8a6a:22f5:8c53', $b],
                    ['2026-08-11 09:39:55', 'Rizka Noviyanti',            'masuk', '2001:448a:2092:10d5:f424:5430:e974:be48', $b],
                ],
                'periksa' => function (array $j) {
                    // Ambang 2 → pemicunya Firda (kejadian ke-2), bukan Rizka.
                    $this->wajib($j[1]['belajar']['vonis'] === WifiBelajarSendiri::DAFTAR, 'prasyarat: harus mendaftar dulu — dapat ' . $j[1]['belajar']['vonis']);

                    $sasaran = DB::table('admin_notification')->where('type', 'wifi_otomatis')
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
            // Kalau tidak, notifikasi kasus sebelumnya menumpuk dan K10 menghitung
            // sasaran dari kejadian yang bukan miliknya.
            DB::table('admin_notification')->where('type', 'wifi_otomatis')->delete();
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
