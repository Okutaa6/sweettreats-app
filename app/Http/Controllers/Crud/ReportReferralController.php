<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\RawAffiliate;
use App\Models\RawBranch;
use App\Models\RawCusReservation;
use App\Models\RawPromo;

class ReportReferralController extends Controller
{
    public function index()
    {
        // Filter dari request
        $filterAffiliate = request('affiliate_id');
        $filterBranch    = request('branch_id');
        $filterDateFrom  = request('date_from', now()->startOfMonth()->format('Y-m-d'));
        $filterDateTo    = request('date_to', now()->format('Y-m-d'));
        $filterKlaim     = request('klaim'); // '' | '0' | '1'

        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch) {
            $filterBranch = $sessionBranch; // paksa filter dan kunci dropdown
        }

        // Query utama:
        // reservasi yang punya promo → join ke affiliate
        $query = RawCusReservation::with([
                'customer',
                'branch',
                'promo.affiliate',
                'services.docService',
            ])
            ->whereNotNull('raw_promo_id') // cuma yang pakai promo/referral
            ->orderBy('date', 'desc');

        // Filter affiliate — by id, lewat kolom raw_promo.raw_affiliate_id.
        // Sengaja BUKAN whereHas('promo.affiliate'): itu membandingkan kolom
        // lawan kolom dan bisa kena galat collation campur di prod (#1267).
        if ($filterAffiliate) {
            $query->whereHas('promo', function ($q) use ($filterAffiliate) {
                $q->where('raw_affiliate_id', $filterAffiliate);
            });
        }

        // Filter cabang
        if ($filterBranch) {
            $query->where('raw_branch_id', $filterBranch);
        }

        // Filter tanggal
        if ($filterDateFrom) {
            $query->whereDate('date', '>=', $filterDateFrom);
        }
        if ($filterDateTo) {
            $query->whereDate('date', '<=', $filterDateTo);
        }

        // Filter status klaim
        if ($filterKlaim !== null && $filterKlaim !== '') {
            $query->where('promo_klaim', $filterKlaim);
        }

        // ── Ringkasan atas: WAJIB dihitung dari SELURUH hasil filter ────
        // Sebelumnya kartu "Sudah/Belum Diklaim" dihitung dari
        // $reservations->getCollection(), yaitu HALAMAN yang sedang dibuka saja.
        // Selama hasilnya muat satu halaman angkanya kebetulan benar; begitu
        // ada halaman kedua, kartu itu diam-diam melaporkan angka yang salah.
        // ->reorder() WAJIB: query induk memakai ORDER BY `date`, dan MySQL
        // menolak SELECT yang isinya agregat semua tapi masih ada ORDER BY
        // kolom non-agregat (galat 1140).
        $ringkas = (clone $query)->toBase()->reorder()->selectRaw("
            COUNT(*)                                      AS total,
            SUM(`promo_klaim` = 1)                        AS diklaim,
            SUM(`promo_klaim` = 0 OR `promo_klaim` IS NULL) AS belum
        ")->first();

        // Id hasil filter dipetik sebagai NILAI lalu dipakai whereIn — bukan
        // subquery — supaya kebal galat collation campur di prod (#1267).
        // Sekalian mengganti $query->get() yang dulu menghidrasi SELURUH baris
        // beserta 4 relasinya hanya untuk menjumlahkan satu kolom.
        $idTersaring = (clone $query)->toBase()->reorder()->pluck('id')->all();
        $totalKomisi = $idTersaring
            ? (float) DB::table('raw_commission')
                ->whereIn('raw_cus_reservation_id', $idTersaring)->sum('amount')
            : 0.0;

        $perPage      = $this->perPage ?? 20;
        $reservations = $query->paginate($perPage)->withQueryString();

        // Data dropdown filter
        // Dropdown filter hanya berisi KOC — KOL & affiliate lain sengaja tidak
        // ikut. Halaman ini dipakai dari tab "Hasil Kode Reff" di /koc, dan
        // daftar yang mencampur semua tipe membuat pencariannya penuh nama
        // yang tak relevan.
        $affiliates = RawAffiliate::where('type', 'koc')->orderBy('name')->get();

        // Kalau URL membawa affiliate yang BUKAN KOC (mis. bookmark lama atau
        // tautan dari halaman lain), baris itu tetap dimasukkan ke dropdown
        // supaya filternya tidak hilang diam-diam dan hasil di tabel tidak
        // terlihat "salah tanpa sebab". Pola yang sama dipakai filter kategori
        // di halaman Jejak Transaksi petty cash.
        if ($filterAffiliate && ! $affiliates->contains('id', $filterAffiliate)) {
            $lain = RawAffiliate::find($filterAffiliate);
            if ($lain) {
                $affiliates = $affiliates->push($lain)->sortBy('name')->values();
            }
        }
        $branches   = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        return view('crud.report-referral.index', compact(
            'reservations',
            'affiliates',
            'branches',
            'filterAffiliate',
            'filterBranch',
            'filterDateFrom',
            'filterDateTo',
            'filterKlaim',
            'totalKomisi', 'ringkas'));
    }
}