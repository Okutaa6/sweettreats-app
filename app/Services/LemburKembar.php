<?php

namespace App\Services;

use App\Models\AttRequest;
use Illuminate\Support\Carbon;

/**
 * Pagar pengajuan lembur KEMBAR — satu sesi kerja, dua kali dibayar.
 *
 * ── Kenapa kelas ini ada ──
 * Afrina Apriliani, 10 Agustus 2026. Dua pengajuan lembur dikirim berselang
 * **116 detik**: tanggal sama, jam sama (19:15–21:10), alasan sama kata per
 * kata, 115 menit masing-masing. Bedanya cuma nama berkas foto — dia
 * meng-upload ulang, mungkin mengira kiriman pertama gagal. Anisa menyetujui
 * KEDUANYA, berselang 8 menit, tanpa satu pun tanda bahwa itu kiriman yang
 * sama. `AttendanceReconciler` menjumlahkan semua lembur yang di-ACC pada
 * tanggal itu → `att_day.lembur_menit_disetujui = 230` untuk sesi yang
 * panjangnya 115 menit. Dibayar dobel, diam-diam, dan baru ketahuan 13 hari
 * kemudian saat fitur DODO membuatnya kelihatan.
 *
 * ── Apa yang DIPAGARI dan apa yang TIDAK ──
 * Dua pengajuan lembur mirip di hari yang sama bisa saja SAH: orang bisa lembur
 * pagi 08:00–10:00 lalu lembur lagi malam 19:00–21:00. Yang salah bukan
 * keberadaan dua barisnya — yang salah adalah **tak ada yang diberi tahu**.
 * Jadi kelas ini tak pernah menggabungkan, tak pernah menolak diam-diam:
 *
 *   • jam PERSIS sama       → ditolak di titik simpan, dengan menyebut nomor
 *                             pengajuan yang sudah ada (bukan sekadar "gagal");
 *   • jam bertindih SEBAGIAN → diteruskan, tapi penyetuju melihat peringatannya
 *                             di kartu, sebelum dia menekan Setujui;
 *   • jam tak bersinggungan → lewat tanpa diganggu.
 *
 * ⚠️ Tindihan SEBAGIAN (19:00–21:00 vs 19:15–21:10) memang tak boleh lolos
 * hanya karena jamnya tak persis sama — itu tetap menit yang sama diklaim dua
 * kali. Karena itu pembandingnya rentang, bukan kecocokan string.
 */
class LemburKembar
{
    /**
     * Status yang masih bisa berujung pada pembayaran. `rejected` sengaja tak
     * ikut: itu justru cara membetulkan kembar yang terlanjur masuk (req#31
     * Afrina di-`rejected`, bukan dihapus, supaya jejaknya tetap ada) — kalau
     * ikut dihitung, pengajuan pengganti yang benar jadi ikut tertolak.
     */
    public const STATUS_HIDUP = ['pending_l1', 'pending_l2', 'approved'];

    /**
     * Pengajuan lembur lain yang bertindih dengan (user, tanggal, jam) ini.
     *
     * ⚠️ **Menyapu baris yang SUDAH ADA? Isi `$kecualiId`, atau pakai `peta()`.**
     * Tanpa itu setiap baris dibandingkan dengan DIRINYA SENDIRI dan semuanya
     * terbaca "kembar persis" — bentuk paling meyakinkan dari hasil palsu,
     * karena angkanya rapi dan salah. `$kecualiId` boleh kosong hanya untuk
     * kiriman BARU, yang memang belum punya id. Sudah menjebak satu orang saat
     * pengadu-an ke data prod (27 Agu); `peta()` menutupnya sendiri, jadi untuk
     * penyapuan itulah pintu yang benar.
     *
     * @param  string|int|null  $kecualiId  id pengajuan yang sedang diperiksa
     *                                      (dipakai layar persetujuan, supaya
     *                                      kartu tak menuduh dirinya sendiri)
     * @return array<int,array{req:AttRequest,persis:bool,jam_lengkap:bool}>
     */
    public static function bertindih(
        string $userId,
        string $tanggal,
        ?string $jamFrom,
        ?string $jamTo,
        $kecualiId = null
    ): array {
        $rows = AttRequest::where('user_id', $userId)
            ->where('tipe', 'lembur')
            ->where('tanggal_from', self::tanggal($tanggal))
            ->whereIn('status', self::STATUS_HIDUP)
            ->when($kecualiId !== null, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->orderBy('id')
            ->get();

        return self::saring($rows, $jamFrom, $jamTo);
    }

    /**
     * Versi borongan untuk layar yang menampilkan banyak kartu sekaligus —
     * satu kueri, bukan satu per kartu.
     *
     * @param  iterable<AttRequest>  $pengajuan  kartu-kartu yang tampil
     * @return array<string,array<int,array{req:AttRequest,persis:bool,jam_lengkap:bool}>>
     *         id pengajuan => daftar tindihannya (yang bersih tak masuk peta)
     */
    public static function peta(iterable $pengajuan): array
    {
        $daftar = collect($pengajuan)->filter(fn ($r) => $r->tipe === 'lembur')->values();
        if ($daftar->isEmpty()) return [];

        $userIds  = $daftar->pluck('user_id')->map(fn ($v) => (string) $v)->unique()->values()->all();
        $tanggals = $daftar->map(fn ($r) => self::tanggal($r->tanggal_from))->unique()->values()->all();

        // Satu kueri untuk semua pasangan. Sengaja whereIn × whereIn (bukan OR
        // berpasangan): kelebihan barisnya disaring di PHP di bawah, dan inbox
        // persetujuan tak pernah sebesar itu.
        $semua = AttRequest::where('tipe', 'lembur')
            ->whereIn('user_id', $userIds)
            ->whereIn('tanggal_from', $tanggals)
            ->whereIn('status', self::STATUS_HIDUP)
            ->orderBy('id')
            ->get();

        $hasil = [];
        foreach ($daftar as $r) {
            $tgl  = self::tanggal($r->tanggal_from);
            $lain = $semua->filter(fn ($x) => (string) $x->user_id === (string) $r->user_id
                && self::tanggal($x->tanggal_from) === $tgl
                && (string) $x->id !== (string) $r->id);

            $tindih = self::saring($lain, $r->jam_from, $r->jam_to);
            if ($tindih) $hasil[(string) $r->id] = $tindih;
        }

        return $hasil;
    }

    /** Ada yang jamnya PERSIS sama? Hanya ini yang boleh menolak kiriman. */
    public static function adaYangPersis(array $tindihan): bool
    {
        foreach ($tindihan as $t) {
            if ($t['persis']) return true;
        }

        return false;
    }

    /**
     * Pesan penolakan untuk kiriman yang jamnya persis sama. Menyebut nomor
     * pengajuan yang sudah ada dan apa yang harus dilakukan — "gagal" saja
     * membuat orang mengirim ulang, yang persis bagaimana kembar ini lahir.
     */
    public static function pesanTolak(array $tindihan, string $tanggal, ?string $jamFrom, ?string $jamTo): string
    {
        $persis = array_values(array_filter($tindihan, fn ($t) => $t['persis']));
        $r = ($persis[0]['req'] ?? $tindihan[0]['req']);

        $jam = ($jamFrom && $jamTo) ? ' jam ' . self::jam($jamFrom) . '–' . self::jam($jamTo) : '';

        return 'Lembur ' . Carbon::parse($tanggal)->translatedFormat('d M Y') . $jam
            . ' sudah pernah dikirim — pengajuan #' . $r->id . ' (' . self::labelStatus($r->status) . '). '
            . 'Kalau ini sesi yang berbeda, ubah jamnya dulu; kalau kiriman pertama yang keliru, '
            . 'minta atasan menolaknya — jangan mengirim ulang, nanti terbayar dua kali.';
    }

    /** Ringkasan satu baris untuk kartu penyetuju. */
    public static function ringkas(array $tindihan): string
    {
        $bagian = [];
        foreach ($tindihan as $t) {
            $r   = $t['req'];
            $jam = ($r->jam_from && $r->jam_to)
                ? self::jam($r->jam_from) . '–' . self::jam($r->jam_to)
                : 'jam tak diisi';
            $bagian[] = '#' . $r->id . ' (' . $jam . ', ' . self::labelStatus($r->status) . ')';
        }

        return implode(' · ', $bagian);
    }

    // ───────────────────────────────────────────────────────────

    /** @return array<int,array{req:AttRequest,persis:bool,jam_lengkap:bool}> */
    private static function saring($rows, ?string $jamFrom, ?string $jamTo): array
    {
        $a = self::menit($jamFrom);
        $b = self::menit($jamTo);

        $hasil = [];
        foreach ($rows as $r) {
            $c = self::menit($r->jam_from);
            $d = self::menit($r->jam_to);

            // Jam tak lengkap di salah satu sisi → rentangnya tak bisa
            // dibandingkan. Tetap dilaporkan (dua lembur di hari yang sama
            // layak dilihat manusia), tapi TAK PERNAH jadi alasan menolak:
            // menolak berdasarkan hal yang tak bisa dipastikan adalah cara
            // membuat orang berhenti percaya pada pesan galatnya.
            if ($a === null || $b === null || $c === null || $d === null) {
                $hasil[] = ['req' => $r, 'persis' => false, 'jam_lengkap' => false];
                continue;
            }

            // Setengah terbuka: 08:00–10:00 dan 10:00–12:00 BERSAMBUNG, bukan
            // bertindih — dua sesi sah yang kebetulan bersambung tak boleh
            // dituduh kembar.
            if (! ($a < $d && $c < $b)) continue;

            $hasil[] = ['req' => $r, 'persis' => ($a === $c && $b === $d), 'jam_lengkap' => true];
        }

        return $hasil;
    }

    /** Menit sejak 00:00. Menerima "19:15" maupun "19:15:00" (kolom TIME). */
    private static function menit($jam): ?int
    {
        if ($jam === null || $jam === '') return null;
        if (! preg_match('/^(\d{1,2}):(\d{2})/', (string) $jam, $m)) return null;

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    private static function jam($v): string
    {
        return substr((string) $v, 0, 5);
    }

    /** `tanggal_from` bisa datang sebagai Carbon (model) atau string (kiriman form). */
    private static function tanggal($v): string
    {
        return $v instanceof \DateTimeInterface
            ? $v->format('Y-m-d')
            : Carbon::parse((string) $v)->toDateString();
    }

    private static function labelStatus(?string $status): string
    {
        return $status === 'approved' ? 'sudah disetujui' : 'menunggu persetujuan';
    }
}
