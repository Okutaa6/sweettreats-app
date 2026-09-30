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
 * Tarikan malam — mengisi kolom DATANG dari cermin rekam medis.
 *
 * INI JANTUNG MODULNYA. Tanpa perintah ini, kolom Datang tidak pernah terisi,
 * kontrol tidak pernah tertutup jadi "Hadir", dan usulan jadwal berikutnya
 * tidak pernah lahir — artinya tombol "Konfirmasi jadwal" tidak punya apa pun
 * untuk dikonfirmasi.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * APA YANG DILAKUKAN MESIN, DAN APA YANG TIDAK
 * ────────────────────────────────────────────────────────────────────────────
 * Mesin HANYA mengisi FAKTA: kunjungan yang benar-benar terjadi. Ia tidak
 * pernah menyimpulkan ketidakhadiran.
 *
 * ⚠ Ini menyimpang dari HANDOFF.md §1 yang menyebut "Tidak hadir" juga diisi
 *   otomatis. Keputusan pemilik produk: tebakan "tidak hadir" akan salah setiap
 *   kali pasien datang di cabang lain atau rekam medisnya telat masuk, dan
 *   salahnya tidak kelihatan. Ketidakhadiran ditutup manusia lewat tombol
 *   "Tandai tidak hadir".
 *
 * ────────────────────────────────────────────────────────────────────────────
 * ALUR
 * ────────────────────────────────────────────────────────────────────────────
 * 1. Baca omnicare_sales_detail per cabang per rentang tanggal. Memakai index
 *    (place_id, trx_date) yang SUDAH ADA — tidak butuh ALTER TABLE.
 * 2. Saring ke item ortho lewat OrtItemRules. Seluruh pengetahuan nama item
 *    hidup di kelas itu, tidak ada satu pun pola di berkas ini.
 * 3. DEDUP PER (pasien, tanggal, cabang) dan pilih WAKIL trx — di OrtVisits,
 *    kelas yang juga dipakai penyemai. Barang penyerta (Kawat, Orthokit)
 *    sering ada di tanggal yang sama dengan kontrol — satu tanggal = satu
 *    kunjungan, bukan tiga. Wakilnya deterministik (pasang > lepas > kontrol
 *    > penyerta, lalu trx_id terkecil), bukan "baris pertama".
 * 4. Cocokkan ke jadwal terbuka TERDEKAT. Selisih <= 45 hari → 'pasti', jadwal
 *    ditutup sebagai Hadir. Lebih dari itu → kunjungan tetap dicatat sebagai
 *    baris tersendiri ber-tanda 'dugaan' dan unscheduled=1, TAPI jadwal yang
 *    terbuka TIDAK disentuh — menutup jadwal diam-diam berdasarkan tebakan
 *    adalah cara tercepat membuat Sales berhenti percaya halaman ini.
 * 5. Sesudah jadwal ditutup Hadir, lahirkan usulan berikutnya (+30 hari,
 *    is_proposal=1, sch_source='sistem'). Manusia yang mengonfirmasinya.
 *    Tanggalnya dijepit OrtControl::tanggalUsulan() supaya tidak pernah lahir
 *    sudah terlewat saat rekam medisnya masuk terlambat.
 * 6. Item "Lepas Bracket" menutup KASUS-nya (status=selesai). Item
 *    "Bracket Metal Lepas" TIDAK — lihat OrtItemRules.
 * 7. Segarkan rollup semua kasus aktif di cabang itu, bukan hanya yang
 *    tersentuh: visit_count_180d basi karena WAKTU BERJALAN, bukan karena ada
 *    yang menulis.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * PAGAR ANTI-DOBEL — DUA LAPIS
 * ────────────────────────────────────────────────────────────────────────────
 * 1. ort_control.omnicare_trx_id UNIQUE (uq_ort_control_trx): transaksi yang
 *    sama tidak pernah menambah baris.
 * 2. (ort_case_id, visit_date): satu kasus, satu tanggal, satu kunjungan.
 *    Lapis pertama saja BOCOR pada tiga keadaan nyata —
 *      • baris cermin datang menyusul dan mengubah wakil (malam pertama cuma
 *        "Kawat", malam kedua "Control"-nya masuk dengan trx lain),
 *      • penyemai dan penarik memilih wakil berbeda untuk kunjungan yang sama,
 *      • Sales sudah menutup jadwalnya manual (Tandai hadir) di tanggal itu,
 *        lalu rekam medisnya masuk.
 *    Ketiganya dulu melahirkan baris riwayat kedua tanpa error apa pun.
 * Karena dua lapis ini, perintah ini aman diulang — dan memang harus, karena
 * cermin rekam medis kadang telat masuk semalam.
 */
class OrtPullVisits extends Command
{
    protected $signature = 'ort:pull-visits
                            {--sejak= : tanggal mulai YYYY-MM-DD (default: 14 hari lalu)}
                            {--sampai= : tanggal akhir YYYY-MM-DD (default: hari ini)}
                            {--cabang= : batasi ke satu place_id}
                            {--dry-run : hitung dan laporkan, JANGAN menulis apa pun}
                            {--daftar-item : cetak setiap nama item ortho + klasifikasinya, lalu keluar}
                            {--uji-aturan : jalankan fixture OrtItemRules, lalu keluar}';

    protected $description = 'Tarikan malam: isi kolom Datang dari cermin rekam medis';

    /** Selisih maksimum hari antara kunjungan dan jadwal sebelum dianggap dugaan. */
    private const TOLERANSI_HARI = 45;

    public function __construct(
        private OrtBranches $branches,
        private OrtItemRules $rules,
        private OrtRollup $rollup,
        private OrtVisits $visits,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('uji-aturan')) {
            return $this->ujiAturan();
        }

        $sejak  = $this->tanggal('sejak',  now()->subDays(14));
        $sampai = $this->tanggal('sampai', now());
        if ($sejak->gt($sampai)) {
            $this->error('--sejak lebih baru dari --sampai.');
            return self::FAILURE;
        }

        $places = $this->option('cabang')
            ? [(string) $this->option('cabang')]
            : array_map('strval', array_keys($this->branches->all()));

        /*
         * --daftar-item punya penjaganya SENDIRI, lebih sempit, dan berjalan
         * SEBELUM penjaga tabel ort_*.
         *
         * Perintah ini cuma membaca katalog item dari cermin lalu mencetak
         * klasifikasinya — nol tulis, nol sentuhan ort_*. Versi lama menaruhnya
         * di bawah penjaga yang juga menuntut `ort_control` ada, jadi pemeriksa
         * katalog ini baru bisa dijalankan SETELAH DDL dipasang di prod —
         * padahal gunanya justru memeriksa SEBELUM modul dipercaya menulis apa
         * pun. Aturan `Lepas Bracket` vs `Bracket Metal Lepas` adalah risiko
         * nomor satu modul ini: salah baca berarti ribuan pasien ditandai
         * selesai di hari pertama. Pemeriksaannya harus bisa dilakukan lebih
         * dulu, dengan tangan kosong.
         */
        if ($this->option('daftar-item')) {
            if (! Schema::hasTable('omnicare_sales_detail')) {
                $this->error('Tabel omnicare_sales_detail belum ada — cermin rekam medis belum disinkronkan.');

                return self::FAILURE;
            }

            return $this->daftarItem($places, $sejak, $sampai);
        }

        if (! Schema::hasTable('ort_control') || ! Schema::hasTable('omnicare_sales_detail')) {
            $this->error('Tabel ort_* atau omnicare_sales_detail belum ada. Jalankan SQL-nya dulu.');

            return self::FAILURE;
        }

        $kering = (bool) $this->option('dry-run');

        $this->info(sprintf(
            '%s %s → %s · %d cabang',
            $kering ? 'DRY-RUN (tidak menulis):' : 'Tarikan malam:',
            $sejak->toDateString(), $sampai->toDateString(), count($places)
        ));
        $this->newLine();

        $total = ['kunjungan' => 0, 'cocok' => 0, 'dugaan' => 0, 'lewati' => 0, 'lewati_tgl' => 0,
                  'usulan' => 0, 'selesai' => 0, 'tanpa_kasus' => 0, 'kasus_selesai' => 0, 'pid_kosong' => 0];

        foreach ($places as $place) {
            $hasil = $this->prosesCabang($place, $sejak, $sampai, $kering);
            foreach ($hasil as $k => $v) {
                $total[$k] += $v;
            }
        }

        $this->newLine();
        $this->table(['Hasil', 'Jumlah'], [
            ['Kunjungan ortho ditemukan',      $total['kunjungan']],
            ['→ cocok jadwal (ditutup Hadir)', $total['cocok']],
            ['→ dugaan / tanpa jadwal',        $total['dugaan']],
            ['→ sudah pernah ditarik (trx sama)',      $total['lewati']],
            ['→ sudah ada kunjungan di tanggal itu',   $total['lewati_tgl']],
            ['→ pasien belum jadi kasus',      $total['tanpa_kasus']],
            ['→ kasusnya sudah selesai',        $total['kasus_selesai']],
            ['→ rekam medis tanpa nomor pasien', $total['pid_kosong']],
            ['Usulan jadwal baru dibuat',      $total['usulan']],
            ['Kasus ditandai selesai',         $total['selesai']],
        ]);

        if ($kering) {
            $this->newLine();
            $this->warn('DRY-RUN — tidak ada satu baris pun yang ditulis.');
        }

        return self::SUCCESS;
    }

    /**
     * Pilih EPISODE yang benar untuk satu kunjungan.
     *
     * Satu pasien bisa punya beberapa baris `ort_case`: behel dilepas, lalu
     * beberapa bulan kemudian pasang lagi. Yang dipilih di sini adalah episode
     * AKTIF yang tanggal pasangnya sudah lewat pada hari kunjungan, dan yang
     * paling dekat dengannya.
     *
     * Kalau tidak ada yang layak tapi pasiennya punya kasus yang sudah
     * SELESAI, itu dikembalikan sebagai sebab tersendiri — bukan disamakan
     * dengan "belum disemai". Bedanya menentukan tindakan: yang pertama
     * berarti pasien datang lagi sesudah behelnya dilepas (mis. beli retainer)
     * dan memang sengaja diabaikan; yang kedua berarti penyemaian belum
     * lengkap dan harus dijalankan lagi.
     *
     * @param  \Illuminate\Support\Collection<int, OrtCase>|null  $semua
     * @return array{kasus: ?OrtCase, sebab: ?string}
     */
    private function episodeUntuk($semua, string $tanggal): array
    {
        if (! $semua || $semua->isEmpty()) {
            return ['kasus' => null, 'sebab' => 'tanpa_kasus'];
        }

        $layak = $semua
            ->filter(fn (OrtCase $k) => $k->status === OrtCase::STATUS_AKTIF)
            ->filter(fn (OrtCase $k) => $k->install_date === null
                                     || $k->install_date->toDateString() <= $tanggal)
            ->sortByDesc(fn (OrtCase $k) => $k->install_date?->toDateString() ?? '')
            ->first();

        if ($layak) {
            return ['kasus' => $layak, 'sebab' => null];
        }

        // Punya kasus, tapi tak satu pun aktif & sah untuk tanggal ini.
        $pernahSelesai = $semua->contains(fn (OrtCase $k) => $k->status !== OrtCase::STATUS_AKTIF);

        return ['kasus' => null, 'sebab' => $pernahSelesai ? 'kasus_selesai' : 'tanpa_kasus'];
    }

    // ════════════════════════════════════════════════════════════════════════
    // Satu cabang
    // ════════════════════════════════════════════════════════════════════════

    private function prosesCabang(string $place, Carbon $sejak, Carbon $sampai, bool $kering): array
    {
        $n = ['kunjungan' => 0, 'cocok' => 0, 'dugaan' => 0, 'lewati' => 0, 'lewati_tgl' => 0,
              'usulan' => 0, 'selesai' => 0, 'tanpa_kasus' => 0, 'kasus_selesai' => 0, 'pid_kosong' => 0];

        $kunjungan = $this->kumpulkanKunjungan($place, $sejak, $sampai);
        if (! $kunjungan) {
            $this->line(sprintf('  %-12s tidak ada transaksi ortho', $this->branches->nama($place)));
            return $n;
        }

        // Satu kueri untuk semua pid sekaligus — bukan satu per kunjungan.
        //
        // groupBy, BUKAN keyBy: satu nomor rekam medis boleh punya banyak
        // episode (kunci uniknya `(omnicare_pid, install_date)`). keyBy
        // menyisakan satu baris per pid dan yang menang bergantung urutan yang
        // tidak ditentukan — kunjungan episode kedua bisa mendarat di episode
        // pertama tanpa error apa pun.
        //
        // Statusnya sengaja TIDAK disaring di kueri ini: kasus yang sudah
        // selesai tetap perlu dikenali, supaya bisa dilaporkan terpisah dari
        // "belum jadi kasus". Dua angka itu menuntut tindakan yang berbeda.
        $pids  = array_values(array_filter(array_unique(array_column($kunjungan, 'pid'))));
        $kasus = OrtCase::whereIn('omnicare_pid', $pids)
            ->orderBy('install_date')
            ->get()
            ->groupBy('omnicare_pid');

        $tersentuh = [];

        foreach ($kunjungan as $v) {
            $n['kunjungan']++;

            // Rekam medis tanpa nomor pasien tidak bisa ditautkan ke kasus mana
            // pun. Dihitung terpisah dari "belum jadi kasus" — yang itu
            // menuntut penyemaian ulang, yang ini menuntut Omnicare dirapikan.
            if ($v['pid'] === '') {
                $n['pid_kosong']++;
                continue;
            }

            $pilih = $this->episodeUntuk($kasus->get($v['pid']), $v['tanggal']);
            $c     = $pilih['kasus'];
            if (! $c) {
                $n[$pilih['sebab']]++;
                continue;
            }

            $hasil = $this->terapkan($c, $v, $place, $kering);
            $n[$hasil['jenis']]++;
            $n['usulan']  += $hasil['usulan'];
            $n['selesai'] += $hasil['selesai'];

            $tersentuh[$c->id] = true;
        }

        if (! $kering) {
            // Semua kasus aktif cabang ini, bukan hanya yang tersentuh:
            // visit_count_180d basi karena waktu berjalan.
            foreach (OrtCase::where('owner_place_id', $place)->where('status', OrtCase::STATUS_AKTIF)->pluck('id') as $id) {
                $tersentuh[$id] = true;
            }
            foreach (array_keys($tersentuh) as $id) {
                $this->rollup->refresh($id);
            }
        }

        $this->line(sprintf(
            '  %-12s %3d kunjungan · %d cocok · %d dugaan · %d dilewati (%d trx, %d tanggal) · %d tanpa kasus · %d kasus selesai · %d tanpa pid',
            $this->branches->nama($place), $n['kunjungan'], $n['cocok'], $n['dugaan'],
            $n['lewati'] + $n['lewati_tgl'], $n['lewati'], $n['lewati_tgl'],
            $n['tanpa_kasus'], $n['kasus_selesai'], $n['pid_kosong']
        ));

        return $n;
    }

    /**
     * Kunjungan ortho cabang ini di rentang itu — terdedup, wakil trx sudah
     * dipilih. Kolom, saringan, klasifikasi, dedup, dan pemilihan wakil
     * SEMUANYA di OrtVisits, bersama penyemai: kalau keduanya berbeda satu
     * baris saja, pagar omnicare_trx_id bocor (satu kunjungan, dua riwayat).
     */
    private function kumpulkanKunjungan(string $place, Carbon $sejak, Carbon $sampai): array
    {
        return $this->visits->kumpulkan(
            $this->visits->kueri([$place], $sejak->toDateString(), $sampai->toDateString())->get()
        );
    }

    /**
     * Terapkan satu kunjungan ke kasusnya.
     *
     * @return array{jenis:string, usulan:int, selesai:int}
     */
    private function terapkan(OrtCase $c, array $v, string $place, bool $kering): array
    {
        // Pagar anti-dobel dibaca DULU, sebelum apa pun ditulis. UNIQUE di DB
        // adalah jaring terakhir; memeriksanya di sini membuat laporan jujur
        // (terhitung "dilewati", bukan gagal menulis).
        if (OrtControl::where('omnicare_trx_id', $v['trx_id'])->exists()) {
            return ['jenis' => 'lewati', 'usulan' => 0, 'selesai' => 0];
        }

        // Pagar KEDUA: satu kasus, satu tanggal, satu kunjungan. Pagar trx di
        // atas tidak berbunyi kalau WAKIL kunjungan berubah (baris "Control"
        // masuk cermin semalam sesudah "Kawat"-nya; atau penyemai memilih
        // wakil lain) atau kalau Sales sudah menutup jadwal tanggal itu manual.
        // Tanpa pagar ini, tiap keadaan itu melahirkan baris riwayat kedua —
        // kehadiran 2/1, dan tak ada error yang memberi tahu siapa pun.
        // Memakai idx_ort_control_visit (ort_case_id, visit_date).
        if (OrtControl::where('ort_case_id', $c->id)->where('visit_date', $v['tanggal'])->exists()) {
            return ['jenis' => 'lewati_tgl', 'usulan' => 0, 'selesai' => 0];
        }

        $tglKunjungan = Carbon::parse($v['tanggal']);

        // Jadwal terbuka TERDEKAT dengan tanggal kunjungan — bukan yang
        // paling awal, bukan yang terakhir.
        $terbuka = OrtControl::where('ort_case_id', $c->id)
            ->whereNull('closed_at')
            ->get()
            ->sortBy(fn ($k) => $k->sch_date ? abs($k->sch_date->diffInDays($tglKunjungan)) : PHP_INT_MAX)
            ->first();

        $selisih = $terbuka && $terbuka->sch_date
            ? abs($terbuka->sch_date->diffInDays($tglKunjungan))
            : null;

        $cocok = $terbuka && $selisih !== null && $selisih <= self::TOLERANSI_HARI;

        $usulan = 0;
        $selesai = 0;

        if ($kering) {
            return [
                'jenis'   => $cocok ? 'cocok' : 'dugaan',
                'usulan'  => $cocok && ! $v['lepas'] ? 1 : 0,
                'selesai' => $v['lepas'] ? 1 : 0,
            ];
        }

        DB::transaction(function () use ($c, $v, $place, $terbuka, $cocok, $tglKunjungan, &$usulan, &$selesai) {
            if ($cocok) {
                // Jadwal yang memang ditunggu — tutup sebagai Hadir.
                $terbuka->fill([
                    // Kunjungan membuat jadwal ini NYATA. Usulan yang ditutup
                    // hadir tapi masih bertanda is_proposal terbaca sebagai
                    // "belum disepakati" di matriks drawer — padahal pasiennya
                    // sudah datang. Fakta mengalahkan usulan.
                    'is_proposal'           => 0,
                    'visit_date'            => $tglKunjungan->toDateString(),
                    'visit_source'          => 'rekam_medis',
                    'visit_confidence'      => 'pasti',
                    'omnicare_trx_id'       => $v['trx_id'],
                    'omnicare_encounter_id' => $v['encounter'],
                    'visit_filled_at'       => now(),
                    'outcome'               => OrtControl::OUT_HADIR,
                    'closed_at'             => now(),
                    'closed_by'             => 'sistem',
                    'place_id'              => $place,   // transfer terdeteksi: pasien datang di cabang lain
                ])->save();
            } else {
                // Tidak ada jadwal yang masuk akal. Kunjungan tetap dicatat
                // sebagai baris tersendiri yang langsung tertutup, supaya
                // riwayat tidak bolong — tapi jadwal terbuka TIDAK disentuh.
                OrtControl::create([
                    'id'                    => (string) Str::uuid(),
                    'ort_case_id'           => $c->id,
                    'seq'                   => (int) OrtControl::where('ort_case_id', $c->id)->max('seq') + 1,
                    'place_id'              => $place,
                    'is_proposal'           => 0,
                    'sch_date'              => $tglKunjungan->toDateString(),
                    'sch_source'            => OrtControl::SCH_REKAM_MEDIS,
                    'visit_date'            => $tglKunjungan->toDateString(),
                    'visit_source'          => 'rekam_medis',
                    'visit_confidence'      => 'dugaan',
                    'omnicare_trx_id'       => $v['trx_id'],
                    'omnicare_encounter_id' => $v['encounter'],
                    'visit_filled_at'       => now(),
                    'unscheduled'           => 1,
                    'outcome'               => OrtControl::OUT_HADIR,
                    'closed_at'             => now(),
                    'closed_by'             => 'sistem',
                    'created_by'            => 'sistem',
                ]);
            }

            if ($v['lepas']) {
                // Akhir perawatan. Tidak ada usulan berikutnya.
                $c->fill([
                    'status'      => OrtCase::STATUS_SELESAI,
                    'finished_at' => $tglKunjungan->toDateString(),
                    'updated_by'  => 'sistem',
                ])->save();
                $selesai = 1;
                return;
            }

            // Usulan jadwal berikutnya — hanya kalau tidak ada lagi yang terbuka.
            $masihAda = OrtControl::where('ort_case_id', $c->id)->whereNull('closed_at')->exists();
            if (! $masihAda) {
                OrtControl::create([
                    'id'          => (string) Str::uuid(),
                    'ort_case_id' => $c->id,
                    'seq'         => (int) OrtControl::where('ort_case_id', $c->id)->max('seq') + 1,
                    'place_id'    => $place,
                    'is_proposal' => 1,
                    // Kunjungan + 30, TAPI tidak pernah lahir sudah terlewat.
                    // Rekam medis yang masuk terlambat dulu melahirkan usulan
                    // bertanggal masa lalu — lihat OrtControl::tanggalUsulan().
                    'sch_date'    => OrtControl::tanggalUsulan($tglKunjungan)->toDateString(),
                    'sch_source'  => OrtControl::SCH_SISTEM,
                    'created_by'  => 'sistem',
                ]);
                $usulan = 1;
            }
        });

        return ['jenis' => $cocok ? 'cocok' : 'dugaan', 'usulan' => $usulan, 'selesai' => $selesai];
    }

    // ════════════════════════════════════════════════════════════════════════
    // Alat verifikasi — tidak menulis apa pun
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Cetak setiap nama item yang BENAR-BENAR ada di cermin beserta
     * klasifikasinya. Inilah cara memeriksa aturan OrtItemRules lawan katalog
     * sungguhan sebelum mempercayainya menulis apa pun.
     */
    private function daftarItem(array $places, Carbon $sejak, Carbon $sampai): int
    {
        $this->info('Daftar item ' . $sejak->toDateString() . ' → ' . $sampai->toDateString() . ' (tidak menulis apa pun)');
        $this->newLine();

        $rows = DB::table('omnicare_sales_detail')
            ->whereIn('place_id', $places)
            ->whereBetween('trx_date', [$sejak->toDateString(), $sampai->toDateString()])
            ->select('item_name', 'item_extra_name', 'item_code', DB::raw('COUNT(*) as n'))
            ->groupBy('item_name', 'item_extra_name', 'item_code')
            ->orderByDesc('n')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada satu baris pun di rentang itu. Cermin Omnicare kosong?');
            return self::SUCCESS;
        }

        $tabel  = [];
        $lepas  = [];
        $bukan  = [];
        $hitung = [];   // jumlah BARIS transaksi per klasifikasi
        $nama   = [];   // jumlah NAMA ITEM BERBEDA per klasifikasi

        foreach ($rows as $r) {
            $jenis = $this->rules->klasifikasi($r->item_name, $r->item_extra_name, $r->item_code) ?? '—';
            $kode  = $r->item_code ?: '';

            $hitung[$jenis] = ($hitung[$jenis] ?? 0) + $r->n;
            $nama[$jenis]   = ($nama[$jenis] ?? 0) + 1;   // satu baris $rows = satu nama

            if ($jenis !== '—') {
                $tabel[] = [$kode, $r->item_name, $r->item_extra_name, $r->n, $jenis];
            } else {
                $bukan[] = [$kode, $r->item_name, $r->item_extra_name, $r->n];
            }
            if ($jenis === 'lepas') {
                $lepas[] = [$kode, $r->item_name, $r->item_extra_name, $r->n];
            }
        }

        $this->table(['Kode', 'Item', 'Extra', 'Baris', 'Klasifikasi'], $tabel);
        $this->newLine();

        /*
         * Dua angka, bukan satu. "Baris" dibandingkan dengan volume transaksi;
         * "nama berbeda" dibandingkan dengan katalog. Brief menyebut 132 nama
         * item pemasangan berbeda pada 2025–2026 — kalau di sini `pasang` cuma
         * belasan nama, berarti sebagian besar tanggal pemasangan nanti harus
         * diperkirakan, dan itu harus diketahui SEBELUM menyemai.
         */
        $this->line('Ringkasan (— berarti dianggap BUKAN ortho):');
        foreach ($hitung as $j => $c) {
            $this->line(sprintf('  %-10s %6d baris · %3d nama berbeda', $j, $c, $nama[$j]));
        }
        $this->newLine();

        /*
         * Baris "lepas" diangkat ke tabelnya sendiri. Di katalog prod tabel di
         * atas panjangnya ratusan baris; peringatan saja tidak cukup untuk
         * membuat orang benar-benar memeriksanya satu per satu.
         */
        if ($lepas) {
            $this->warn('PERIKSA SATU PER SATU — ' . count($lepas) . ' nama diklasifikasi "lepas":');
            $this->table(['Kode', 'Item', 'Extra', 'Baris'], $lepas);
            $this->warn('Yang boleh ada di sini HANYA pelepasan behel akhir perawatan.');
            $this->warn('"Bracket Metal Lepas" (bracket copot lalu dipasang ulang) TIDAK boleh.');
            $this->warn('Brief konsultan: ±218 baris "Lepas Bracket" sepanjang masa. Kalau');
            $this->warn('angka di atas mencapai ribuan, aturannya salah baca — JANGAN menyemai.');
        } else {
            $this->line('Tidak ada satu pun item diklasifikasi "lepas" di rentang ini.');
        }

        /*
         * Nama yang TIDAK terklasifikasi juga harus terlihat, bukan cuma
         * terhitung. Item ortho yang namanya tak lazim akan mendarat di sini
         * dan bersembunyi di antara Scaling dan Tambal — persis yang terjadi
         * pada "Payday Pemasangan Signature Metal", yang tidak mengandung satu
         * pun kata ortho. Brief §6.1 memang menyatakan nama pemasangan tidak
         * punya pola baku; satu-satunya yang bisa menangkapnya adalah mata.
         */
        if ($bukan) {
            $this->newLine();
            $this->warn('DIANGGAP BUKAN ORTHO — ' . count($bukan) . ' nama. Sapu daftar ini:');
            $this->warn('cari yang sebenarnya ortho tapi namanya tak mengandung kata ortho');
            $this->warn('(mis. "Payday Pemasangan Signature Metal"). Yang ketemu harus');
            $this->warn('ditambahkan ke OrtItemRules SEBELUM menyemai.');
            usort($bukan, fn ($a, $b) => $b[3] <=> $a[3]);
            $this->table(['Kode', 'Item', 'Extra', 'Baris'], array_slice($bukan, 0, 40));
            if (count($bukan) > 40) {
                $this->line('  … ' . (count($bukan) - 40) . ' nama lagi (volume lebih kecil).');
            }
        }

        return self::SUCCESS;
    }

    /** Jalankan fixture OrtItemRules. Tidak menyentuh DB sama sekali. */
    private function ujiAturan(): int
    {
        $this->info('Fixture OrtItemRules');
        $this->newLine();

        $gagal = 0;
        $tabel = [];

        foreach (OrtItemRules::fixture() as $baris) {
            [$nama, $harap] = $baris;
            $kode  = $baris[2] ?? null;       // elemen ketiga opsional: item_code
            $dapat = $this->rules->klasifikasi($nama, null, $kode);
            $ok    = $dapat === $harap;
            if (! $ok) {
                $gagal++;
            }
            $tabel[] = [$ok ? 'OK' : 'GAGAL', $kode ?? '', $nama, $harap ?? '—', $dapat ?? '—'];
        }

        $this->table(['', 'Kode', 'Item', 'Diharapkan', 'Didapat'], $tabel);

        if ($gagal) {
            $this->error("$gagal dari " . count(OrtItemRules::fixture()) . ' fixture GAGAL.');
            return self::FAILURE;
        }

        $this->info('Semua ' . count(OrtItemRules::fixture()) . ' fixture lolos.');
        return self::SUCCESS;
    }

    private function tanggal(string $opsi, Carbon $default): Carbon
    {
        $v = $this->option($opsi);

        return $v ? Carbon::parse($v)->startOfDay() : $default->copy()->startOfDay();
    }
}
