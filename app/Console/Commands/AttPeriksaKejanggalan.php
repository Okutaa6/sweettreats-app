<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\AttendanceReconciler;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksa harian kejanggalan absensi & cuti — BACA-SAJA.
 *
 * Lahir 5 Sep 2026. Empat mutasi cuti yang salah (Yovanka 2, Ramdan 2 + satu
 * tambalan) baru ketahuan karena seseorang menyapu tabelnya dengan tangan pada
 * suatu malam. Tak ada apa pun yang memberi tahu, dan ketiga pola di bawah
 * ini sudah TERBUKTI terjadi — bukan kekhawatiran teoretis. Ketiganya
 * menentukan uang.
 *
 * ⛔ TIDAK MEMBETULKAN APA PUN. Dua alasan, dan keduanya sudah menggigit:
 *
 *   1. Sebuah potongan di hari libur bisa saja SUDAH ditutup koreksi manual di
 *      tempat lain. Ramdan persis begitu: dua baris janggal, tapi saldonya
 *      benar karena ada koreksi −3 yang menutupinya. Pembetulan otomatis di
 *      situ akan MENAMBAH jatah orang yang jatahnya sudah tepat.
 *   2. Yang tahu duduk perkaranya manusia, dan hasilnya angka gaji.
 *
 * Karena itu tiap temuan potongan ikut menyebutkan apakah orangnya punya
 * koreksi manual — supaya pembacanya memeriksa saldo dulu, bukan langsung
 * mencabut baris.
 *
 * ── Kenapa waktu jalan terakhir disimpan ──
 * "Nol temuan" punya dua arti yang kelihatannya sama: *sudah diperiksa, bersih*
 * dan *tak pernah dijalankan*. Yang kedua jauh lebih berbahaya karena terlihat
 * menenangkan. Stempel waktunya disimpan di `sys_setting` supaya layar mana pun
 * bisa menampilkan "terakhir diperiksa ...".
 *
 *   php artisan att:periksa-kejanggalan
 *   php artisan att:periksa-kejanggalan --hari=90
 */
class AttPeriksaKejanggalan extends Command
{
    protected $signature = 'att:periksa-kejanggalan
                            {--hari=60 : sapu mundur berapa hari}
                            {--diam : hanya keluarkan ringkasan (buat cron)}';

    protected $description = 'Sapu kejanggalan absensi/cuti: potongan di hari libur, potongan kembar, orang tanpa aturan kerja';

    /** Kunci stempel waktu di sys_setting — dibaca layar Kesehatan. */
    public const KUNCI_JALAN = 'att_periksa_kejanggalan_terakhir';

    /**
     * Role yang memang TIDAK punya aturan kerja, dan benar begitu: akun
     * sistem/loket, bukan orang yang absen. Menyebutnya sebagai temuan tiap
     * hari akan membuat pemeriksaan ini diabaikan dalam sepekan — dan
     * pemeriksa yang diabaikan sama saja dengan tak ada.
     *
     * ⚠️ Dikecualikan, BUKAN disembunyikan: jumlahnya tetap dicetak. Daftar
     * yang diam-diam menelan baris adalah cara berikutnya sesuatu hilang
     * tanpa ada yang tahu.
     */
    private const ROLE_SISTEM = ['Admin', 'Admin General', 'Admin Backup', 'Super Admin',
        'Sales', 'Cabang'];

    /** Nama akun sistem yang dilewati cek 3 — dicetak bersama bagiannya. */
    private array $dikecualikan = [];

    public function handle(): int
    {
        $hari = max(1, (int) $this->option('hari'));
        $to   = Carbon::today();
        $from = $to->copy()->subDays($hari);

        $this->info("Sapu {$from->toDateString()} s/d {$to->toDateString()} ({$hari} hari).");

        $temuan = [
            'cuti_di_hari_libur' => $this->cutiDiHariLibur($from, $to),
            'potongan_kembar'    => $this->potonganKembar($from, $to),
            'tanpa_aturan'       => $this->tanpaAturanKerja(),
        ];

        $total = array_sum(array_map('count', $temuan));

        if (! $this->option('diam')) {
            $this->laporkan('1. Potongan cuti jatuh di HARI LIBUR orangnya', $temuan['cuti_di_hari_libur'],
                'Cuti tak memakan hari libur orangnya (keputusan Ekky 5 Sep 2026). '
                . 'PERIKSA SALDONYA DULU — sebagian sudah tertutup koreksi manual.');

            $this->laporkan('2. Potongan cuti KEMBAR (orang + tanggal sama)', $temuan['potongan_kembar'],
                'Satu hari cuti terpotong lebih dari sekali. Cabut dengan mutasi pembalik, jangan hapus barisnya.');

            $this->laporkan('3. Karyawan aktif TANPA aturan kerja', $temuan['tanpa_aturan'],
                'Tanpa aturan kerja orangnya LENYAP dari layar Kehadiran — scan-nya tetap tersimpan '
                . 'tapi tak pernah jadi hari kerja. Beri aturan, dengan effective_from = hari MULAI KERJA.',
                $this->dikecualikan
                    ? count($this->dikecualikan) . ' akun sistem dikecualikan: ' . implode(', ', $this->dikecualikan)
                    : null);
        }

        // Stempel ditulis SETIAP kali selesai, termasuk saat nol temuan —
        // itulah gunanya. Ditulis di akhir supaya sapuan yang meledak di tengah
        // tidak meninggalkan jejak "sudah diperiksa" yang palsu.
        Setting::put(self::KUNCI_JALAN, Carbon::now()->toDateTimeString());

        if ($total === 0) {
            $this->info('✓ Nol temuan. Stempel waktu diperbarui — ini "sudah diperiksa", bukan "belum pernah jalan".');

            return self::SUCCESS;
        }

        $this->warn("Total {$total} temuan. Semua BACA-SAJA — tak ada yang diubah.");

        return self::SUCCESS;
    }

    /**
     * Potongan cuti yang jatuh di hari libur orangnya.
     *
     * "Libur" ditanyakan ke AttendanceReconciler::liburUntuk() — bukan dihitung
     * ulang di sini. Definisinya menyangkut pola mingguan, kalender libur, dan
     * cabang tempat orangnya bertugas; salinan yang lupa satu lapis akan
     * menuduh dengan meyakinkan.
     */
    private function cutiDiHariLibur(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('att_cuti_mutasi as m')
            ->join('users as u', DB::raw('u.id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('m.user_id COLLATE utf8mb4_unicode_ci'))
            ->where('m.hari', '>', 0)
            ->whereBetween('m.tanggal', [$from->toDateString(), $to->toDateString()])
            ->orderBy('m.tanggal')
            ->get(['m.id', 'm.user_id', 'm.tanggal', 'm.hari', 'm.jenis', 'm.keterangan', 'u.nama']);

        if ($rows->isEmpty()) return [];

        $pasangan = $rows->map(fn ($r) => [(string) $r->user_id, substr((string) $r->tanggal, 0, 10)])->all();
        $libur = (new AttendanceReconciler())->liburUntuk($pasangan);

        // Siapa yang punya koreksi manual sama sekali — penanda "periksa saldo
        // dulu". Sengaja per ORANG, bukan per tanggal: koreksi penutup lazimnya
        // dicatat di tanggal lain daripada kesalahan yang ditutupnya.
        $adaKoreksi = DB::table('att_cuti_mutasi')
            ->where('sumber', 'manual')
            ->whereIn('user_id', $rows->pluck('user_id')->unique()->all())
            ->pluck('user_id')->unique()->flip();

        $out = [];
        foreach ($rows as $r) {
            $tgl = substr((string) $r->tanggal, 0, 10);
            if (($libur[$r->user_id . '|' . $tgl] ?? false) !== true) continue;

            $out[] = [
                'nama'    => $r->nama,
                'tanggal' => $tgl,
                'ket'     => "mutasi #{$r->id} · {$r->hari} hari · {$r->jenis}"
                    . (isset($adaKoreksi[$r->user_id]) ? ' · ⚠ ADA koreksi manual — periksa saldo dulu' : ''),
            ];
        }

        return $out;
    }

    /** Dua potongan atau lebih untuk orang + tanggal yang sama. */
    private function potonganKembar(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('att_cuti_mutasi as m')
            ->join('users as u', DB::raw('u.id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('m.user_id COLLATE utf8mb4_unicode_ci'))
            ->where('m.hari', '>', 0)
            ->whereBetween('m.tanggal', [$from->toDateString(), $to->toDateString()])
            ->groupBy('m.user_id', 'm.tanggal', 'u.nama')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('m.tanggal')
            ->get([
                'm.user_id', 'm.tanggal', 'u.nama',
                DB::raw('COUNT(*) as n'),
                DB::raw('SUM(m.hari) as jml'),
                DB::raw('GROUP_CONCAT(m.id ORDER BY m.id) as ids'),
            ]);

        return $rows->map(fn ($r) => [
            'nama'    => $r->nama,
            'tanggal' => substr((string) $r->tanggal, 0, 10),
            'ket'     => "{$r->n} potongan (total {$r->jml} hari) — mutasi #{$r->ids}",
        ])->all();
    }

    /**
     * Karyawan aktif yang tak punya baris att_user_rule sama sekali.
     *
     * Bukan sekadar rapi-rapi: tanpa aturan, roster layar Kehadiran tak memuat
     * orangnya — bukan hadir, bukan alpa, bukan apa-apa. Ela punya 6 scan dan
     * nol baris att_day karena ini.
     */
    private function tanpaAturanKerja(): array
    {
        $rows = DB::table('users as u')
            ->leftJoin('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('u.status', 1)
            // COLLATE eksplisit, sama seperti dua kueri di atas. Di dev ketiga
            // kolomnya utf8mb4_unicode_ci (dipaksa sama oleh FK ke users), jadi
            // ini tak mengubah hasil — yang dihindari adalah SATU kueri di berkas
            // ini berperilaku beda dari dua saudaranya kalau ada environment yang
            // collation-nya menyimpang.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('att_user_rule as ur')
                ->whereRaw('ur.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci'))
            ->orderBy('u.nama')
            ->get(['u.id', 'u.nama', 'g.nama as role']);

        // Akun sistem disingkirkan; catatannya DISIMPAN, tidak dicetak dari
        // sini. Mencetak di dalam pengumpul membuat barisnya muncul di ATAS
        // seluruh laporan — sebelum judul bagian mana pun — padahal ia milik
        // bagian 3; dan ia lolos dari --diam, yang gunanya justru membungkam
        // rincian. Pengumpul mengumpulkan; yang mencetak cuma laporkan().
        $sistem = $rows->filter(fn ($r) => in_array($r->role, self::ROLE_SISTEM, true));
        $rows   = $rows->reject(fn ($r) => in_array($r->role, self::ROLE_SISTEM, true))->values();

        $this->dikecualikan = $sistem->pluck('nama')->all();

        if ($rows->isEmpty()) return [];

        // Scan yang sudah masuk tapi tak pernah jadi hari kerja — ini yang
        // membedakan "akun sistem yang wajar kosong" dari "orang sungguhan
        // yang sedang hilang dari rekap".
        $scan = DB::table('att_scan')
            ->whereIn('user_id', $rows->pluck('id')->all())
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as n'))
            ->pluck('n', 'user_id');

        return $rows->map(fn ($r) => [
            'nama'    => $r->nama,
            'tanggal' => '—',
            'ket'     => 'role ' . ($r->role ?? '?')
                . (($scan[$r->id] ?? 0) > 0
                    ? ' · ⚠ SUDAH ADA ' . $scan[$r->id] . ' scan yang tak pernah tercatat'
                    : ' · belum ada scan'),
        ])->all();
    }

    private function laporkan(string $judul, array $baris, string $arahan, ?string $catatan = null): void
    {
        $this->newLine();
        $this->line("── {$judul}");
        if ($catatan) $this->line('   (' . $catatan . ')');

        if (! $baris) {
            $this->info('   ✓ nihil');

            return;
        }

        $this->warn('   ' . count($baris) . ' temuan — ' . $arahan);
        $this->table(['Nama', 'Tanggal', 'Keterangan'],
            array_map(fn ($b) => [$b['nama'], $b['tanggal'], $b['ket']], $baris));
    }
}
