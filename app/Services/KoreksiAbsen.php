<?php

namespace App\Services;

use App\Models\AttDayKoreksi;
// AttendanceReconciler::reconcile() menuntut Illuminate\Support\Carbon,
// bukan Carbon\Carbon induknya — turunan tak bisa disubstitusi ke atas.
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi status absen harian oleh admin — satu-satunya tempat artinya
 * diputuskan.
 *
 * ── Kenapa koreksi TIDAK ditulis ke att_day ──
 * `att_day` itu turunan; `AttendanceReconciler` menghitungnya ulang dan
 * meng-`upsert` kolom `status`. Koreksi yang ditulis langsung ke sana bertahan
 * sampai penjadwal harian berjalan, lalu hilang tanpa satu pun pesan. Maka
 * koreksi disimpan sebagai deklarasi (att_day_koreksi) dan reconciler
 * membacanya sebagai KATA TERAKHIR tiap kali menghitung — pola yang sama
 * dengan pengajuan yang di-ACC.
 *
 * ── Kenapa periode tertutup ditolak, bukan didiamkan ──
 * Menutup periode membekukan `att_day` (§D2) karena angkanya sudah jadi dasar
 * gaji yang DIBAYARKAN. Mengubahnya belakangan membuat layar dan slip gaji
 * berselisih tanpa ada yang tahu mana yang benar. Jadi koreksi di rentang itu
 * ditolak dengan pesan yang menyebut periodenya — bukan diterima diam-diam
 * lalu diabaikan reconciler, yang akan terlihat seperti fitur yang rusak.
 */
class KoreksiAbsen
{
    /**
     * Seluruh status att_day — cerminan enum kolomnya, dipakai terapkan() dan
     * validasi baris yang SUDAH ada. Bukan daftar yang boleh dipilih manusia;
     * untuk itu lihat STATUS_PILIHAN.
     */
    public const STATUS = ['hadir', 'telat', 'alpa', 'cuti', 'izin', 'sakit', 'libur', 'wfh'];

    /**
     * Status yang punya BUKU BESAR sendiri di luar att_day.
     *
     * Cuma `cuti`: jatahnya hidup di `att_cuti_mutasi`, dan yang memotongnya
     * HANYA AttendanceRequestWorkflow saat sebuah pengajuan di-ACC. Koreksi
     * absen tak pernah menyentuh buku besar itu — dan tak boleh, sebab jatah
     * cuti punya alur persetujuannya sendiri.
     *
     * ⛔ Akibatnya kalau dibiarkan bisa dipilih: satu baris Lembar Gaji memuat
     * dua angka yang saling membantah — kolom **Cuti** (dihitung dari
     * att_day) ikut naik, sementara **Sisa Cuti** (dihitung dari buku besar)
     * tidak bergerak. Orangnya dapat hari cuti yang tak memakan jatahnya, dan
     * tak ada satu pun galat yang muncul.
     *
     * `sakit`, `izin`, `wfh` TIDAK di sini: ketiganya tak punya jatah
     * tersimpan di mana pun (diperiksa 5 Sep 2026), jadi mengoreksinya tak
     * membuat angka mana pun berselisih.
     */
    public const STATUS_BERBUKU = ['cuti'];

    /** Status yang boleh DIPILIH di layar koreksi. */
    public const STATUS_PILIHAN = ['hadir', 'telat', 'alpa', 'izin', 'sakit', 'libur', 'wfh'];

    /**
     * Kenapa sebuah status tak boleh dipilih — dipakai layar & validasi supaya
     * penolakannya menyebutkan ALASAN dan JALAN KELUARNYA, bukan cuma menolak.
     */
    public static function alasanTerlarang(string $status): ?string
    {
        if (! in_array($status, self::STATUS_BERBUKU, true)) return null;

        return 'Status "cuti" tidak bisa disetel lewat koreksi absen, sebab jatah cuti '
            . 'hidup di buku besarnya sendiri dan hanya berkurang lewat pengajuan yang di-ACC. '
            . 'Kalau disetel dari sini, kolom Cuti di Lembar Gaji naik sementara Sisa Cuti '
            . 'tidak bergerak — orangnya dapat hari cuti yang tak memakan jatahnya. '
            . 'Kalau pengajuan cutinya MEMANG sudah di-ACC tapi harinya belum berubah, '
            . 'jalankan rekonsiliasi ulang. Kalau belum ada pengajuannya, buat dan setujui dulu.';
    }

    /**
     * Peringatan saat status LAMA-nya bertumpu buku besar. Ini TIDAK diblokir —
     * membetulkan cuti yang salah tercatat memang pekerjaan yang sah — tapi
     * jatahnya tak ikut kembali sendiri, dan itu harus dikatakan, bukan
     * dibiarkan ditemukan sendiri saat gajian.
     */
    public static function peringatanStatusLama(?string $statusLama): ?string
    {
        if (! in_array((string) $statusLama, self::STATUS_BERBUKU, true)) return null;

        return '⚠️ Hari ini sebelumnya tercatat CUTI. Jatah cutinya TIDAK ikut dikembalikan '
            . 'oleh koreksi ini — kalau memang harus kembali, betulkan lewat layar Cuti.';
    }

    /**
     * Status yang berarti orangnya TIDAK bekerja hari itu. Saat koreksi
     * memindahkan sebuah hari ke sini, `telat_menit` ikut dinolkan: "telat 30
     * menit" pada hari yang dinyatakan libur bukan sekadar aneh dibaca — angka
     * itu ikut terjumlah di kolom Total Telat pada Lembar Gaji, jadi
     * membiarkannya berarti koreksi membetulkan satu angka sambil merusak yang
     * lain.
     */
    private const TAK_BEKERJA = ['alpa', 'cuti', 'izin', 'sakit', 'libur'];

    /**
     * Koreksi yang berlaku untuk satu rentang — dimuat SEKALI, bukan per hari
     * per orang (pola loadRules/loadExceptions).
     *
     * Kalau satu (orang, tanggal) punya lebih dari satu koreksi aktif — bisa
     * terjadi lewat dua tab yang dikirim berbarengan — yang menang adalah yang
     * TERBARU. Diurutkan naik lalu ditimpa, jadi hasilnya tidak bergantung
     * pada urutan yang kebetulan diberikan MySQL.
     *
     * @return array<string,object>  "userId|Y-m-d" => baris koreksi
     */
    public static function petaAktif(string $from, string $to, ?string $userId = null): array
    {
        // Rekonsiliasi berjalan tiap hari untuk SELURUH roster. Kalau SQL-nya
        // belum dijalankan di sebuah environment, ketiadaan tabel ini tak boleh
        // menjatuhkan absensi semua orang — cukup berarti "belum ada koreksi".
        // Diperiksa sekali per proses, bukan per pemanggilan.
        static $ada = null;
        $ada ??= tabelAda('att_day_koreksi'); // bukan Schema::has*() — membaca information_schema, membunuh mysqld lokal (lihat kolomAda/tabelAda)
        if (! $ada) return [];

        $rows = DB::table('att_day_koreksi')
            ->whereNull('dicabut_at')
            ->whereBetween('work_date', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->get(['id', 'user_id', 'work_date', 'status', 'alasan']);

        $peta = [];
        foreach ($rows as $r) {
            $peta[$r->user_id . '|' . substr((string) $r->work_date, 0, 10)] = $r;
        }

        return $peta;
    }

    /**
     * Timpa status baris hasil rekonsiliasi dengan koreksi yang berlaku.
     *
     * Sengaja sebuah langkah TERPISAH sesudah reconcileUser, bukan sisipan di
     * dalamnya: reconcileUser punya banyak jalan keluar, dan menyisipkan di
     * tiap jalan berarti cepat atau lambat ada satu yang terlewat. Satu tempat
     * = tak ada cabang yang lolos.
     *
     * @param  array<int,array>      $rows  baris siap-upsert dari reconcileUser
     * @param  array<string,object>  $peta  hasil petaAktif()
     */
    public static function terapkan(array $rows, array $peta): array
    {
        if (! $peta) return $rows;

        foreach ($rows as $i => $r) {
            $k = $r['user_id'] . '|' . $r['work_date'];
            if (! isset($peta[$k])) continue;

            $rows[$i]['status'] = $peta[$k]->status;

            // Hari yang dinyatakan tak-bekerja tak boleh membawa sisa telat
            // dari perhitungan scan yang barusan dibatalkan koreksi ini.
            if (in_array($peta[$k]->status, self::TAK_BEKERJA, true)) {
                $rows[$i]['telat_menit'] = 0;
            }
        }

        return $rows;
    }

    /**
     * Apakah tanggal ini terkunci periode gaji bagi orang ini? Pertanyaannya
     * diteruskan ke AttendanceReconciler supaya definisi "terkunci" tetap
     * satu — kalau disalin ke sini, dua-duanya akan berselisih begitu aturan
     * periode per-cabang berubah.
     */
    public static function terkunci(string $userId, string $tanggal): bool
    {
        return (new AttendanceReconciler())->tanggalTerkunci($tanggal, self::cabangAsal($userId));
    }

    /** Nama periode yang mengunci tanggal ini — supaya penolakan bisa menyebut namanya. */
    public static function periodePengunci(string $userId, string $tanggal): ?string
    {
        $home = self::cabangAsal($userId);

        $p = DB::table('att_period')
            ->where('status', 'closed')
            ->where('periode_from', '<=', $tanggal)
            ->where('periode_to', '>=', $tanggal)
            ->where(fn ($q) => $q->whereNull('raw_branch_id')->orWhere('raw_branch_id', $home))
            ->first(['nama', 'periode_from', 'periode_to']);

        if (! $p) return null;

        return $p->nama ?: (substr((string) $p->periode_from, 0, 10) . ' s/d ' . substr((string) $p->periode_to, 0, 10));
    }

    /**
     * Cabang ASAL — bukan cabang bertugas. Penguncian periode gaji mengikuti
     * cabang asal; menukarnya dengan cabang bertugas membuat kuncian ikut
     * berpindah saat orang sedang rolling (peringatan yang sama ada di
     * AttendanceReconciler::reconcileUser).
     */
    private static function cabangAsal(string $userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('raw_branch_id');
    }

    /** Status att_day yang tercatat sekarang (untuk disalin ke status_lama). */
    public static function statusSekarang(string $userId, string $tanggal): ?string
    {
        return DB::table('att_day')
            ->where('user_id', $userId)
            ->whereDate('work_date', $tanggal)
            ->value('status');
    }

    /**
     * Catat koreksi lalu hitung ulang hari itu supaya layar langsung berubah.
     *
     * Rekonsiliasi ter-scope SATU orang SATU hari: murah, dan tak menyentuh
     * hari orang lain — koreksi tak punya urusan dengan mereka.
     */
    public static function simpan(string $userId, string $tanggal, string $status, string $alasan,
                                  ?string $olehId, ?string $olehNama): AttDayKoreksi
    {
        // Satu transaksi: baris buku besar dan hari yang dihitung ulang harus
        // lahir bersama. Kalau catatannya tersimpan tapi hitung-ulangnya gagal,
        // buku besar bilang "sudah dikoreksi" sementara layar masih menampilkan
        // status lama — dan selisih itu bertahan sampai penjadwal besok pagi.
        return DB::transaction(function () use ($userId, $tanggal, $status, $alasan, $olehId, $olehNama) {
            $k = AttDayKoreksi::create([
                'user_id'     => $userId,
                'work_date'   => $tanggal,
                'status'      => $status,
                'status_lama' => self::statusSekarang($userId, $tanggal),
                'alasan'      => $alasan,
                'dibuat_oleh' => $olehId,
                'dibuat_nama' => $olehNama,
                'created_at'  => Carbon::now(),
            ]);

            self::hitungUlang($userId, $tanggal);

            return $k;
        });
    }

    /**
     * Cabut koreksi — DITANDAI, tidak dihapus. Sesudahnya hari itu kembali ke
     * apa pun yang dihitung rekonsiliasi, jadi hari itu perlu dihitung ulang
     * juga; tanpa itu status koreksian tertinggal di layar seperti hantu.
     */
    public static function cabut(AttDayKoreksi $k, ?string $olehId, ?string $olehNama, ?string $alasan = null): void
    {
        DB::transaction(function () use ($k, $olehId, $olehNama, $alasan) {
            $k->update([
                'dicabut_at'     => Carbon::now(),
                'dicabut_oleh'   => $olehId,
                'dicabut_nama'   => $olehNama,
                'dicabut_alasan' => $alasan,
            ]);

            self::hitungUlang($k->user_id, $k->work_date->toDateString());
        });
    }

    private static function hitungUlang(string $userId, string $tanggal): void
    {
        $hari = Carbon::parse($tanggal)->startOfDay();

        (new AttendanceReconciler())->reconcile($hari, $hari->copy(), $userId);
    }
}
