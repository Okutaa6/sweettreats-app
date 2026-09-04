<?php

namespace App\Console\Commands;

use App\Models\ItModul;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * it:hitung-pemakaian — mengisi `it_modul.pakai_7hari` + `pakai_at`.
 *
 * KENAPA ADA (rencana §14.26): dengan tiga angka lama (hidup · berjalan ·
 * utang), modul yang SUDAH DITINGGALKAN terlihat sempurna sehat — Keluhan
 * Sistem berstatus Hidup, nol tiket berjalan, nol utang, padahal terukur mati
 * (3 catatan seumur hidup, terakhir 28 Juni). Angka pemakaian satu-satunya yang
 * membedakan modul TENANG KARENA BERES dari modul SUNYI KARENA DITINGGALKAN.
 *
 * KENAPA DISIMPAN, bukan dihitung saat halaman dibuka: tabel sumbernya besar
 * (omnicare_sales_detail 146rb baris) dan katalog dibuka lintas peran.
 * ⚠️ Konsekuensinya angka ini BISA BASI — karena itu `pakai_at` ikut disimpan
 *    dan WAJIB tampil di sebelah angkanya di layar. Angka tersimpan boleh,
 *    asal usianya kelihatan.
 *
 * ⛔ Modul tanpa pemetaan (`pakai_tabel` NULL) TIDAK ditulis 0 — ia tetap NULL
 *    dan layar menulis "—". Nol berarti MATI; itu beda dari BELUM TERUKUR, dan
 *    menyamakan keduanya adalah kebohongan yang paling mudah dipercaya.
 */
class ItHitungPemakaian extends Command
{
    protected $signature = 'it:hitung-pemakaian
                            {--hari=7 : jendela hitung dalam hari}
                            {--diam : tanpa rincian per modul}';

    protected $description = 'Hitung "dipakai N× dalam 7 hari" per modul katalog (disimpan + distempel waktu)';

    public function handle(): int
    {
        $hari  = max(1, (int) $this->option('hari'));
        $batas = now()->subDays($hari);
        $saat  = now();

        $modul = ItModul::whereNotNull('pakai_tabel')->orderBy('urutan')->get();
        if ($modul->isEmpty()) {
            $this->output->writeln('<comment>Tak ada modul ber-pemetaan pemakaian. Jalankan it:seed-katalog dulu.</comment>');
            return self::SUCCESS;
        }

        $hasil = []; $gagal = [];
        foreach ($modul as $m) {
            $tabel = (string) $m->pakai_tabel;
            $kolom = (string) ($m->pakai_kolom ?: 'created_at');

            // Nama tabel/kolom masuk ke SQL sebagai teks (SHOW TABLES LIKE tak
            // menerima binding di MariaDB), jadi keduanya disaring dulu — isinya
            // memang dari konstanta seeder, tapi kolom DB bisa disunting siapa pun
            // yang punya akses tulis ke it_modul.
            if (! preg_match('/^[A-Za-z0-9_]+$/', $tabel) || ! preg_match('/^[A-Za-z0-9_]+$/', $kolom)) {
                $gagal[$m->nama] = "nama tabel/kolom tidak wajar ({$tabel}.{$kolom}) — dilewati";
                continue;
            }

            // SHOW TABLES LIKE + SELECT … LIMIT 0 — BUKAN information_schema /
            // SHOW COLUMNS / DESCRIBE (membunuh mysqld lokal).
            if (! DB::select("SHOW TABLES LIKE '{$tabel}'")) {
                $gagal[$m->nama] = "tabel {$tabel} tidak ada di lingkungan ini";
                continue;
            }
            try {
                DB::select("SELECT `{$kolom}` FROM `{$tabel}` LIMIT 0");
                $n = (int) DB::table($tabel)->where($kolom, '>=', $batas)->count();
            } catch (\Throwable $e) {
                $gagal[$m->nama] = "kolom {$tabel}.{$kolom}: " . mb_substr($e->getMessage(), 0, 70);
                continue;
            }

            // ⛔ pakai_at DISENTUH tiap kali dihitung, walau angkanya sama —
            //    yang dibaca manusia adalah USIA angkanya, bukan perubahannya.
            $m->forceFill(['pakai_7hari' => $n, 'pakai_at' => $saat])->save();
            $hasil[$m->nama] = $n;
        }

        arsort($hasil);
        $this->output->writeln('<info>Pemakaian ' . $hari . ' hari terakhir</info> (per ' . $saat->translatedFormat('j M Y H:i') . '):');
        if (! $this->option('diam')) {
            foreach ($hasil as $nama => $n) {
                $tanda = $n === 0 ? '  ← sunyi' : '';
                $this->output->writeln('  ' . str_pad($nama, 32) . str_pad((string) $n, 6, ' ', STR_PAD_LEFT) . $tanda);
            }
        }
        $sunyi = count(array_filter($hasil, fn ($n) => $n === 0));
        $this->output->writeln('  ' . count($hasil) . ' modul terukur · ' . $sunyi . ' di antaranya NOL '
            . '(sunyi — periksa: tenang karena beres, atau ditinggalkan?)');

        $tanpa = ItModul::whereNull('pakai_tabel')->count();
        $this->output->writeln('  ' . $tanpa . ' modul belum punya pemetaan → tetap "—" di layar, BUKAN 0.');

        foreach ($gagal as $nama => $sebab) {
            $this->output->writeln("<comment>  ⚠ {$nama}: {$sebab} — angkanya dibiarkan apa adanya, tidak dinolkan.</comment>");
        }

        return self::SUCCESS;
    }
}
