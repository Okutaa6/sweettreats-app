<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pusat Akun → Kesehatan — detektor kegagalan-senyap.
 *
 * Satu jenis masalah sudah kejadian 8× di proyek ini: SEMUA tanpa error, semua
 * ketemu karena digali manual (landing terkunci 3×, scan yatim 2× dgn 538 scan
 * lenyap, lupa rebuild, node dobel, approval nyasar). Halaman ini membaliknya:
 * masalah ketahuan DULUAN, sebelum orang teriak.
 *
 * Prinsip (rencana-pusat-akun.md §8): halaman ini MENDETEKSI, tidak MENGUBAH.
 * Perbaikan tetap lewat halaman masing-masing — tidak ada tombol ajaib.
 * Query = versi hidup dari docs/deploy-absensi.md §9.
 *
 * Akses: node 'setkesehatan' (AuthGuard) — 4 admin + Head IT + IT.
 */
class KesehatanController extends Controller
{
    /** Akun dianggap "tak bertuan" kalau tak pernah kelihatan ≥ hari ini. */
    private const TAK_BERTUAN_HARI = 30;

    // SKIP_RBAC: satu sumber kebenaran di AuthGuard::SKIP_RBAC (jangan disalin).

    /** Severity dasar tiap detektor (jadi hijau "sehat" saat 0 kasus). */
    private const SEVERITY = [
        'landing'   => 'crit',   // user terkunci = mendesak
        'approval'  => 'crit',   // auto-approve = risiko keamanan
        'scan'      => 'warn',
        'rebuild'   => 'warn',
        'dobel'     => 'warn',
        'bertuan'   => 'warn',
        'perangkat' => 'warn',
    ];

    /** Semua detektor sekali jalan — dipakai index() dan badge sidebar. */
    private function semuaDetektor(): array
    {
        return [
            'landing'   => $this->landingTerkunci(),
            'scan'      => $this->scanYatim(),
            'rebuild'   => $this->grantBelumRebuild(),
            'dobel'     => $this->nodeMenuDobel(),
            'approval'  => $this->approvalNyasar(),
            'bertuan'   => $this->akunTakBertuan(),
            'perangkat' => $this->perangkatMenggantung(),
        ];
    }

    /**
     * Total temuan buat badge sidebar — di-CACHE 10 menit (detektor lengkap
     * terlalu mahal buat jalan tiap page-load). Halaman Kesehatan sendiri
     * selalu hitung segar + menyegarkan cache ini.
     */
    public static function totalCached(): int
    {
        return (int) \Illuminate\Support\Facades\Cache::remember('pk_kesehatan_total', 600, function () {
            $c = new static();
            return array_sum(array_map(fn ($d) => count($d['rows']), $c->semuaDetektor()));
        });
    }

    public function index()
    {
        $detektor = $this->semuaDetektor();

        // lekatkan severity + unit + ikon per kartu
        foreach ($detektor as $key => &$d) {
            $d['sev']  = self::SEVERITY[$key] ?? 'warn';
            $d['icon'] = self::IKON[$key] ?? 'shield';
        }
        unset($d);

        $totalMasalah = array_sum(array_map(fn ($d) => count($d['rows']), $detektor));
        \Illuminate\Support\Facades\Cache::put('pk_kesehatan_total', $totalMasalah, 600);
        $nKritis  = array_sum(array_map(fn ($d) => $d['sev'] === 'crit' && count($d['rows']) ? count($d['rows']) : 0, $detektor));
        $nDitinjau= array_sum(array_map(fn ($d) => $d['sev'] === 'warn' && count($d['rows']) ? count($d['rows']) : 0, $detektor));
        $nSehat   = count(array_filter($detektor, fn ($d) => count($d['rows']) === 0));
        $nBermasalah = count(array_filter($detektor, fn ($d) => count($d['rows']) > 0));

        return view('set.kesehatan', compact('detektor', 'totalMasalah', 'nKritis', 'nDitinjau', 'nSehat', 'nBermasalah'));
    }

    /** Nama ikon per detektor (dipadankan ke SVG di view). */
    private const IKON = [
        'landing'   => 'lock',
        'scan'      => 'unlink',
        'rebuild'   => 'sync',
        'dobel'     => 'copy',
        'approval'  => 'route',
        'bertuan'   => 'user-question',
        'perangkat' => 'mobile',
    ];

    /**
     * #1 Landing terkunci — role yang landing_page-nya menunjuk halaman yang
     * TIDAK di-grant ke role itu → semua penggunanya 403 selamanya, tanpa pesan.
     * Sudah kejadian 3× (Sales prod, FO/Asdent/Koord dev, Finance/IT baru).
     */
    private function landingTerkunci(): array
    {
        $rows = DB::select("
            SELECT g.id, g.nama, g.landing_page,
                   (SELECT COUNT(*) FROM users u WHERE u.group_id = g.id AND u.status = 1) AS n_user
            FROM group_path g
            WHERE g.landing_page IS NOT NULL AND g.landing_page <> ''
              AND SUBSTRING_INDEX(TRIM(LEADING '/' FROM g.landing_page), '/', 1) NOT IN ('" . implode("','", \App\Http\Middleware\AuthGuard::SKIP_RBAC) . "')
              AND NOT EXISTS (
                  SELECT 1 FROM group_access ga
                  JOIN access_path ap ON ap.id = ga.access_id
                  WHERE ga.group_id = g.id
                    AND ap.link = SUBSTRING_INDEX(TRIM(LEADING '/' FROM g.landing_page), '/', 1)
              )
            ORDER BY n_user DESC, g.nama
        ");

        return [
            'judul'  => 'Landing terkunci',
            'arti'   => 'Role yang halaman pembukanya tidak di-grant — penggunanya kena 403 begitu login, tanpa petunjuk apa pun.',
            'benerin'=> 'Buka Role → ganti landing page ke halaman yang di-grant, ATAU buka Akses → grant halaman itu ke role tsb. Lalu jalankan rebuild akses.',
            'rows'   => array_map(fn ($r) => [
                'utama' => $r->nama,
                'detail'=> "landing '{$r->landing_page}' tidak di-grant · {$r->n_user} user aktif terdampak",
            ], $rows),
        ];
    }

    /**
     * #2 Scan yatim — orang PUNYA scan absen tapi TIDAK punya aturan kerja
     * (att_user_rule) → scan-nya tak pernah dinilai reconciler, kehadiran
     * lenyap tanpa jejak. Sudah kejadian 2× (538 scan Head IT lenyap).
     */
    private function scanYatim(): array
    {
        $rows = DB::select("
            SELECT u.id, u.nama, COUNT(*) AS n_scan, MAX(s.scanned_at) AS terakhir
            FROM att_scan s
            JOIN users u ON u.id = s.user_id
            WHERE s.user_id IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM att_user_rule ur WHERE ur.user_id = s.user_id)
            GROUP BY u.id, u.nama
            ORDER BY n_scan DESC
        ");

        return [
            'judul'  => 'Scan yatim',
            'arti'   => 'Orang yang punya scan absen tapi tidak punya aturan kerja — scan-nya tidak pernah dinilai, kehadirannya lenyap diam-diam.',
            'benerin'=> 'Pasangkan aturan kerja di att_user_rule (baku/fleksibel) untuk orang tsb, lalu jalankan att:reconcile ulang untuk rentang tanggal scan-nya.',
            'rows'   => array_map(fn ($r) => [
                'utama' => $r->nama,
                'detail'=> "{$r->n_scan} scan tak ternilai · terakhir " . substr((string) $r->terakhir, 0, 10),
            ], $rows),
        ];
    }

    /**
     * #3 Grant belum rebuild — isi group_access di DB ≠ file cache
     * storage/app/group_access/<groupId>.txt yang DIPAKAI runtime.
     * SQL sukses tapi menu tak muncul = jenis ini. Sudah kejadian 1×.
     */
    private function grantBelumRebuild(): array
    {
        $rows = [];
        $groups = DB::table('group_path')->get(['id', 'nama']);

        foreach ($groups as $g) {
            $dbIds = DB::table('group_access')->where('group_id', $g->id)
                ->pluck('access_id')->sort()->values()->all();

            $file = storage_path('app/group_access/' . $g->id . '.txt');
            if (! is_file($file)) {
                if ($dbIds) {
                    $rows[] = ['utama' => $g->nama, 'detail' => 'file cache akses TIDAK ADA — semua grant role ini belum hidup'];
                }
                continue;
            }

            $tree = json_decode((string) file_get_contents($file), true) ?: [];
            $fileIds = [];
            // ⚠ format cache 3-level pakai kunci BERBEDA per level: 'sub' (level 2)
            // dan 'child' (level 3) — walker wajib ikuti keduanya, kalau tidak
            // node level-3 dianggap "gugur" padahal tampil (false alarm).
            $walk = function ($nodes) use (&$walk, &$fileIds) {
                foreach ($nodes as $n) {
                    if (isset($n['id'])) $fileIds[] = $n['id'];
                    if (! empty($n['sub']))   $walk($n['sub']);
                    if (! empty($n['child'])) $walk($n['child']);
                }
            };
            $walk($tree);
            sort($fileIds);

            $kurang = array_diff($dbIds, $fileIds);   // di DB tapi tak ada di pohon menu
            $lebih  = array_diff($fileIds, $dbIds);   // di pohon tapi sudah dicabut di DB
            if ($kurang || $lebih) {
                $d = [];
                if ($kurang) {
                    // sebutkan NODE-nya — "6 grant" doang bikin orang nebak-nebak
                    $nama = DB::table('access_path')->whereIn('id', $kurang)->pluck('nama')->all();
                    $d[] = 'tak muncul di menu: ' . implode(', ', array_slice($nama, 0, 6))
                         . (count($nama) > 6 ? ' +' . (count($nama) - 6) : '');
                }
                if ($lebih) $d[] = count($lebih) . ' pencabutan belum hidup';
                $rows[] = ['utama' => $g->nama, 'detail' => implode(' · ', $d)];
            }
        }

        return [
            'judul'  => 'Grant tak berefek',
            'arti'   => 'Akses di database tidak sampai ke menu yang dipakai aplikasi. Dua sebab umum: belum jalankan rebuild, ATAU child di-grant tapi PARENT-nya tidak (node gugur dari pohon).',
            'benerin'=> 'Jalankan php artisan access:rebuild dulu. Kalau masih muncul di sini → grant juga node PARENT-nya di halaman Akses, lalu rebuild lagi.',
            'rows'   => $rows,
        ];
    }

    /**
     * #4 Node menu dobel — link sama, id beda → pencabutan akses gagal senyap.
     * Catatan pola: parent(section)→child(halaman) dengan link sama itu IDIOM
     * SAH di app ini (mis. Absensi→Upload Data) — ditandai, bukan disembunyikan,
     * biar manusia yang menilai (detektor mendeteksi, tidak menghakimi).
     */
    private function nodeMenuDobel(): array
    {
        $dups = DB::select("
            SELECT link FROM access_path
            WHERE link IS NOT NULL AND link <> ''
            GROUP BY link HAVING COUNT(*) > 1
        ");

        $rows = [];
        foreach ($dups as $d) {
            $nodes = DB::table('access_path')->where('link', $d->link)->get(['id', 'pid', 'nama']);
            $ids = $nodes->pluck('id')->flip();
            $labels = $nodes->map(function ($n) use ($ids) {
                // pid-nya sesama grup link yang sama = pola parent→child
                return $n->nama . (isset($ids[$n->pid]) ? ' (child dari node se-link — bisa disengaja)' : '');
            })->all();
            $rows[] = [
                'utama' => $d->link,
                'detail'=> count($nodes) . ' node: ' . implode(' / ', $labels),
            ];
        }

        return [
            'judul'  => 'Node menu dobel',
            'arti'   => 'Dua node akses menunjuk link yang sama — mencabut salah satu tidak mencabut aksesnya, kelihatan sudah dicabut padahal belum.',
            'benerin'=> 'Buka Akses → periksa: yang berpola parent→child biasanya disengaja; sisanya hapus duplikatnya, lalu rebuild akses.',
            'rows'   => $rows,
        ];
    }

    /**
     * #5 Approval nyasar — att_approval_rule menunjuk role yang tidak ada
     * (mis. UUID dari mesin lain) → rantai kosong → pengajuan auto-approve.
     * Sudah kejadian 1× (SEMUA cuti auto-approve).
     */
    private function approvalNyasar(): array
    {
        // COLLATE eksplisit: att_approval_rule kebetulan utf8mb4_general_ci,
        // beda dari group_path (unicode_ci) → join polos = error 1267.
        // (Temuan sampingan detektor ini sendiri; normalisasi kolomnya masuk
        // daftar rapi-rapi pra-prod.)
        $rows = DB::select("
            SELECT r.id,
                   COALESCE(g1.nama, CONCAT('role hilang: ', r.role_id)) AS role_nama,
                   COALESCE(g2.nama, CONCAT('role hilang: ', r.approver_role_id)) AS approver_nama,
                   (g1.id IS NULL) AS role_hilang, (g2.id IS NULL) AS approver_hilang
            FROM att_approval_rule r
            LEFT JOIN group_path g1 ON g1.id = r.role_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN group_path g2 ON g2.id = r.approver_role_id COLLATE utf8mb4_unicode_ci
            WHERE g1.id IS NULL OR g2.id IS NULL
        ");

        return [
            'judul'  => 'Aturan persetujuan nyasar',
            'arti'   => 'Aturan approval menunjuk role yang tidak ada di server ini — rantai persetujuan kosong, pengajuan bisa lolos otomatis.',
            'benerin'=> 'Perbaiki att_approval_rule: arahkan ke role yang benar (cek nama role di halaman Role). Jangan hapus barisnya tanpa pengganti.',
            'rows'   => array_map(fn ($r) => [
                'utama' => $r->role_nama . ' → ' . $r->approver_nama,
                'detail'=> $r->role_hilang ? 'role pengaju tidak ditemukan' : 'role approver tidak ditemukan',
            ], $rows),
        ];
    }

    /** #6 Akun tak bertuan — aktif tapi tak pernah/lama tak login. */
    private function akunTakBertuan(): array
    {
        $batas = Carbon::now()->subDays(self::TAK_BERTUAN_HARI)->toDateTimeString();
        // masa tenggang: akun yang BARU dibuat wajar belum pernah login —
        // jangan langsung dicap tak bertuan (semua akun migrasi bakal kena)
        $rows = DB::select("
            SELECT u.nama, g.nama AS role, u.last_login
            FROM users u
            LEFT JOIN group_path g ON g.id = u.group_id
            WHERE u.status = 1
              AND (u.last_login IS NULL OR u.last_login < ?)
              AND (u.created_at IS NULL OR u.created_at < ?)
            ORDER BY u.last_login IS NULL DESC, u.last_login ASC
        ", [$batas, $batas]);

        return [
            'judul'  => 'Akun tak bertuan',
            'arti'   => 'Akun aktif yang tidak pernah login atau sudah ≥' . self::TAK_BERTUAN_HARI . ' hari tak terlihat — kandidat dinonaktifkan atau memang belum dipakai.',
            'benerin'=> 'Cek satu per satu: kalau orangnya resign/akun peran lama → nonaktifkan dari halaman User. Kalau akun baru belum dipakai → biarkan.',
            'rows'   => array_map(fn ($r) => [
                'utama' => $r->nama . ($r->role ? " ({$r->role})" : ''),
                'detail'=> $r->last_login ? ('terakhir login ' . substr($r->last_login, 0, 10)) : 'TIDAK PERNAH login',
            ], $rows),
        ];
    }

    /** #7 Perangkat menggantung — perangkat "diingat" hidup milik akun nonaktif. */
    private function perangkatMenggantung(): array
    {
        $rows = DB::select("
            SELECT u.nama, d.label, d.last_used_at
            FROM user_device d
            JOIN users u ON u.id = d.user_id
            WHERE d.revoked_at IS NULL AND u.status <> 1
            ORDER BY d.last_used_at DESC
        ");

        return [
            'judul'  => 'Perangkat menggantung',
            'arti'   => 'Perangkat "Ingat perangkat ini" yang masih hidup padahal akunnya sudah nonaktif — pintu masuk yang lupa dikunci.',
            'benerin'=> 'Cabut dari halaman User → detail user → perangkat. (Login otomatisnya sendiri sudah ditolak untuk akun nonaktif — ini bersih-bersih lapisan kedua.)',
            'rows'   => array_map(fn ($r) => [
                'utama' => $r->nama,
                'detail'=> ($r->label ?: 'Perangkat') . ' · terakhir dipakai ' . substr((string) $r->last_used_at, 0, 16),
            ], $rows),
        ];
    }
}
