<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * DODO — lembur yang dikerjakan pada HARI LIBUR orang itu sendiri.
 *
 * Tarifnya berbeda dari lembur biasa, jadi Finance harus bisa melihat keduanya
 * terpisah. Kelas ini satu-satunya tempat "mana yang DODO" diputuskan; Lembar
 * Gaji dan layar Kehadiran sama-sama memanggilnya. Dua layar yang menghitung
 * hal yang sama sendiri-sendiri adalah cara paling umum angka mulai berselisih.
 *
 * ── Apa yang dihitung DODO ──
 * Pengajuan lembur DISETUJUI yang ditandai chip **"Masuk hari libur"**
 * (`att_request.sub_tipe = 'tambah_hari'`). Itu deklarasi karyawan yang
 * disetujui atasannya — dan bayaran harus mengikuti apa yang disetujui, bukan
 * tafsiran ulang sistem di belakang.
 *
 * Sengaja BUKAN "tanggalnya jatuh di hari libur": orang ber-aturan fleksibel
 * (staf kantor, Anisa, Irma) tak punya hari libur mingguan sama sekali di data
 * — aturan fleksibel memang tak menyimpan baris hari — jadi patokan kalender
 * diam-diam melewatkan mereka. Ketidakcocokan antara deklarasi dan kalender
 * TIDAK didiamkan: lihat chipMencurigakan().
 *
 * ── Kenapa jamnya tak boleh dijumlahkan dengan angka lembur yang lama ──
 * `att_day.lembur_menit_disetujui` sudah berisi SEMUA lembur yang di-ACC,
 * termasuk yang DODO — reconciler tak memeriksa sub_tipe saat menjumlahkannya.
 * Jadi DODO itu **pecahan dari angka lama, bukan tambahan**. Aturan pakainya:
 *
 *     lembur biasa = (total att_day) − (DODO dari kelas ini)
 *
 * Ditulis sebagai pengurangan, bukan dua penjumlahan terpisah, supaya
 * "biasa + DODO" SELALU sama persis dengan angka yang selama ini dipakai.
 * Kalau suatu hari tidak sama, itu tanda ada yang bocor — bukan pembulatan.
 *
 * ── Kenapa di-JOIN ke att_day ──
 * Yang dihitung hanya klaim pada tanggal yang PUNYA baris att_day, karena
 * itulah yang ikut terjumlah di totalnya. Tanpa join, sebuah klaim di tanggal
 * yang belum/tak terekonsiliasi akan membuat "biasa" jadi minus.
 */
class LemburDodo
{
    /** Reconciler menghitung lembur HANYA pada tanggal mulainya (bukan sepanjang rentang). */
    private static function dasar(string $from, string $to)
    {
        return DB::table('att_request as r')
            ->join('att_day as d', function ($j) {
                $j->on('d.user_id', '=', 'r.user_id')->on('d.work_date', '=', 'r.tanggal_from');
            })
            ->where('r.tipe', 'lembur')
            ->where('r.status', 'approved')
            ->where('r.sub_tipe', 'tambah_hari')
            ->whereBetween('r.tanggal_from', [$from, $to]);
    }

    /**
     * Menit DODO per orang dalam rentang.
     *
     * @return array<string,int> user_id => menit
     */
    public static function menitPerOrang(string $from, string $to): array
    {
        return self::dasar($from, $to)
            ->groupBy('r.user_id')
            ->select('r.user_id as uid', DB::raw('SUM(r.claimed_menit) as menit'))
            ->pluck('menit', 'uid')
            ->map(fn ($m) => (int) $m)->all();
    }

    /**
     * Menit DODO per orang PER TANGGAL — buat penanda di kalender dan angka di
     * baris. Dipakai layar Kehadiran, yang memang bekerja per sel tanggal.
     *
     * @return array<string,array<string,int>> user_id => ['Y-m-d' => menit]
     */
    public static function menitPerHari(string $from, string $to): array
    {
        $peta = [];
        foreach (self::dasar($from, $to)
                    ->groupBy('r.user_id', 'r.tanggal_from')
                    ->select('r.user_id as uid', 'r.tanggal_from as tgl', DB::raw('SUM(r.claimed_menit) as menit'))
                    ->get() as $b) {
            $peta[(string) $b->uid][substr((string) $b->tgl, 0, 10)] = (int) $b->menit;
        }

        return $peta;
    }

    /**
     * Chip yang kemungkinan salah pilih — dan kenapa ini penting.
     *
     * Reconciler menerima lembur APA PUN di hari libur; ia tak memeriksa
     * chipnya. Artinya orang bisa masuk di hari liburnya lalu memilih "Lembur
     * biasa", dan sistem menerimanya tanpa protes — hasilnya DODO yang dibayar
     * tarif lembur biasa, tanpa ada yang tahu. Kebalikannya juga terjadi:
     * menandai "Masuk hari libur" pada hari kerja biasa = dibayar tarif DODO.
     *
     * Keduanya salah bayar, dan dua-duanya diam. Jadi ketidakcocokan antara
     * DEKLARASI (chip) dan KALENDER (hari libur orang itu) ditampilkan, bukan
     * diselesaikan diam-diam oleh sistem — yang berhak membetulkan itu manusia
     * yang tahu duduk perkaranya, sebelum gaji dibayar.
     *
     * @return array<int,array{user_id:string,nama:string,tanggal:string,menit:int,sub_tipe:string,libur:bool,masalah:string}>
     */
    public static function chipMencurigakan(string $from, string $to): array
    {
        $rows = DB::table('att_request as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.tipe', 'lembur')
            ->where('r.status', 'approved')
            ->whereBetween('r.tanggal_from', [$from, $to])
            ->orderBy('r.tanggal_from')
            ->get(['r.user_id', 'r.tanggal_from', 'r.claimed_menit', 'r.sub_tipe', 'u.nama']);

        if ($rows->isEmpty()) return [];

        // "Hari ini libur atau tidak" ditanyakan ke rekonsiliasi, bukan dihitung
        // ulang di sini — definisinya menyangkut aturan mingguan, kalender libur,
        // DAN cabang tempat orangnya bertugas hari itu. Menyalinnya ke sini
        // berarti dua definisi yang lambat laun berselisih.
        $pasangan = $rows->map(fn ($r) => [(string) $r->user_id, substr((string) $r->tanggal_from, 0, 10)])->all();
        $libur    = (new AttendanceReconciler())->liburUntuk($pasangan);

        $hasil = [];
        foreach ($rows as $r) {
            $tgl  = substr((string) $r->tanggal_from, 0, 10);
            $isLibur = $libur[$r->user_id . '|' . $tgl] ?? null;
            if ($isLibur === null) continue;              // aturannya tak diketahui → jangan menuduh

            $dodo = ($r->sub_tipe === 'tambah_hari');
            if ($dodo === $isLibur) continue;             // cocok

            $hasil[] = [
                'user_id'  => (string) $r->user_id,
                'nama'     => (string) $r->nama,
                'tanggal'  => $tgl,
                'menit'    => (int) $r->claimed_menit,
                'sub_tipe' => (string) ($r->sub_tipe ?? '-'),
                'libur'    => $isLibur,
                'masalah'  => $isLibur
                    ? 'Hari libur, tapi diajukan sebagai lembur biasa — kemungkinan DODO yang terbayar tarif biasa.'
                    : 'Ditandai "masuk hari libur", padahal hari itu hari kerja biasa.',
            ];
        }

        return $hasil;
    }
}
