<?php

namespace App\Console\Commands;

use App\Models\GroupPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detektor kegagalan senyap di subsistem role → akses → wewenang ACC.
 *
 * Lahir 2026-08-22 dari kerjaan role Konsultan: pemeriksaan yang sama, kalau
 * dijalankan sekali sebagai skrip sekali-pakai, menemukan enam menu ber-403 di
 * prod yang tak ada yang tahu. Yang sekali-pakai tak akan menemukannya lagi
 * bulan depan — makanya jadi perintah.
 *
 * Semuanya BACA-SAJA. Aman dijalankan di prod kapan pun.
 *
 * Prinsip yang dijaga: JANGAN MENYALIN daftar gerbang ke sini. Salinan akan
 * basi diam-diam dan detektornya justru jadi sumber kebohongan baru. Karena itu
 * gerbang dievaluasi dari sumbernya:
 *   · canSeeAllBranches()/isFinance() → DIPANGGIL sungguhan dengan sesi palsu
 *   · CutiController::BOLEH dkk       → dibaca lewat refleksi konstanta
 * Sejak 2026-08-22 tak ada lagi cermin: gerbang terakhir yang masih berupa array
 * inline (`absensi/aturan`) diangkat jadi `AturanKerjaController::BOLEH` dan
 * dipakai bersama oleh keempat tabnya, termasuk Alur Persetujuan.
 *
 *   php artisan att:audit-role
 *   php artisan att:audit-role --berat     (hanya temuan berat, buat cron)
 */
class AuditRole extends Command
{
    protected $signature = 'att:audit-role {--berat : hanya tampilkan temuan berat}';

    protected $description = 'Audit role/akses/alur persetujuan — cari menu 403, lolos-otomatis, rujukan hantu';

    /**
     * Node yang TAK PERNAH "kerjaan bidang": alat keamanan & administrasi.
     * Standar kloning role konsultan (docs/rencana/rencana-konsultan-struktur.md):
     * kalau role pengamat memegang salah satu ini, itu temuan.
     */
    private const ALAT_KEAMANAN = [
        'setusermanagement', 'setgroup', 'setaccess', 'setsystemprofile',
        'absensi/wifi', 'auditlog',
    ];

    /**
     * Node yang berarti MENGAJUKAN sesuatu. Role pengamat tak boleh memegangnya
     * (keputusan Ekky 2026-08-22: konsultan tak mengajukan apa pun, termasuk
     * ROP). Kategorinya sengaja dipisah dari ALAT_KEAMANAN: alasannya bukan
     * keamanan sistem, melainkan wewenang.
     */
    private const NODE_PENGAJUAN = ['pengajuan'];

    /** @var array<int,array{0:string,1:string,2:string}> */
    private array $temuan = [];

    public function handle(): int
    {
        $roles  = GroupPath::all()->keyBy('id');
        $orang  = DB::table('users')->where('status', 1)
            ->select('group_id', DB::raw('COUNT(*) c'))->groupBy('group_id')->pluck('c', 'group_id')->all();

        $this->info("Audit role — {$roles->count()} role, " . array_sum($orang) . ' user aktif');

        $this->cariMenu403($roles, $orang);
        $this->cariRujukanHantu($roles);
        $this->cariLolosOtomatis($roles, $orang);
        $this->cariPenyetujuKosong($roles, $orang);
        $this->cariSiklus($roles);
        $this->cariPengamatBermasalah($roles);
        $this->cariChecklistPincang($roles, $orang);
        $this->cariUserYatim($roles);

        return $this->laporkan();
    }

    private function catat(string $berat, string $judul, string $detail = ''): void
    {
        $this->temuan[] = [$berat, $judul, $detail];
    }

    /** Gerbang nama-role per link, dievaluasi dari SUMBERNYA sebisa mungkin. */
    private function gerbang(): array
    {
        $konst = function (string $kelas, string $nama): array {
            try { return (new \ReflectionClass($kelas))->getConstants()[$nama] ?? []; }
            catch (\Throwable $e) { return []; }
        };

        // Dibaca lewat refleksi → ikut berubah sendiri kalau controllernya diubah.
        $cuti  = $konst(\App\Http\Controllers\Absensi\CutiController::class, 'BOLEH');
        $gaji  = $konst(\App\Http\Controllers\Absensi\LembarGajiController::class, 'BOLEH');
        $antri = $konst(\App\Services\RopService::class, 'ROLE_ANTRIAN');
        $rekap = $konst(\App\Services\RopService::class, 'ROLE_REKAP');

        // Dipanggil sungguhan per role (lihat lolosGerbang) → nol salinan.
        $lintas = '@canSeeAllBranches';

        // Dulu CERMIN (array inline di metode privat). Sejak 2026-08-22 daftarnya
        // jadi konstanta AturanKerjaController::BOLEH — dipakai bersama oleh
        // keempat tab termasuk Alur Persetujuan — jadi bisa dibaca refleksi
        // seperti gerbang lain. Nol salinan.
        $aturan = $konst(\App\Http\Controllers\Absensi\AturanKerjaController::class, 'BOLEH');

        return [
            'absensi'             => $lintas,
            'absensi/kehadiran'   => $lintas,
            'absensi/mapping'     => $lintas,
            'absensi/periode'     => $lintas,
            'absensi/recap'       => $lintas,
            'finance/pettycash'   => $lintas,
            'absensi/cuti'        => $cuti,
            'absensi/lembar-gaji' => $gaji,
            'finance/rop'         => [$lintas, $antri],
            'finance/rop/rekap'   => [$lintas, $rekap],
            'absensi/aturan'      => $aturan,
        ];
    }

    /** Evaluasi satu gerbang untuk satu nama role. */
    private function lolosGerbang(mixed $aturan, string $namaRole): bool
    {
        if ($aturan === '@canSeeAllBranches') {
            session(['group_name' => $namaRole]);
            return canSeeAllBranches();
        }
        if (is_array($aturan) && isset($aturan[0]) && $aturan[0] === '@canSeeAllBranches') {
            return $this->lolosGerbang('@canSeeAllBranches', $namaRole)
                || in_array($namaRole, $aturan[1] ?? [], true);
        }
        return in_array($namaRole, (array) $aturan, true);
    }

    private function cariMenu403($roles, array $orang): void
    {
        $gerbang = $this->gerbang();
        $rows = DB::table('group_access as ga')
            ->join('access_path as ap', 'ap.id', '=', DB::raw('ga.access_id COLLATE utf8mb4_unicode_ci'))
            ->whereNotNull('ap.link')->where('ap.link', '<>', '')
            ->select('ga.group_id', 'ap.link', 'ap.nama')->get();

        foreach ($rows as $r) {
            $role = $roles[$r->group_id] ?? null;
            if (! $role || ! isset($gerbang[$r->link])) continue;
            if ($this->lolosGerbang($gerbang[$r->link], $role->nama)) continue;

            $n = (int) ($orang[$r->group_id] ?? 0);
            $this->catat($n > 0 ? 'SEDANG' : 'RINGAN',
                "menu 403: {$role->nama} → \"{$r->nama}\" ({$r->link})", "$n user aktif");
        }
        session()->forget('group_name');
    }

    private function cariRujukanHantu($roles): void
    {
        foreach (DB::table('att_approval_rule')->get() as $r) {
            foreach (['role_id' => $r->role_id, 'approver_role_id' => $r->approver_role_id] as $kol => $v) {
                if (! isset($roles[$v])) {
                    $this->catat('BERAT', "att_approval_rule.$kol menunjuk role yang tak ada",
                        "nilai: $v — tabel ini tak punya foreign key, salah ketik masuk diam-diam");
                }
            }
        }
    }

    private function peta(): array
    {
        $p = [];
        foreach (DB::table('att_approval_rule')->get() as $r) $p[$r->role_id][] = $r->approver_role_id;
        return $p;
    }

    /** Role berpenghuni yang rantainya buntu → pengajuannya lolos tanpa jejak. */
    private function cariLolosOtomatis($roles, array $orang): void
    {
        $peta = $this->peta();
        $approver = [];
        foreach (DB::table('att_approval_rule')->get() as $r) $approver[$r->approver_role_id] = true;

        foreach ($roles as $id => $role) {
            $n = (int) ($orang[$id] ?? 0);
            if ($n === 0) continue;

            $adaPenyetuju = false;
            $seen = [$id => true]; $frontier = [$id];
            while ($frontier && ! $adaPenyetuju) {
                $next = [];
                foreach ($frontier as $f) foreach ($peta[$f] ?? [] as $a) {
                    if (isset($seen[$a])) continue;
                    $seen[$a] = true;
                    if ((int) ($orang[$a] ?? 0) > 0) { $adaPenyetuju = true; break 2; }
                    $next[] = $a;
                }
                $frontier = $next;
            }
            if ($adaPenyetuju) continue;

            if (isset($approver[$id])) {
                // Rantai kosong + role ini penyetuju orang lain = pagar
                // pastikanApexBukanSalahKonfigurasi() menganggapnya apex sah,
                // jadi pengajuannya lolos otomatis. Itu BENAR untuk puncak
                // organisasi (Executive) dan BENCANA untuk yang lain.
                //
                // Pembedanya diambil dari struktur, bukan daftar nama: puncak
                // sungguhan tak punya atasan di bagan. Role yang PUNYA atasan
                // tapi rantai ACC-nya buntu = pengajuannya lolos tanpa jejak
                // padahal ada orang di atasnya yang seharusnya menyetujui —
                // itulah lubang §3.2, dan itu yang pantas membangunkan orang.
                $puncakSah = $role->parent_id === null;

                $this->catat($puncakSah ? 'OK' : 'BERAT',
                    ($puncakSah ? 'apex sah: ' : 'LOLOS SENDIRI: ')
                        . "pengajuan {$role->nama} disetujui tanpa siapa pun",
                    $puncakSah
                        ? "$n user aktif — puncak bagan, memang tak ada yang di atasnya"
                        : "$n user aktif — PUNYA atasan di bagan tapi rantai ACC-nya buntu");
            } else {
                $pengamat = ($role->wewenang_pengajuan ?? 'penuh') === 'pengamat';
                $this->catat($pengamat ? 'OK' : 'INFO',
                    ($pengamat ? 'pengamat (memang tak mengajukan): ' : 'tak bisa mengajukan: ') . $role->nama,
                    "$n user aktif" . ($pengamat ? '' : ' — ditolak dgn pesan; kebijakan atau kelalaian?'));
            }
        }
    }

    private function cariPenyetujuKosong($roles, array $orang): void
    {
        $peta = $this->peta();
        $approver = [];
        foreach (DB::table('att_approval_rule')->get() as $r) $approver[$r->approver_role_id] = true;

        foreach (array_keys($approver) as $aid) {
            if (! isset($roles[$aid]) || (int) ($orang[$aid] ?? 0) > 0) continue;
            $kena = [];
            foreach ($peta as $rid => $as) if (in_array($aid, $as, true)) $kena[] = $roles[$rid]->nama ?? $rid;
            $this->catat('SEDANG', "penyetuju kosong: {$roles[$aid]->nama}",
                'rantai diam-diam naik ke atasnya. Pengaju terdampak: ' . implode(', ', $kena));
        }
    }

    private function cariSiklus($roles): void
    {
        $peta = $this->peta();
        foreach ($roles as $id => $role) {
            $seen = []; $frontier = [$id];
            while ($frontier) {
                $next = [];
                foreach ($frontier as $f) foreach ($peta[$f] ?? [] as $a) {
                    if ($a === $id) {
                        $this->catat('BERAT', "siklus persetujuan: {$role->nama}", 'rantainya kembali ke dirinya sendiri');
                        break 3;
                    }
                    if (isset($seen[$a])) continue;
                    $seen[$a] = true; $next[] = $a;
                }
                $frontier = $next;
            }
        }
        foreach ($roles as $id => $role) {
            $seen = []; $cur = $role;
            while ($cur && $cur->parent_id) {
                if (isset($seen[$cur->parent_id])) {
                    $this->catat('BERAT', "siklus bagan: {$role->nama}", 'parent_id melingkar');
                    break;
                }
                $seen[$cur->parent_id] = true;
                $cur = $roles[$cur->parent_id] ?? null;
            }
        }
    }

    /** Invarian role pengamat: nol wewenang ACC, nol alat keamanan. */
    private function cariPengamatBermasalah($roles): void
    {
        $adaKolom = Schema::hasColumn('group_path', 'wewenang_pengajuan');
        if (! $adaKolom) {
            $this->catat('SEDANG', 'kolom group_path.wewenang_pengajuan belum ada',
                'pagar pengamat jatuh ke parasut daftar-nama di kode — jalankan SQL konsultan');
            return;
        }

        foreach ($roles->where('wewenang_pengajuan', 'pengamat') as $id => $role) {
            $sbgApprover = DB::table('att_approval_rule')->where('approver_role_id', $id)->count();
            $sbgPengaju  = DB::table('att_approval_rule')->where('role_id', $id)->count();

            if ($sbgApprover > 0) {
                $this->catat('BERAT', "pengamat {$role->nama} terdaftar sebagai PENYETUJU",
                    "$sbgApprover baris — kode menolaknya saat ACC, tapi barisnya bikin dia muncul di rantai & notifikasi");
            }
            if ($sbgPengaju > 0) {
                $this->catat('SEDANG', "pengamat {$role->nama} punya rantai persetujuan",
                    "$sbgPengaju baris — mubazir, pengajuannya ditolak pagar kebijakan");
            }

            $alat = DB::table('group_access as ga')
                ->join('access_path as ap', 'ap.id', '=', DB::raw('ga.access_id COLLATE utf8mb4_unicode_ci'))
                ->where('ga.group_id', $id)->whereIn('ap.link', self::ALAT_KEAMANAN)
                ->pluck('ap.link')->all();
            if ($alat) {
                $this->catat('BERAT', "pengamat {$role->nama} memegang alat keamanan",
                    implode(', ', $alat) . ' — standar kloning konsultan melarang ini');
            }

            $ajukan = DB::table('group_access as ga')
                ->join('access_path as ap', 'ap.id', '=', DB::raw('ga.access_id COLLATE utf8mb4_unicode_ci'))
                ->where('ga.group_id', $id)->whereIn('ap.link', self::NODE_PENGAJUAN)
                ->pluck('ap.link')->all();
            if ($ajukan) {
                $this->catat('SEDANG', "pengamat {$role->nama} masih punya menu pengajuan",
                    implode(', ', $ajukan) . ' — kodenya menolak (403), tapi menunya tampil sia-sia');
            }
        }
    }

    private function cariChecklistPincang($roles, array $orang): void
    {
        if (! Schema::hasTable('sys_checklist_division')) return;

        $namaRole = $roles->pluck('nama')->all();
        foreach (DB::table('sys_checklist_division')->where('is_active', 1)->get() as $d) {
            if (! in_array($d->name, $namaRole, true)) {
                $this->catat('SEDANG', "divisi checklist tanpa role senama: {$d->name}",
                    'checklistDivisionForGroup() mencocokkan NAMA — divisi ini takkan pernah ketemu');
            }
        }

        $punya = DB::table('group_access as ga')
            ->join('access_path as ap', 'ap.id', '=', DB::raw('ga.access_id COLLATE utf8mb4_unicode_ci'))
            ->whereIn('ap.link', ['checklist', 'checklist/review', 'checklist/setup'])
            ->distinct()->pluck('ga.group_id')->all();

        foreach ($punya as $gid) {
            $role = $roles[$gid] ?? null;
            if (! $role || in_array($role->nama, ['Super Admin', 'Admin General', 'Admin Backup'], true)) continue;
            $desc = $role->supervisedDescendants()->pluck('nama')->all();
            $n = DB::table('sys_checklist_division')->where('is_active', 1)
                ->whereIn('name', $desc ?: ['~tak-ada~'])->count();
            if ($n === 0) {
                $this->catat('SEDANG', "pengawas checklist tanpa divisi: {$role->nama}",
                    (int) ($orang[$gid] ?? 0) . ' user — halaman terbuka tapi isinya selalu kosong');
            }
        }
    }

    private function cariUserYatim($roles): void
    {
        $yatim = DB::table('users')->where('status', 1)
            ->whereNotIn('group_id', $roles->keys()->all())->pluck('nama')->all();
        if ($yatim) $this->catat('BERAT', 'user aktif ber-role hantu', implode(', ', $yatim));

        $tanpa = DB::table('users')->where('status', 1)->whereNull('group_id')->count();
        if ($tanpa) $this->catat('BERAT', 'user aktif tanpa role', "$tanpa user — lenyap dari Kehadiran");
    }

    private function laporkan(): int
    {
        $urut = ['BERAT' => 0, 'SEDANG' => 1, 'INFO' => 2, 'RINGAN' => 3, 'OK' => 4];
        usort($this->temuan, fn ($a, $b) => $urut[$a[0]] <=> $urut[$b[0]]);

        $hanyaBerat = (bool) $this->option('berat');
        $warna = ['BERAT' => 'error', 'SEDANG' => 'comment', 'INFO' => 'line', 'RINGAN' => 'line', 'OK' => 'info'];

        $this->newLine();
        foreach ($this->temuan as [$b, $judul, $detail]) {
            if ($hanyaBerat && $b !== 'BERAT') continue;
            $this->{$warna[$b]}(sprintf('  [%-6s] %s', $b, $judul));
            if ($detail !== '') $this->line("           $detail");
        }

        $hitung = fn (string $b) => count(array_filter($this->temuan, fn ($t) => $t[0] === $b));
        $berat = $hitung('BERAT');

        $this->newLine();
        $this->line(sprintf('  berat %d · sedang %d · info %d · ringan %d · ok %d',
            $berat, $hitung('SEDANG'), $hitung('INFO'), $hitung('RINGAN'), $hitung('OK')));
        $this->line('  catatan: semua gerbang di atas dibaca dari sumbernya (refleksi konstanta / '
            . 'fungsi dipanggil sungguhan). Tak ada cermin yang bisa rot diam-diam.');
        $this->newLine();

        return $berat > 0 ? self::FAILURE : self::SUCCESS;
    }
}
