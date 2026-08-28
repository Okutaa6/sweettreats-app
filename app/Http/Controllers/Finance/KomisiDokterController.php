<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Omnicare\KomisiQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Komisi Dokter — laporan fee dokter untuk Finance (zona burgundy).
 *
 * Menggantikan pekerjaan manual: buka Omnicare, print PDF "Rincian Komisi" satu
 * per satu per cabang, rekap ulang di spreadsheet. Halaman ini READ-ONLY: tidak
 * ada alur pembayaran fee, tidak ada pengaturan skema komisi (itu di Omnicare).
 *
 * Dua hal yang membedakan halaman ini dari SalesReportController:
 *
 *  • Default periodenya BULAN LALU, bukan bulan berjalan. Basis angkanya
 *    "after payment" — bulan yang sudah lewat sudah final dan itulah yang
 *    dipakai membayar fee. Bulan berjalan masih naik tiap hari.
 *
 *  • Segmen URL-nya sendiri (/komisi-dokter), bukan di bawah /finance yang ada
 *    di AuthGuard::SKIP_RBAC. Node menu ber-link 'komisi-dokter' jadi gerbang
 *    akses yang sebenarnya.
 *
 * Branch scoping disalin dari SalesReportController: user ter-scope cabang
 * (session raw_branch_id) terkunci ke cabangnya dan ?place= diabaikan.
 */
class KomisiDokterController extends Controller
{
    /** Tab layar detail. Key = ?tab=, value = label. */
    private const TABS = [
        'perawatan' => 'Per Perawatan',
        'tren'      => 'Tren Bulanan',
        'rincian'   => 'Rincian',
    ];

    /** Pilihan baris per halaman di tab Rincian. */
    private const PER_PAGE = [25, 50, 100];

    public function __construct(private KomisiQuery $q)
    {
    }

    // ════════════════════════════════════════════════════════════════
    // Layar 1 — daftar dokter
    // ════════════════════════════════════════════════════════════════

    public function index()
    {
        $ctx = $this->resolveContext();

        $data = $this->dataDaftar($ctx);

        return view('finance.komisi.index', array_merge($ctx, $data));
    }

    /** Semua angka layar daftar — dipakai bersama oleh index() dan exportList(). */
    private function dataDaftar(array $ctx): array
    {
        [$s, $e, $p] = [$ctx['start'], $ctx['end'], $ctx['placeId']];

        $rows   = $this->q->perDokter($s, $e, $p);
        $prev   = $this->q->komisiPerDokter($ctx['prevStart'], $ctx['prevEnd'], $p);
        $master = $this->q->masterDokter($rows->pluck('doc_id')->all());
        $cabang = $this->q->perDokterCabang($s, $e, $p);

        // Gabung master di PHP, bukan lewat JOIN — lihat catatan di
        // KomisiQuery::perDokter(). Fan-out di halaman uang = total berlipat.
        $dokter = $rows->map(function ($r) use ($master, $prev, $cabang, $ctx) {
            $m = $master->get($r->doc_id);

            return (object) [
                'doc_id'    => $r->doc_id,
                'nama'      => $m->name ?? $r->doctor_fallback ?? '(tanpa nama)',
                'foto'      => $m->photo ?? null,
                // Badge "Nonaktif" HANYA kalau dokternya benar-benar ter-mapping
                // DAN ditandai nonaktif. Yang belum ter-mapping tidak dapat badge
                // apa pun — statusnya memang tidak diketahui, jangan diasumsikan.
                'terLink'   => $m !== null,
                'nonaktif'  => $m !== null && (int) $m->is_active === 0,
                'komisi'    => (float) $r->komisi,
                'pasien'    => (int) $r->pasien,
                'kunjungan' => (int) $r->kunjungan,
                'tindakan'  => (float) $r->tindakan,
                'omzet'     => (float) $r->omzet,
                'nCabang'   => (int) $r->n_cabang,
                'cabangList' => $r->cabang_list,
                'delta'     => KomisiQuery::delta((float) $r->komisi,
                                                  (float) ($prev[$r->doc_id] ?? 0),
                                                  $ctx['prevLabel']),
                'cabang'    => $cabang->get($r->doc_id, collect()),
            ];
        });

        // Urutan dilakukan atas koleksi yang sudah di tangan — tidak ada query
        // tambahan. Dipilih server-side (bukan JS) supaya URL-nya bisa dikirim
        // ke orang lain dengan urutan yang sama.
        $urut = request('urut', 'komisi');
        $dokter = match ($urut) {
            'pasien' => $dokter->sortByDesc('pasien')->values(),
            'nama'   => $dokter->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            default  => $dokter->sortByDesc('komisi')->values(),
        };
        if (! in_array($urut, ['komisi', 'pasien', 'nama'], true)) {
            $urut = 'komisi';
        }

        $ringkas = $this->q->ringkas($s, $e, $p);
        $banding = $this->q->ringkas($ctx['prevStart'], $ctx['prevEnd'], $p);

        return [
            'dokter'   => $dokter,
            'urut'     => $urut,
            'ringkas'  => $ringkas,
            'banding'  => $banding,
            'kartu'    => $this->kartuRingkas($ringkas, $banding, $ctx['prevLabel']),
            'sinkron'  => $this->infoSinkron(),
            'asisten'  => $this->q->komisiAsisten($s, $e, $p),
        ];
    }

    /** 4 kartu ringkasan layar daftar, lengkap dengan pembandingnya. */
    private function kartuRingkas(object $a, object $b, string $label): array
    {
        $rataA = $a->n_dokter > 0 ? $a->komisi / $a->n_dokter : 0;
        $rataB = $b->n_dokter > 0 ? $b->komisi / $b->n_dokter : 0;

        return [
            ['key' => 'komisi', 'label' => 'Total Komisi', 'tipe' => 'rupiah',
             'nilai' => (float) $a->komisi,
             'delta' => KomisiQuery::delta((float) $a->komisi, (float) $b->komisi, $label)],

            ['key' => 'dokter', 'label' => 'Jumlah Dokter', 'tipe' => 'dokter',
             'nilai' => (int) $a->n_dokter,
             'delta' => KomisiQuery::delta((float) $a->n_dokter, (float) $b->n_dokter, $label)],

            ['key' => 'pasien', 'label' => 'Total Pasien', 'tipe' => 'orang',
             'nilai' => (int) $a->pasien,
             'delta' => KomisiQuery::delta((float) $a->pasien, (float) $b->pasien, $label)],

            ['key' => 'rata', 'label' => 'Rata-rata / Dokter', 'tipe' => 'rupiah',
             'nilai' => $rataA,
             'delta' => KomisiQuery::delta($rataA, $rataB, $label)],
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // Layar 2 — detail satu dokter
    // ════════════════════════════════════════════════════════════════

    public function show(string $docId)
    {
        $ctx = $this->resolveContext();
        $tab = request('tab', 'perawatan');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'perawatan';
        }

        $data = $this->dataDetail($ctx, $docId, $tab);

        return view('finance.komisi.show', array_merge($ctx, $data, [
            'tab'   => $tab,
            'tabs'  => self::TABS,
            'docId' => $docId,
        ]));
    }

    /** Identitas + kartu metrik + isi tab aktif. */
    private function dataDetail(array $ctx, string $docId, string $tab): array
    {
        [$s, $e, $p] = [$ctx['start'], $ctx['end'], $ctx['placeId']];

        $ringkas = $this->q->ringkas($s, $e, $p, $docId);
        $banding = $this->q->ringkas($ctx['prevStart'], $ctx['prevEnd'], $p, $docId);

        $out = [
            'dokter'  => $this->identitasDokter($docId, $s, $e, $p),
            'ringkas' => $ringkas,
            'banding' => $banding,
            'kartu'   => $this->kartuDetail($ringkas, $banding, $ctx['prevLabel']),
            'sinkron' => $this->infoSinkron(),
            'chip'    => $this->chipFilter(),
            'jumlah'  => [],   // badge angka di tiap tab
        ];

        // Badge jumlah baris tiap tab dihitung apa adanya, bukan angka tetap —
        // "Tren Bulanan (12)" yang isinya 6 bulan adalah kebohongan kecil yang
        // bikin Finance mengira ada data yang tidak tampil.
        $perawatan = $this->q->perPerawatan($s, $e, $p, $docId,
                                            request('sort', 'komisi'), request('dir', 'desc'));
        $tren      = $this->trenAdaptif($ctx, $docId);
        $rincianQ  = $this->q->rincian($s, $e, $p, $docId, $this->filterRincian());

        $out['jumlah'] = [
            'perawatan' => $perawatan->count(),
            'tren'      => count($tren['bulan']),
            'rincian'   => (clone $rincianQ)->getCountForPagination(),
        ];

        if ($tab === 'perawatan') {
            $beragam = $perawatan->where('n_skema', '>', 1)->pluck('item_code')->all();
            $out['perawatan'] = $perawatan;
            $out['skema']     = $this->q->skemaDetail($s, $e, $p, $docId, $beragam);
        }

        if ($tab === 'tren') {
            $out['tren'] = $tren;
        }

        if ($tab === 'rincian') {
            $per = (int) request('per', 25);
            if (! in_array($per, self::PER_PAGE, true)) {
                $per = 25;
            }

            $out['baris']      = $rincianQ->paginate($per)->withQueryString();
            $out['perPage']    = $per;
            $out['perPilihan'] = self::PER_PAGE;
            // Dua angka, bukan satu. Tanpa pembedaan ini Finance akan menyalin
            // subtotal 25 baris dan mengira itu total komisi dokternya.
            $out['totalFilter'] = (float) (clone $rincianQ)
                ->reorder()->sum('oc.commission_amount');
        }

        return $out;
    }

    /**
     * Nama & konteks dokter. Diambil dari data komisi periode ini; kalau dokter
     * tidak punya baris di periode terpilih, jatuh ke baris kapan pun supaya
     * halaman tetap menampilkan nama (bukan "(tanpa nama)") dengan empty state.
     */
    private function identitasDokter(string $docId, string $s, string $e, ?string $p): object
    {
        $q = DB::table('omnicare_commission as oc')
            ->where('oc.commission_role', 'doctor')
            ->where('oc.doc_id', $docId);

        $inPeriode = (clone $q)
            ->whereBetween('oc.trx_date', [$s, $e])
            ->when($p, fn ($x) => $x->where('oc.place_id', $p))
            ->selectRaw("MAX(oc.doctor) AS doctor, GROUP_CONCAT(DISTINCT oc.branch_name ORDER BY oc.branch_name SEPARATOR ' · ') AS cabang_list")
            ->first();

        $nama = $inPeriode->doctor ?? null;
        if (! $nama) {
            $nama = (clone $q)->max('oc.doctor');
        }

        $m = $this->q->masterDokter([$docId])->get($docId);

        return (object) [
            'doc_id'     => $docId,
            'nama'       => $m->name ?? $nama ?? '(tanpa nama)',
            'foto'       => $m->photo ?? null,
            'terLink'    => $m !== null,
            'nonaktif'   => $m !== null && (int) $m->is_active === 0,
            'cabangList' => $inPeriode->cabang_list ?? null,
            'ada'        => $nama !== null,
        ];
    }

    /** 5 kartu metrik layar detail. Kartu Total Komisi paling menonjol. */
    private function kartuDetail(object $a, object $b, string $label): array
    {
        $perPasienA = $a->pasien > 0 ? $a->komisi / $a->pasien : 0;
        $perPasienB = $b->pasien > 0 ? $b->komisi / $b->pasien : 0;

        return [
            ['key' => 'komisi', 'label' => 'Total Komisi', 'tipe' => 'rupiah', 'utama' => true,
             'nilai' => (float) $a->komisi,
             'delta' => KomisiQuery::delta((float) $a->komisi, (float) $b->komisi, $label)],

            ['key' => 'pasien', 'label' => 'Pasien', 'tipe' => 'angka',
             'nilai' => (int) $a->pasien,
             'delta' => KomisiQuery::delta((float) $a->pasien, (float) $b->pasien, $label)],

            ['key' => 'kunjungan', 'label' => 'Kunjungan', 'tipe' => 'angka',
             'nilai' => (int) $a->kunjungan,
             'delta' => KomisiQuery::delta((float) $a->kunjungan, (float) $b->kunjungan, $label)],

            ['key' => 'omzet', 'label' => 'Omzet Dikomisikan', 'tipe' => 'rupiah',
             'nilai' => (float) $a->omzet,
             'delta' => KomisiQuery::delta((float) $a->omzet, (float) $b->omzet, $label)],

            ['key' => 'perpasien', 'label' => 'Komisi / Pasien', 'tipe' => 'rupiah',
             'nilai' => $perPasienA,
             'delta' => KomisiQuery::delta($perPasienA, $perPasienB, $label)],
        ];
    }

    /**
     * Tren 12 bulan — ADAPTIF terhadap cakupan data yang benar-benar ada.
     *
     * Tabel komisi baru ditambahkan belakangan dan cron harian hanya menarik
     * window 3 hari; kalau backfill belum dijalankan, riwayatnya bisa jauh lebih
     * pendek dari 12 bulan. Menggambar bar kosong untuk bulan yang datanya
     * memang belum masuk = memberi tahu Finance bahwa dokter tidak dapat komisi
     * di bulan itu. Itu salah, dan di halaman uang salah seperti itu mahal.
     *
     * Aturannya: bulan di LUAR cakupan tidak digambar sama sekali; bulan di
     * DALAM cakupan yang dokternya tidak punya baris digambar nol (itu fakta).
     */
    private function trenAdaptif(array $ctx, string $docId): array
    {
        $cak    = $this->q->cakupan();
        $anchor = Carbon::parse($ctx['end'])->startOfMonth();

        $winStart = $anchor->copy()->subMonthsNoOverflow(11);
        $mulai    = $cak->first_date ? Carbon::parse($cak->first_date)->startOfMonth() : null;
        if ($mulai && $mulai->gt($winStart)) {
            $winStart = $mulai->copy();
        }

        $bulan = [];
        if ($cak->first_date) {
            for ($m = $winStart->copy(); $m->lte($anchor); $m->addMonthNoOverflow()) {
                $bulan[] = $m->copy();
            }
        }

        $data  = $bulan
            ? $this->q->tren($winStart->format('Y-m-d'),
                             $anchor->copy()->endOfMonth()->format('Y-m-d'),
                             $ctx['placeId'], $docId)
            : collect();

        $hariIni = Carbon::now()->format('Y-m');
        $rows    = [];
        $sebelum = null;

        foreach ($bulan as $m) {
            $ym  = $m->format('Y-m');
            $d   = $data->get($ym);
            $kom = (float) ($d->komisi ?? 0);

            $rows[] = (object) [
                'ym'        => $ym,
                'label'     => $m->translatedFormat('F Y'),
                'pasien'    => (int) ($d->pasien ?? 0),
                'kunjungan' => (int) ($d->kunjungan ?? 0),
                'tindakan'  => (float) ($d->tindakan ?? 0),
                'komisi'    => $kom,
                // Baris pertama tidak punya pembanding — tulis "—", bukan
                // "+Rp x" yang seolah naik dari nol.
                'selisih'   => $sebelum === null ? null : $kom - $sebelum,
                'berjalan'  => $ym === $hariIni,
            ];
            $sebelum = $kom;
        }

        $awalData = $cak->first_date ? Carbon::parse($cak->first_date) : null;

        return [
            'bulan'    => $rows,
            'maks'     => collect($rows)->max('komisi') ?: 0,
            'parsial'  => count($rows) > 0 && count($rows) < 12,
            'mulaiLbl' => $awalData?->translatedFormat('F Y'),
            // Bulan pertama cakupan bisa tanggung (data mulai tanggal 14, bukan
            // 1). Kalau tidak ditandai, Finance akan membandingkannya dengan
            // bulan penuh dan menyimpulkan komisinya anjlok.
            'tanggung' => $awalData && $awalData->day > 1 && $rows
                          && $rows[0]->ym === $awalData->format('Y-m'),
            'tglAwal'  => $awalData?->translatedFormat('j M'),
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // CSV & cetak
    // ════════════════════════════════════════════════════════════════

    /** CSV layar daftar — satu baris per dokter, mengikuti filter aktif. */
    public function exportList(): StreamedResponse
    {
        $ctx  = $this->resolveContext();
        $data = $this->dataDaftar($ctx);

        $headers = ['Dokter', 'Kode Dokter', 'Status', 'Cabang', 'Pasien', 'Kunjungan',
                    'Tindakan', 'Omzet Dikomisikan', 'Komisi'];

        $rows = $data['dokter']->map(fn ($d) => [
            $d->nama,
            $d->doc_id,
            $d->terLink ? ($d->nonaktif ? 'Nonaktif' : 'Aktif') : 'Belum terhubung',
            $d->cabangList,
            $d->pasien,
            $d->kunjungan,
            (int) $d->tindakan,
            $d->omzet,
            $d->komisi,
        ]);

        return $this->csv('komisi-dokter_' . $ctx['start'] . '_' . $ctx['end'] . '.csv',
                          $headers, $rows);
    }

    /**
     * CSV layar detail — satu baris per tindakan, SELURUH hasil filter
     * (bukan hanya halaman yang sedang tampil).
     */
    public function exportDetail(string $docId): StreamedResponse
    {
        $ctx = $this->resolveContext();

        $rows = $this->q
            ->rincian($ctx['start'], $ctx['end'], $ctx['placeId'], $docId, $this->filterRincian())
            ->get()
            ->map(fn ($r) => [
                $r->trx_date,
                $r->invoice_num,
                $r->p_name,
                $r->item_code,
                $r->item_name,
                (int) $r->qty_sold,
                $r->net_sell_price,
                self::labelSkema($r->commission_type, (float) $r->commission_rate),
                $r->commission_amount,
                $r->branch_name,
            ]);

        $headers = ['Tanggal', 'No. Invoice', 'Pasien', 'Kode', 'Tindakan', 'Jumlah',
                    'Omzet Dikomisikan', 'Skema', 'Komisi', 'Cabang'];

        return $this->csv('komisi_' . $docId . '_' . $ctx['start'] . '_' . $ctx['end'] . '.csv',
                          $headers, $rows);
    }

    /** Halaman cetak — rincian polos tanpa shell, untuk diberikan ke dokter. */
    public function cetak(string $docId)
    {
        $ctx = $this->resolveContext();

        $baris = $this->q
            ->rincian($ctx['start'], $ctx['end'], $ctx['placeId'], $docId, $this->filterRincian())
            ->get();

        return view('finance.komisi.cetak', array_merge($ctx, [
            'docId'   => $docId,
            'dokter'  => $this->identitasDokter($docId, $ctx['start'], $ctx['end'], $ctx['placeId']),
            'ringkas' => $this->q->ringkas($ctx['start'], $ctx['end'], $ctx['placeId'], $docId),
            'baris'   => $baris,
        ]));
    }

    /**
     * CSV writer. Delimiter ';' + BOM UTF-8 — Excel Indonesia memecah kolom
     * pada ';' (locale id-ID), dan tanpa BOM huruf beraksen jadi mojibake.
     */
    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows) {
            $h = fopen('php://output', 'w');
            fprintf($h, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($h, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($h, $row, ';');
            }
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // Context
    // ════════════════════════════════════════════════════════════════

    private function resolveContext(): array
    {
        $today = Carbon::now();

        $start = $end = null;
        try {
            if (request('date_from')) {
                $start = Carbon::parse(request('date_from'))->format('Y-m-d');
            }
            if (request('date_to')) {
                $end = Carbon::parse(request('date_to'))->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            $start = $end = null;
        }

        // Default BULAN LALU (beda dari Laporan Penjualan yang default bulan
        // berjalan). Basis "after payment" membuat bulan lalu sudah final —
        // itulah periode yang dipakai membayar fee dokter.
        if (! $start || ! $end) {
            $start = $today->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
            $end   = $today->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');
        }
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $bulanLalu = [
            $today->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
            $today->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'),
        ];

        // Empat preset saja, sesuai desain. Preset "aktif" bukan state terpisah:
        // dia menyala kalau rentang saat ini PERSIS sama dengan presetnya.
        $presets = [
            'Bulan Lalu' => $bulanLalu,
            'Bulan Ini'  => [$today->copy()->startOfMonth()->format('Y-m-d'), $today->format('Y-m-d')],
            '30 Hari'    => [$today->copy()->subDays(29)->format('Y-m-d'), $today->format('Y-m-d')],
            '90 Hari'    => [$today->copy()->subDays(89)->format('Y-m-d'), $today->format('Y-m-d')],
        ];

        // Periode pembanding = rentang sepanjang hari yang SAMA, tepat sebelum
        // $start. Bukan "bulan sebelumnya" — supaya "30 Hari" dibandingkan
        // dengan 30 hari, bukan dengan sebulan penuh.
        $len       = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
        $prevEnd   = Carbon::parse($start)->subDay();
        $prevStart = $prevEnd->copy()->subDays($len - 1);

        [$placeId, $branchLocked, $branchName, $branches] = $this->resolveBranch();

        $hari       = $today->format('Y-m-d');
        $belumFinal = ($start <= $hari && $end >= $hari);

        return [
            'start'       => $start,
            'end'         => $end,
            'hari'        => $len,
            'presets'     => $presets,
            'bulanLalu'   => $bulanLalu,
            'bulanLaluLbl' => $today->copy()->subMonthNoOverflow()->translatedFormat('F Y'),
            'prevStart'   => $prevStart->format('Y-m-d'),
            'prevEnd'     => $prevEnd->format('Y-m-d'),
            'prevLabel'   => 'vs ' . $prevStart->translatedFormat('F'),
            'belumFinal'  => $belumFinal,
            'placeId'     => $placeId,
            'branchLocked' => $branchLocked,
            'branchName'  => $branchName,
            // Versi pendek untuk layar ("Cinere", bukan "SWEETTREATS CINERE"),
            // supaya subjudul halaman sejalan dengan kolom Cabang di tabel.
            // $branchName yang penuh tetap dipakai lembar cetak & CSV.
            'branchLabel' => self::labelCabang($branchName),
            'branches'    => $branches,
        ];
    }

    /**
     * Resolve branch scope — pola sama persis dengan SalesReportController.
     *
     * @return array{0:?string,1:bool,2:string,3:\Illuminate\Support\Collection}
     *   [placeId(null=semua), locked, branchName, allBranches]
     */
    private function resolveBranch(): array
    {
        $branches = DB::table('omnicare_location')
            ->whereIn('id', config('omnicare.active_branches', []))
            ->orderBy('name')
            ->get(['id', 'name']);

        // User ter-scope ke cabang → terkunci, dan ?place= diabaikan total
        // (return lebih awal) supaya tidak bisa mengintip cabang lain lewat URL.
        $branchUuid = session('raw_branch_id');
        if ($branchUuid) {
            $placeId = DB::table('raw_branch')->where('id', $branchUuid)->value('omnicare_place_id');
            if ($placeId) {
                $name = optional($branches->firstWhere('id', $placeId))->name ?? 'Cabang';
                return [$placeId, true, $name, $branches];
            }
        }

        $placeId = request('place') ?: null;
        if ($placeId && ! $branches->contains('id', $placeId)) {
            $placeId = null;
        }
        $name = $placeId
            ? (optional($branches->firstWhere('id', $placeId))->name ?? 'Cabang')
            : 'Semua Cabang';

        return [$placeId, false, $name, $branches];
    }

    /** Filter tab Rincian dari query string. */
    private function filterRincian(): array
    {
        return [
            'code'  => request('code') ?: null,
            'q'     => trim((string) request('q')) ?: null,
            'sort'  => request('sort'),
            'dir'   => request('dir'),
        ];
    }

    /** Chip "Disaring: …" yang bisa dihapus satu-satu. */
    private function chipFilter(): array
    {
        $chip = [];

        if ($code = request('code')) {
            $nama = DB::table('omnicare_commission')
                ->where('item_code', $code)->value('item_name');
            $chip[] = ['key' => 'code', 'label' => ($nama ?: $code) . ' (' . $code . ')'];
        }

        if ($q = trim((string) request('q'))) {
            $chip[] = ['key' => 'q', 'label' => 'Pencarian "' . $q . '"'];
        }

        return $chip;
    }

    /**
     * Status sinkron untuk pill di header.
     *
     * Bukan sekadar "kapan terakhir": kalau cron 02:00 terlewat atau sebagian
     * cabang gagal, Finance harus tahu SEBELUM memakai angkanya untuk membayar.
     */
    private function infoSinkron(): array
    {
        $log = $this->q->statusSinkron();

        if (! $log) {
            return ['nada' => 'netral', 'teks' => 'Sinkronisasi belum pernah tercatat', 'detail' => null];
        }

        $waktu = Carbon::parse($log->finished_at ?: $log->started_at);
        $teks  = 'Tersinkron ' . $waktu->format('H:i') . ', ' . $waktu->translatedFormat('j M Y');

        if ($log->status === 'partial') {
            return ['nada' => 'awas', 'teks' => $teks, 'detail' => 'Sebagian cabang gagal disinkronkan'];
        }

        // 26 jam = satu siklus harian + 2 jam kelonggaran.
        if ($waktu->diffInHours(Carbon::now()) > 26) {
            return ['nada' => 'awas', 'teks' => $teks, 'detail' => 'Cron 02:00 tampaknya terlewat'];
        }

        return ['nada' => 'baik', 'teks' => $teks, 'detail' => null];
    }

    /**
     * Nama cabang untuk TAMPILAN — prefiks "SWEETTREATS" dibuang.
     *
     * Omnicare menyimpannya sebagai "SWEETTREATS BINTARO". Di dalam aplikasi
     * Sweet Treats sendiri, kata itu tidak menambah informasi apa pun; yang
     * dibaca orang cuma "Bintaro". Untuk dokter yang praktek di tiga cabang,
     * membuangnya memangkas kolom CABANG dari tiga baris jadi satu, dan ruang
     * yang kembali itu dipakai kolom rupiah supaya angkanya tidak terpotong.
     *
     * Hanya untuk layar. CSV dan lembar cetak tetap memakai nama penuh karena
     * dokumen itu keluar dari aplikasi dan butuh konteks lengkap.
     */
    public static function labelCabang(?string $nama): string
    {
        return trim(preg_replace('/\bSWEET\s*TREATS\s*/i', '', (string) $nama));
    }

    /** Label skema komisi satu baris: "12%" atau "Rp 60.000 flat". */
    public static function labelSkema(?string $tipe, float $rate): string
    {
        if ($tipe === '%') {
            return rtrim(rtrim(number_format($rate, 2, ',', '.'), '0'), ',') . '%';
        }

        return 'Rp ' . number_format($rate, 0, ',', '.') . ' flat';
    }
}
