<?php

namespace App\Console\Commands;

use App\Support\OmnicareIdentitas as Ident;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Auto-match pasien SweetTreats (raw_customer, dari sheets) ke pasien
 * Omnicare (omnicare_patient, dari API) — orang yang SAMA, ID beda.
 *
 *   php artisan omnicare:match-patients              # match yang belum ke-link
 *   php artisan omnicare:match-patients --rematch    # hapus link 'system' lalu match ulang
 *   php artisan omnicare:match-patients --dry-run    # hitung saja, tidak tulis DB
 *
 * Strategi (lihat customer_omnicare_link SQL):
 *   - Phone = GATE. Tanpa kecocokan nomor (normalized), tidak dipertimbangkan.
 *     Alasan: model contact-anchored (1 nomor banyak pasien) bikin phone
 *     PERLU tapi TIDAK cukup — nama/DOB yang membedakan anggota keluarga.
 *   - Skor 0-100: phone 30 + DOB 35 + nama 35 (exact). Auto-link bila ≥ 60.
 *   - Ambiguous (2 kandidat skor mirip) TIDAK di-link — disisakan review manual.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * ⭐ PERUBAHAN 2026-09-16 — SATU PASIEN BOLEH PUNYA BANYAK pid
 * ────────────────────────────────────────────────────────────────────────────
 * Omnicare menerbitkan `p_id` PER CABANG (B- · S- · C- · D- · P-). 892 orang
 * punya lebih dari satu pid — 1.797 pid kembar. Contoh: ISMU RAHARJO →
 * B-2007-2758 dan B-2008-2793.
 *
 * Versi lama memakai `$linkedCustomers` untuk MELEWATI pasien yang sudah
 * tertaut, memaksa 1 pid per pasien. Akibatnya kunjungan pasien di cabang
 * kedua tidak akan pernah bisa terjodoh — selamanya. Terukur: dari 379
 * kunjungan yang nomornya jelas ada di raw_customer tapi tak tertaut,
 * 150 gagal justru KARENA pasien kita sudah tertaut ke pid lain.
 *
 * Sekarang: pasien yang sudah tertaut TETAP DIPINDAI, mencari pid TAMBAHAN.
 *
 * ⛔ DUA PAGAR YANG TIDAK BOLEH DILONGGARKAN:
 *
 *   1. Satu `omnicare_pid` tetap hanya menempel ke SATU `raw_customer`.
 *      Dijaga UNIQUE `uq_col_omnicare` di database (sengaja dipertahankan
 *      saat `uq_col_customer` dilepas) DAN oleh `$linkedPids` di sini.
 *      Inilah yang mencegah dua orang bertukar rekam medis.
 *
 *   2. Penjaga ambiguitas TETAP ADA. Ia tidak dibuang — ia DIPERTAJAM.
 *      Dulu: "kandidat #2 terlalu dekat skornya → jangan link apa pun."
 *      Itu memblokir justru kasus yang mau kita perbaiki, karena dua pid
 *      milik ORANG YANG SAMA selalu berskor identik.
 *      Sekarang pertanyaannya dua tingkat:
 *        a. Apakah kandidat-kandidat yang berhimpit itu orang yang SAMA
 *           (OmnicareIdentitas::samaOrang — nama token-set persis, DOB tidak
 *           bertentangan)? Kalau ya → taut SEMUANYA, itu memang satu orang.
 *        b. Kalau ada kandidat berhimpit yang BUKAN orang yang sama → tahan,
 *           persis seperti dulu. Itu ibu & anak di satu nomor.
 *      Kita punya 682 kontak dengan 2–7 pasien di satu nomor; pagar ini nyata
 *      gunanya. Yang berubah cuma: ia berhenti menghukum orang yang cuma
 *      punya banyak kartu.
 *
 * ⚠️ PRASYARAT SQL. `database/sql/2026_09_16_customer_omnicare_link_multi_pid.sql`
 *    HARUS sudah dijalankan. Selama `uq_col_customer` masih terpasang, setiap
 *    pid tambahan ditolak "Duplicate entry" dan — karena insert dilakukan per
 *    chunk 500 — seluruh chunk-nya ikut gagal.
 */
class MatchOmnicarePatients extends Command
{
    protected $signature = 'omnicare:match-patients
                            {--rematch : Hapus link buatan sistem lalu match ulang dari nol}
                            {--dry-run : Tidak tulis ke DB, hanya laporkan hasil}
                            {--min-score=60 : Skor minimum untuk auto-link (default 60)}
                            {--daftar-ambigu : Cetak daftar kasus ambigu yang disisakan untuk manusia}';

    protected $description = 'Auto-match raw_customer ↔ omnicare_patient (phone+DOB+nama) ke customer_omnicare_link';

    /** Selisih skor minimum antara kandidat #1 dan penantangnya supaya tidak ambigu. */
    private const AMBIGUITY_MARGIN = 10;

    private bool $dryRun = false;
    private int  $minScore = 60;

    public function handle(): int
    {
        $this->dryRun  = (bool) $this->option('dry-run');
        $this->minScore = max(40, (int) $this->option('min-score'));

        $this->line('');
        $this->line(str_repeat('═', 64));
        $this->info('Omnicare Patient Match — ' . now()->format('Y-m-d H:i:s'));
        $this->line(str_repeat('═', 64));
        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada penulisan DB');
        }

        // ── Potret SEBELUM (kriteria terima #1) ───────────────────────────
        //    Diambil SEBELUM --rematch menghapus apa pun; kalau tidak, angka
        //    "sebelum" sudah memotret keadaan sesudah penghapusan dan
        //    perbandingannya jadi bohong tanpa terlihat bohong.
        $sebelum = $this->potret();

        if ($this->option('rematch') && ! $this->dryRun) {
            $deleted = DB::table('customer_omnicare_link')->where('matched_by', 'system')->delete();
            $this->warn("▶ --rematch: {$deleted} link buatan sistem dihapus");
        }

        // ── Link yang sudah ada ───────────────────────────────────────────
        // pidTertaut  : pagar 1:1 di sisi pid — pid ini SUDAH punya pemilik.
        // pidPerPasien: pid-pid milik tiap pasien, dipakai untuk mencari pid
        //               TAMBAHAN milik orang yang sama (bukan untuk skip lagi).
        $existing = DB::table('customer_omnicare_link')
            ->select('raw_customer_id', 'omnicare_pid')
            ->get();

        $linkedPids   = $existing->pluck('omnicare_pid')->flip();
        $pidPerPasien = [];
        foreach ($existing as $e) {
            $pidPerPasien[$e->raw_customer_id][] = $e->omnicare_pid;
        }

        // ── Index pasien Omnicare berdasarkan phone key ───────────────────
        $this->line('▶ Memuat omnicare_patient & membangun index nomor…');
        [$phoneIndex, $identitasPid] = $this->buildPhoneIndex($linkedPids);
        $this->line('  ' . count($phoneIndex) . ' nomor unik ter-index');

        // ── Iterasi raw_customer ──────────────────────────────────────────
        $customers = DB::table('raw_customer')
            ->select('id', 'name', 'phone', 'tanggal_lahir')
            ->get();

        $stats = [
            'total'         => $customers->count(),
            'sudah_tertaut' => 0,   // punya link, tidak ada pid tambahan ketemu
            'no_phone'      => 0,
            'no_candidate'  => 0,
            'below_score'   => 0,
            'ambiguous'     => 0,
            'baru'          => 0,   // pasien yang tadinya belum tertaut sama sekali
            'tambahan'      => 0,   // pid EKSTRA (kembar) yang benar-benar di-stage
            'kembar_dob'    => 0,   // pid tambahan yang dikuatkan DOB
            'kembar_nama'   => 0,   // pid tambahan berdasar nama saja (DOB kosong)
            'ditolak_dob'   => 0,   // KANDIDAT ditolak: DOB pasien KITA bertentangan
            'terblokir_dob' => 0,   // …di antaranya yang benar-benar MENGUBAH hasil
            'baru_pasien'   => [],  // penampung sementara, dibuang sebelum dilaporkan
        ];

        // staged[omnicarePid] = baris link kandidat terbaik (resolve bentrok by skor)
        $staged     = [];
        $ambigu     = [];   // untuk daftar tinjauan manusia
        $ditolakDob = [];   // idem — ditolak karena DOB kita bertentangan

        foreach ($customers as $c) {
            $pidLama    = $pidPerPasien[$c->id] ?? [];
            $adaLinkLama = ! empty($pidLama);

            $keys = $this->phoneKeys($c->phone);
            if (empty($keys)) {
                $adaLinkLama ? $stats['sudah_tertaut']++ : $stats['no_phone']++;
                continue;
            }

            // Kumpulkan kandidat unik dari semua phone key customer ini.
            // (Index sudah membuang pid yang tertaut ke SIAPA PUN — pagar 1.)
            $candidates = [];
            foreach ($keys as $k) {
                foreach ($phoneIndex[$k] ?? [] as $p) {
                    $candidates[$p['pid']] = $p;
                }
            }
            if (empty($candidates)) {
                $adaLinkLama ? $stats['sudah_tertaut']++ : $stats['no_candidate']++;
                continue;
            }

            $scored = [];
            foreach ($candidates as $p) {
                $scored[] = $this->score($c, $p) + ['ident' => $p];
            }
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

            $layak = array_values(array_filter($scored, fn($s) => $s['score'] >= $this->minScore));
            if (empty($layak)) {
                $adaLinkLama ? $stats['sudah_tertaut']++ : $stats['below_score']++;
                continue;
            }

            // Identitas pid yang SUDAH dipegang pasien ini. Kosong = pasien
            // belum tertaut sama sekali. Keputusannya sendiri ada di
            // pilihKandidat() — murni, dan diuji di tests/Unit/PenjodohanPidTest.php.
            $identLama = [];
            foreach ($pidLama as $pid) {
                if (isset($identitasPid[$pid])) {
                    $identLama[] = $identitasPid[$pid];
                }
            }
            if ($adaLinkLama && empty($identLama)) {
                // pid lama tidak ada di omnicare_patient (sinkron pasien tertinggal)
                $stats['sudah_tertaut']++;
                continue;
            }

            $putusan = $this->pilihKandidat($layak, $identLama, Ident::validDob($c->tanggal_lahir));

            // ⚠️ "Berapa kandidat ditolak" BUKAN "berapa tautan dicegah".
            //    Kandidat yang ditolak bisa saja kandidat kalah yang memang tak
            //    akan dipakai, atau akan digugurkan pagar berikutnya. Satu-
            //    satunya cara jujur menjawabnya adalah menghitung tandingannya:
            //    jalankan keputusan yang SAMA tanpa pagar DOB, lalu bandingkan
            //    pid yang terambil. Murah — hanya untuk pelanggan yang memang
            //    punya penolakan (8 di lokal, ~130 di prod).
            if (! empty($putusan['ditolak'])) {
                $tanpaPagar = $this->pilihKandidat($layak, $identLama, null);
                if ($this->pidTerambil($tanpaPagar) !== $this->pidTerambil($putusan)) {
                    $stats['terblokir_dob']++;
                }
            }
            foreach ($putusan['ditolak'] as $tolak) {
                $stats['ditolak_dob']++;
                $ditolakDob[] = [
                    'raw_customer_id' => $c->id,
                    'nama'            => (string) $c->name,
                    'dob_kita'        => (string) substr((string) $c->tanggal_lahir, 0, 10),
                    'pid'             => $tolak['kandidat']['pid'],
                    'dob_omnicare'    => (string) ($tolak['kandidat']['ident']['dob'] ?? '—'),
                    'skor'            => $tolak['kandidat']['score'],
                ];
            }

            if ($putusan['ambigu'] !== null) {
                $stats['ambiguous']++;
                $ambigu[] = [
                    'raw_customer_id' => $c->id,
                    'nama'            => (string) $c->name,
                    'phone'           => (string) $c->phone,
                    'pid_1'           => $putusan['ambigu']['best']['pid'],
                    'skor_1'          => $putusan['ambigu']['best']['score'],
                    'pid_2'           => $putusan['ambigu']['penantang']['pid'],
                    'skor_2'          => $putusan['ambigu']['penantang']['score'],
                ];
                continue;
            }

            if (empty($putusan['ambil'])) {
                $adaLinkLama ? $stats['sudah_tertaut']++ : $stats['below_score']++;
                continue;
            }

            foreach ($putusan['ambil'] as $ambil) {
                $this->stage($staged, $c->id, $ambil['kandidat'], $ambil['tanda']);
            }
        }

        // ── Hitung hasil dari $staged, BUKAN dari penghitung di dalam loop ──
        //    Satu pid bisa direbut pasien lain yang skornya lebih tinggi
        //    (lihat stage()). Penghitung di dalam loop sudah terlanjur
        //    mencatatnya, jadi laporannya akan rapi dan salah. Menghitung dari
        //    hasil akhir membuat angka laporan = angka yang benar-benar ditulis.
        foreach ($staged as $r) {
            $kembar = str_contains((string) $r['match_method'], 'pid_kembar');
            if ($kembar) {
                $stats['tambahan']++;
                $stats[str_ends_with((string) $r['match_method'], '_nama') ? 'kembar_nama' : 'kembar_dob']++;
            }
            if (! isset($pidPerPasien[$r['raw_customer_id']])) {
                $stats['baru_pasien'][$r['raw_customer_id']] = true;
            }
        }
        $stats['baru'] = count($stats['baru_pasien']);
        unset($stats['baru_pasien']);

        // ── Tulis hasil ───────────────────────────────────────────────────
        $ditulis = 0;
        if (! $this->dryRun && ! empty($staged)) {
            $now = now();
            $rows = [];
            foreach ($staged as $r) {
                $rows[] = $r + [
                    'id'         => (string) Str::uuid(),
                    'matched_at' => $now,
                    'matched_by' => 'system',
                    'created_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                // insertOrIgnore, BUKAN insert: kalau `uq_col_omnicare` menolak
                // satu baris, sisa chunk harus tetap masuk. Dengan insert()
                // biasa, satu tabrakan menjatuhkan 500 baris sekaligus dan
                // perintah ini melapor sukses palsu.
                $ditulis += DB::table('customer_omnicare_link')->insertOrIgnore($chunk);
            }
        }

        $sesudah = $this->dryRun
            ? $this->ramalan($sebelum, $staged, $pidPerPasien)
            : $this->potret();

        $this->report($stats, $staged, $sebelum, $sesudah, $ditulis, $ambigu, $ditolakDob);

        return self::SUCCESS;
    }

    /**
     * ⭐ INTI PERUBAHAN 2026-09-16 — pid mana saja yang diambil untuk SATU pasien.
     *
     * Murni: tanpa DB, tanpa state. Itu disengaja — keputusan di sini menentukan
     * siapa berbagi rekam medis dengan siapa, dan harus bisa diuji sendiri.
     * Lihat tests/Unit/PenjodohanPidTest.php.
     *
     * @param array   $layak     kandidat berskor ≥ minScore, TERURUT menurun,
     *                           tiap elemen punya 'pid','score','method','ident'
     * @param array   $identLama identitas pid yang SUDAH tertaut ke pasien ini
     *                           (kosong = pasien belum tertaut sama sekali)
     * @param ?string $dobKita   tanggal lahir pasien KITA (raw_customer).
     *                           ⛔ WAJIB diisi pemanggil — sengaja tanpa nilai
     *                           bawaan supaya tak ada yang diam-diam kehilangan
     *                           pagarnya. `null` berarti memang tidak kita punya.
     *
     * @return array{ambil: array<int,array{kandidat:array,tanda:?string,tambahan:bool,kuat:?string}>,
     *               ambigu: ?array{best:array,penantang:array},
     *               ditolak: array<int,array{kandidat:array,sebab:string}>}
     */
    public function pilihKandidat(array $layak, array $identLama, ?string $dobKita): array
    {
        $kosong = ['ambil' => [], 'ambigu' => null, 'ditolak' => []];
        if (empty($layak)) {
            return $kosong;
        }

        // ─────────────────────────────────────────────────────────────────
        // ⛔ PAGAR NOL — tanggal lahir PASIEN KITA SENDIRI.
        //
        // Temuan audit HUB 16 Sep: seluruh pagar DOB di bawah bekerja di sisi
        // OMNICARE saja (samaOrang membandingkan kandidat dgn pid lama,
        // dobBercabang memeriksa kelompok pid). Tanggal lahir yang KITA pegang
        // tak pernah ikut. Dan `score()` tidak MENGHUKUM DOB yang bertentangan
        // — ia cuma tidak memberi bonus — sehingga
        // phone 30 + nama persis 35 = 65 ≥ 60 tetap auto-link.
        // Akibatnya pid lama tanpa DOB jadi jembatan ke orang yang tanggal
        // lahirnya jelas-jelas bertentangan dengan pasien kita.
        //
        // ⛔ HANYA `bertentangan` yang ditolak — BUKAN `tertukar` maupun
        //    `selisih_sehari`. Impor massal 12 Mei 2026 menulis ~472 tanggal
        //    lahir di sisi KITA dengan hari & bulan tertukar (diukur di prod).
        //    Menolak kategori itu akan membuang ratusan tautan yang BENAR
        //    hanya karena tanggal kita sendiri salah ketik.
        //
        //    Diukur di PROD memakai `bedaDob()` ini sendiri — 130 kandidat yang
        //    DOB-nya berbeda terbagi: `tertukar` 108 · `selisih_sehari` 3 ·
        //    **`bertentangan` 19**. Jadi pagar ini menahan 19, bukan 5.
        //    ⚠️ Angka sebelumnya ("125 / 5") memakai ambang kasar "selisih >5
        //    tahun" — ukuran yang BUKAN ukuran pagar ini. Jangan menjelaskan
        //    sebuah pagar dengan angka yang tidak dihasilkan pagar itu sendiri.
        //
        // ⚠️ 19 itu "ditahan untuk diperiksa manusia", BUKAN "19 orang salah".
        //    Sebagian besar kemungkinan salah ketik tanggal 2–19 hari (ambang
        //    `selisih_sehari` cuma ≤1 hari, jadi mereka jatuh ke sini); sisanya
        //    selisih 5–7 tahun dan memang patut dicurigai orang berbeda.
        //    Penolakannya sengaja TERLIHAT (--daftar-ambigu + arahan ke
        //    `omnicare:tinjau-tautan --dob`), bukan hilang diam-diam — itu yang
        //    membuat menahan lebih murah daripada menyatukan dua orang.
        // ─────────────────────────────────────────────────────────────────
        $ditolak = [];
        if ($dobKita !== null) {
            $lolos = [];
            foreach ($layak as $s) {
                if (Ident::bedaDob($dobKita, $s['ident']['dob'] ?? null) === 'bertentangan') {
                    $ditolak[] = ['kandidat' => $s, 'sebab' => 'dob_pasien_kita_bertentangan'];
                    continue;
                }
                $lolos[] = $s;
            }
            $layak = $lolos;
            if (empty($layak)) {
                // ⚠️ JANGAN menyimpulkan di sini bahwa penolakan ini "mengubah
                //    hasil". Tanpa pagar ini pun kandidatnya bisa saja tetap
                //    gugur di pagar berikutnya — terbukti di lokal: 8 ditolak,
                //    nol hasil berubah. Yang berhak menjawabnya cuma
                //    perbandingan dengan tandingan, dan itu ada di handle().
                return ['ambil' => [], 'ambigu' => null, 'ditolak' => $ditolak];
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // JALUR A — pasien SUDAH tertaut: cari pid TAMBAHAN miliknya.
        //
        // Ujinya bukan skor, melainkan identitas: kandidat hanya diterima
        // kalau ia orang yang SAMA dengan salah satu pid yang sudah kita
        // pegang. Itu uji yang lebih keras daripada margin skor — anggota
        // keluarga di nomor yang sama punya token nama berbeda dan gugur di
        // sini tanpa perlu heuristik apa pun. Karena itu jalur ini tidak
        // memakai penjaga ambiguitas: tidak ada yang sedang dipilih, yang ada
        // cuma pengenalan "ini orang yang sudah kita kenal".
        // ─────────────────────────────────────────────────────────────────
        if (! empty($identLama)) {
            $ambil = [];
            foreach ($layak as $s) {
                foreach ($identLama as $lama) {
                    if (! Ident::samaOrang($s['ident'], $lama)) {
                        continue;
                    }
                    $kuat = Ident::kekuatan($s['ident'], $lama);
                    $ambil[] = [
                        'kandidat' => $s,
                        'tanda'    => 'pid_kembar_' . $kuat,
                        'tambahan' => true,
                        'kuat'     => $kuat,
                    ];
                    break; // satu pid cukup diambil sekali
                }
            }

            return $this->tolakBilaDobBercabang($ambil, $identLama) + ['ditolak' => $ditolak];
        }

        // ─────────────────────────────────────────────────────────────────
        // JALUR B — pasien BELUM tertaut sama sekali.
        // Perilaku lama, dengan satu perbaikan: kandidat yang berhimpit TAPI
        // terbukti orang yang sama tidak lagi dianggap ambigu.
        // ─────────────────────────────────────────────────────────────────
        $best      = $layak[0];
        $kembar    = [$best];
        $penantang = [];
        foreach (array_slice($layak, 1) as $s) {
            if (Ident::samaOrang($s['ident'], $best['ident'])) {
                $kembar[] = $s;
            } else {
                $penantang[] = $s;
            }
        }

        // ⛔ Penjaga ambiguitas — kini diuji terhadap SEMUA penantang, bukan
        //    hanya kandidat #2. Versi lama bisa lolos kalau #2 kebetulan
        //    kembar dan #3 adalah orang LAIN yang sama dekatnya: persis
        //    bentuk "ibu punya dua kartu, anak nebeng nomor yang sama".
        foreach ($penantang as $p) {
            if (($best['score'] - $p['score']) < self::AMBIGUITY_MARGIN) {
                return ['ambil' => [], 'ambigu' => ['best' => $best, 'penantang' => $p], 'ditolak' => $ditolak];
            }
        }

        $ambil = [];
        foreach ($kembar as $i => $s) {
            // ⚠️ Akhiran kekuatan WAJIB ikut, sama seperti jalur A. Tanpa itu
            //    ada dua ejaan untuk satu konsep (`pid_kembar` vs
            //    `pid_kembar_dob`), dan laporan tidak bisa lagi membedakan pid
            //    tambahan yang dikuatkan DOB dari yang cuma bermodal nama —
            //    justru pembedaan yang paling ingin dilihat manusia.
            $kuat = $i > 0 ? Ident::kekuatan($s['ident'], $best['ident']) : null;
            $ambil[] = [
                'kandidat' => $s,
                'tanda'    => $i === 0 ? null : 'pid_kembar_' . $kuat,
                'tambahan' => $i > 0,
                'kuat'     => $kuat,
            ];
        }

        return $this->tolakBilaDobBercabang($ambil, []) + ['ditolak' => $ditolak];
    }

    /**
     * ⛔ PAGAR TERAKHIR — kelompok yang mau disatukan tidak boleh punya lebih
     *    dari satu tanggal lahir.
     *
     * `samaOrang()` memaafkan DOB kosong, dan harus begitu (54,5% tautan lama
     * tanpa DOB). Tapi maaf itu tidak menurun: rekam tanpa DOB cocok dengan
     * ayah MAUPUN anak yang senama, sehingga ia menjadi jembatan yang
     * menyatukan dua orang. Pemeriksaan berpasangan tidak pernah bisa
     * menangkapnya — kelompoknya yang harus diperiksa sekaligus.
     *
     * Kalau bercabang: tidak mengambil apa pun dan menyerahkannya ke manusia.
     * Menahan tautan yang benar itu murah — kita kehilangan satu jodoh.
     * Menyatukan dua orang itu mahal — rekam medis mereka bertukar.
     */
    private function tolakBilaDobBercabang(array $ambil, array $identLama): array
    {
        if (empty($ambil)) {
            return ['ambil' => [], 'ambigu' => null];
        }

        $kumpulan = $identLama;
        foreach ($ambil as $a) {
            $kumpulan[] = $a['kandidat']['ident'];
        }

        if (! Ident::dobBercabang($kumpulan)) {
            return ['ambil' => $ambil, 'ambigu' => null];
        }

        // Untuk daftar tinjauan: tunjukkan dua kandidat kalau ada dua, kalau
        // cuma satu berarti ia bertengkar dengan tautan LAMA pasien ini.
        $penantang = $ambil[1]['kandidat'] ?? ['pid' => '(tautan lama pasien ini)', 'score' => 0];

        return ['ambil' => [], 'ambigu' => [
            'best'      => $ambil[0]['kandidat'],
            'penantang' => $penantang,
        ]];
    }

    /** pid yang benar-benar terambil sebuah putusan — untuk membandingkan tandingan. */
    private function pidTerambil(array $putusan): array
    {
        $pid = array_map(fn($a) => $a['kandidat']['pid'], $putusan['ambil']);
        sort($pid);
        return $pid;
    }

    /**
     * Stage satu baris link. Bentrok pid antar-customer diselesaikan pakai
     * skor tertinggi — pagar "1 pid = 1 pasien" ditegakkan di sini, sebelum
     * database sempat menolaknya.
     */
    private function stage(array &$staged, string $custId, array $s, ?string $tandaMetode): void
    {
        $row = [
            'raw_customer_id'  => $custId,
            'omnicare_pid'     => $s['pid'],
            'match_confidence' => $this->tier($s['score']),
            'match_method'     => $tandaMetode ? $s['method'] . '+' . $tandaMetode : $s['method'],
            'match_score'      => $s['score'],
        ];

        if (isset($staged[$s['pid']])) {
            if ($s['score'] > $staged[$s['pid']]['match_score']) {
                $staged[$s['pid']] = $row;
            }
            return;
        }

        $staged[$s['pid']] = $row;
    }

    /** Potret tabel tautan — kriteria terima #1 & #2. */
    private function potret(): array
    {
        $aktifSejak = now()->subDays(90)->toDateString();

        $aktif = DB::table('raw_cus_reservation')
            ->whereDate('date', '>=', $aktifSejak)
            ->whereNotNull('raw_customer_id')
            ->distinct()
            ->pluck('raw_customer_id');

        $aktifTertaut = $aktif->isEmpty() ? 0 : DB::table('customer_omnicare_link')
            ->whereIn('raw_customer_id', $aktif->all())
            ->distinct()
            ->count('raw_customer_id');

        return [
            'tautan'        => DB::table('customer_omnicare_link')->count(),
            'pasien'        => DB::table('customer_omnicare_link')->distinct()->count('raw_customer_id'),
            'pid'           => DB::table('customer_omnicare_link')->distinct()->count('omnicare_pid'),
            'aktif'         => $aktif->count(),
            'aktif_tertaut' => $aktifTertaut,
            'aktif_ids'     => $aktif->flip(),   // untuk ramalan dry-run
        ];
    }

    /**
     * Ramalan potret SESUDAH untuk --dry-run.
     *
     * ⚠️ Tanpa ini kolom "Sesudah" cuma menyalin "Sebelum" dan dry-run tampak
     *    seolah tidak akan mengubah apa pun — sama menyesatkannya dengan
     *    dry-run --revisit yang dulu melapor nol. Dry-run harus MERAMAL.
     *
     * @param array $staged       baris yang akan ditulis, berkunci pid
     * @param array $pidPerPasien pid yang sudah dipegang tiap pasien
     */
    private function ramalan(array $sebelum, array $staged, array $pidPerPasien): array
    {
        $pasienBaru = [];
        $aktifBaru  = [];

        foreach ($staged as $r) {
            $cid = $r['raw_customer_id'];
            if (isset($pidPerPasien[$cid])) {
                continue;               // pasien ini sudah terhitung tertaut
            }
            $pasienBaru[$cid] = true;
            if (isset($sebelum['aktif_ids'][$cid])) {
                $aktifBaru[$cid] = true;
            }
        }

        return [
            'tautan'        => $sebelum['tautan'] + count($staged),
            'pasien'        => $sebelum['pasien'] + count($pasienBaru),
            'pid'           => $sebelum['pid'] + count($staged),
            'aktif'         => $sebelum['aktif'],
            'aktif_tertaut' => $sebelum['aktif_tertaut'] + count($aktifBaru),
            'aktif_ids'     => $sebelum['aktif_ids'],
        ];
    }

    /**
     * Index omnicare_patient: phoneKey → array kandidat, plus peta identitas
     * SEMUA pid (termasuk yang sudah tertaut — dibutuhkan untuk menilai
     * "apakah kandidat ini orang yang sama dengan pid yang sudah kita pegang").
     *
     * Pasien yang sudah ke-link tetap DIBUANG dari index kandidat: satu pid
     * hanya boleh menempel ke satu pasien.
     *
     * @return array{0:array,1:array}
     */
    private function buildPhoneIndex($linkedPids): array
    {
        $index    = [];
        $identitas = [];

        DB::table('omnicare_patient')
            ->select('pid', 'full_name', 'dob', 'mobile_number', 'whatsapp_number')
            ->orderBy('pid')
            ->chunk(2000, function ($patients) use (&$index, &$identitas, $linkedPids) {
                foreach ($patients as $p) {
                    $cand = [
                        'pid'    => $p->pid,
                        'name'   => Ident::normName($p->full_name),
                        'tokens' => Ident::tokenSet($p->full_name),
                        'dob'    => Ident::validDob($p->dob),
                    ];
                    $identitas[$p->pid] = $cand;

                    if (isset($linkedPids[$p->pid])) {
                        continue; // pagar: pid ini sudah punya pemilik
                    }
                    foreach ([$p->mobile_number, $p->whatsapp_number] as $raw) {
                        foreach ($this->phoneKeys($raw) as $k) {
                            $index[$k][$p->pid] = $cand;
                        }
                    }
                }
            });

        foreach ($index as $k => $byPid) {
            $index[$k] = array_values($byPid);
        }

        return [$index, $identitas];
    }

    /**
     * Skor satu pasangan customer↔pasien. Total 0-100.
     *   phone 30 (selalu kena — index berbasis phone) + DOB 35 + nama ≤35.
     */
    private function score(object $c, array $p): array
    {
        $score = 30;            // phone gate — sudah pasti cocok di sini
        $parts = ['phone'];

        $cDob = Ident::validDob($c->tanggal_lahir);
        if ($cDob !== null && $p['dob'] !== null && $cDob === $p['dob']) {
            $score += 35;
            $parts[] = 'dob';
        }

        [$nameScore, $nameTag] = $this->nameScore($c->name, $p);
        $score += $nameScore;
        if ($nameTag !== null) {
            $parts[] = $nameTag;
        }

        return [
            'pid'    => $p['pid'],
            'score'  => min(100, $score),
            'method' => implode('+', $parts),
        ];
    }

    /**
     * Skor nama: exact/token-set 35, subset 25, fuzzy 15, else 0.
     * @return array{0:int,1:?string}
     */
    private function nameScore(?string $rawName, array $p): array
    {
        $cName   = Ident::normName($rawName);
        $cTokens = Ident::tokenSet($rawName);
        if ($cName === '' || empty($p['tokens'])) {
            return [0, null];
        }

        if ($cName === $p['name'] || $cTokens === $p['tokens']) {
            return [35, 'name'];
        }

        $short = count($cTokens) <= count($p['tokens']) ? $cTokens : $p['tokens'];
        $long  = count($cTokens) <= count($p['tokens']) ? $p['tokens'] : $cTokens;
        if (count($short) >= 2 && count(array_intersect($short, $long)) === count($short)) {
            return [25, 'name~sub'];
        }

        similar_text($cName, $p['name'], $pct);
        if ($pct >= 82.0) {
            return [15, 'name~fuzzy'];
        }

        return [0, null];
    }

    /** Confidence tier dari skor numerik. */
    private function tier(int $score): string
    {
        return match (true) {
            $score >= 100 => 'exact',
            $score >= 80  => 'high',
            default       => 'medium',
        };
    }

    /**
     * Phone key untuk join. Pakai Phone::normalize (standar app) + 1 varian
     * untuk typo umum '00xxx' (orang ketik 0 dua kali) → '0xxx'.
     */
    private function phoneKeys(?string $raw): array
    {
        $keys = [];

        $n = Phone::normalize($raw);
        if ($n !== null) {
            $keys[$n] = true;
        }

        $d = preg_replace('/\D/', '', (string) $raw);
        if (strlen((string) $d) > 3 && str_starts_with((string) $d, '00') && ! str_starts_with((string) $d, '0062')) {
            $n2 = Phone::normalize(substr($d, 1)); // buang satu '0' di depan
            if ($n2 !== null) {
                $keys[$n2] = true;
            }
        }

        return array_keys($keys);
    }

    private function report(array $s, array $staged, array $sebelum, array $sesudah, int $ditulis, array $ambigu, array $ditolakDob): void
    {
        $tiers = ['exact' => 0, 'high' => 0, 'medium' => 0];
        foreach ($staged as $r) {
            $tiers[$r['match_confidence']]++;
        }

        $this->line('');
        $this->line(str_repeat('─', 64));
        $this->info('HASIL MATCHING');
        $this->line(str_repeat('─', 64));
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['raw_customer diproses',          number_format($s['total'])],
                ['  ↳ sudah tertaut, tak ada pid tambahan', number_format($s['sudah_tertaut'])],
                ['  ↳ tanpa nomor valid',          number_format($s['no_phone'])],
                ['  ↳ nomor tak ada di Omnicare',  number_format($s['no_candidate'])],
                ['  ↳ skor di bawah ambang',       number_format($s['below_score'])],
                ['  ↳ ambigu (perlu manual)',      number_format($s['ambiguous'])],
                // ⚠️ Dua angka, sengaja dipisah. Kandidat yang ditolak belum
                //    tentu tautan yang dicegah — ia bisa saja kandidat kalah
                //    yang memang tak akan dipakai. Yang benar-benar mengubah
                //    hasil hanya baris kedua.
                ['  ↳ kandidat ditolak (DOB pasien kita bertentangan)', number_format($s['ditolak_dob'])],
                ['     …yang MENGUBAH hasil (diadu dgn tandingan tanpa pagar)', number_format($s['terblokir_dob'])],
                ['PASIEN BARU tertaut',            number_format($s['baru'])],
                ['pid TAMBAHAN (cabang lain)',     number_format($s['tambahan'])],
                ['  ↳ dikuatkan DOB',              number_format($s['kembar_dob'])],
                ['  ↳ nama saja (DOB kosong)',     number_format($s['kembar_nama'])],
                ['TOTAL baris link di-stage',      number_format(count($staged))],
                ['  ↳ exact (100)',                number_format($tiers['exact'])],
                ['  ↳ high (80-99)',               number_format($tiers['high'])],
                ['  ↳ medium (60-79)',             number_format($tiers['medium'])],
            ]
        );

        // ── Kriteria terima #1 & #2 — sebelum vs sesudah ──────────────────
        $this->line('');
        $this->info('SEBELUM vs SESUDAH (kriteria terima #1 & #2)'
            . ($this->dryRun ? ' — kolom "Sesudah" adalah RAMALAN' : ''));
        $pct = fn($a, $b) => $b > 0 ? round($a / $b * 100, 1) . '%' : '—';
        $this->table(
            ['Metrik', 'Sebelum', 'Sesudah', 'Selisih'],
            [
                ['Jumlah tautan',        number_format($sebelum['tautan']), number_format($sesudah['tautan']), $this->delta($sebelum['tautan'], $sesudah['tautan'])],
                ['raw_customer tertaut', number_format($sebelum['pasien']), number_format($sesudah['pasien']), $this->delta($sebelum['pasien'], $sesudah['pasien'])],
                ['pid tertaut',          number_format($sebelum['pid']),    number_format($sesudah['pid']),    $this->delta($sebelum['pid'], $sesudah['pid'])],
                ['Pasien AKTIF tertaut',
                    $sebelum['aktif_tertaut'] . ' / ' . $sebelum['aktif'] . ' (' . $pct($sebelum['aktif_tertaut'], $sebelum['aktif']) . ')',
                    $sesudah['aktif_tertaut'] . ' / ' . $sesudah['aktif'] . ' (' . $pct($sesudah['aktif_tertaut'], $sesudah['aktif']) . ')',
                    $this->delta($sebelum['aktif_tertaut'], $sesudah['aktif_tertaut'])],
            ]
        );

        // ── Kriteria terima #3 — pagar keselamatan ────────────────────────
        $bocor = DB::table('customer_omnicare_link')
            ->select('omnicare_pid')
            ->groupBy('omnicare_pid')
            ->havingRaw('COUNT(DISTINCT raw_customer_id) > 1')
            ->get();

        $this->line('');
        if ($bocor->isEmpty()) {
            $this->info('✓ PAGAR #3 — nol pid menempel ke lebih dari satu pasien.');
        } else {
            $this->error('⛔ PAGAR #3 BOCOR — ' . $bocor->count() . ' pid menempel ke >1 pasien. BERHENTI dan laporkan:');
            foreach ($bocor->take(20) as $b) {
                $this->error('   ' . $b->omnicare_pid);
            }
        }

        // ── Kriteria terima #4 ────────────────────────────────────────────
        $this->line('▶ PAGAR #4 — perintah ini HANYA menyisipkan baris baru '
            . '(insertOrIgnore); tidak ada UPDATE/DELETE pada tautan lama '
            . 'kecuali dijalankan dengan --rematch.');

        // ── Antrean manusia ───────────────────────────────────────────────
        $this->line('');
        if (empty($ambigu)) {
            $this->line('▶ Tidak ada kasus ambigu baru.');
        } elseif ($this->option('daftar-ambigu')) {
            $this->warn('▶ ' . count($ambigu) . ' kasus AMBIGU disisakan untuk manusia:');
            $this->table(
                ['pasien (kita)', 'nama', 'nomor', 'pid #1', 'skor', 'pid #2', 'skor'],
                array_map(fn($a) => [
                    $a['raw_customer_id'], $a['nama'], $a['phone'],
                    $a['pid_1'], $a['skor_1'], $a['pid_2'], $a['skor_2'],
                ], $ambigu)
            );
        } else {
            $this->warn('▶ ' . count($ambigu) . ' kasus AMBIGU disisakan untuk manusia.');
            $this->warn('  ⚠️ LAYAR TINJAUANNYA BELUM ADA — ini utang yang diakui, bukan kelalaian.');
            $this->warn('     Daftarnya: php artisan omnicare:match-patients --dry-run --daftar-ambigu');
        }

        // ── Kandidat yang ditolak karena DOB kita bertentangan ────────────
        if (! empty($ditolakDob)) {
            $this->line('');
            $this->warn('▶ ' . count($ditolakDob) . ' kandidat DITOLAK — tanggal lahir pasien kita '
                . 'bertentangan dengan Omnicare.');
            $this->warn('  ⚠️ Sebagian besar kemungkinan salah TANGGAL di sisi kita, bukan salah ORANG.');
            $this->warn('     Periksa dulu: php artisan omnicare:tinjau-tautan --dob');
            if ($this->option('daftar-ambigu')) {
                $this->table(
                    ['pasien (kita)', 'nama', 'DOB kita', 'pid', 'DOB Omnicare', 'skor'],
                    array_map(fn($d) => [
                        $d['raw_customer_id'], $d['nama'], $d['dob_kita'],
                        $d['pid'], $d['dob_omnicare'], $d['skor'],
                    ], array_slice($ditolakDob, 0, 100))
                );
            }
        }

        $totalLinkable = $s['total'] - $s['sudah_tertaut'];
        if ($totalLinkable > 0) {
            $rate = round(($s['baru'] + $s['tambahan']) / $totalLinkable * 100, 1);
            $this->line("▶ Match rate: {$rate}% dari {$totalLinkable} customer yang masih bisa ditaut");
        }
        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada baris ditulis. Jalankan tanpa --dry-run untuk commit.');
        } else {
            $this->info('✓ ' . number_format($ditulis) . ' baris ditulis ke customer_omnicare_link');
            if ($ditulis < count($staged)) {
                $this->warn('⚠️ ' . (count($staged) - $ditulis) . ' baris DITOLAK database.');
                $this->warn('   Dua sebab mungkin, dan keduanya PENTING dibedakan:');
                $this->warn('   1. `uq_col_omnicare` — pagar bekerja, satu pid direbut dua pasien. Periksa.');
                $this->warn('   2. `uq_col_customer` MASIH TERPASANG — SQL multi-pid belum dijalankan,');
                $this->warn('      jadi setiap pid tambahan dibuang diam-diam dan perbaikan ini NOL efek.');
                $this->warn('      Jalankan database/sql/2026_09_16_customer_omnicare_link_multi_pid.sql dulu.');
            }
        }
    }

    private function delta(int $a, int $b): string
    {
        $d = $b - $a;
        return ($d > 0 ? '+' : '') . number_format($d);
    }
}
