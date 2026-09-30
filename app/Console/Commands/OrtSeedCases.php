<?php

namespace App\Console\Commands;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtItemRules;
use App\Services\Ortho\OrtRollup;
use App\Services\Ortho\OrtVisits;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * PENYEMAI — membangun ort_case + riwayat kontrolnya dari cermin rekam medis.
 *
 * INI YANG MEMBUAT MODUL PUNYA ISI. Tarikan malam (`ort:pull-visits`) hanya
 * MENCOCOKKAN kunjungan ke kasus yang sudah ada — ia tidak pernah membuat
 * kasus. Di database yang ort_case-nya kosong, setiap kunjungan mendarat
 * sebagai "pasien belum jadi kasus" dan halaman tetap kosong selamanya.
 * Perintah inilah yang mengisi kekosongan itu, sekali, di awal.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * DUA PENGAMAN YANG TIDAK BOLEH DILEPAS
 * ────────────────────────────────────────────────────────────────────────────
 * 1. MENULIS BUTUH `--commit`. Tanpa itu perintah ini memindai, menghitung,
 *    melaporkan — lalu tidak menulis apa pun. Tidak ada `--dry-run`, karena
 *    pengaman yang harus diingat untuk dinyalakan adalah pengaman yang suatu
 *    hari lupa dinyalakan.
 * 2. `seed_source='rekam_medis'` + `created_by='ort:seed-cases'` pada SETIAP
 *    baris. Itulah tombol urungkan di produksi: `--hapus-seed --commit`
 *    menghapus persis apa yang perintah ini tulis, dan tidak menyentuh satu
 *    pun kasus yang dibuat manusia.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA WAKIL TRX HARUS SAMA DENGAN TARIKAN MALAM
 * ────────────────────────────────────────────────────────────────────────────
 * Riwayat yang ditulis di sini membawa `omnicare_trx_id`. Itu pagar anti-dobel
 * tarikan malam. Kalau penyemai memilih baris wakil yang BERBEDA dari yang
 * dipilih tarikan malam untuk kunjungan yang sama, pagarnya tidak berbunyi:
 * malam berikutnya kunjungan yang sama dicatat ulang, kehadiran jadi 2/1, dan
 * tidak ada error yang memberi tahu siapa pun. Karena itu keduanya memakai
 * `OrtVisits` — satu kelas, satu aturan. Jangan pernah menyalin logikanya
 * ke sini.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * ALUR
 * ────────────────────────────────────────────────────────────────────────────
 * 1. Alirkan cermin satu arah, terurut per pasien (`cursor()`), siram tiap kali
 *    nomor pasien berganti. 125 ribu baris lewat tanpa menumpuk di memori.
 * 2. Baris → KUNJUNGAN lewat OrtVisits (saring, dedup, pilih wakil).
 * 3. Kunjungan → EPISODE, kronologis. Lihat pecahEpisode().
 * 4. Episode → satu `ort_case` + N `ort_control` tertutup, seq 1..N.
 * 5. OrtRollup::refresh() menghitung cabang penagih, kehadiran, jarak rata-rata.
 *
 * ⚠ TIDAK ADA JADWAL TERBUKA YANG DIBUAT. Semua pasien hasil penyemaian
 *   mendarat di antrean "Belum punya jadwal" — memang begitu rancangannya
 *   (brief §kartu ke-3). Melahirkan ribuan usulan sekaligus akan membanjiri
 *   Sales pada hari pertama dan membuat antrean itu kehilangan artinya.
 */
class OrtSeedCases extends Command
{
    protected $signature = 'ort:seed-cases
                            {--sejak=2023-01-01 : batas awal pemindaian cermin}
                            {--sampai= : batas akhir (default: kemarin)}
                            {--cabang= : hanya TULIS kasus yang cabang pasangnya ini}
                            {--commit : benar-benar menulis. TANPA ini tidak ada yang ditulis}
                            {--pid= : telusuri satu pasien, cetak jejak keputusannya}
                            {--batas= : berhenti sesudah N pasien (uji coba)}
                            {--laporan= : tulis CSV per-kasus ke storage/app/ortho/}
                            {--hapus-seed : HAPUS semua kasus hasil perintah ini, lalu keluar}';

    protected $description = 'Bangun ort_case + riwayat kontrol dari cermin rekam medis';

    /** Jejak di setiap baris. Inilah yang membuat --hapus-seed bisa tepat sasaran. */
    private const SUMBER  = 'rekam_medis';
    private const PENULIS = 'ort:seed-cases';

    /** Pasien per gelombang tulis. Menahan jumlah kueri identitas tetap kecil. */
    private const GELOMBANG = 200;

    private array $n = [];

    /** pid yang SUDAH punya kasus — dimuat sekali, bukan ditanya per pasien. */
    private array $sudahAda = [];

    /** Baris CSV, hanya kalau --laporan diminta. */
    private array $laporan = [];

    private bool $tulis  = false;
    private bool $telusur = false;

    public function __construct(
        private OrtBranches $branches,
        private OrtVisits $visits,
        private OrtRollup $rollup,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('ort_case') || ! Schema::hasTable('omnicare_sales_detail')) {
            $this->error('Tabel ort_* atau omnicare_sales_detail belum ada. Jalankan SQL-nya dulu.');

            return self::FAILURE;
        }

        if ($this->option('hapus-seed')) {
            return $this->hapusSeed();
        }

        $sejak   = Carbon::parse($this->option('sejak'))->toDateString();
        $sampai  = $this->option('sampai')
            ? Carbon::parse($this->option('sampai'))->toDateString()
            : now()->subDay()->toDateString();

        if ($sejak > $sampai) {
            $this->error('--sejak lebih baru dari --sampai.');

            return self::FAILURE;
        }

        $this->tulis   = (bool) $this->option('commit');
        $this->telusur = filled($this->option('pid'));

        // Pemindaian SELALU lima cabang, walau --cabang dipakai. Pasien yang
        // pasang di Bintaro lalu pindah kontrol ke Sunter punya separuh
        // riwayatnya di cabang lain; memindai satu cabang saja akan memotong
        // riwayat itu diam-diam dan membuat jarak rata-rata bohong.
        $places = array_map('strval', array_keys($this->branches->all()));
        if (! $places) {
            $this->error('Tidak ada cabang terpeta (raw_branch.omnicare_place_id kosong).');

            return self::FAILURE;
        }

        $this->n = array_fill_keys([
            'pasien', 'sudah_ada', 'hanya_penyerta', 'pid_kosong', 'lain_cabang',
            'episode', 'pasti', 'perkiraan', 'selesai', 'kasus', 'kontrol',
        ], 0);

        $this->sudahAda = $this->telusur
            ? []
            : array_flip(OrtCase::whereNotNull('omnicare_pid')->pluck('omnicare_pid')->all());

        $this->info(sprintf(
            '%s %s → %s · %d cabang%s',
            $this->tulis ? 'PENYEMAIAN (menulis):' : 'PRATINJAU (tidak menulis):',
            $sejak, $sampai, count($places),
            $this->option('cabang') ? ' · tulis hanya cabang ' . $this->branches->nama($this->option('cabang')) : ''
        ));
        $this->newLine();

        $this->pindai($places, $sejak, $sampai);

        $this->laporkan($sejak);

        return self::SUCCESS;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Pemindaian — aliran satu arah, memori tetap
    // ════════════════════════════════════════════════════════════════════════

    private function pindai(array $places, string $sejak, string $sampai): void
    {
        $q = $this->visits->kueri($places, $sejak, $sampai)
            ->orderBy('p_id')
            ->orderBy('trx_date')
            ->orderBy('trx_id');

        if ($this->telusur) {
            $q->where('p_id', $this->option('pid'));
        }

        $batas   = $this->option('batas') ? (int) $this->option('batas') : null;
        $pidKini = null;
        $baris   = [];
        $antre   = [];

        foreach ($q->cursor() as $r) {
            // Dikelompokkan pakai p_id MENTAH, sejalan dengan ORDER BY —
            // penormalan ('', '0', '-' → kosong) dikerjakan di olahPasien().
            if ($pidKini !== null && (string) $r->p_id !== $pidKini) {
                $antre = $this->olahPasien($pidKini, $baris, $antre, $sejak);
                $baris = [];

                if ($batas !== null && $this->n['pasien'] >= $batas) {
                    $pidKini = null;
                    break;
                }
            }

            $pidKini = (string) $r->p_id;
            $baris[] = $r;
        }

        if ($pidKini !== null && $baris) {
            $antre = $this->olahPasien($pidKini, $baris, $antre, $sejak);
        }

        if ($antre) {
            $this->siramGelombang($antre, $sejak);
        }
    }

    /**
     * Satu pasien: baris cermin → kunjungan → episode → antrean tulis.
     *
     * @param  list<object>  $baris
     * @param  list<array>   $antre
     * @return list<array>   antrean sesudah pasien ini (mungkin sudah disiram)
     */
    private function olahPasien(string $pidMentah, array $baris, array $antre, string $sejak): array
    {
        $kunjungan = $this->visits->kumpulkan($baris);
        if (! $kunjungan) {
            return $antre;   // pasien ini tidak punya satu pun transaksi ortho
        }

        $pid = OrtVisits::pid($pidMentah);

        // Rekam medis tanpa nomor pasien tidak bisa ditautkan ke siapa pun.
        // Dihitung, bukan dibuang diam-diam: ini temuan yang harus dirapikan
        // di Omnicare, bukan sampah.
        if ($pid === '') {
            $this->n['pid_kosong'] += count($kunjungan);

            return $antre;
        }

        $this->n['pasien']++;

        // Pasien yang SUDAH punya kasus dilewati seluruhnya.
        //
        // ⚠ Sengaja sekasar itu. Tanggal pasang yang DIPERKIRAKAN diturunkan
        //   dari kunjungan pertama yang terlihat di rentang pindai. Kalau
        //   perintah ini dijalankan lagi dengan --sejak lebih awal, perkiraannya
        //   berubah → `uq_ort_case_pid` melihatnya sebagai kasus BARU → satu
        //   pasien punya dua kartu. Melewati pasien yang sudah punya kasus
        //   menutup jebakan itu sepenuhnya, dan penyemaian bertahap untuk
        //   pasien BARU tetap jalan. Mau menyemai ulang dengan rentang lain?
        //   `--hapus-seed --commit` dulu.
        if (isset($this->sudahAda[$pid])) {
            $this->n['sudah_ada']++;

            return $antre;
        }

        $episode = $this->pecahEpisode($kunjungan);

        if ($this->telusur) {
            $this->cetakJejak($pid, $kunjungan, $episode);
        }

        if (! $episode) {
            // Semua kunjungannya barang penyerta (beli Ortho Wax, tempat
            // retainer) atau pelepasan tanpa pemasangan yang terlihat. Tidak
            // cukup untuk menyatakan orang ini sedang dirawat.
            $this->n['hanya_penyerta']++;

            return $antre;
        }

        $antre[] = ['pid' => $pid, 'episode' => $episode];

        if (count($antre) >= self::GELOMBANG) {
            $this->siramGelombang($antre, $sejak);

            return [];
        }

        return $antre;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Kunjungan → episode
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Pecah riwayat satu pasien jadi episode perawatan, kronologis.
     *
     * Satu episode = satu kali behel dipasang sampai dilepas. Pasien yang
     * pernah lepas lalu pasang lagi punya dua baris `ort_case` — memang
     * begitu rancangannya (`uq_ort_case_pid` berkunci pada install_date).
     *
     * ATURANNYA:
     *   • kunjungan `pasang`                    → buka episode, tanggal PASTI
     *   • belum ada episode, bukan `lepas`,
     *     bukan MURNI penyerta                  → buka episode, tanggal DIPERKIRAKAN
     *   • kunjungan `lepas`                     → tutup episode (selesai)
     *   • `lepas`/penyerta saat belum ada episode → dilewati
     *
     * Jalur "diperkirakan" itu yang membuat modul ini berguna sama sekali:
     * brief §6.1 mencatat 132 nama item pemasangan berbeda, jadi sebagian
     * besar pemasangan TIDAK akan tertangkap POLA_PASANG. Tanpa jalur ini,
     * pasien yang sudah dua tahun kontrol rutin tidak akan pernah muncul di
     * layar hanya karena nama promo pemasangannya tak lazim. Jumlahnya
     * dilaporkan terpisah, tidak disembunyikan.
     *
     * Pemasangan SAAT episode masih terbuka TIDAK membuka episode baru — itu
     * penambahan rahang atau pemasangan ulang bracket yang copot, bukan
     * perawatan baru. Episode baru hanya lahir sesudah yang lama ditutup.
     *
     * @param  list<array>  $kunjungan  terurut tanggal (jaminan OrtVisits)
     * @return list<array{install:string, place:string, perkiraan:bool, selesai:?string, kunjungan:list<array>}>
     */
    private function pecahEpisode(array $kunjungan): array
    {
        $episode = [];
        $kini    = null;

        foreach ($kunjungan as $v) {
            $murniPenyerta = ! array_diff($v['jenis'], [OrtItemRules::PENYERTA]);

            if ($kini === null) {
                if ($v['lepas'] || $murniPenyerta) {
                    continue;
                }

                $kini = [
                    'install'   => $v['tanggal'],
                    'place'     => $v['place'],
                    'perkiraan' => ! $v['pasang'],
                    'selesai'   => null,
                    'kunjungan' => [],
                ];
            }

            $kini['kunjungan'][] = $v;

            if ($v['lepas']) {
                $kini['selesai'] = $v['tanggal'];
                $episode[]       = $kini;
                $kini            = null;
            }
        }

        if ($kini !== null) {
            $episode[] = $kini;
        }

        return $episode;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Menulis
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Tulis satu gelombang pasien. Identitas diambil sekali untuk seluruh
     * gelombang — dua `whereIn`, bukan dua kueri per pasien.
     *
     * @param  list<array>  $antre
     */
    private function siramGelombang(array $antre, string $sejak): void
    {
        $pids = array_column($antre, 'pid');

        // Nol JOIN, semuanya whereIn dengan literal PHP. Collation prod campur
        // (lihat kepala berkas SQL skema): join lintas keluarga tabel akan
        // gagal #1267 di sana walau mulus di lokal.
        $orang = Schema::hasTable('omnicare_patient')
            ? DB::table('omnicare_patient')->whereIn('pid', $pids)
                ->get(['pid', 'full_name', 'mobile_number'])->keyBy('pid')
            : collect();

        $tautan = Schema::hasTable('customer_omnicare_link')
            ? DB::table('customer_omnicare_link')->whereIn('omnicare_pid', $pids)
                ->pluck('raw_customer_id', 'omnicare_pid')
            : collect();

        foreach ($antre as $a) {
            $o = $orang->get($a['pid']);

            foreach ($a['episode'] as $ep) {
                $this->n['episode']++;
                $this->n[$ep['perkiraan'] ? 'perkiraan' : 'pasti']++;
                if ($ep['selesai']) {
                    $this->n['selesai']++;
                }

                if ($this->option('cabang') && (string) $ep['place'] !== (string) $this->option('cabang')) {
                    $this->n['lain_cabang']++;
                    continue;
                }

                $this->tulisEpisode($a['pid'], $ep, [
                    'nama'     => $o->full_name ?? null,
                    'telepon'  => $o->mobile_number ?? null,
                    'customer' => $tautan[$a['pid']] ?? null,
                ], $sejak);
            }
        }
    }

    private function tulisEpisode(string $pid, array $ep, array $info, string $sejak): void
    {
        $kunjungan = $ep['kunjungan'];
        $pertama   = $kunjungan[0];

        // Nama: rekam medis → nama di baris penjualan → nomor rekam medisnya.
        // Kolom patient_name NOT NULL, dan kartu tanpa nama tidak bisa dicari
        // Sales — jadi selalu ada isinya, dengan urutan kepercayaan yang jelas.
        $nama = trim((string) ($info['nama'] ?: ($pertama['p_name'] ?? ''))) ?: ('Pasien ' . $pid);

        // Jenis bracket & rahang: kunjungan PERTAMA yang menyebutkannya. Nama
        // item kontrol pun sering menyebutnya ("Control Metal Braces"), jadi
        // episode ber-tanggal-perkiraan tetap bisa terisi.
        $bracket = $rahang = null;
        foreach ($kunjungan as $v) {
            $bracket ??= OrtItemRules::jenisBracket($v['item']);
            $rahang  ??= OrtItemRules::rahang($v['item']);
        }

        // Dokter: dari kunjungan TERAKHIR yang punya rujukan dokter, bukan dari
        // pemasangan. Yang dibutuhkan Sales adalah dokter yang menangani
        // SEKARANG — pemasangnya bisa sudah tidak praktik di sana.
        $dokterId = $dokterNama = null;
        foreach ($kunjungan as $v) {
            if (filled($v['doc_reff'])) {
                $dokterId   = $v['doc_reff'];
                $dokterNama = $v['dokter'];
            }
        }

        $this->n['kasus']++;
        $this->n['kontrol'] += count($kunjungan);

        if ($this->option('laporan')) {
            $this->laporan[] = [
                $pid, $nama, $ep['install'], $ep['perkiraan'] ? 'perkiraan' : 'pasti',
                $this->branches->nama($ep['place']), $ep['selesai'] ? 'selesai' : 'aktif',
                $ep['selesai'] ?? '', count($kunjungan), $bracket ?? '', $dokterNama ?? '',
            ];
        }

        if (! $this->tulis) {
            return;
        }

        DB::transaction(function () use ($pid, $ep, $info, $sejak, $nama, $bracket, $rahang, $dokterId, $dokterNama) {
            $case = OrtCase::create([
                'id'               => (string) Str::uuid(),
                'omnicare_pid'     => $pid,
                'raw_customer_id'  => $info['customer'],
                'patient_name'     => $nama,
                'patient_phone'    => $info['telepon'],
                'install_date'     => $ep['install'],
                'install_place_id' => $ep['place'],
                'bracket_type'     => $bracket,
                'bracket_arch'     => $rahang,
                'doctor_ref_id'    => $dokterId,
                'doctor_name'      => $dokterNama,
                'status'           => $ep['selesai'] ? OrtCase::STATUS_SELESAI : OrtCase::STATUS_AKTIF,
                'finished_at'      => $ep['selesai'],
                // Sementara. OrtRollup::refresh() di bawah menimpanya dengan
                // cabang kontrol TERAKHIR — itulah yang memindahkan pasien ke
                // tab cabang yang benar.
                'owner_place_id'   => $ep['place'],
                'seed_source'      => self::SUMBER,
                'created_by'       => self::PENULIS,
                'note'             => $ep['perkiraan'] ? sprintf(
                    'Tanggal pasang DIPERKIRAKAN dari kunjungan ortho pertama yang terlihat '
                    . 'di rekam medis sejak %s; pemasangan sebenarnya mungkin lebih lama.',
                    $sejak
                ) : null,
            ]);

            $seq = 0;
            foreach ($ep['kunjungan'] as $v) {
                OrtControl::create([
                    'id'                    => (string) Str::uuid(),
                    'ort_case_id'           => $case->id,
                    'seq'                   => ++$seq,
                    'place_id'              => $v['place'],
                    'is_proposal'           => 0,
                    // Jadwal = tanggal datangnya. Riwayat lama tidak punya
                    // jadwal yang pernah disepakati; menyamakan keduanya
                    // membuat matriks drawer terbaca benar tanpa mengarang.
                    'sch_date'              => $v['tanggal'],
                    'sch_source'            => OrtControl::SCH_REKAM_MEDIS,
                    'visit_date'            => $v['tanggal'],
                    'visit_source'          => 'rekam_medis',
                    'visit_confidence'      => 'pasti',
                    // KUNCI SEGALANYA — pagar anti-dobel tarikan malam.
                    'omnicare_trx_id'       => $v['trx_id'],
                    'omnicare_encounter_id' => $v['encounter'],
                    'visit_filled_at'       => now(),
                    'outcome'               => OrtControl::OUT_HADIR,
                    'closed_at'             => now(),
                    'closed_by'             => self::PENULIS,
                    'created_by'            => self::PENULIS,
                ]);
            }

            $this->rollup->refresh($case->id);
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // Urungkan
    // ════════════════════════════════════════════════════════════════════════

    private function hapusSeed(): int
    {
        $ids = OrtCase::where('seed_source', self::SUMBER)
            ->where('created_by', self::PENULIS)
            ->pluck('id');

        $this->warn($ids->count() . ' kasus hasil ' . self::PENULIS . ' ditemukan.');

        if ($ids->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('commit')) {
            $this->line('Tambahkan --commit untuk benar-benar menghapusnya.');

            return self::SUCCESS;
        }

        // Kasus yang disentuh manusia sesudah disemai (dijadwalkan, difollow-up,
        // dipindahkan) TIDAK boleh ikut terhapus — pekerjaan Sales hilang tanpa
        // jejak. Disaring di sini, bukan dipercayakan pada ingatan operator.
        $dipakai = OrtControl::whereIn('ort_case_id', $ids)
            ->where(function ($q) {
                $q->where('created_by', '<>', self::PENULIS)
                  ->orWhereNull('closed_at')
                  ->orWhereNotNull('reservation_id')
                  ->orWhereNotNull('fu_date');
            })
            ->distinct()
            ->pluck('ort_case_id');

        $aman = $ids->diff($dipakai);

        DB::transaction(function () use ($aman) {
            OrtControl::whereIn('ort_case_id', $aman)->delete();
            OrtCase::whereIn('id', $aman)->delete();
        });

        $this->info($aman->count() . ' kasus dihapus.');
        if ($dipakai->isNotEmpty()) {
            $this->warn($dipakai->count() . ' kasus DIPERTAHANKAN karena sudah disentuh manusia '
                . '(punya jadwal terbuka, follow-up, atau reservasi).');
        }

        return self::SUCCESS;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Laporan
    // ════════════════════════════════════════════════════════════════════════

    /** Jejak keputusan untuk satu pasien — `--pid`. Inilah alat bantah. */
    private function cetakJejak(string $pid, array $kunjungan, array $episode): void
    {
        $this->line('Pasien <options=bold>' . $pid . '</> — ' . count($kunjungan)
            . ' kunjungan ortho, ' . count($episode) . ' episode');
        $this->newLine();

        $milik = [];
        foreach ($episode as $i => $ep) {
            foreach ($ep['kunjungan'] as $v) {
                $milik[$v['trx_id']] = $i + 1;
            }
        }

        $tabel = [];
        foreach ($kunjungan as $v) {
            $tabel[] = [
                $v['tanggal'],
                $this->branches->nama($v['place']),
                mb_substr((string) $v['item'], 0, 38),
                $v['kode'] ?: '—',
                implode('+', array_unique($v['jenis'])),
                $v['trx_id'],
                $milik[$v['trx_id']] ?? 'dilewati',
            ];
        }
        $this->table(['Tanggal', 'Cabang', 'Item (wakil)', 'Kode', 'Jenis', 'trx wakil', 'Episode'], $tabel);

        foreach ($episode as $i => $ep) {
            $this->line(sprintf(
                '  Episode %d: pasang %s (%s) di %s · %s · %d kunjungan',
                $i + 1, $ep['install'],
                $ep['perkiraan'] ? 'DIPERKIRAKAN' : 'pasti',
                $this->branches->nama($ep['place']),
                $ep['selesai'] ? 'selesai ' . $ep['selesai'] : 'masih aktif',
                count($ep['kunjungan'])
            ));
        }
        $this->newLine();
    }

    private function laporkan(string $sejak): void
    {
        $n = $this->n;

        $this->newLine();
        $this->table(['Hasil', 'Jumlah'], [
            ['Pasien dengan transaksi ortho',        $n['pasien']],
            ['→ sudah punya kasus (dilewati)',       $n['sudah_ada']],
            ['→ hanya barang penyerta / lepas saja', $n['hanya_penyerta']],
            ['Kunjungan tanpa nomor rekam medis',    $n['pid_kosong']],
            ['Episode perawatan ditemukan',          $n['episode']],
            ['→ tanggal pasang PASTI',               $n['pasti']],
            ['→ tanggal pasang DIPERKIRAKAN',        $n['perkiraan']],
            ['→ sudah selesai (behel dilepas)',      $n['selesai']],
            ['→ cabang lain (tidak ditulis)',        $n['lain_cabang']],
            [$this->tulis ? 'Kasus DITULIS' : 'Kasus akan ditulis',     $n['kasus']],
            [$this->tulis ? 'Riwayat kontrol DITULIS' : 'Riwayat kontrol akan ditulis', $n['kontrol']],
        ]);

        if ($n['episode'] > 0 && $n['perkiraan'] / $n['episode'] > 0.5) {
            $this->newLine();
            $this->warn(sprintf(
                '%d dari %d episode tanggal pasangnya DIPERKIRAKAN (%d%%).',
                $n['perkiraan'], $n['episode'], round($n['perkiraan'] / $n['episode'] * 100)
            ));
            $this->warn('Artinya nama item pemasangan banyak yang belum dikenali OrtItemRules.');
            $this->warn('Jalankan `ort:pull-visits --daftar-item --sejak=' . $sejak . '` dan sapu');
            $this->warn('tabel "DIANGGAP BUKAN ORTHO" sebelum menyemai dengan --commit.');
        }

        if ($this->laporan) {
            $berkas = 'ortho/' . ($this->option('laporan') ?: 'seed-cases-' . now()->format('Ymd-His') . '.csv');
            $isi    = "pid,nama,tanggal_pasang,keyakinan,cabang_pasang,status,selesai,kunjungan,bracket,dokter\n";
            foreach ($this->laporan as $b) {
                $isi .= implode(',', array_map(fn ($x) => '"' . str_replace('"', '""', (string) $x) . '"', $b)) . "\n";
            }
            \Illuminate\Support\Facades\Storage::disk('local')->put($berkas, $isi);
            $this->newLine();
            $this->info('Laporan per-kasus: storage/app/' . $berkas);
        }

        if (! $this->tulis) {
            $this->newLine();
            $this->warn('PRATINJAU — tidak ada satu baris pun yang ditulis. Tambahkan --commit.');
        }
    }
}
