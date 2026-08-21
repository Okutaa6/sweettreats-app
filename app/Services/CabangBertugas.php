<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Cabang tempat seseorang BERTUGAS pada satu tanggal — versi PRAMUAT.
 *
 * Bedanya dengan `positionBranchOn()` di Helpers.php: helper itu satu kueri
 * per (orang, tanggal). Rekonsiliasi absensi menilai puluhan orang × puluhan
 * hari dalam satu jalan, jadi memanggilnya di dalam loop harian berarti
 * puluhan ribu kueri untuk sebulan. Kelas ini memuat barisnya SEKALI lalu
 * menyelesaikan sisanya di memori — pola yang sama dengan
 * `loadRules()` / `loadExceptions()` di AttendanceReconciler.
 *
 * ⚠️ Aturannya sengaja SEMPIT — hanya baris `kind = 'rolling'` yang boleh
 * menang, `home` TIDAK. Alasannya sama persis dengan `cabangSesiUntuk()`:
 * tombol reassign di halaman Posisi Checklist menulis baris `home` tanpa
 * menyentuh `users.raw_branch_id` (DigitalChecklistController::positionAssign),
 * sehingga keduanya bisa berselisih. Kalau `home` ikut menang, supervisor
 * checklist diam-diam bisa memindahkan HARI LIBUR seseorang — dan sejak
 * libur menentukan gaji, itu wewenang yang tak pernah diberikan ke peran itu.
 * Rolling justru dibuat persis untuk penempatan sementara: punya tanggal,
 * punya jejak audit.
 *
 * ⚠️ Cabang asal NULL (ABG/Office) tetap NULL. NULL artinya "tak menempel satu
 * klinik"; baris penempatan tidak boleh menguncinya ke salah satu cabang,
 * sebab itu akan membuat libur per-cabang tiba-tiba berlaku untuk orang kantor.
 */
class CabangBertugas
{
    /**
     * Pramuat SELURUH baris penempatan sementara yang berpotensi aktif.
     * Penyaringan tanggal sengaja TIDAK dilakukan di sini — pemanggil menilai
     * banyak tanggal sekaligus, jadi rentangnya dibiarkan utuh dan diselesaikan
     * per tanggal di `pada()`.
     *
     * @return array<string, array<int, object>> user_id => baris rolling
     */
    public static function muat(?string $onlyUser = null): array
    {
        try {
            $rows = DB::table('sys_user_position')
                ->where('kind', 'rolling')
                ->where('is_primary', 1)
                ->whereNotNull('confirmed_at')          // usulan pending bukan penempatan
                ->when($onlyUser, fn ($q) => $q->where('user_id', $onlyUser))
                ->get(['user_id', 'raw_branch_id', 'effective_from', 'effective_to']);
        } catch (\Throwable $e) {
            // Tabel belum ada di lingkungan ini → perilaku lama (cabang asal).
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $from = self::tanggal($r->effective_from);
            $to   = self::tanggal($r->effective_to);

            // Rentang TERBALIK (berakhir sebelum dimulai) dibuang di sini juga,
            // bukan cuma ditolak di titik simpan. Baris seperti ini sudah
            // telanjur ada di prod (Verini, id a2821d77: 15 Agu → 14 Agu) dan
            // panjangnya nol — membiarkannya lolos berarti membiarkan data
            // rusak ikut menentukan gaji. Lihat §5 rencana.
            if ($from !== null && $to !== null && $to < $from) continue;

            $out[$r->user_id][] = (object) [
                'raw_branch_id'  => $r->raw_branch_id,
                'effective_from' => $from,
                'effective_to'   => $to,
            ];
        }

        // Urutan penentu saat ada lebih dari satu rolling yang melingkupi satu
        // tanggal (makeRolling menolak yang beririsan, tapi data lama bisa saja
        // punya). Sama dengan UserPosition::scopeActiveOn(): yang BERBATAS
        // menang atas yang terbuka, lalu effective_from terbaru. Tanpa urutan
        // ini pemenangnya ditentukan MySQL — orang yang sama bisa libur di satu
        // kali jalan dan tidak di kali berikutnya.
        foreach ($out as &$rows) {
            usort($rows, function ($a, $b) {
                $ab = $a->effective_to === null ? 1 : 0;
                $bb = $b->effective_to === null ? 1 : 0;
                if ($ab !== $bb) return $ab <=> $bb;
                return strcmp((string) $b->effective_from, (string) $a->effective_from);
            });
        }
        unset($rows);

        return $out;
    }

    /**
     * Cabang bertugas pada satu tanggal — MURNI memori, nol kueri.
     *
     * @param  array<int, object>  $rows          baris rolling milik user ini (dari muat())
     * @param  string              $tanggal       'Y-m-d'
     * @param  ?string             $cabangAsal    users.raw_branch_id
     */
    public static function pada(array $rows, string $tanggal, ?string $cabangAsal): ?string
    {
        if ($cabangAsal === null) return null;   // ABG/Office — jangan dikunci

        foreach ($rows as $r) {
            if ($r->effective_from !== null && $r->effective_from > $tanggal) continue;
            if ($r->effective_to   !== null && $r->effective_to   < $tanggal) continue;
            if ($r->raw_branch_id) return $r->raw_branch_id;
        }

        return $cabangAsal;
    }

    /** Kolom DATE bisa datang sebagai string, Carbon, atau NULL — samakan ke 'Y-m-d'. */
    private static function tanggal($v): ?string
    {
        if ($v === null || $v === '') return null;
        return substr((string) $v, 0, 10);
    }
}
