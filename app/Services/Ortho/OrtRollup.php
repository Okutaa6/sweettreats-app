<?php

namespace App\Services\Ortho;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Models\OrtTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Penghitung ulang kolom rollup di ort_case.
 *
 * ⚠ KELAS INI SATU-SATUNYA YANG BOLEH MENULIS KOLOM ROLLUP.
 *   owner_place_id · open_control_id · next_sch_date · next_is_proposal ·
 *   last_visit_date · last_visit_place_id · control_count · visit_count ·
 *   visit_count_180d · avg_gap_days · median_gap_days · branches_visited ·
 *   rollup_at
 *
 * Kolom itu turunan yang disimpan. Alasannya bukan kecepatan render, tapi
 * karena tiganya dipakai sebagai PENYARING — open_control_id IS NULL memilih
 * antrean, visit_count_180d < 2 memilih "Jarang kontrol", owner_place_id
 * memilih cabang. Menyaring pada nilai yang dihitung saat baca = full scan.
 *
 * Harganya: jalur tulis yang lupa memanggil refresh() membuat halaman BOHONG
 * TANPA ERROR. Karena itu `ort:rollup --verify` membandingkan cache dengan
 * hitungan sebenarnya.
 *
 * CATATAN visit_count_180d: kolom ini BASI SENDIRINYA karena waktu berjalan,
 * bukan karena ada yang menulis. Pasien bisa jatuh ke "Jarang kontrol" tanpa
 * satu baris pun berubah. Karena itu tarikan malam wajib memanggil
 * refreshSemua(), bukan cuma kasus yang tersentuh.
 */
class OrtRollup
{
    /** Hitung ulang satu kasus. Dipanggil sesudah SETIAP tulis ke ort_control. */
    public function refresh(string $caseId): void
    {
        $case = OrtCase::find($caseId);
        if (! $case) {
            return;
        }

        $controls = OrtControl::where('ort_case_id', $caseId)->orderBy('seq')->get();

        // Transfer sementara ("kontrol sekali saja di sana") harus memulangkan
        // pasien SENDIRI. Dijalankan sebelum rollup dihitung, karena ia
        // memindahkan place_id kontrol — dan owner_place_id diturunkan dari situ.
        $pulangKe = $this->pulangkanTransferSementara($caseId, $controls);
        if ($pulangKe !== null) {
            $controls = OrtControl::where('ort_case_id', $caseId)->orderBy('seq')->get();
        }

        $terbuka   = $controls->firstWhere(fn ($c) => $c->closed_at === null);
        $kunjungan = $controls->whereNotNull('visit_date')->sortBy('visit_date')->values();
        $terakhir  = $kunjungan->last();

        // Cabang penagih = cabang kontrol TERAKHIR (aturan Sales: "dihitung dari
        // terakhir dia perawatan"). Kalau belum ada kontrol sama sekali, jatuh
        // ke cabang pasang.
        $kontrolTerakhir = $controls->last();
        $owner = $terbuka->place_id
              ?? $kontrolTerakhir?->place_id
              ?? $case->install_place_id;

        // Transfer sementara yang baru jatuh tempo MENANG atas aturan di atas.
        // Tanpa ini, pasien yang dititipkan sekali kontrol tidak pernah pulang
        // selama belum ada jadwal berikutnya: kontrol terbuka tidak ada, jadi
        // owner jatuh ke cabang kontrol TERAKHIR — yaitu cabang titipan itu.
        if ($pulangKe !== null) {
            $owner = $pulangKe;
        }

        $batas180 = Carbon::today()->subDays(OrtCase::HILANG_HARI);

        $case->forceFill([
            'owner_place_id'      => $owner,
            'open_control_id'     => $terbuka?->id,
            'next_sch_date'       => $terbuka?->sch_date,
            'next_is_proposal'    => (bool) ($terbuka?->is_proposal ?? false),
            'last_visit_date'     => $terakhir?->visit_date,
            'last_visit_place_id' => $terakhir?->place_id,
            'control_count'       => $controls->count(),
            'visit_count'         => $kunjungan->count(),
            'visit_count_180d'    => $kunjungan->filter(
                                         fn ($c) => $c->visit_date->gte($batas180)
                                     )->count(),
            'avg_gap_days'        => $this->rataJarak($kunjungan),
            // Irama pasien. Rumusnya tinggal di OrtCase::irama() karena
            // OrtUsulan menghitung angka yang sama saat mengusulkan tanggal,
            // dan keduanya tidak boleh pernah berbeda.
            'median_gap_days'     => OrtCase::irama($kunjungan->pluck('visit_date'))['median'],
            'branches_visited'    => $this->cabangDidatangi($controls),
            'rollup_at'           => now(),
        ])->save();
    }

    /** Hitung ulang semua kasus aktif. Dipakai tarikan malam & `ort:rollup`. */
    /**
     * Pulangkan pasien dari transfer SEMENTARA.
     *
     * Saat Sales mencentang "kontrol sekali saja di sana", ort_transfer dicatat
     * dengan is_temporary=1 dan return_after_control_id = kontrol yang dititipkan.
     * Begitu kontrol itu DITUTUP, janjinya jatuh tempo: kontrol yang masih
     * terbuka dikembalikan ke cabang asal, dan returned_at distempel.
     *
     * ⚠ KENAPA DI SINI, BUKAN DI CONTROLLER. Kontrol bisa tertutup dari banyak
     *   arah — tombol "Tandai tidak hadir", tarikan malam ort:pull-visits, atau
     *   "Tandai Selesai". Menaruh logika ini di salah satunya berarti dua jalur
     *   lain lupa memulangkan pasien, dan tidak ada yang akan sadar: kolom
     *   cabangnya cuma salah, bukan error. refresh() dipanggil dari SEMUA jalur
     *   tulis, jadi ini satu-satunya tempat yang pasti terlewati semuanya.
     *
     * @return string|null cabang asal kalau ada janji yang JATUH TEMPO di
     *                      pemanggilan ini; null kalau tidak ada.
     */
    private function pulangkanTransferSementara(string $caseId, $controls): ?string
    {
        if (! Schema::hasTable('ort_transfer')) {
            return null;
        }

        $janji = OrtTransfer::where('ort_case_id', $caseId)
            ->where('is_temporary', 1)
            ->whereNull('returned_at')
            ->whereNotNull('return_after_control_id')
            ->get();

        if ($janji->isEmpty()) {
            return null;
        }

        $pulangKe = null;

        foreach ($janji as $t) {
            $dititipkan = $controls->firstWhere('id', $t->return_after_control_id);

            // Belum jatuh tempo: kontrolnya belum ditutup.
            if (! $dititipkan || $dititipkan->closed_at === null) {
                continue;
            }

            foreach ($controls as $c) {
                if ($c->closed_at === null && (string) $c->place_id !== (string) $t->from_place_id) {
                    $c->forceFill(['place_id' => $t->from_place_id])->save();
                }
            }

            $t->forceFill(['returned_at' => now()])->save();
            $pulangKe = (string) $t->from_place_id;
        }

        return $pulangKe;
    }

    public function refreshSemua(?string $placeId = null): int
    {
        $q = OrtCase::query()->where('status', OrtCase::STATUS_AKTIF);
        if ($placeId) {
            $q->where('owner_place_id', $placeId);
        }

        $n = 0;
        $q->orderBy('id')->chunkById(200, function ($cases) use (&$n) {
            foreach ($cases as $c) {
                $this->refresh($c->id);
                $n++;
            }
        });

        return $n;
    }

    /**
     * Selisih yang ditemukan antara kolom cache dan hitungan sebenarnya.
     * Dipakai `ort:rollup --verify`. Mengembalikan daftar masalah, bukan
     * memperbaikinya — perbaikan adalah keputusan sadar.
     */
    public function verifikasi(?string $placeId = null): array
    {
        $masalah = [];

        // Kasus yang punya LEBIH DARI SATU kontrol terbuka. MySQL tidak punya
        // UNIQUE parsial, jadi aturan "satu kontrol terbuka per kasus" cuma
        // dijaga service — ini jaring pengamannya.
        $ganda = DB::table('ort_control')
            ->select('ort_case_id', DB::raw('COUNT(*) AS n'))
            ->whereNull('closed_at')
            ->groupBy('ort_case_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($ganda as $g) {
            $masalah[] = ['kasus' => $g->ort_case_id, 'jenis' => 'kontrol terbuka ganda', 'nilai' => $g->n];
        }

        // Perawatan sudah ditutup, tapi jadwalnya masih menggantung terbuka.
        //
        // Dua jalur penutupan berperilaku BEDA dengan sengaja: "Tandai Selesai"
        // membatalkan jadwal yang tersisa, sedangkan penutupan otomatis di
        // tarikan malam (Lepas Bracket / paket Freedom) tidak menyentuhnya.
        // Selisih itu tidak terlihat di layar mana pun — daftar kerja menyaring
        // status kasus — jadi satu-satunya tempat ia bisa ketahuan adalah di
        // sini. Dilaporkan, BUKAN diperbaiki diam-diam: memilih perilaku yang
        // benar di antara keduanya itu keputusan pemilik produk.
        //
        // JOIN sesama ort_* saja — aman, satu keluarga dan satu collation.
        $gantung = DB::table('ort_control as c')
            ->join('ort_case as k', 'k.id', '=', 'c.ort_case_id')
            ->whereNull('c.closed_at')
            ->where('k.status', '<>', OrtCase::STATUS_AKTIF)
            ->distinct()
            ->get(['k.id AS kasus_id', 'k.status']);

        foreach ($gantung as $g) {
            $masalah[] = [
                'kasus' => $g->kasus_id,
                'jenis' => 'kasus ' . $g->status . ' masih punya jadwal terbuka',
                'nilai' => '—',
            ];
        }

        // Kolom cache yang tidak cocok dengan hitungan sebenarnya.
        $q = OrtCase::query()->where('status', OrtCase::STATUS_AKTIF);
        if ($placeId) {
            $q->where('owner_place_id', $placeId);
        }

        $q->orderBy('id')->chunkById(200, function ($cases) use (&$masalah) {
            foreach ($cases as $c) {
                $sebelum = $c->only([
                    'owner_place_id', 'open_control_id', 'control_count',
                    'visit_count', 'visit_count_180d',
                    // median_gap_days ikut diperiksa karena ia punya satu cara
                    // gagal yang sangat sunyi: sesudah SQL Tahap C dijalankan
                    // tapi `ort:rollup` TIDAK, kolomnya NULL untuk semua orang.
                    // Usulan jadwalnya tetap benar (OrtUsulan menghitung segar),
                    // jadi tidak ada yang rusak dan tidak ada galat — yang
                    // terjadi cuma layar diam-diam berhenti menampilkan irama
                    // dan berbunyi "belum cukup kunjungan" untuk semua pasien.
                    // --verify adalah satu-satunya tempat itu bisa ketahuan.
                    'median_gap_days',
                ]);

                $this->refresh($c->id);
                $sesudah = OrtCase::find($c->id)->only(array_keys($sebelum));

                foreach ($sebelum as $kolom => $nilai) {
                    if ((string) $nilai !== (string) $sesudah[$kolom]) {
                        $masalah[] = [
                            'kasus' => $c->id,
                            'jenis' => "cache {$kolom} melenceng",
                            'nilai' => "{$nilai} → {$sesudah[$kolom]}",
                        ];
                    }
                }
            }
        });

        return $masalah;
    }

    /** Rata-rata jarak antar kunjungan, dalam hari. NULL kalau kunjungan < 2. */
    private function rataJarak($kunjungan): ?int
    {
        if ($kunjungan->count() < 2) {
            return null;
        }

        $total = 0;
        for ($i = 1; $i < $kunjungan->count(); $i++) {
            $total += $kunjungan[$i - 1]->visit_date->diffInDays($kunjungan[$i]->visit_date);
        }

        return (int) round($total / ($kunjungan->count() - 1));
    }

    /**
     * CSV place_id cabang yang pernah didatangi, urut kemunculan. Disimpan
     * sebagai kolom supaya pil cabang di kartu tidak butuh kueri per baris.
     */
    private function cabangDidatangi($controls): ?string
    {
        $list = $controls->pluck('place_id')->filter()->unique()->values()->all();

        return empty($list) ? null : implode(',', $list);
    }
}
