<?php

namespace App\Console\Commands;

use App\Models\ItModul;
use App\Services\ItTracker\KatalogModul;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * it:seed-katalog — mengisi Katalog Modul dari docs/rencana/katalog-modul-2026-09.md
 * (33 entri, 8 departemen, sudah dikoreksi Ekky 4 Sep).
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

    protected $description = 'Isi Katalog Modul (33 entri) — idempoten by nama; access_id diselesaikan lewat jalur nama menu';

    /**
     * 33 entri: nama · departemen · jenis · status · sejak · terbukti · jalur node menu · catatan.
     * Sumber tunggal: docs/rencana/katalog-modul-2026-09.md.
     */
    private const KATALOG = [
        // ── SALES ──────────────────────────────────────────────────────────
        ['Reservasi', 'Sales', 'Operasional', 'hidup', '2026-04-07', true, 'Data Center>Reservation Data Center>Reservation',
            'Bukti data: raw_cus_reservation 6.039 (7 Apr). Halaman lain yang memakai modul ini — Reservasi Cabang & Reservasi Pasien — memakai link yang sama (rawreservation) lewat cabang menu lain.'],
        ['Master Jadwal & Layanan', 'Sales', 'Operasional', 'hidup', '2026-03-11', true, 'Data Center>Reservation Data Center>Doctor',
            'Bukti data: raw_doctor_schedule 105 · raw_doc_service_detail 148 (11 Mar). Branch & Doctor Service duduk di cabang menu yang sama dengan modul lain, jadi tak ikut terhitung di sini.'],
        ['Laporan Penjualan', 'Sales', 'Laporan & Dashboard', 'hidup', null, false, 'Laporan>Laporan>Laporan Penjualan',
            'Rekonsiliasi ada di cabang menu yang sama dan dipakai bersama modul lain.'],
        ['Dashboard Sales', 'Sales', 'Laporan & Dashboard', 'hidup', '2026-06-01', false, 'Data Center>Dashboard>Dashboard Sales',
            'Tanggalnya perkiraan bulan (Jun 2026) — tak ada tabel khusus yang membuktikan hari pertamanya.'],
        ['Pasien Baru / Lead Cekat AI', 'Sales', 'Operasional', 'hidup', '2026-08-27', true, null,
            'Formulir publik per cabang + tab Pasien Cekat AI — tanpa node menu sendiri.'],
        ['Kontak & Percakapan', 'Sales', 'Operasional', 'hidup', '2026-02-18', true, null,
            'Tanpa menu — berjalan di belakang. Bukti data: raw_contact 12.231 (18 Feb — data tertua di sistem) · raw_chat 34.313.'],

        // ── MARKETING ──────────────────────────────────────────────────────
        ['Konten', 'Marketing', 'Operasional', 'hidup', '2026-05-07', true, 'Konten',
            'Bukti data: ep_konten_pillar 22 (7 Mei) · ep_konten 214 (12 Mei).'],
        ['Referral & Member', 'Marketing', 'Operasional', 'hidup', '2026-04-01', true, 'Referral & Member',
            'Bukti data: raw_promo 32 (1 Apr) · raw_affiliate 133 (15 Mei).'],
        ['Promo', 'Marketing', 'Operasional', 'hidup', '2026-06-20', true, 'Data Center>Reservation Data Center>Promo (Marketing)',
            'Bukti data: marketing_promo 16 (20 Jun).'],
        ['Dashboard & Laporan Marketing', 'Marketing', 'Laporan & Dashboard', 'hidup', null, false, 'Dashboard & Laporan',
            'Dikonfirmasi Ekky terukur hidup (koreksi #4, 4 Sep). Tanggal pertamanya tak ada tabel yang membuktikan.'],

        // ── FINANCE ────────────────────────────────────────────────────────
        ['Petty Cash', 'Finance', 'Operasional', 'hidup', '2026-06-04', true, 'Keuangan>Petty Cash',
            'Bukti data: petty_cash_periods 23 (4 Jun) · petty_cash_transactions 359 (5 Jun). Operational Expense duduk di cabang menu Report.'],
        ['ROP (Pengajuan Pengeluaran)', 'Finance', 'Operasional', 'hidup', '2026-08-06', true, 'Keuangan>Pengajuan',
            'Bukti data: rop_requests 89 (6 Agu). Antrian Pengajuan · Rekap Pengeluaran · Lembar Bayar memakai cabang menu yang sama dengan modul Keuangan lain.'],
        ['Komisi Dokter', 'Finance', 'Laporan & Dashboard', 'hidup', '2026-08-29', true, 'Keuangan>Komisi Dokter',
            'Naik prod 29 Agu; angkanya cocok tiga arah dengan Accurate.'],
        ['Integrasi Accurate', 'Finance', 'Integrasi', 'hidup', '2026-06-12', true, null,
            'Tanpa menu — berjalan di belakang lewat accurate:push-daily. ⏰ Push DIJEDA sejak 28 Agu, terakhir ter-push 27 Agu; menyalakan lagi WAJIB sekali manual dengan --days lebar dulu. Bukti data: accurate_push_log 3.118 (12 Jun).'],
        ['Laporan Keuangan', 'Finance', 'Laporan & Dashboard', 'rencana', null, false, null, null],

        // ── OPERASIONAL / HR ───────────────────────────────────────────────
        ['Absensi', 'Operasional / HR', 'Operasional', 'hidup', '2026-08-04', true, 'Absensi',
            'Bukti data: att_work_rule 6 + att_user_rule 41 (4 Agu) · att_scan 1.045 (5 Agu) · att_hp_bukti 1.045.'],
        ['Pengawasan Absen', 'Operasional / HR', 'Operasional', 'hidup', '2026-08-05', true, 'Pengawasan',
            'WiFi Cabang duduk di cabang menu Pusat Akun, jadi terhitung di modul itu.'],
        ['Checklist Operasional', 'Operasional / HR', 'Operasional', 'hidup', '2026-07-01', true, 'Monitoring Formchecklist',
            'Bukti data: sys_checklist_division 3 (1 Jul) · sys_checklist_submission 403 (4 Agu). "Checklist Saya" adalah akar menu terpisah (halaman pribadi).'],
        ['Slip Gaji & Insentif', 'Operasional / HR', 'Operasional', 'rencana', null, false, null, null],
        ['Rolling / Penempatan global', 'Operasional / HR', 'Operasional', 'rencana', null, false, null,
            'Kini masih di dalam Checklist Operasional.'],

        // ── DATA PASIEN & REKAM MEDIS ──────────────────────────────────────
        ['Data Pasien', 'Data Pasien & Rekam Medis', 'Operasional', 'hidup', '2026-04-05', true, 'Data Center>Reservation Data Center>Pasien',
            'Bukti data: raw_customer 15.656 (5 Apr).'],
        ['Integrasi Omnicare', 'Data Pasien & Rekam Medis', 'Integrasi', 'hidup', '2026-05-20', true, null,
            'Tanpa menu — sinkron harian. ⚠️ Rekam medis tidak seragam: tindakan 100%, keluhan 27%, diagnosa 39%; doc_id berisi nama di rekam medis tapi angka di komisi. Jangan menjanjikan fitur rekam medis tanpa memeriksa ini dulu. Bukti data: omnicare_sales_detail 146.542 · omnicare_medical_record 51.020.'],

        // ── EXECUTIVE ──────────────────────────────────────────────────────
        ['Executive Report', 'Executive', 'Laporan & Dashboard', 'hidup', null, false, 'Data Center>Report>Executive', null],
        ['Ringkasan', 'Executive', 'Laporan & Dashboard', 'hidup', null, false, 'Dashboard>Menu>Ringkasan', null],
        ['Target vs Pencapaian', 'Executive', 'Laporan & Dashboard', 'dibangun', null, false, null,
            'Lulus dev, prod belum.'],

        // ── IT ─────────────────────────────────────────────────────────────
        ['Pusat Akun', 'IT', 'Platform', 'hidup', '2022-04-27', true, 'Data Center>Pusat Akun',
            'WARISAN: kerangka ber-UUID v1 bertanggal 27 Apr 2022 15:51 — empat tahun sebelum kerjaan Sweet Treats. Bagian mana yang warisan dan mana yang kita tulis ulang belum dipisahkan.'],
        ['Persetujuan', 'IT', 'Platform', 'hidup', '2026-08-01', false, 'Persetujuan',
            'Tanggalnya perkiraan bulan (Agu 2026) — tak ada tabel yang membuktikan hari pertamanya.'],
        ['Akun Saya', 'IT', 'Platform', 'hidup', '2026-08-01', false, 'Akun Saya',
            'Tanggalnya perkiraan bulan (Agu 2026) — tak ada tabel yang membuktikan hari pertamanya.'],
        ['Keluhan Sistem', 'IT', 'Platform', 'hidup', '2026-06-20', true, 'Keluhan',
            'Keluhan atas SISTEM, bukan keluhan pasien (koreksi #2 Ekky — nama node menu bukan nama pemilik).'],
        ['IT Request Tracker', 'IT', 'Platform', 'dibangun', null, false, 'IT',
            'Live di dev, prod belum.'],
        ['Katalog Modul', 'IT', 'Platform', 'rencana', null, false, null,
            'Halaman ini — lahir dari pertanyaan Executive "apa saja yang kami punya".'],

        // ── STOK / INVENTORY ───────────────────────────────────────────────
        ['Inventory / Stok', 'Stok / Inventory', 'Operasional', 'rencana', null, false, null,
            'Departemennya ada di rencana sebelum ada di perangkat lunak — dan itu memang harus terlihat.'],
        ['COGS — kendali harga & HPP', 'Stok / Inventory', 'Operasional', 'rencana', null, false, null, null],
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
            foreach (self::KATALOG as $i => [$nama, $dept, $jenis, $status, $sejak, $terbukti, $jalur, $catatan]) {
                $accessId = null;
                if ($jalur !== null) {
                    $accessId = $this->katalog->cariJalur($jalur);
                    // node tak ada di lingkungan ini → NULL + dilaporkan, bukan didiamkan
                    if ($accessId === null) $nodeHilang[] = $nama . '  ←  ' . $jalur;
                    else $fitur[$nama] = count($this->katalog->fitur($accessId));
                } else {
                    $tanpaNode[] = $nama;
                }

                $isi = ['departemen' => $dept, 'jenis' => $jenis, 'status' => $status,
                    'sejak' => $sejak, 'sejak_terbukti' => $terbukti, 'access_id' => $accessId,
                    'catatan' => $catatan, 'urutan' => ($i + 1) * 10];

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
