<?php

namespace App\Services\ItTracker;

use App\Models\ItModul;
use App\Models\ItTicket;
use Illuminate\Support\Facades\DB;

/**
 * Katalog Modul — "apa saja yang kami punya", halaman saudara papan antrean.
 * Rencana §14.24–14.25; isi: docs/rencana/katalog-modul-2026-09.md.
 *
 * TIGA ATURAN YANG MEMBENTUK KELAS INI (jangan dilanggar diam-diam):
 *
 * 1. FITUR DITURUNKAN, bukan diketik — dan diturunkan **per LINK**, bukan per
 *    node (§14.27, ralat spesifikasi). `it_modul.fitur_link` menyimpan daftar
 *    `access_path.link` milik modul; fiturnya = link-link itu, DEDUP PER LINK.
 *
 *    Kenapa bukan per node: MENU mengelompokkan berdasarkan NAVIGASI, KATALOG
 *    berdasarkan KEPEMILIKAN, dan keduanya tak selalu berimpit — Branch·Doctor·
 *    Doctor Service bersaudara di bawah parent yang juga memuat modul lain.
 *    Dan `rawreservation` muncul 4× di menu dengan empat nama: menghitung node
 *    = menghitung LABEL MENU, menghitung link = menghitung HALAMAN. Yang
 *    dijanjikan katalog kepada Executive adalah "halaman yang bisa dibuka".
 *
 *    ⚠️ `link` juga STABIL antar lingkungan, sedangkan id node tidak.
 *    Modul lama yang belum punya daftar link tetap dilayani lewat node-nya
 *    (aturan shellCollectLeaves), supaya tak ada yang mendadak nol.
 *
 * 2. TIGA ANGKA DIHITUNG: hidup (status modul) · berjalan & utang (it_ticket).
 *    Tak satu pun disimpan. "utang" = tiket TERBUKA yang menempel di modul
 *    (termasuk yang ditunda — ditunda tetap utang); "berjalan" = yang terbuka
 *    dan tidak ditunda.
 *
 * 3. ⛔ TAK ADA TEBAKAN MODUL DARI JUDUL. Mockup memakai regex; tebakan yang
 *    meleset tidak terlihat siapa pun. Tiket tanpa `modul_id` masuk baris
 *    "Tanpa modul" — dan penjumlahannya ditulis eksplisit di layar supaya bisa
 *    dicocokkan dengan papan, bukan dipercaya.
 */
class KatalogModul
{
    /** Urutan tetap — dipakai kelompok, matriks, dan pilihan di dialog. */
    public const DEPARTEMEN = ['Sales', 'Marketing', 'Finance', 'Operasional / HR',
        'Data Pasien & Rekam Medis', 'Executive', 'IT', 'Stok / Inventory'];

    public const JENIS = ['Operasional', 'Laporan & Dashboard', 'Integrasi', 'Platform'];

    /** hidup · dibangun · rencana — BUKAN progres (jangan pernah jadi persen). */
    public const STATUS = [
        'hidup'    => ['nama' => 'HIDUP',    'sub' => 'dipakai orang hari ini'],
        'dibangun' => ['nama' => 'DIBANGUN', 'sub' => 'lulus dev, prod belum'],
        'rencana'  => ['nama' => 'RENCANA',  'sub' => 'disepakati, belum ada'],
    ];

    /** Tiket yang belum tuntas — SELESAI & DITOLAK tidak lagi jadi utang. */
    public const FASE_TERBUKA_KECUALI = ['SELESAI', 'DITOLAK'];

    /** peta pohon access_path, dimuat sekali per permintaan */
    private ?array $anak = null;
    private ?array $node = null;

    // ── pohon menu ─────────────────────────────────────────────────────────

    private function muatPohon(): void
    {
        if ($this->node !== null) return;

        $this->node = [];
        $this->anak = [];
        foreach (DB::table('access_path')->get(['id', 'pid', 'nama', 'link', 'urutan']) as $r) {
            $this->node[$r->id] = $r;
            $this->anak[(string) $r->pid][] = $r->id;
        }
    }

    /**
     * Fitur satu MODUL — jalur utama (§14.27): daftar link, dedup per link.
     * Link yang tak ada di menu lingkungan ini tidak dihitung: katalog
     * menjanjikan halaman yang BISA DIBUKA, bukan yang seharusnya ada.
     * Modul tanpa daftar link jatuh kembali ke node-nya (perilaku lama).
     *
     * @return array<int, array{nama: string, link: string}>
     */
    public function fiturModul(ItModul $m): array
    {
        $link = array_values(array_filter(array_map('trim', (array) ($m->fitur_link ?? []))));
        if (! $link) return $this->dedup($this->fitur($m->access_id));

        $this->muatPohon();
        $out = [];
        foreach (array_unique($link) as $l) {
            if ($l === '' || $l === '-') continue;
            $nama = null;
            foreach ($this->node as $n) {
                if (trim((string) $n->link) === $l) { $nama = (string) $n->nama; break; }
            }
            if ($nama === null) continue;         // tak ada di menu sini → tak bisa dibuka
            $out[] = ['nama' => $nama, 'link' => $l];
        }

        return $out;
    }

    /** Buang link kembar — satu HALAMAN dihitung sekali walau labelnya banyak. */
    private function dedup(array $fitur): array
    {
        $lihat = [];
        $out = [];
        foreach ($fitur as $f) {
            if (isset($lihat[$f['link']])) continue;
            $lihat[$f['link']] = true;
            $out[] = $f;
        }

        return $out;
    }

    /**
     * Fitur dari SATU node (jalur cadangan & perkakas seeder): bercabang → turun
     * ke anak; daun ber-link → dirinya sendiri; daun tanpa link → nol (persis
     * seperti sidebar: ia tak pernah tampil).
     *
     * @return array<int, array{nama: string, link: string}>
     */
    public function fitur(?string $accessId): array
    {
        if (! $accessId) return [];
        $this->muatPohon();
        if (! isset($this->node[$accessId])) return [];

        return $this->daun($accessId);
    }

    /** @return array<int, array{nama: string, link: string}> */
    private function daun(string $id): array
    {
        $anak = $this->anak[$id] ?? [];
        if ($anak) {
            $out = [];
            foreach ($anak as $a) $out = array_merge($out, $this->daun($a));
            return $out;
        }

        $n    = $this->node[$id];
        $link = trim((string) $n->link);
        if ($link === '' || $link === '-') return [];

        return [['nama' => (string) $n->nama, 'link' => $link]];
    }

    /** Node menu ada di lingkungan ini? (dev/prod bisa berbeda dari lokal) */
    public function nodeAda(?string $accessId): bool
    {
        if (! $accessId) return false;
        $this->muatPohon();
        return isset($this->node[$accessId]);
    }

    /**
     * Cari node berdasarkan JALUR NAMA ("Data Center>Pusat Akun") — dipakai
     * seeder. Id node berbeda antar lingkungan (jebakan 2026-07-24), namanya
     * tidak; jadi seed menyebut nama, bukan id.
     */
    public function cariJalur(string $jalur): ?string
    {
        $this->muatPohon();
        $pid = '';
        foreach (explode('>', $jalur) as $nama) {
            $nama    = trim($nama);
            $ketemu  = null;
            foreach ($this->anak[$pid] ?? [] as $id) {
                if (mb_strtolower((string) $this->node[$id]->nama) === mb_strtolower($nama)) { $ketemu = $id; break; }
            }
            // akar kadang ber-pid NULL, kadang '' — dua-duanya dilayani muatPohon
            if ($ketemu === null && $pid === '') {
                foreach ($this->node as $id => $n) {
                    if (($n->pid === null || $n->pid === '') && mb_strtolower((string) $n->nama) === mb_strtolower($nama)) { $ketemu = $id; break; }
                }
            }
            if ($ketemu === null) return null;
            $pid = $ketemu;
        }

        return $pid !== '' ? $pid : null;
    }

    // ── angka ──────────────────────────────────────────────────────────────

    /** Semua modul, urut departemen → status → nama. */
    public function modul()
    {
        $urutDept   = array_flip(self::DEPARTEMEN);
        $urutStatus = ['hidup' => 0, 'dibangun' => 1, 'rencana' => 2];

        return ItModul::all()->sortBy([
            fn ($a, $b) => ($urutDept[$a->departemen] ?? 99) <=> ($urutDept[$b->departemen] ?? 99),
            fn ($a, $b) => ($urutStatus[$a->status] ?? 9) <=> ($urutStatus[$b->status] ?? 9),
            fn ($a, $b) => strcasecmp($a->nama, $b->nama),
        ])->values();
    }

    /**
     * Cacah tiket terbuka & berjalan per modul_id (NULL ikut, kuncinya 0).
     * SATU kueri — jangan pernah menghitungnya di dalam loop modul.
     *
     * @return array<int, array{terbuka: int, berjalan: int, ditunda: int}>
     */
    public function cacahTiket(): array
    {
        $rows = DB::table('it_ticket')
            ->whereNotIn('fase', self::FASE_TERBUKA_KECUALI)
            ->selectRaw('COALESCE(modul_id, 0) AS mid, fase, COUNT(*) AS n')
            ->groupBy('mid', 'fase')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $mid = (int) $r->mid;
            $out[$mid] ??= ['terbuka' => 0, 'berjalan' => 0, 'ditunda' => 0];
            $out[$mid]['terbuka'] += (int) $r->n;
            if ($r->fase === 'DITUNDA') $out[$mid]['ditunda'] += (int) $r->n;
            else                        $out[$mid]['berjalan'] += (int) $r->n;
        }

        return $out;
    }

    /** Tiket terbuka + bentuk mesin fasenya, dikelompokkan per modul_id (0 = tanpa modul). */
    public function tiketTerbuka(ItTrackerService $svc): array
    {
        $tiket = $svc->muatRelasi(ItTicket::whereNotIn('fase', self::FASE_TERBUKA_KECUALI))
            ->orderByDesc('fase_since')->get();

        $out = [];
        foreach ($tiket as $t) {
            $v = $svc->viewTicket($t);
            $out[(int) ($t->modul_id ?: 0)][] = ['t' => $t, 'wait' => PhaseEngine::waitOf($v)];
        }

        return $out;
    }

    /** Label status yang aman dipakai layar (status tak dikenal tak boleh menjatuhkan halaman). */
    public static function statusLabel(string $status): string
    {
        return self::STATUS[$status]['nama'] ?? mb_strtoupper($status);
    }

    /**
     * "Warisan" = kerangka yang lahir sebelum kerjaan Sweet Treats (Pusat Akun
     * ber-UUID v1 bertanggal 27 Apr 2022). DITURUNKAN dari tanggalnya, bukan
     * disimpan sebagai penanda — supaya tak ada dua sumber kebenaran.
     */
    public static function warisan(?ItModul $m): bool
    {
        return $m && $m->sejak && $m->sejak->year < 2026;
    }
}
