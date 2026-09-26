<?php

namespace App\Services;

use App\Models\RawAffiliate;
use App\Models\RawCustomer;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tautan KOC → rekam pasien, dan ringkasan kontrol yang dibaca lewatnya.
 *
 * `raw_affiliate` tak punya penghubung ke pasien, dan menjodohkan lewat nomor
 * HP saat layar dibuka TERBUKTI tak deterministik: HP 087880405047 cocok ke
 * DUA pasien (Dewi Santi, lahir sama, nama mirip — satu orang dua rekam), lalu
 * `first()` tanpa `orderBy` membaca 23 kontrol di satu kueri dan 0 di kueri
 * berikutnya. Angka yang berubah sendiri lebih buruk daripada tak ada angka.
 *
 * Karena itu tautannya DISIMPAN di `raw_affiliate.raw_customer_id`:
 *   - diisi otomatis HANYA kalau kandidatnya tunggal (tautkanOtomatis);
 *   - kalau ganda atau kosong → dibiarkan NULL, layar bilang "perlu dipilih",
 *     dan manusia memilih (tautkan).
 * ⛔ Jangan pernah menjodohkan di jalur baca layar.
 */
class KocPasien
{
    public const TERTAUT = 'tertaut';
    public const SUDAH   = 'sudah';
    public const GANDA   = 'ganda';
    public const KOSONG  = 'kosong';

    /** Digit pembanding: EKOR nomor sepanjang ini, sesudah tanda baca dibuang. */
    public const PANJANG_EKOR = 10;

    /**
     * Pasien yang nomornya "sama" dengan nomor KOC — langsung di raw_customer,
     * atau lewat jangkar raw_contact (model 1-nomor-banyak-pasien).
     * Urutannya dipaksa (nama, id) supaya daftar pilihan selalu sama.
     *
     * Dicocokkan lewat 10 DIGIT TERAKHIR, bukan varian persis 08/62/0062.
     * ⛔ Varian persis TERBUKTI terlalu sempit di prod (26 Sep): rekam kedua
     *    Dewi Santi tersimpan `07880405047` — `087880405047` yang kehilangan
     *    satu angka. Varian persis cuma menemukan SATU rekam, dan backfill
     *    akan menautkannya "otomatis, cocok tunggal" — padahal rekam ganda.
     *    Di sini kandidat SENGAJA lebar: kelebihan kandidat hanya menambah
     *    satu keputusan manusia; kekurangan kandidat = tautan salah yang diam.
     */
    public function kandidat(RawAffiliate $affiliate): Collection
    {
        $ekor = $this->ekorNomor($affiliate->phone);
        if ($ekor === null) {
            return collect();
        }

        $pola = '%' . $ekor;

        return RawCustomer::query()
            ->where(function ($q) use ($pola) {
                $q->whereRaw($this->digitSaja('phone') . ' LIKE ?', [$pola])
                  ->orWhereIn('raw_contact_id', function ($sub) use ($pola) {
                      $sub->select('id')->from('raw_contact')
                          ->whereRaw($this->digitSaja('phone') . ' LIKE ?', [$pola]);
                  });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /** 10 digit terakhir nomor ter-normalisasi; null kalau nomornya tak layak dicocokkan. */
    public function ekorNomor(?string $phone): ?string
    {
        $norm = Phone::normalize($phone);
        if ($norm === null) {
            return null;
        }
        return substr($norm, -self::PANJANG_EKOR);
    }

    /** Ekspresi SQL kolom nomor dengan tanda baca umum dibuang (MySQL & SQLite). */
    private function digitSaja(string $kolom): string
    {
        $expr = $kolom;
        foreach (['-', ' ', '+', '.', '(', ')', ';'] as $c) {
            $expr = "REPLACE({$expr}, '{$c}', '')";
        }
        return $expr;
    }

    /**
     * Isi tautan kalau (dan hanya kalau) kandidatnya tunggal. Tak pernah
     * menimpa tautan yang sudah ada.
     */
    public function tautkanOtomatis(RawAffiliate $affiliate, bool $simpan = true): string
    {
        if ($affiliate->raw_customer_id) {
            return self::SUDAH;
        }

        $kandidat = $this->kandidat($affiliate);
        if ($kandidat->count() === 0) return self::KOSONG;
        if ($kandidat->count() > 1)   return self::GANDA;

        if ($simpan) {
            $this->simpanTautan($affiliate, $kandidat->first()->id);
        }
        return self::TERTAUT;
    }

    /** Tautan dipilih manusia (kasus ganda/kosong, atau membetulkan tautan). */
    public function tautkan(RawAffiliate $affiliate, ?string $customerId): void
    {
        if ($customerId !== null && !RawCustomer::whereKey($customerId)->exists()) {
            throw new \InvalidArgumentException('Pasien tidak ditemukan.');
        }
        $this->simpanTautan($affiliate, $customerId);
    }

    private function simpanTautan(RawAffiliate $affiliate, ?string $customerId): void
    {
        // Query builder, bukan save(): kolom sengaja di luar $fillable dan
        // model ini $timestamps=false — tak ada yang perlu ikut tertulis.
        RawAffiliate::whereKey($affiliate->id)->update(['raw_customer_id' => $customerId]);
        $affiliate->raw_customer_id = $customerId;
    }

    /**
     * Jumlah kontrol + tanggal kontrol terakhir per KOC, satu kueri untuk
     * sekumpulan KOC.
     *
     *   raw_affiliate.raw_customer_id
     *     → customer_omnicare_link.omnicare_pid   (satu pasien bisa banyak pid)
     *     → omnicare_sales_detail.p_id  WHERE item_name LIKE '%ontrol%'
     *
     * "Satu kali kontrol" = satu (pid, tanggal transaksi) — dua item kontrol
     * dalam satu kunjungan tetap dihitung sekali.
     *
     * Hanya DITAMPILKAN, tak pernah dipakai sebagai syarat klaim: kelayakan
     * reward murni dari jumlah video (keputusan Ikhsan 26 Sep).
     *
     * @param  iterable<RawAffiliate>  $affiliates
     * @return array<string, array{status:string, jumlah:?int, terakhir:?string}>
     *         status 'ok' | 'perlu_dipilih' (belum tertaut — ganda atau kosong)
     */
    public function ringkasanKontrol(iterable $affiliates): array
    {
        $hasil = [];
        $perPasien = [];
        foreach ($affiliates as $a) {
            if (!$a->raw_customer_id) {
                $hasil[$a->id] = ['status' => 'perlu_dipilih', 'jumlah' => null, 'terakhir' => null];
                continue;
            }
            $hasil[$a->id] = ['status' => 'ok', 'jumlah' => 0, 'terakhir' => null];
            $perPasien[$a->raw_customer_id][] = $a->id;
        }

        // Tautan ke rekam yang sudah tak ada (dihapus / digabung FO) bukan
        // "0 kontrol" — itu tautan patah. Tampilkan "perlu dipilih".
        $masihAda = array_flip(RawCustomer::whereIn('id', array_keys($perPasien))->pluck('id')->all());
        foreach ($perPasien as $customerId => $affiliateIds) {
            if (!isset($masihAda[$customerId])) {
                foreach ($affiliateIds as $affiliateId) {
                    $hasil[$affiliateId] = ['status' => 'perlu_dipilih', 'jumlah' => null, 'terakhir' => null];
                }
                unset($perPasien[$customerId]);
            }
        }
        if (empty($perPasien)) {
            return $hasil;
        }

        $perCustomer = $this->kontrolPerPasien(array_keys($perPasien));
        foreach ($perPasien as $customerId => $affiliateIds) {
            foreach ($affiliateIds as $affiliateId) {
                $hasil[$affiliateId]['jumlah']   = $perCustomer[$customerId]['jumlah'];
                $hasil[$affiliateId]['terakhir'] = $perCustomer[$customerId]['terakhir'];
            }
        }

        return $hasil;
    }

    /**
     * Jumlah kontrol + terakhir per raw_customer_id (dipakai juga layar pilih
     * pasien, supaya manusia melihat rekam mana yang "hidup").
     *
     * @param  array<int,string>  $customerIds
     * @return array<string, array{jumlah:int, terakhir:?string}>
     */
    public function kontrolPerPasien(array $customerIds): array
    {
        $hasil = [];
        foreach ($customerIds as $id) {
            $hasil[$id] = ['jumlah' => 0, 'terakhir' => null];
        }
        if (empty($hasil)) {
            return $hasil;
        }

        $baris = DB::table('customer_omnicare_link as l')
            ->join('omnicare_sales_detail as s', 's.p_id', '=', 'l.omnicare_pid')
            ->whereIn('l.raw_customer_id', array_keys($hasil))
            ->where('s.item_name', 'like', '%ontrol%')
            ->groupBy('l.raw_customer_id', 's.p_id', 's.trx_date')
            ->select('l.raw_customer_id', 's.p_id', 's.trx_date')
            ->get();

        foreach ($baris as $b) {
            $h = &$hasil[$b->raw_customer_id];
            $h['jumlah']++;
            $tgl = substr((string) $b->trx_date, 0, 10);
            if ($h['terakhir'] === null || $tgl > $h['terakhir']) {
                $h['terakhir'] = $tgl;
            }
            unset($h);
        }

        return $hasil;
    }

    /** pid Omnicare per pasien — ditampilkan di layar pilih pasien. */
    public function pidPerPasien(array $customerIds): array
    {
        if (empty($customerIds)) return [];

        return DB::table('customer_omnicare_link')
            ->whereIn('raw_customer_id', $customerIds)
            ->orderBy('omnicare_pid')
            ->get(['raw_customer_id', 'omnicare_pid'])
            ->groupBy('raw_customer_id')
            ->map(fn ($g) => $g->pluck('omnicare_pid')->all())
            ->all();
    }
}
