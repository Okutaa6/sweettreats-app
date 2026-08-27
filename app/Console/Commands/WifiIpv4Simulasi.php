<?php

namespace App\Console\Commands;

use App\Models\AttBranchIp;
use App\Models\AttHpBukti;
use App\Services\WifiBelajarSendiri;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Putar ulang absen SUNGGUHAN lewat jalur belajar-sendiri IPv4 — di dalam
 * transaksi yang DIBATALKAN (uji terima §7-10 rencana-wifi-ipv4-belajar.md).
 *
 * KENAPA HARUS DATA SUNGGUHAN. Kasus karangan cuma membuktikan aturannya
 * berjalan seperti yang ditulis; ia tak bisa membuktikan aturannya menolong,
 * dan tak bisa membuktikan ia tak merusak yang sudah jalan. Dua perbaikan
 * sebelumnya diputuskan dengan cara ini — pelebaran /48 (21 Agu: 351 alamat
 * diputar ulang, 52 baru cocok, 0 hilang, 0 pindah cabang) dan libur-cabang
 * (22 Agu) — dan pola itu yang dipakai lagi di sini.
 *
 * TIDAK MENULIS APA PUN. Seluruh pemutaran berjalan di dalam satu transaksi
 * yang selalu di-rollback, termasuk saat galat. Jumlah baris att_branch_ip
 * dihitung sebelum dan sesudah sebagai bukti, bukan sebagai janji. Karena itu
 * perkakas ini — beda dengan att:wifi-uji — AMAN dijalankan di produksi, dan
 * memang di sanalah datanya berada.
 *
 * CARA MEMBACANYA. "sebelum" = kenyataan yang tercatat (att_hp_bukti.
 * branch_id_cocok saat absen itu terjadi); "sesudah" = hasil kode baru dengan
 * tabel pendaftaran yang ikut tumbuh sepanjang pemutaran. Yang dicari:
 *   · tertolong  naik   — itu gunanya
 *   · hilang     NOL    — tak boleh ada yang dulu cocok lalu jadi tak cocok
 *   · pindah     NOL    — tak boleh ada yang berpindah cabang diam-diam
 *
 * ⚠️ Yang diputar ulang adalah PENCOCOKAN dan PEMBELAJARAN (matchBranch +
 * WifiBelajarSendiri), bukan seluruh AttendanceHpAbsen::absen(). Flag, scan,
 * dan dedup tidak ikut disimulasikan — perubahan ini memang tak menyentuhnya.
 */
class WifiIpv4Simulasi extends Command
{
    protected $signature = 'att:wifi-ipv4-simulasi
        {--sejak= : tanggal mulai (Y-m-d), default 21 hari ke belakang}
        {--sampai= : tanggal akhir (Y-m-d), default hari ini}
        {--cabut-ipv4-sejak= : cabut dulu baris IPv4 yang didaftarkan pada/sesudah tanggal ini, supaya dunia diputar dari sebelum tambalan manual}
        {--rinci : tampilkan tiap absen yang berubah nasibnya}';

    protected $description = 'Putar ulang absen nyata lewat belajar-sendiri IPv4, di dalam transaksi yang dibatalkan (§7-10)';

    public function handle(): int
    {
        $sejak  = Carbon::parse($this->option('sejak') ?: Carbon::now()->subDays(21)->toDateString())->startOfDay();
        $sampai = Carbon::parse($this->option('sampai') ?: Carbon::now()->toDateString())->endOfDay();

        $this->line('');
        $this->line('<fg=yellow>Simulasi belajar-sendiri IPv4 — TIDAK menulis apa pun.</>');
        $this->line("   rentang : {$sejak->toDateString()} .. {$sampai->toDateString()}");
        $this->line('   saklar  : ' . (WifiBelajarSendiri::aktif() ? 'menyala' : '<fg=red>MATI — hasilnya akan nol, nyalakan dulu untuk menilai</>'));

        $barisSebelum = AttBranchIp::count();
        $buktiSebelum = AttHpBukti::count();

        DB::beginTransaction();
        try {
            $hasil = $this->putarUlang($sejak, $sampai);
        } finally {
            DB::rollBack();
        }

        // Bukti bahwa tak ada yang tertinggal — dihitung, bukan dijanjikan.
        $barisSesudah = AttBranchIp::count();
        $buktiSesudah = AttHpBukti::count();
        $utuh = $barisSebelum === $barisSesudah && $buktiSebelum === $buktiSesudah;

        $this->line('');
        $this->line($utuh
            ? "<fg=green>✓ DB utuh — att_branch_ip {$barisSesudah} baris, att_hp_bukti {$buktiSesudah} baris (sama seperti sebelum).</>"
            : "<fg=red>✗ JEJAK TERTINGGAL: att_branch_ip {$barisSebelum}→{$barisSesudah}, att_hp_bukti {$buktiSebelum}→{$buktiSesudah}</>");

        return ($hasil['hilang'] === 0 && $hasil['pindah'] === 0 && $utuh) ? 0 : 1;
    }

    private function putarUlang(Carbon $sejak, Carbon $sampai): array
    {
        // ── Putar dunianya kembali ke sebelum tambalan manual ────────────────
        // Tanpa ini, alamat yang sudah didaftarkan tangan 23 Agu akan langsung
        // cocok dan simulasinya cuma mengulang kerja manusia — bukan menjawab
        // "apakah mesin akan menangkapnya sendiri".
        if ($sejakCabut = $this->option('cabut-ipv4-sejak')) {
            $batas = Carbon::parse($sejakCabut)->startOfDay();
            $cabut = AttBranchIp::all()->filter(
                fn ($r) => AttBranchIp::teksIpv4((string) $r->ip) !== null
                    && $r->created_at !== null
                    && $r->created_at->gte($batas)
            );
            $this->line('');
            $this->line("   dicabut dulu ({$cabut->count()} baris IPv4 didaftarkan pada/sesudah {$batas->toDateString()}):");
            foreach ($cabut as $r) {
                $this->line(sprintf('     · %-16s %s  %s', $r->ip,
                    $r->created_at->format('d M H.i'), mb_substr((string) $r->label, 0, 60)));
            }
            AttBranchIp::whereIn('id', $cabut->pluck('id'))->delete();
        }

        // ── Kejadian, urut waktu ─────────────────────────────────────────────
        // Urutan itu inti simulasinya: baris yang lahir di menit ke-5 harus
        // sudah berlaku untuk absen di menit ke-10, persis seperti di lapangan.
        $bukti = AttHpBukti::query()
            ->whereBetween('taken_at', [$sejak, $sampai])
            ->whereNotNull('user_id')
            ->orderBy('taken_at')->orderBy('id')
            ->get(['id', 'user_id', 'ip', 'branch_id_cocok', 'branch_id_manual', 'taken_at']);

        $nama = DB::table('raw_branch')->pluck('name', 'id');
        $belajar = new WifiBelajarSendiri();

        $tertolong = $hilang = $pindah = $tetapGagal = $tetapCocok = 0;
        $lahir = [];
        $sisa  = [];
        $baris = [];

        foreach ($bukti as $b) {
            $ip      = (string) $b->ip;
            $manual  = $b->branch_id_manual;
            $sebelum = $b->branch_id_cocok;                   // kenyataan yang tercatat
            $saat    = Carbon::parse($b->taken_at);

            $cocok   = AttBranchIp::matchBranch($ip);
            $sesudah = $cocok ? $cocok->raw_branch_id : null;

            // Persis seperti AttendanceHpAbsen: belajar hanya kalau tak cocok
            // DAN orangnya memilih cabang.
            $vonis = null;
            if (! $cocok && $manual) {
                $vonis = $belajar->pelajari($ip, $manual, $saat);
                if (($vonis['vonis'] ?? null) === WifiBelajarSendiri::DAFTAR) {
                    $lahir[] = [
                        'ip'     => $vonis['prefix'],
                        'cabang' => $nama[$manual] ?? $manual,
                        'saat'   => $saat->format('d M H.i'),
                        'saksi'  => implode(', ', $vonis['pendukung']),
                    ];
                }
            }

            if ($sebelum === null && $sesudah !== null) {
                $tertolong++;
                $baris[] = ['+', $saat, $ip, '—', $nama[$sesudah] ?? $sesudah, $manual ? ($nama[$manual] ?? $manual) : '—'];
            } elseif ($sebelum !== null && $sesudah === null) {
                $hilang++;
                $baris[] = ['-', $saat, $ip, $nama[$sebelum] ?? $sebelum, '—', $manual ? ($nama[$manual] ?? $manual) : '—'];
            } elseif ($sebelum !== null && $sesudah !== null && $sebelum !== $sesudah) {
                $pindah++;
                $baris[] = ['~', $saat, $ip, $nama[$sebelum] ?? $sebelum, $nama[$sesudah] ?? $sesudah, $manual ? ($nama[$manual] ?? $manual) : '—'];
            } elseif ($sesudah === null) {
                $tetapGagal++;
                $sisa[$ip] = ($sisa[$ip] ?? []);
                $sisa[$ip][] = $vonis['alasan'] ?? 'tanpa_cabang';
            } else {
                $tetapCocok++;
            }
        }

        // ── Laporan ──────────────────────────────────────────────────────────
        $this->line('');
        $this->line("   {$bukti->count()} absen diputar ulang.");
        $this->line('');
        $this->line(sprintf('   <fg=green>tertolong  %4d</>  (dulu gagal cocok, sekarang cocok)', $tertolong));
        $this->line(sprintf('   %s  (dulu cocok, sekarang tidak — HARUS NOL)',
            sprintf($hilang === 0 ? '<fg=green>hilang     %4d</>' : '<fg=red>hilang     %4d</>', $hilang)));
        $this->line(sprintf('   %s  (cocok ke cabang LAIN — HARUS NOL)',
            sprintf($pindah === 0 ? '<fg=green>pindah     %4d</>' : '<fg=red>pindah     %4d</>', $pindah)));
        $this->line(sprintf('   tetap cocok %3d   ·   tetap gagal %3d', $tetapCocok, $tetapGagal));

        if ($lahir) {
            $this->line('');
            $this->line('   <fg=cyan>Alamat yang didaftarkan SENDIRI oleh mesin:</>');
            foreach ($lahir as $l) {
                $this->line(sprintf('     · %-16s %-10s %s   saksi: %s',
                    $l['ip'], $l['cabang'], $l['saat'], $l['saksi']));
            }
        } else {
            $this->line('');
            $this->line('   <fg=yellow>Tak ada alamat yang terdaftar sendiri.</>');
        }

        if ($sisa) {
            $this->line('');
            $this->line('   Alamat yang TETAP gagal (dan kenapa — ini yang sengaja dibiarkan):');
            uasort($sisa, fn ($a, $b) => count($b) <=> count($a));
            foreach (array_slice($sisa, 0, 40, true) as $ip => $alasan) {
                $hitung = array_count_values($alasan);
                arsort($hitung);
                $ringkas = [];
                foreach ($hitung as $a => $n) $ringkas[] = "{$a}×{$n}";
                $this->line(sprintf('     · %-42s %s', $ip, implode(' ', $ringkas)));
            }
            if (count($sisa) > 40) {
                $this->line('     … ' . (count($sisa) - 40) . ' alamat lagi tak ditampilkan.');
            }
        }

        if ($this->option('rinci') && $baris) {
            $this->line('');
            $this->line('   Rincian yang berubah nasibnya (+ tertolong, - hilang, ~ pindah):');
            foreach ($baris as [$tanda, $saat, $ip, $dari, $ke, $pilih]) {
                $this->line(sprintf('     %s %s  %-42s %-10s → %-10s (pilihan orangnya: %s)',
                    $tanda, $saat->format('d M H.i'), $ip, $dari, $ke, $pilih));
            }
        }

        return compact('tertolong', 'hilang', 'pindah', 'tetapGagal', 'tetapCocok', 'lahir');
    }
}
