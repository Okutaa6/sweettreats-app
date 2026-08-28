<?php

namespace App\Services\Omnicare;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query komisi dokter dari mirror Omnicare (`omnicare_commission`).
 *
 * Satu tempat untuk SEMUA angka halaman Komisi Dokter — HTML, CSV, dan halaman
 * cetak memakai builder yang sama, supaya tidak pernah ada dua versi kebenaran.
 *
 * Dua aturan yang tidak boleh dilanggar di kelas ini:
 *
 *  1. `commission_role = 'doctor'` difilter di lapisan SQL (lihat base()).
 *     Baris asisten DIBUANG di query, bukan disembunyikan di view — halaman uang
 *     tidak boleh mengandalkan @if di Blade.
 *
 *  2. Angka PASIEN & KUNJUNGAN tidak pernah dijumlahkan antar cabang. Satu pasien
 *     bisa datang ke dua cabang, jadi totalnya selalu dihitung ulang dari baris
 *     (COUNT DISTINCT atas seluruh scope), bukan SUM kolom per cabang.
 */
class KomisiQuery
{
    /**
     * Metrik dasar — ditulis sekali, dipakai di banyak agregasi.
     *
     * NULLIF(p_id,'') penting: COUNT(DISTINCT) mengabaikan NULL tapi menghitung
     * '' sebagai SATU bucket. Tanpa ini, semua baris tanpa p_id (transaksi OTC)
     * akan terhitung "1 pasien". Barisnya sendiri TIDAK boleh dibuang dari query
     * — komisinya nyata dan harus ikut total.
     */
    private const M_PASIEN    = "COUNT(DISTINCT NULLIF(oc.p_id,''))";
    private const M_KUNJUNGAN = "COUNT(DISTINCT CASE WHEN oc.p_id IS NOT NULL AND oc.p_id <> '' THEN CONCAT(oc.p_id,'|',oc.trx_date) END)";
    private const M_TINDAKAN  = 'COALESCE(SUM(oc.qty_sold),0)';
    private const M_OMZET     = 'COALESCE(SUM(oc.net_sell_price),0)';
    private const M_KOMISI    = 'COALESCE(SUM(oc.commission_amount),0)';

    /** Kolom yang boleh dipakai ORDER BY — whitelist, jangan pernah dari request. */
    private const SORT_PERAWATAN = [
        'kode'   => 'oc.item_code',
        'nama'   => 'item_name',
        'jumlah' => 'jumlah',
        'omzet'  => 'omzet',
        'komisi' => 'komisi',
    ];

    private const SORT_RINCIAN = [
        'tanggal'  => 'oc.trx_date',
        'pasien'   => 'oc.p_name',
        'invoice'  => 'oc.invoice_num',
        'tindakan' => 'oc.item_name',
        'omzet'    => 'oc.net_sell_price',
        'komisi'   => 'oc.commission_amount',
        'cabang'   => 'oc.branch_name',
    ];

    // ────────────────────────────────────────────────────────────────
    // Scope dasar
    // ────────────────────────────────────────────────────────────────

    /** Semua query halaman ini berangkat dari sini. */
    private function base(string $start, string $end, ?string $placeId)
    {
        $q = DB::table('omnicare_commission as oc')
            ->where('oc.commission_role', 'doctor')
            ->whereBetween('oc.trx_date', [$start, $end]);

        if ($placeId) {
            $q->where('oc.place_id', $placeId);
        }

        return $q;
    }

    private function metrikDasar(): string
    {
        return implode(', ', [
            self::M_KOMISI . ' AS komisi',
            self::M_PASIEN . ' AS pasien',
            self::M_KUNJUNGAN . ' AS kunjungan',
            self::M_TINDAKAN . ' AS tindakan',
            self::M_OMZET . ' AS omzet',
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Ringkasan
    // ────────────────────────────────────────────────────────────────

    /**
     * Satu baris agregat untuk kartu ringkasan. Dipanggil dua kali: periode
     * aktif dan periode pembanding.
     *
     * Ini juga satu-satunya sumber angka PASIEN total — jangan pernah
     * menjumlahkan kolom pasien dari tabel dokter atau tabel per cabang.
     */
    public function ringkas(string $start, string $end, ?string $placeId, ?string $docId = null): object
    {
        $row = $this->base($start, $end, $placeId)
            ->when($docId, fn ($q) => $q->where('oc.doc_id', $docId))
            ->selectRaw($this->metrikDasar() . ", COUNT(DISTINCT NULLIF(oc.doc_id,'')) AS n_dokter")
            ->first();

        // Periode kosong tetap harus mengembalikan objek, bukan null — view
        // memanggil propertinya tanpa guard.
        return $row ?: (object) ['komisi' => 0, 'pasien' => 0, 'kunjungan' => 0,
                                 'tindakan' => 0, 'omzet' => 0, 'n_dokter' => 0];
    }

    // ────────────────────────────────────────────────────────────────
    // Layar 1 — daftar dokter
    // ────────────────────────────────────────────────────────────────

    /**
     * Agregat per dokter. SENGAJA TANPA JOIN ke raw_doctor.
     *
     * LEFT JOIN akan fan-out kalau ada dua baris raw_doctor dengan
     * omnicare_doc_id sama → SUM(commission_amount) berlipat. Di halaman uang
     * risiko itu tidak boleh diambil; nama & status aktif diambil terpisah
     * lewat masterDokter() lalu digabung di PHP.
     *
     * Daftar dokter digerakkan dari DATA KOMISI (GROUP BY doc_id), bukan dari
     * master — dokter yang omnicare_doc_id-nya belum ter-mapping tetap muncul
     * dengan namanya sendiri, tidak pernah salah tampil Rp 0.
     */
    public function perDokter(string $start, string $end, ?string $placeId): Collection
    {
        return $this->base($start, $end, $placeId)
            ->selectRaw(implode(', ', [
                'oc.doc_id',
                'MAX(oc.doctor) AS doctor_fallback',
                $this->metrikDasar(),
                'COUNT(DISTINCT oc.place_id) AS n_cabang',
                "GROUP_CONCAT(DISTINCT oc.branch_name ORDER BY oc.branch_name SEPARATOR ' · ') AS cabang_list",
            ]))
            ->groupBy('oc.doc_id')
            ->orderByDesc('komisi')
            ->get();
    }

    /** Komisi per dokter di periode pembanding — untuk kolom "VS PERIODE LALU". */
    public function komisiPerDokter(string $start, string $end, ?string $placeId): Collection
    {
        return $this->base($start, $end, $placeId)
            ->selectRaw('oc.doc_id, ' . self::M_KOMISI . ' AS komisi')
            ->groupBy('oc.doc_id')
            ->pluck('komisi', 'doc_id');
    }

    /**
     * Master dokter untuk sekumpulan doc_id, ter-key by omnicare_doc_id.
     * doc_id yang tidak ada di hasil = belum ter-mapping ke raw_doctor.
     */
    public function masterDokter(array $docIds): Collection
    {
        $docIds = array_values(array_filter($docIds));
        if (! $docIds) {
            return collect();
        }

        return DB::table('raw_doctor')
            ->whereIn('omnicare_doc_id', $docIds)
            ->get(['omnicare_doc_id', 'name', 'photo', 'is_active'])
            ->keyBy('omnicare_doc_id');
    }

    /**
     * Beban komisi per cabang untuk SEMUA dokter sekaligus — dipakai dropdown
     * di dalam baris dokter. Satu query grouped, bukan N+1 per baris.
     *
     * @return Collection<string,Collection> ter-group by doc_id
     */
    public function perDokterCabang(string $start, string $end, ?string $placeId): Collection
    {
        return $this->base($start, $end, $placeId)
            ->selectRaw(implode(', ', [
                'oc.doc_id',
                'oc.place_id',
                'MAX(oc.branch_name) AS branch_name',
                $this->metrikDasar(),
            ]))
            ->groupBy('oc.doc_id', 'oc.place_id')
            ->orderByDesc('komisi')
            ->get()
            ->groupBy('doc_id');
    }

    // ────────────────────────────────────────────────────────────────
    // Layar 2 — tab Per Perawatan
    // ────────────────────────────────────────────────────────────────

    /**
     * Agregat per kode tindakan + deteksi skema komisi.
     *
     * n_skema > 1 berarti tindakan yang sama dihitung dengan cara berbeda di
     * periode/cabang ini (biasanya rate-nya beda antar cabang) — kolom SKEMA
     * harus menampilkan rentang, bukan satu angka yang menyesatkan.
     */
    public function perPerawatan(string $start, string $end, ?string $placeId, string $docId,
                                 string $sort = 'komisi', string $dir = 'desc'): Collection
    {
        $kolom = self::SORT_PERAWATAN[$sort] ?? 'komisi';

        return $this->base($start, $end, $placeId)
            ->where('oc.doc_id', $docId)
            ->selectRaw(implode(', ', [
                'oc.item_code',
                'MAX(oc.item_name) AS item_name',
                self::M_TINDAKAN . ' AS jumlah',
                self::M_OMZET . ' AS omzet',
                self::M_KOMISI . ' AS komisi',
                "COUNT(DISTINCT CONCAT(COALESCE(oc.commission_type,''),':',oc.commission_rate)) AS n_skema",
                "MIN(CASE WHEN oc.commission_type = '%' THEN oc.commission_rate END) AS pct_min",
                "MAX(CASE WHEN oc.commission_type = '%' THEN oc.commission_rate END) AS pct_max",
                "MIN(CASE WHEN COALESCE(oc.commission_type,'') = '' THEN oc.commission_rate END) AS flat_min",
                "MAX(CASE WHEN COALESCE(oc.commission_type,'') = '' THEN oc.commission_rate END) AS flat_max",
            ]))
            ->groupBy('oc.item_code')
            ->orderBy($kolom, $dir === 'asc' ? 'asc' : 'desc')
            ->get();
    }

    /**
     * Rincian skema per cabang — HANYA untuk kode yang skemanya beragam.
     * Isinya jadi tooltip ikon peringatan di kolom SKEMA.
     */
    public function skemaDetail(string $start, string $end, ?string $placeId, string $docId, array $codes): Collection
    {
        if (! $codes) {
            return collect();
        }

        return $this->base($start, $end, $placeId)
            ->where('oc.doc_id', $docId)
            ->whereIn('oc.item_code', $codes)
            ->selectRaw("oc.item_code, MAX(oc.branch_name) AS branch_name,
                         COALESCE(oc.commission_type,'') AS ctype, oc.commission_rate,
                         COUNT(*) AS n_baris")
            ->groupBy('oc.item_code', 'oc.place_id', 'ctype', 'oc.commission_rate')
            ->orderBy('oc.item_code')
            ->orderBy('branch_name')
            ->get()
            ->groupBy('item_code');
    }

    // ────────────────────────────────────────────────────────────────
    // Layar 2 — tab Tren Bulanan
    // ────────────────────────────────────────────────────────────────

    /**
     * Rentang tanggal yang benar-benar ada datanya di tabel komisi.
     *
     * Penggerak tren adaptif: kalau backfill komisi belum sampai 12 bulan ke
     * belakang, jangan menggambar bar kosong — Finance akan mengira dokternya
     * tidak dapat komisi, padahal datanya yang belum masuk.
     */
    public function cakupan(): object
    {
        $row = DB::table('omnicare_commission')
            ->where('commission_role', 'doctor')
            ->selectRaw('MIN(trx_date) AS first_date, MAX(trx_date) AS last_date')
            ->first();

        return $row ?: (object) ['first_date' => null, 'last_date' => null];
    }

    /** Agregat bulanan satu dokter, ter-key 'YYYY-MM'. */
    public function tren(string $winStart, string $winEnd, ?string $placeId, string $docId): Collection
    {
        return $this->base($winStart, $winEnd, $placeId)
            ->where('oc.doc_id', $docId)
            ->selectRaw("DATE_FORMAT(oc.trx_date,'%Y-%m') AS ym, " . $this->metrikDasar())
            ->groupByRaw("DATE_FORMAT(oc.trx_date,'%Y-%m')")
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');
    }

    // ────────────────────────────────────────────────────────────────
    // Layar 2 — tab Rincian
    // ────────────────────────────────────────────────────────────────

    /**
     * Baris tindakan mentah — alat pembuktian ke dokter.
     *
     * Query builder dikembalikan (belum dieksekusi) supaya pemanggil bisa
     * paginate() untuk layar DAN sum() untuk total seluruh hasil filter, tanpa
     * menulis ulang filternya di dua tempat.
     *
     * @param  array{code?:string,place?:string,q?:string,sort?:string,dir?:string}  $f
     */
    public function rincian(string $start, string $end, ?string $placeId, string $docId, array $f = [])
    {
        $kolom = self::SORT_RINCIAN[$f['sort'] ?? 'tanggal'] ?? 'oc.trx_date';
        $arah  = ($f['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->base($start, $end, $placeId)
            ->where('oc.doc_id', $docId)
            ->when($f['code'] ?? null, fn ($q, $v) => $q->where('oc.item_code', $v))
            ->when($f['place'] ?? null, fn ($q, $v) => $q->where('oc.place_id', $v))
            ->when($f['q'] ?? null, function ($q, $v) {
                $like = '%' . addcslashes($v, '%_\\') . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('oc.p_name', 'like', $like)
                      ->orWhere('oc.item_name', 'like', $like)
                      ->orWhere('oc.invoice_num', 'like', $like)
                      ->orWhere('oc.item_code', 'like', $like);
                });
            })
            ->select('oc.id', 'oc.trx_date', 'oc.p_id', 'oc.p_name', 'oc.invoice_num',
                     'oc.item_code', 'oc.item_name', 'oc.qty_sold', 'oc.net_sell_price',
                     'oc.commission_type', 'oc.commission_rate', 'oc.commission_amount',
                     'oc.branch_name')
            ->orderBy($kolom, $arah)
            // Tiebreak WAJIB. Tanpa ini, mengurutkan kolom yang banyak nilai
            // kembarnya (omzet, tanggal) membuat baris muncul dua kali atau
            // hilang saat pindah halaman — urutan MySQL tidak dijamin stabil.
            ->orderBy('oc.id');
    }

    // ────────────────────────────────────────────────────────────────
    // Konteks & kejujuran angka
    // ────────────────────────────────────────────────────────────────

    /**
     * Status sinkronisasi terakhir endpoint komisi.
     *
     * Dibaca dari omnicare_sync_log (punya index endpoint+started_at), BUKAN
     * MAX(last_synced_at) di omnicare_commission yang harus men-scan puluhan
     * ribu baris tanpa index.
     */
    public function statusSinkron(): ?object
    {
        return DB::table('omnicare_sync_log')
            ->where('endpoint', '/get/commission')
            ->whereIn('status', ['success', 'partial'])
            ->orderByDesc('started_at')
            ->first(['started_at', 'finished_at', 'status', 'records_count']);
    }

    /**
     * Total komisi ASISTEN pada scope yang sama.
     *
     * Bukan untuk ditampilkan sebagai data, tapi untuk catatan kaki: jurnal
     * "Hutang Fee Dokter" di Accurate menjumlahkan SEMUA peran, jadi angkanya
     * selalu lebih besar dari halaman ini. Menyebut selisihnya dengan angka
     * konkret jauh lebih menolong daripada peringatan abstrak.
     */
    public function komisiAsisten(string $start, string $end, ?string $placeId): float
    {
        $q = DB::table('omnicare_commission as oc')
            ->where('oc.commission_role', 'assistant')
            ->whereBetween('oc.trx_date', [$start, $end]);

        if ($placeId) {
            $q->where('oc.place_id', $placeId);
        }

        return (float) $q->sum('oc.commission_amount');
    }

    // ────────────────────────────────────────────────────────────────
    // Helper
    // ────────────────────────────────────────────────────────────────

    /**
     * Delta terhadap periode pembanding.
     *
     * Aturan dari design handoff: 0 → x ditulis "baru" (bukan naik tak hingga),
     * selisih di bawah 0,05% ditulis "setara" (bukan "0,0%" yang terbaca seperti
     * stagnan padahal cuma efek pembulatan).
     *
     * @return array{txt:string,tone:string,sub:string}
     */
    public static function delta(float $now, float $prev, string $label): array
    {
        if ($prev <= 0) {
            return ['txt' => $now > 0 ? 'baru' : '—', 'tone' => 'netral', 'sub' => $label];
        }

        $p = ($now - $prev) / $prev * 100;

        if (abs($p) < 0.05) {
            return ['txt' => 'setara', 'tone' => 'netral', 'sub' => $label];
        }

        return [
            'txt'  => ($p > 0 ? '▲ ' : '▼ ') . number_format(abs($p), 1, ',', '.') . '%',
            'tone' => $p > 0 ? 'naik' : 'turun',
            'sub'  => $label,
        ];
    }
}
