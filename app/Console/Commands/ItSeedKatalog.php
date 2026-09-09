<?php

namespace App\Console\Commands;

use App\Models\ItModul;
use App\Services\ItTracker\KatalogModul;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * it:seed-katalog — mengisi Katalog Modul dari docs/rencana/katalog-modul-2026-09.md
 * (37 entri, 8 departemen; §14.26 menambah 4 entri & memindahkan 2 departemen).
 *
 * ⚠️ Perintah ini juga menetapkan PEMETAAN pemakaian (tabel + kolom waktu),
 *    bukan angkanya. Angka ditulis `it:hitung-pemakaian` supaya halaman katalog
 *    tak menghitung 146rb baris tiap kali dibuka.
 *
 * Aturan yang membentuknya:
 * - IDEMPOTEN by `nama` (unik). Jalan ulang memperbarui, tidak menggandakan.
 * - `access_id` diselesaikan lewat JALUR NAMA node menu, bukan id — id node
 *   berbeda antar lingkungan (jebakan 2026-07-24). Node yang tak ada di
 *   lingkungan ini ditinggal NULL dan DILAPORKAN, bukan didiamkan.
 * - Tanggal: hanya yang bisa dibuktikan tabelnya yang ber-`sejak_terbukti`=1.
 *   Yang tak terbukti tetap ditulis apa adanya + penjelasan di catatan —
 *   JANGAN mengarang tanggal, jangan biarkan kosong tanpa keterangan.
 * - ⛔ Fitur TIDAK ikut di-seed: ia diturunkan dari access_path saat dibaca.
 */
class ItSeedKatalog extends Command
{
    protected $signature = 'it:seed-katalog
                            {--env-guard= : local | dev | prod — WAJIB cocok dengan lingkungan ini}
                            {--kering : hanya laporkan, jangan menulis apa pun}';

    protected $description = 'Isi Katalog Modul (37 entri) — idempoten by nama; access_id & pemetaan pemakaian diselesaikan di sini';

    /**
     * 37 entri: nama · departemen · jenis · status · sejak · terbukti · jalur node
     * menu · catatan · tabel pemakaian · kolom waktu pemakaian · DAFTAR LINK fitur.
     *
     * ⚠️ KOLOM TERAKHIR (§14.27) yang menentukan jumlah fitur — bukan `jalur`.
     * `jalur` tinggal menandai "rumah menu" modul; fiturnya dihitung dari link,
     * DEDUP PER LINK, karena satu halaman bisa punya banyak label menu
     * (rawreservation muncul 4×). Link juga stabil antar lingkungan.
     * Sumber tunggal: docs/rencana/katalog-modul-2026-09.md (§14.26 — 37 entri:
     * 25 hidup · 3 dibangun · 9 direncanakan).
     *
     * DUA KOLOM TERAKHIR menjawab pertanyaan yang tak bisa dijawab tiga angka
     * lama: "modul ini masih dipakai orang atau tidak". NULL = belum terukur →
     * layar menulis "—", BUKAN 0. Nol berarti MATI, dan itu beda dari tak
     * terukur. Angkanya ditulis `php artisan it:hitung-pemakaian` (harian).
     */
    private const KATALOG = [
        // ── SALES ──────────────────────────────────────────────────────────
        ['Reservasi', 'Sales', 'Operasional', 'hidup', '2026-04-07', true, 'Data Center>Reservation Data Center>Reservation',
            'Bukti data: raw_cus_reservation 6.039 (7 Apr). Halaman lain yang memakai modul ini — Reservasi Cabang & Reservasi Pasien — memakai link yang sama (rawreservation) lewat cabang menu lain.',
            'raw_cus_reservation', 'created_at',
            ['rawreservation', 'reservasicabang']],
        ['Master Jadwal & Layanan', 'Sales', 'Operasional', 'hidup', '2026-03-11', true, 'Data Center>Reservation Data Center>Doctor',
            'Bukti data: raw_doctor_schedule 105 · raw_doc_service_detail 148 (11 Mar). Branch & Doctor Service duduk di cabang menu yang sama dengan modul lain, jadi tak ikut terhitung di sini.',
            null, null,
            ['rawbranch', 'rawdoctor', 'rawdocservice']],
        ['Laporan Penjualan', 'Sales', 'Laporan & Dashboard', 'hidup', null, false, 'Laporan>Laporan>Laporan Penjualan',
            'Rekonsiliasi ada di cabang menu yang sama dan dipakai bersama modul lain.', null, null,
            ['report']],
        ['Dashboard Sales', 'Sales', 'Laporan & Dashboard', 'hidup', '2026-06-01', false, 'Data Center>Dashboard>Dashboard Sales',
            'Tanggalnya perkiraan bulan (Jun 2026) — tak ada tabel khusus yang membuktikan hari pertamanya.', null, null,
            ['sales-dashboard']],
        ['Pasien Baru / Lead Cekat AI', 'Sales', 'Operasional', 'hidup', '2026-08-27', true, null,
            'Formulir publik per cabang + tab Pasien Cekat AI — tanpa node menu sendiri.',
            'raw_contact', 'created_at',
            []],
        ['Kontak & Percakapan', 'Sales', 'Operasional', 'hidup', '2026-02-18', true, null,
            'Tanpa menu — berjalan di belakang. Bukti data: raw_contact 12.231 (18 Feb — data tertua di sistem) · raw_chat 34.313. Pemakaian dihitung dari raw_chat supaya tak menghitung ganda dengan modul Lead (raw_contact).',
            'raw_chat', 'created_at',
            []],
        ['ORTHO — tracking & retensi', 'Sales', 'Operasional', 'rencana', null, false, null,
            'Lahir dari tiket antrean IT (§14.26). Belum ada perangkat lunaknya.', null, null,
            []],
        ['Akun Dokter', 'Sales', 'Operasional', 'dibangun', null, false, null,
            'Halaman dokter (Hari Ini · Jadwal Saya · Pasien Saya) duduk di bawah akar menu "Akun Saya", jadi fiturnya terhitung di modul itu — bukan di sini, supaya tak dihitung dua kali.',
            null, null,
            []],

        // ── MARKETING ──────────────────────────────────────────────────────
        ['Konten', 'Marketing', 'Operasional', 'hidup', '2026-05-07', true, 'Konten',
            'Bukti data: ep_konten_pillar 22 (7 Mei) · ep_konten 214 (12 Mei).',
            'ep_konten', 'created_at',
            ['content-review', 'konten-pillar', 'ep-konten', 'ep-breakdown', 'ep-campaign', 'rawkeyword']],
        ['Referral & Member', 'Marketing', 'Operasional', 'hidup', '2026-04-01', true, 'Referral & Member',
            'Bukti data: raw_promo 32 (1 Apr) · raw_affiliate 133 (15 Mei).', null, null,
            ['rawaffiliate', 'kol', 'koc', 'sweet-mates', 'rawpromo']],
        ['Promo', 'Marketing', 'Operasional', 'hidup', '2026-06-20', true, 'Data Center>Reservation Data Center>Promo (Marketing)',
            'Bukti data: marketing_promo 16 (20 Jun).', null, null,
            ['marketing-promo']],
        ['Dashboard & Laporan Marketing', 'Marketing', 'Laporan & Dashboard', 'hidup', null, false, 'Dashboard & Laporan',
            'Dikonfirmasi Ekky terukur hidup (koreksi #4, 4 Sep). Tanggal pertamanya tak ada tabel yang membuktikan.', null, null,
            ['marketing-dashboard', 'commission-claim', 'report-referral']],

        // ── FINANCE ────────────────────────────────────────────────────────
        ['Petty Cash', 'Finance', 'Operasional', 'hidup', '2026-06-04', true, 'Keuangan>Petty Cash',
            'Bukti data: petty_cash_periods 23 (4 Jun) · petty_cash_transactions 359 (5 Jun). Operational Expense duduk di cabang menu Report.',
            'petty_cash_transactions', 'created_at',
            ['finance/pettycash']],
        ['ROP (Pengajuan Pengeluaran)', 'Finance', 'Operasional', 'hidup', '2026-08-06', true, 'Keuangan>Pengajuan',
            'Bukti data: rop_requests 89 (6 Agu). ⚠️ Keyakinan lama "adopsi tipis" PATAH: terukur 4 Sep 89 total, 33 dalam 7 hari. Antrian Pengajuan · Rekap Pengeluaran · Lembar Bayar memakai cabang menu yang sama dengan modul Keuangan lain.',
            'rop_requests', 'created_at',
            ['pengajuan']],
        ['Komisi Dokter', 'Finance', 'Laporan & Dashboard', 'hidup', '2026-08-29', true, 'Keuangan>Komisi Dokter',
            'Naik prod 29 Agu; angkanya cocok tiga arah dengan Accurate. Tak masuk tiket stabilisasi karena tak punya pertanyaan terbuka (§14.26).',
            null, null,
            ['komisi-dokter']],
        ['Integrasi Accurate', 'Finance', 'Integrasi', 'hidup', '2026-06-12', true, null,
            'Tanpa menu — berjalan di belakang lewat accurate:push-daily. ⏰ Push DIJEDA sejak 28 Agu, terakhir ter-push 27 Agu; menyalakan lagi WAJIB sekali manual dengan --days lebar dulu. Bukti data: accurate_push_log 3.118 (12 Jun).',
            'accurate_push_log', 'created_at',
            []],
        ['Laporan Keuangan', 'Finance', 'Laporan & Dashboard', 'rencana', null, false, null, null, null, null,
            []],

        // ── OPERASIONAL / HR ───────────────────────────────────────────────
        ['Absensi', 'Operasional / HR', 'Operasional', 'hidup', '2026-08-04', true, 'Absensi',
            'Bukti data: att_work_rule 6 + att_user_rule 41 (4 Agu) · att_scan 1.045 (5 Agu) · att_hp_bukti 1.045.',
            'att_scan', 'created_at',
            ['absensi', 'absensi/kehadiran', 'absensi/mapping', 'absensi/aturan', 'absensi/cuti', 'absensi/lembar-gaji', 'absensi/periode', 'absensi/recap']],
        ['Pengawasan Absen', 'Operasional / HR', 'Operasional', 'hidup', '2026-08-05', true, 'Pengawasan',
            'WiFi Cabang duduk di cabang menu Pusat Akun, jadi terhitung di modul itu. Evaluasinya dilebur ke tiket Absensi — satu rilis, satu evaluator (§14.26).',
            null, null,
            ['pengawasan']],
        ['Checklist Operasional', 'Operasional / HR', 'Operasional', 'hidup', '2026-07-01', true, 'Monitoring Formchecklist',
            'Bukti data: sys_checklist_division 3 (1 Jul) · sys_checklist_submission 403 (4 Agu). "Checklist Saya" adalah akar menu terpisah (halaman pribadi).',
            'sys_checklist_submission', 'created_at',
            ['checklist', 'checklist/setup', 'checklist/review', 'my-checklist']],
        ['Persetujuan', 'Operasional / HR', 'Operasional', 'hidup', '2026-08-01', false, 'Persetujuan',
            'PINDAH dari IT ke Operasional/HR (koreksi Ekky 4 Sep, terukur): PersetujuanController ada di app/Http/Controllers/Absensi/ dan att_request isinya HANYA tipe absensi (lembur 258 · koreksi 25 · cuti 20 · tukar 2 · sakit 2 · izin 1). ROP, top-up, dan checklist-reopen punya jalur persetujuan sendiri yang tak lewat inbox ini.',
            'att_request_approval', 'created_at',
            ['persetujuan']],
        ['Akun Saya', 'Operasional / HR', 'Operasional', 'hidup', '2026-08-01', false, 'Akun Saya',
            'PINDAH dari IT ke Operasional/HR (koreksi Ekky 4 Sep): isinya halaman pribadi absensi & dokter, bukan perkakas IT. Tanggalnya perkiraan bulan.',
            null, null,
            ['akun', 'akun/dokter', 'akun/dokter/jadwal', 'akun/dokter/pasien']],
        ['Slip Gaji & Insentif', 'Operasional / HR', 'Operasional', 'rencana', null, false, null, null, null, null,
            []],
        ['Rolling / Penempatan global', 'Operasional / HR', 'Operasional', 'rencana', null, false, null,
            'Kini masih di dalam Checklist Operasional.', null, null,
            []],

        // ── DATA PASIEN & REKAM MEDIS ──────────────────────────────────────
        ['Data Pasien', 'Data Pasien & Rekam Medis', 'Operasional', 'hidup', '2026-04-05', true, 'Data Center>Reservation Data Center>Pasien',
            'Bukti data: raw_customer 15.656 (5 Apr).', null, null,
            ['rawcustomer']],
        ['Integrasi Omnicare', 'Data Pasien & Rekam Medis', 'Integrasi', 'hidup', '2026-05-20', true, null,
            'Tanpa menu — sinkron harian. ⚠️ Rekam medis tidak seragam: tindakan 100%, keluhan 27%, diagnosa 39%; doc_id berisi nama di rekam medis tapi angka di komisi. Jangan menjanjikan fitur rekam medis tanpa memeriksa ini dulu. Bukti data: omnicare_sales_detail 146.542 · omnicare_medical_record 51.020.',
            'omnicare_sales_detail', 'created_at',
            []],

        // ── EXECUTIVE ──────────────────────────────────────────────────────
        ['Executive Report', 'Executive', 'Laporan & Dashboard', 'hidup', null, false, 'Data Center>Report>Executive', null, null, null,
            ['report/executive']],
        ['Ringkasan', 'Executive', 'Laporan & Dashboard', 'hidup', null, false, 'Dashboard>Menu>Ringkasan', null, null, null,
            ['dashboard']],
        ['Target vs Pencapaian', 'Executive', 'Laporan & Dashboard', 'dibangun', null, false, null,
            'Lulus dev, prod belum.', null, null,
            []],

        // ── IT ─────────────────────────────────────────────────────────────
        ['Pusat Akun', 'IT', 'Platform', 'hidup', '2022-04-27', true, 'Data Center>Pusat Akun',
            'WARISAN: kerangka ber-UUID v1 bertanggal 27 Apr 2022 15:51 — empat tahun sebelum kerjaan Sweet Treats. Bagian mana yang warisan dan mana yang kita tulis ulang belum dipisahkan.',
            null, null,
            ['setusermanagement', 'setgroup', 'setaccess', 'setsystemprofile', 'setkesehatan', 'absensi/wifi']],
        ['Keluhan Sistem', 'IT', 'Platform', 'hidup', '2026-06-20', true, 'Keluhan',
            'Keluhan atas SISTEM, bukan keluhan pasien (koreksi #2 Ekky — nama node menu bukan nama pemilik). ⚠️ Terukur SUNYI: 3 catatan seumur hidup, terakhir 28 Jun, nol dalam 30 hari — kemungkinan sudah tergantikan IT Tracker. Inilah modul yang melahirkan kolom pemakaian.',
            'sys_complaint', 'created_at',
            ['complaint']],
        ['IT Request Tracker', 'IT', 'Platform', 'dibangun', null, false, 'IT',
            'Live di dev, prod belum.', null, null,
            ['it-tracker']],
        ['Katalog Modul', 'IT', 'Platform', 'rencana', null, false, null,
            'Halaman ini — lahir dari pertanyaan Executive "apa saja yang kami punya".', null, null,
            []],
        ['Project Tracker', 'IT', 'Platform', 'rencana', null, false, null,
            'Fase A selesai di lokal lalu diparkir di branch feature/project-tracker; belum pernah naik dev maupun prod.', null, null,
            []],
        ['Notifikasi', 'IT', 'Platform', 'rencana', null, false, null,
            'WA reservasi + hub pemberitahuan internal. Hari ini TAK ADA satu pun pemberitahuan keluar aplikasi — itulah kenapa tiket yang menunggu di luar IT cuma terlihat kalau orangnya kebetulan login.',
            null, null,
            []],

        // ── STOK / INVENTORY ───────────────────────────────────────────────
        ['Inventory / Stok', 'Stok / Inventory', 'Operasional', 'rencana', null, false, null,
            'Departemennya ada di rencana sebelum ada di perangkat lunak — dan itu memang harus terlihat.', null, null,
            []],
        ['COGS — kendali harga & HPP', 'Stok / Inventory', 'Operasional', 'rencana', null, false, null, null, null, null,
            []],
    ];

    public function __construct(private KatalogModul $katalog)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $minta = (string) $this->option('env-guard');
        $nyata = ItSeedAntrian::lingkunganNyata();
        if ($minta === '' || $minta !== $nyata) {
            $this->output->writeln('<fg=red>DITOLAK: --env-guard=' . ($minta ?: '(kosong)') . " tetapi lingkungan ini terbaca '{$nyata}' "
                . '(APP_ENV=' . config('app.env') . ', APP_URL=' . config('app.url') . '). Sebut lingkungan yang benar secara eksplisit.</>');
            return self::FAILURE;
        }

        $kering = (bool) $this->option('kering');
        $baru = 0; $ubah = 0; $tetap = 0; $tanpaNode = []; $nodeHilang = []; $fitur = [];

        DB::transaction(function () use (&$baru, &$ubah, &$tetap, &$tanpaNode, &$nodeHilang, &$fitur, $kering) {
            foreach (self::KATALOG as $i => [$nama, $dept, $jenis, $status, $sejak, $terbukti, $jalur, $catatan, $pakaiTabel, $pakaiKolom, $link]) {
                $accessId = null;
                if ($jalur !== null) {
                    $accessId = $this->katalog->cariJalur($jalur);
                    // node tak ada di lingkungan ini → NULL + dilaporkan, bukan didiamkan
                    if ($accessId === null) $nodeHilang[] = $nama . '  ←  ' . $jalur;
                } else {
                    $tanpaNode[] = $nama;
                }

                // ⛔ pakai_7hari & pakai_at TIDAK ikut di sini: itu milik
                //    it:hitung-pemakaian. Seeder cuma menetapkan PEMETAANNYA.
                $isi = ['departemen' => $dept, 'jenis' => $jenis, 'status' => $status,
                    'sejak' => $sejak, 'sejak_terbukti' => $terbukti, 'access_id' => $accessId,
                    'catatan' => $catatan, 'urutan' => ($i + 1) * 10,
                    'pakai_tabel' => $pakaiTabel, 'pakai_kolom' => $pakaiKolom,
                    // §14.27: daftar LINK, bukan satu node — menu mengelompokkan
                    // berdasarkan navigasi, katalog berdasarkan kepemilikan.
                    'fitur_link' => $link ?: null];

                $m = ItModul::where('nama', $nama)->first();
                if (! $m) {
                    if (! $kering) ItModul::create(['nama' => $nama] + $isi);
                    $baru++;
                    continue;
                }

                $beda = false;
                foreach ($isi as $k => $v) {
                    $lama = $k === 'sejak' ? optional($m->sejak)->toDateString() : $m->{$k};
                    if ($k === 'sejak_terbukti') { $lama = (bool) $lama; $v = (bool) $v; }
                    if ($lama != $v) { $beda = true; break; }
                }
                if ($beda) {
                    if (! $kering) $m->update($isi);
                    $ubah++;
                } else {
                    $tetap++;
                }
            }
        });

        // Hitungan fitur dilaporkan lewat jalur yang SAMA dengan layar (§14.27:
        // daftar link, dedup per link) — laporan yang memakai jalur lain akan
        // berselisih dengan halaman dan tak ada yang tahu mana yang benar.
        $semuaLink = [];
        foreach (ItModul::all() as $m) {
            $f = $this->katalog->fiturModul($m);
            if ($f) $fitur[$m->nama] = count($f);
            foreach ($f as $x) $semuaLink[$x['link']] = true;
        }

        // ── laporan: yang tak bisa dibuktikan HARUS terbaca, bukan didiamkan ──
        $total = ItModul::count();
        $per   = fn (string $kolom) => ItModul::selectRaw("$kolom AS k, COUNT(*) AS n")->groupBy($kolom)->pluck('n', 'k')->all();

        $this->output->writeln('');
        $this->output->writeln('<info>Katalog Modul' . ($kering ? ' (KERING — tak ada yang ditulis)' : '') . ':</info> '
            . "{$baru} baru · {$ubah} diperbarui · {$tetap} tetap · total di tabel: {$total}");
        foreach (['status', 'jenis', 'departemen'] as $kolom) {
            $p = $per($kolom);
            ksort($p);
            $this->output->writeln('  ' . str_pad($kolom, 11) . ': ' . implode(' · ', array_map(fn ($k, $n) => "$k $n", array_keys($p), $p)));
        }

        $this->output->writeln('  link unik seluruh katalog: ' . count($semuaLink)
            . ' (fitur dihitung PER LINK, dedup — satu halaman sekali walau labelnya banyak)');
        if ($fitur) {
            arsort($fitur);
            $atas = array_slice($fitur, 0, 6, true);
            $this->output->writeln('  fitur diturunkan (terbanyak): '
                . implode(' · ', array_map(fn ($k, $n) => "$k $n", array_keys($atas), $atas)));
        }
        if ($tanpaNode) {
            $this->output->writeln('  tanpa menu (disengaja, ' . count($tanpaNode) . '): ' . implode(' · ', $tanpaNode));
        }
        if ($nodeHilang) {
            $this->output->writeln('<comment>  ⚠ node menu tak ditemukan di lingkungan ini — access_id dibiarkan NULL, fitur 0:</comment>');
            foreach ($nodeHilang as $h) $this->output->writeln('     ' . $h);
            $this->output->writeln('    (wajar di lokal yang menunya tertinggal; di dev/prod ini berarti nama node berubah — periksa.)');
        }

        return self::SUCCESS;
    }
}
