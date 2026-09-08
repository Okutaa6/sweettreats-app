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
    /**
     * Status yang ikut diperiksa detektor chip. `approved` = uangnya sudah
     * mengalir; `pending_*` = masih bisa dicegah, dan itu justru yang paling
     * murah. `pending_l2` diikutkan walau lembur hari ini hanya satu lapis —
     * kalau suatu saat rantainya diperpanjang, pagar ini tak ikut berlubang
     * diam-diam. (Pola yang sudah dua kali menggigit: sesuatu yang baru lupa
     * didaftarkan di sebuah peta, lalu gagal dengan diam.)
     */
    private const STATUS_DIPERIKSA = ['approved', 'pending_l1', 'pending_l2'];

    /** Kunci ambang DODO di `sys_setting` — diubah Finance dari layar Lembar Gaji. */
    public const KUNCI_AMBANG = 'dodo_ambang_menit';

    /** 7 jam (keputusan Ekky 2026-09-05). Dipakai kalau setelannya belum pernah diisi. */
    public const AMBANG_BAWAAN = 420;

    /**
     * Ambang DODO dalam menit — SATU angka yang berperan ganda:
     *
     *   * **batas atas**  — lebih dari ambang, sisanya jadi lembur biasa;
     *   * **batas bawah** — kurang dari ambang, TIDAK ada DODO sama sekali.
     *
     * Contoh dari Ekky (2026-09-05), ambang 7 jam:
     * 10 jam -> 7 DODO + 3 biasa · 2 jam -> 0 DODO + 2 biasa · 7 jam -> 7 DODO.
     *
     * Alasannya DODO itu tarif SEHARI, bukan tarif per jam: masuk di hari libur
     * dibayar satu hari penuh, dan setengah hari tidak membeli setengah hari.
     * Karena itu "kurang dari ambang" bukan pembulatan ke bawah — memang bukan
     * DODO. Yang tak jadi DODO tidak menguap: ia tetap terbayar sebagai lembur
     * biasa, sebab `biasa = total - dodo`.
     *
     * Disimpan di setelan, BUKAN ditanam di kode (Ekky setuju): angka ini
     * menentukan uang dan akan berubah tanpa deploy.
     */
    public static function ambangMenit(): int
    {
        $v = \App\Models\Setting::get(self::KUNCI_AMBANG);

        // Setelan kosong / bukan angka / negatif -> kembali ke bawaan. Ambang
        // yang rusak tak boleh diam-diam berarti "nol": nol punya arti sendiri
        // di bawah (pagar dimatikan), dan mengacaukan keduanya menggeser uang.
        return (is_numeric($v) && (int) $v >= 0) ? (int) $v : self::AMBANG_BAWAAN;
    }

    /**
     * Terapkan ambang pada menit DODO SATU HARI.
     *
     * ⚠️ Per HARI, bukan per periode. DODO adalah tarif harian: dua hari libur
     * yang masing-masing penuh = dua hari DODO. Kalau ambangnya dikenakan pada
     * jumlah sebulan, hari libur kedua dan seterusnya hilang tanpa jejak.
     *
     * Ambang 0 = pagar dimatikan (klaim diambil apa adanya) — jalan keluar
     * kalau kebijakannya berubah, tanpa perlu deploy.
     */
    public static function terapkanAmbang(int $menit, ?int $ambang = null): int
    {
        $ambang ??= self::ambangMenit();
        if ($ambang <= 0) return $menit;

        return $menit >= $ambang ? $ambang : 0;
    }

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
     * Menit DODO per orang dalam rentang — SESUDAH ambang.
     *
     * ⚠️ Dihitung dari peta per-hari, BUKAN dari SUM() satu kueri. Ambangnya
     * berlaku per hari (lihat terapkanAmbang), jadi menjumlah dulu lalu
     * mengambangkan akan memberi jawaban lain: dua hari libur penuh 7+7 jam
     * harus jadi 14 jam DODO, bukan 7. Ini juga yang menjaga jumlah sel di
     * layar Kehadiran selalu sama dengan angka orang itu di Lembar Gaji.
     *
     * @return array<string,int> user_id => menit
     */
    public static function menitPerOrang(string $from, string $to): array
    {
        $out = [];
        foreach (self::menitPerHari($from, $to) as $uid => $perTanggal) {
            $j = array_sum($perTanggal);
            if ($j > 0) $out[$uid] = $j;
        }

        return $out;
    }

    /**
     * Menit DODO per orang PER TANGGAL — buat penanda di kalender dan angka di
     * baris. Dipakai layar Kehadiran, yang memang bekerja per sel tanggal.
     *
     * Ambang sudah diterapkan di sini, sekali, supaya tak ada pemanggil yang
     * lupa menerapkannya. Hari yang tak mencapai ambang TIDAK dimasukkan ke
     * peta — nilainya nol, dan pemanggil membacanya lewat `?? 0`.
     *
     * @return array<string,array<string,int>> user_id => ['Y-m-d' => menit]
     */
    public static function menitPerHari(string $from, string $to): array
    {
        $ambang = self::ambangMenit();

        $peta = [];
        foreach (self::dasar($from, $to)
                    ->groupBy('r.user_id', 'r.tanggal_from')
                    ->select('r.user_id as uid', 'r.tanggal_from as tgl', DB::raw('SUM(r.claimed_menit) as menit'))
                    ->get() as $b) {
            $menit = self::terapkanAmbang((int) $b->menit, $ambang);
            if ($menit <= 0) continue;

            $peta[(string) $b->uid][substr((string) $b->tgl, 0, 10)] = $menit;
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
     * ── Kenapa yang PENDING ikut diperiksa ──
     * Sampai 24 Agu penyaringnya cuma `status = 'approved'`, jadi peringatannya
     * datang SESUDAH keputusan diambil: menolong Finance membatalkan, tapi tak
     * menolong penyetuju menghindari. Dua kejadian nyata dalam 48 jam
     * membuktikan itu bukan teori — req#172 (Minggu, chip `jam`, akan terbayar
     * tarif biasa) dan req#162 (Sabtu hari kerja, chip `tambah_hari`, akan
     * terbayar tarif libur) — keduanya masih `pending_l1` dan karena itu tak
     * terlihat sama sekali. Menangkap sebelum ACC jauh lebih murah daripada
     * mencabut ACC yang sudah jadi.
     *
     * @return array<int,array{user_id:string,nama:string,tanggal:string,menit:int,sub_tipe:string,status:string,libur:bool,masalah:string}>
     */
    public static function chipMencurigakan(string $from, string $to): array
    {
        $rows = DB::table('att_request as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.tipe', 'lembur')
            ->whereIn('r.status', self::STATUS_DIPERIKSA)
            ->whereBetween('r.tanggal_from', [$from, $to])
            ->orderBy('r.tanggal_from')
            ->get(['r.user_id', 'r.tanggal_from', 'r.claimed_menit', 'r.sub_tipe', 'r.status', 'u.nama']);

        if ($rows->isEmpty()) return [];

        $libur = self::liburUntukBaris($rows);

        $hasil = [];
        foreach ($rows as $r) {
            $tgl     = substr((string) $r->tanggal_from, 0, 10);
            $isLibur = $libur[$r->user_id . '|' . $tgl] ?? null;
            $masalah = self::masalahChip($isLibur, $r->sub_tipe);
            if ($masalah === null) continue;

            $hasil[] = [
                'user_id'  => (string) $r->user_id,
                'nama'     => (string) $r->nama,
                'tanggal'  => $tgl,
                'menit'    => (int) $r->claimed_menit,
                'sub_tipe' => (string) ($r->sub_tipe ?? '-'),
                'status'   => (string) $r->status,
                'libur'    => $isLibur,
                'masalah'  => $masalah,
            ];
        }

        return $hasil;
    }

    /**
     * Peta "chip ini bermasalah atau tidak" untuk sekumpulan pengajuan yang
     * sudah ada di tangan — dipakai layar persetujuan, yang memeriksa kartu di
     * inbox, bukan rentang tanggal.
     *
     * Statusnya sengaja TIDAK disaring di sini: pemanggilnya yang menentukan
     * baris mana yang relevan (inbox hanya berisi yang menunggu keputusan).
     *
     * @param  iterable  $pengajuan  baris dengan id, user_id, tanggal_from, sub_tipe
     * @return array<string,array{libur:bool,masalah:string}>  id => temuan
     */
    public static function petaChip(iterable $pengajuan): array
    {
        $rows = collect($pengajuan)->filter(fn ($r) => ($r->tipe ?? 'lembur') === 'lembur')->values();
        if ($rows->isEmpty()) return [];

        $libur = self::liburUntukBaris($rows);

        $hasil = [];
        foreach ($rows as $r) {
            $tgl     = self::tanggal($r->tanggal_from);
            $isLibur = $libur[$r->user_id . '|' . $tgl] ?? null;
            $masalah = self::masalahChip($isLibur, $r->sub_tipe);
            if ($masalah === null) continue;

            $hasil[(string) $r->id] = ['libur' => $isLibur, 'masalah' => $masalah];
        }

        return $hasil;
    }

    /**
     * SATU-SATUNYA tempat "chip cocok atau tidak" diputuskan. Layar Lembar Gaji
     * dan layar persetujuan memakai yang sama persis; kalau suatu hari
     * aturannya berubah, dua-duanya ikut berubah bersamaan.
     *
     * @param  ?bool  $isLibur  null = aturan kerjanya tak bisa dijawab
     * @return ?string  null kalau tak ada masalah
     */
    public static function masalahChip(?bool $isLibur, ?string $subTipe): ?string
    {
        // Orang ber-aturan `Fleksibel` tak punya baris hari sama sekali, jadi
        // "hari itu libur atau tidak" memang tak punya jawaban. Menuduh
        // berdasarkan ketidaktahuan = peringatan yang lama-lama diabaikan.
        if ($isLibur === null) return null;

        $dodo = ($subTipe === 'tambah_hari');
        if ($dodo === $isLibur) return null;

        return $isLibur
            ? 'Hari libur, tapi diajukan sebagai lembur biasa — kemungkinan DODO yang terbayar tarif biasa.'
            : 'Ditandai "masuk hari libur", padahal hari itu hari kerja biasa.';
    }

    /**
     * "Hari ini libur atau tidak" ditanyakan ke rekonsiliasi, TIDAK dihitung
     * ulang di sini — definisinya menyangkut aturan mingguan, kalender libur,
     * DAN cabang tempat orangnya bertugas hari itu. Menyalinnya berarti dua
     * definisi yang lambat laun berselisih; jalan pintas `att_day.status =
     * 'libur'` sudah terbukti memberi 2 positif palsu (reconciler menulis
     * `hadir` ketika orang bekerja di hari liburnya).
     *
     * @return array<string,bool>  "userId|Y-m-d" => libur
     */
    private static function liburUntukBaris($rows): array
    {
        $pasangan = collect($rows)
            ->map(fn ($r) => [(string) $r->user_id, self::tanggal($r->tanggal_from)])
            ->all();

        return (new AttendanceReconciler())->liburUntuk($pasangan);
    }

    /** `tanggal_from` datang sebagai string (DB::table) atau Carbon (model). */
    private static function tanggal($v): string
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : substr((string) $v, 0, 10);
    }
}
