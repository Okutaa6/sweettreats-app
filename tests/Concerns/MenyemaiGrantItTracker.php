<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyemai grant menu `it-tracker` (+ induknya) untuk sebuah group DI DALAM
 * transaksi uji — pola pastikanGrant() KehadiranExecutiveTest.
 *
 * Kenapa: AuthGuard membaca group_access di DB, dan siapa yang memegang grant
 * itu KEPUTUSAN yang berubah-ubah — 9 Sep 2026 Ekky mencabut Finance, Head
 * Marketing, dan Admin (SQL 2026_09_09_it_tracker_akses_sementara). Delapan
 * belas uji berpersona `head` langsung merah bukan karena kodenya cacat,
 * melainkan karena personanya dipantulkan 302 ke landing sebelum sampai ke
 * controller yang diuji. Uji yang menebak "siapa yang kebetulan punya akses
 * hari ini" akan merah lagi setiap keputusan berganti.
 *
 * Dengan menyemai grantnya sendiri, uji menguji KEMAMPUAN peran: head melihat
 * angka tapi bukan judul tiket departemen lain, /it-tracker memantul ke
 * /it-tracker/permintaan, detail tiket orang lain 403. Peran `head` kini nol
 * penghuni di prod; penjaga aturan penyensoran itu hanya hidup di sini.
 *
 * Grant hasil semai ikut rollback DatabaseTransactions — tak ada jejak di DB.
 * ⛔ JANGAN dipakai di uji yang justru menguji ketiadaan grant
 *    (ItTrackerPeluncuranSenyapTest punya perkakasnya sendiri).
 */
trait MenyemaiGrantItTracker
{
    /** @param string|null $groupId id group_path (bukan nama); 'grp-uji' = group tak ada → tak ada yang disemai */
    protected function pastikanGrantItTracker(?string $groupId): void
    {
        if (! $groupId || $groupId === 'grp-uji') return;

        $node = DB::table('access_path')->where('link', 'it-tracker')->first(['id', 'pid']);
        if (! $node) return;   // kelas pemanggil sudah punya markTestSkipped-nya sendiri utk SQL akses

        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            $ada = DB::table('group_access')->where('group_id', $groupId)->where('access_id', $accessId)->exists();
            if (! $ada) {
                // group_access.id tak punya default — WAJIB diisi sendiri.
                DB::table('group_access')->insert(['id' => (string) Str::uuid(), 'group_id' => $groupId, 'access_id' => $accessId]);
            }
        }
    }
}
