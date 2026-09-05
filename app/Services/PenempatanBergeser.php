<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Siapa yang penempatannya BERGESER sejak sebuah rentang tanggal berlalu.
 *
 * ── Kenapa ini ada ──
 * `att_day` dihitung dari keadaan organisasi **HARI INI**, bukan keadaan saat
 * tanggal itu berlangsung. Menjalankan `att:reconcile` atas tanggal LAMPAU
 * karena itu menimpa baris lama memakai penempatan/cabang yang berlaku
 * sekarang — dan hari yang dulu benar bisa berubah jadi salah, **tanpa satu pun
 * galat**.
 *
 * Kejadian nyata 5 Sep 2026: Alpajri 25 Agu berubah `libur` → `alpa`. Ia
 * ditempatkan di Bintaro (yang libur Maulid) sampai penempatan itu ditutup
 * 1 Sep saat restrukturisasi Konsultan; rekonsiliasi ulang membacanya sebagai
 * "tak punya penempatan" lalu menilai Selasa itu hari kerja biasa.
 *
 * ⚠️ Yang PALING berbahaya bukan kasus seperti Alpajri. Ia ketahuan justru
 * karena kini tak punya penempatan aktif sama sekali, jadi hasilnya mencolok.
 * Yang berbahaya adalah orang yang penempatannya **BERTUKAR** — hari Agustus
 * mereka akan dihitung ulang memakai kalender libur cabang BARU dan hasilnya
 * tetap terlihat masuk akal. Tak ada yang akan curiga. (Ekky, 5 Sep 2026:
 * Ramdan & Verini bertukar cabang 1 September.)
 *
 * Kelas ini BACA-SAJA.
 */
class PenempatanBergeser
{
    /**
     * Orang yang punya penempatan menyentuh [$from,$to] tapi penempatan itu
     * SUDAH TIDAK sama sekarang — entah ditutup, dipendekkan, atau diubah.
     *
     * Dua bentuk, dua-duanya menggeser hasil rekonsiliasi:
     *   1. penempatannya kini BERAKHIR (effective_to terisi & sudah lewat)
     *      → libur cabang tak lagi terbaca untuk hari lampau itu;
     *   2. barisnya DIUBAH sesudah rentang itu berlalu (`updated_at > $to`)
     *      → cabangnya bisa jadi sudah lain sama sekali.
     *
     * @return array<int,array{nama:string,cabang:?string,jenis:string,dari:?string,sampai:?string,sebab:string}>
     */
    public static function dalamRentang(string $from, string $to): array
    {
        try {
            $rows = DB::table('sys_user_position as p')
                ->join('users as u', 'u.id', '=', 'p.user_id')
                ->leftJoin('raw_branch as b', 'b.id', '=', 'p.raw_branch_id')
                // ⚠️ SENGAJA TANPA penyaring `kind`. CabangBertugas hanya membaca
                // `rolling`, tapi cabang bertugas jatuh kembali ke cabang ASAL
                // (`users.raw_branch_id`) kalau tak ada rolling — dan baris
                // `kind = 'home'` di sinilah jejak kapan cabang asal itu berubah.
                //
                // Menyalin penyaring CabangBertugas apa adanya membuat detektor ini
                // MELEWATKAN justru kasus yang melahirkannya: penempatan Alpajri di
                // Bintaro ber-`kind = 'home'`. Diukur 5 Sep 2026 — versi ber-filter
                // menemukan 3 orang, yang benar 6. Detektor yang melapor KURANG jauh
                // lebih berbahaya daripada yang melapor berlebih.
                ->where('p.is_primary', 1)
                ->whereNotNull('p.confirmed_at')
                // menyentuh rentang: mulai sebelum `to`, dan belum berakhir sebelum `from`
                ->where(fn ($q) => $q->whereNull('p.effective_from')->orWhere('p.effective_from', '<=', $to))
                ->where(fn ($q) => $q->whereNull('p.effective_to')->orWhere('p.effective_to', '>=', $from))
                ->where(fn ($q) => $q
                    ->whereNotNull('p.effective_to')            // (1) sudah/akan berakhir
                    ->orWhere('p.updated_at', '>', $to))        // (2) disentuh sesudah rentangnya lewat
                ->orderBy('u.nama')
                ->get(['u.nama', 'b.name as cabang', 'p.kind', 'p.effective_from', 'p.effective_to', 'p.updated_at']);
        } catch (\Throwable $e) {
            // Tabel belum ada di lingkungan ini → tak ada yang bisa bergeser.
            return [];
        }

        $hariIni = now()->toDateString();
        $out = [];

        foreach ($rows as $r) {
            $tutup  = $r->effective_to !== null && substr((string) $r->effective_to, 0, 10) < $hariIni;
            $diubah = $r->updated_at !== null && substr((string) $r->updated_at, 0, 10) > $to;

            if (! $tutup && ! $diubah) continue;

            $out[] = [
                'nama'   => $r->nama,
                'cabang' => $r->cabang,
                'jenis'  => $r->kind,
                'dari'   => $r->effective_from ? substr((string) $r->effective_from, 0, 10) : null,
                'sampai' => $r->effective_to ? substr((string) $r->effective_to, 0, 10) : null,
                'sebab'  => $tutup && $diubah ? 'ditutup & diubah sesudah rentang'
                    : ($tutup ? 'penempatannya sudah berakhir' : 'barisnya diubah sesudah rentang'),
            ];
        }

        return $out;
    }
}
