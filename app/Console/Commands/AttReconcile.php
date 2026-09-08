<?php

namespace App\Console\Commands;

use App\Services\AttendanceReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Hitung ulang att_day dari att_scan + aturan, untuk rentang tanggal.
 *
 *   php artisan att:reconcile 2026-06-01 2026-06-30
 */
class AttReconcile extends Command
{
    protected $signature = 'att:reconcile {from : YYYY-MM-DD} {to : YYYY-MM-DD}
                            {--force : hitung ulang tanggal lampau MESKI penempatan sudah bergeser}';
    protected $description = 'Rekonsiliasi att_scan → att_day (telat/alpa/hadir per user per hari)';

    /**
     * Sejauh apa ke belakang boleh dihitung ulang tanpa `--force`.
     *
     * Penjadwal harian memakai kemarin+hari ini, jadi ia tak pernah menyentuh
     * pagar ini. Angkanya longgar untuk koreksi normal (backfill orang baru,
     * pembetulan sepekan-dua) tapi menutup jangkauan ke bulan gaji yang sudah
     * lewat — yaitu tempat pergeseran penempatan menumpuk.
     */
    public const BATAS_HARI = 14;

    public function handle(AttendanceReconciler $engine): int
    {
        $from = Carbon::parse($this->argument('from'))->startOfDay();
        $to = Carbon::parse($this->argument('to'))->startOfDay();
        if ($to->lt($from)) {
            $this->error('Rentang tanggal terbalik.');
            return self::FAILURE;
        }

        if (! $this->pagarLampau($from, $to)) return self::FAILURE;

        $t0 = microtime(true);
        $res = $engine->reconcile($from, $to);
        $ms = round((microtime(true) - $t0) * 1000);

        $this->info("Rekonsiliasi {$from->toDateString()} s/d {$to->toDateString()} ({$ms}ms)");
        $this->line("  user diproses: {$res['users']}  |  baris att_day: {$res['days']}");
        $rows = [];
        foreach ($res['per_status'] as $status => $n) $rows[] = [$status, $n];
        usort($rows, fn ($a, $b) => $b[1] <=> $a[1]);
        $this->table(['status', 'jumlah'], $rows);

        if (! empty($res['basi_dibuang'])) {
            $this->line("  baris basi dibuang: {$res['basi_dibuang']} (hari yg kini libur/tak dinilai lagi; periode tertutup tak disentuh)");
        }

        // Peringatan keras: orang yang scan-nya masuk tapi belum punya aturan kerja
        // TIDAK diproses sama sekali — dia hilang dari rekap & kehadirannya nol,
        // tanpa error. Lebih baik berisik di sini daripada ketahuan saat gajian.
        if (! empty($res['tanpa_aturan'])) {
            $n = count($res['tanpa_aturan']);
            $this->newLine();
            $this->warn("⚠  {$n} orang punya scan di rentang ini tapi BELUM punya aturan kerja (att_user_rule).");
            $this->warn('   Scan mereka tidak dihitung dan mereka tidak muncul di rekap:');
            foreach ($res['tanpa_aturan'] as $nama) $this->line("     - {$nama}");
            $this->warn('   Isi att_user_rule mereka lalu jalankan ulang perintah ini.');
        }

        $this->laporanDokter($res);

        return self::SUCCESS;
    }

    /**
     * Pagar tanggal lampau.
     *
     * ── Kenapa MENOLAK, bukan sekadar memperingatkan ──
     * `att_day` dihitung dari keadaan organisasi HARI INI. Menghitung ulang
     * tanggal lampau berarti menerapkan struktur sekarang ke masa lalu, dan
     * hari yang dulu benar bisa berubah jadi salah tanpa satu pun galat.
     *
     * Peringatan hanya menolong orang yang SUDAH curiga. Yang menyebabkan
     * kejadian 5 Sep 2026 justru orang yang tidak curiga — mengetik rentang
     * lampau untuk keperluan lain, lalu tak pernah tahu ada hari yang bergeser.
     * Karena itu jawabannya penolakan, dan `--force` yang harus diketik sadar.
     * (Keputusan Ekky, 5 Sep 2026.)
     */
    private function pagarLampau(Carbon $from, Carbon $to): bool
    {
        $umur = $from->diffInDays(Carbon::today());
        if ($umur <= self::BATAS_HARI || $this->option('force')) {
            if ($this->option('force') && $umur > self::BATAS_HARI) {
                $this->warn("⚠  --force: menghitung ulang tanggal {$umur} hari ke belakang. "
                    . 'Struktur organisasi HARI INI akan diterapkan ke tanggal itu.');
            }

            return true;
        }

        $this->error("Ditolak: {$from->toDateString()} berumur {$umur} hari — lebih tua dari batas "
            . self::BATAS_HARI . ' hari.');
        $this->newLine();
        $this->line('  att_day dihitung dari keadaan organisasi HARI INI, bukan keadaan saat tanggal');
        $this->line('  itu berlangsung. Menghitung ulang rentang lampau menimpa baris lama memakai');
        $this->line('  penempatan/cabang yang berlaku sekarang — hari yang dulu benar bisa berubah');
        $this->line('  jadi salah, tanpa galat apa pun.');
        $this->newLine();

        $geser = \App\Services\PenempatanBergeser::dalamRentang($from->toDateString(), $to->toDateString());

        if (! $geser) {
            $this->info('  Kabar baik: TAK ADA penempatan yang bergeser di rentang ini.');
            $this->line('  Kalau memang perlu, jalankan ulang dengan --force.');

            return false;
        }

        $this->warn('  ' . count($geser) . ' penempatan BERGESER sejak rentang ini berlalu — '
            . 'hari mereka paling mungkin berubah:');
        $this->table(
            ['Nama', 'Cabang', 'Jenis', 'Dari', 'Sampai', 'Sebab'],
            array_map(fn ($g) => [$g['nama'], $g['cabang'] ?? '—', $g['jenis'], $g['dari'] ?? '—', $g['sampai'] ?? '—', $g['sebab']], $geser)
        );
        $this->line('  ⚠  Yang bertukar cabang paling berbahaya: hasilnya tetap terlihat masuk akal,');
        $this->line('     jadi tak ada yang akan curiga.');
        $this->newLine();
        $this->line('  Kalau memang perlu, jalankan ulang dengan --force.');

        return false;
    }
    /**
     * Temuan aturan bertipe `jadwal` (dokter). SEMUA yang dilaporkan di sini
     * TIDAK menghasilkan baris att_day — jadi kalau tak dicetak, ia betul-betul
     * tak terlihat di mana pun. Diam tanpa hitungan itu kegagalan-senyap; ini
     * yang membuat diamnya jadi sikap yang bisa dipertanggungjawabkan.
     */
    private function laporanDokter(array $res): void
    {
        // Akun bertipe jadwal tapi tak tertaut ke dokter mana pun = salah pasang.
        // Paling gawat di daftar ini: harinya tak bisa dinilai sama sekali.
        if (! empty($res['dokter_tak_tertaut'])) {
            $this->newLine();
            $this->error('⚠  Akun beraturan `jadwal` TAPI tak tertaut ke dokter (users.raw_doctor_id kosong):');
            foreach ($res['dokter_tak_tertaut'] as $uid) $this->line("     - user_id {$uid}");
            $this->warn('   Jadwal prakteknya tak bisa dibaca, jadi harinya tak dinilai. Tautkan dulu.');
        }

        // Hari praktik tanpa scan — vonisnya SENGAJA ditahan sampai cara bayar
        // dokter dijawab Finance (K1). Angkanya tetap harus terlihat.
        if (! empty($res['dokter_praktik_tanpa_scan'])) {
            $n = count($res['dokter_praktik_tanpa_scan']);
            $this->newLine();
            $this->warn("⚠  {$n} hari praktik dokter TANPA scan — sengaja tidak diberi baris att_day.");
            $this->line('   Vonisnya menunggu jawaban Finance: dokter dibayar per tindakan atau gaji bulanan?');
            $this->line('   (per tindakan → "alpa" tak punya arti; gaji bulanan → alpa biasa yang benar)');
            foreach (array_slice($res['dokter_praktik_tanpa_scan'], 0, 15) as $r) {
                $this->line("     - {$r['tanggal']}  user {$r['user_id']}");
            }
            if ($n > 15) $this->line('     ... +' . ($n - 15) . ' lagi');
        }

        // Cabang yang didatangi tak ada di jadwal hari itu. Belum jadi flag —
        // flag terbuka memblokir tutup periode gaji SELURUH klinik, dan itu alat
        // terlalu keras untuk keputusan yang belum di-ACC (K2).
        if (! empty($res['dokter_cabang_beda_jadwal'])) {
            $n = count($res['dokter_cabang_beda_jadwal']);
            $this->newLine();
            $this->warn("⚠  {$n} hari dokter absen di cabang yang TIDAK ada di jadwalnya hari itu.");
            $this->line('   Belum dijadikan flag (flag terbuka mengunci tutup periode gaji semua orang).');
            foreach (array_slice($res['dokter_cabang_beda_jadwal'], 0, 10) as $r) {
                $this->line("     - {$r['tanggal']}  user {$r['user_id']}"
                    . '  didatangi=' . implode(',', $r['didatangi'])
                    . '  dijadwalkan=' . implode(',', $r['dijadwalkan']));
            }
            if ($n > 10) $this->line('     ... +' . ($n - 10) . ' lagi');
        }

        // Datang di hari yang bukan hari prakteknya — bukan pelanggaran, tapi
        // layak terlihat (baris att_day-nya berstatus `libur`).
        if (! empty($res['dokter_hadir_di_luar_jadwal'])) {
            $n = count($res['dokter_hadir_di_luar_jadwal']);
            $this->newLine();
            $this->line("ℹ  {$n} hari dokter hadir di luar hari prakteknya (dicatat sebagai `libur`).");
        }
    }
}
