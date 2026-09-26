<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\AffiliateContent;
use App\Models\RawAffiliate;
use App\Models\RawBranch;
use App\Models\RawDoctor;
use App\Models\RawPromo;
use App\Services\KocPasien;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * KocController
 *
 * Mengelola halaman Daftar KOC dengan 3 tab:
 *   - Semua KOC                : seluruh data type='koc'
 *   - Confirmed Sosmed         : koc_sosmed_confirmed=1 (manual flag oleh admin)
 *   - Sudah Submit Konten      : punya minimal 1 affiliate_content status='approved'
 *
 * KOC = pasien yang di-approach jadi partner/affiliate. Beda dengan KOL
 * (eksternal influencer), KOC sudah jadi customer dari klinik dulu.
 */
class KocController extends Controller
{
    /** Nama bulan Indonesia — dipakai label periode & tanggal ringkas. */
    private const BULAN = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

    /**
     * Batas baris tabel rincian kartu "Hasil Kode Referral".
     * Halaman ini bukan tempat daftar panjang — lebih dari ini diarahkan ke
     * halaman Report Referral yang memang punya filter & pagination.
     */
    private const KOC_DETAIL_MAKS = 25;

    /**
     * Halaman Daftar KOC (default index).
     * URL: /koc
     */
    public function index()
    {
        // ─── Filter dari request ─────────────────────────────────────────
        $filterTab      = request('tab', 'all'); // 'all' | 'confirmed' | 'submitted'
        // Tabel mana yang ditampilkan di kartu bawah — BUKAN penyaring.
        // Sengaja parameter sendiri, terpisah dari `tab`: `tab` menyaring
        // daftar KOC, `tampil` mengganti tabelnya. Mencampur keduanya dalam
        // satu parameter membuat "Sudah Submit Konten" dan "Hasil Kode Reff"
        // saling meniadakan padahal keduanya bisa berlaku bersamaan.
        $tampil         = request('tampil') === 'hasil' ? 'hasil' : 'list';
        $filterBranch   = request('branch', 'all');
        $filterDoctor   = request('doctor', 'all');
        $filterPlatform = request('platform', 'all');
        $search         = trim((string) request('q', ''));

        // ─── Base query: semua KOC ───────────────────────────────────────
        $baseQuery = RawAffiliate::where('type', 'koc');

        // ─── Hitung counter per tab (selalu dari base query, sebelum filter) ──
        $counts = [
            'all'        => (clone $baseQuery)->count(),
            'confirmed'  => (clone $baseQuery)->confirmedSosmed()->count(),
            'submitted'  => (clone $baseQuery)
                ->whereHas('contents', function ($q) {
                    $q->where('status', AffiliateContent::STATUS_APPROVED);
                })
                ->count(),
            // Tab "Hasil Kode Reff" — hanya KOC yang punya kode referral aktif.
            // KOC tanpa kode mustahil menghasilkan pasien, jadi tidak ikut.
            'hasil'      => (clone $baseQuery)
                ->whereHas('promos', function ($q) { $q->where('is_active', 1); })
                ->count(),
        ];

        // ─── Hitung jumlah KOC per cabang (untuk summary cards) ──────────
        $branchCounts = (clone $baseQuery)
            ->selectRaw('raw_branch_id, COUNT(*) as total')
            ->groupBy('raw_branch_id')
            ->pluck('total', 'raw_branch_id')
            ->toArray();

        $branches = RawBranch::orderBy('name')->get()->map(function ($b) use ($branchCounts) {
            $b->kol_count = $branchCounts[$b->id] ?? 0; // pakai 'kol_count' supaya partial bisa di-reuse
            return $b;
        });

        $totalCount = array_sum($branchCounts);

        // ─── Build query untuk hasil tabel ───────────────────────────────
        $query = (clone $baseQuery)
            ->with(['branch', 'doctor', 'docService', 'promos' => function ($q) {
                $q->where('is_active', 1)->orderBy('created_at');
            }])
            ->withCount(['contents as approved_content_count' => function ($q) {
                $q->where('status', AffiliateContent::STATUS_APPROVED);
            }]);

        // Apply tab filter
        if ($filterTab === 'confirmed') {
            $query->confirmedSosmed();
        } elseif ($filterTab === 'submitted') {
            $query->whereHas('contents', function ($q) {
                $q->where('status', AffiliateContent::STATUS_APPROVED);
            });
        }

        // Tabel "Hasil Kode Reff" hanya memuat KOC yang PUNYA kode aktif —
        // KOC tanpa kode mustahil menghasilkan pasien. Penyaring tab di atas
        // (Sudah Submit / Confirmed Sosmed) tetap berlaku di atasnya.
        if ($tampil === 'hasil') {
            $query->whereHas('promos', function ($q) { $q->where('is_active', 1); });
        }

        // Apply other filters
        if ($filterBranch !== 'all') {
            $query->where('raw_branch_id', $filterBranch);
        }
        if ($filterDoctor !== 'all') {
            $query->where('raw_doctor_id', $filterDoctor);
        }
        if ($filterPlatform !== 'all') {
            // Cek di kolom 'social_platform' (single) atau di kolom social_<platform>
            $query->where(function ($q) use ($filterPlatform) {
                $q->where('social_platform', $filterPlatform);
                if ($filterPlatform === 'tiktok')    $q->orWhereNotNull('social_tiktok');
                if ($filterPlatform === 'instagram') $q->orWhereNotNull('social_instagram');
                if ($filterPlatform === 'youtube')   $q->orWhereNotNull('social_youtube');
            });
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('social_username', 'like', "%{$search}%")
                  ->orWhere('social_instagram', 'like', "%{$search}%")
                  ->orWhere('social_tiktok', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // ─── Id KOC yang tersaring (sebelum paginate) ────────────────────
        // Dipakai kolom Aktivasi & Hasil Kode: ringkasannya harus mencakup
        // SELURUH hasil filter, bukan cuma halaman yang sedang dibuka.
        $idTersaring = (clone $query)->pluck('id')->all();

        // ─── Eksekusi & paginate ─────────────────────────────────────────
        $kocs = $query
            ->orderBy('raw_branch_id')
            ->orderBy('name')
            ->paginate($this->perPage)
            ->appends(request()->query());

        // ─── Data pendukung untuk dropdown filter ────────────────────────
        $doctors = RawDoctor::orderBy('name')->get();

        $activeBranch = $filterBranch !== 'all'
            ? $branches->firstWhere('id', $filterBranch)
            : null;

        // ─── Periode kolom "Hasil Kode Referral" (?bulan=YYYY-MM) ───────
        // Hanya menyetir tiga kolom pasien di tabel; daftar KOC-nya sendiri
        // tidak terpengaruh. Divalidasi regex supaya ?bulan ngawur tidak
        // melempar exception.
        $bulanParam = (string) request('bulan', '');
        $periode    = preg_match('/^\d{4}-\d{2}$/', $bulanParam)
            ? Carbon::createFromFormat('Y-m-d', $bulanParam . '-01')->startOfMonth()
            : Carbon::now()->startOfMonth();
        $periodeValue = $periode->format('Y-m');
        $periodeLabel = self::BULAN[$periode->month] . ' ' . $periode->year;

        $branchScope = $filterBranch !== 'all' ? [$filterBranch] : $branches->pluck('id')->all();
        $mulai  = $periode->format('Y-m-d');
        $sampai = $periode->copy()->endOfMonth()->format('Y-m-d');

        $aktivasi = $this->ringkasanAktivasi($idTersaring);

        // Hitungan pasien: dipakai tab "Hasil Kode Reff" (angka per baris +
        // ringkasan). Tab lain tidak perlu membayarnya.
        $hasilKoc = ($aktivasi['tersedia'] && $tampil === 'hasil')
            ? $this->hasilKodePerKoc($idTersaring, $branchScope, $mulai, $sampai)
            : [];

        // Rincian reservasi, DIKELOMPOKKAN per KOC, hanya untuk baris yang
        // benar-benar tampil di halaman ini — bukan seluruh hasil filter.
        $rincianPerKoc = ($aktivasi['tersedia'] && $tampil === 'hasil')
            ? $this->rincianPerKoc($kocs->pluck('id')->all(), $branchScope, $mulai, $sampai, $branches)
            : [];

        // Jumlah kontrol per KOC (baris yang tampil saja). Dibaca dari tautan
        // yang DISIMPAN (raw_customer_id) — tak pernah menjodohkan di sini.
        // Hanya tampilan: kelayakan reward tetap murni dari jumlah video.
        $kontrol = app(KocPasien::class)->ringkasanKontrol($kocs->getCollection());

        // Total kolom tabel — dipakai colspan baris kosong.
        // Tabel daftar KOC: No, Nama, Cabang, Dokter, Perawatan, Platform,
        // Contact, Feedback, Konten, Kontrol, Kode Ref, Login Portal = 12,
        // + 1 bila tab bukan 'all' (kolom Confirmed). (Angka lama 14/15
        // menghitung kolom tabel "Hasil Kode Reff" yang tabelnya terpisah.)
        $kolomTotal = $filterTab !== 'all' ? 13 : 12;

        return view('crud.koc.list', compact(
            'kocs',
            'branches',
            'doctors',
            'totalCount',
            'counts',
            'filterTab',
            'tampil',
            'filterBranch',
            'filterDoctor',
            'filterPlatform',
            'search',
            'activeBranch',
            'aktivasi',
            'hasilKoc',
            'rincianPerKoc',
            'periodeValue',
            'periodeLabel',
            'kolomTotal',
            'kontrol'
        ));
    }


    // ════════════════════════════════════════════════════════════════
    //  AKTIVASI KOC (tiket IT-2026-045)
    //  Dua pertanyaan Marketing yang selama ini tak terjawab:
    //    (1) berapa KOC yang sudah BERHASIL masuk portal affiliate, dan
    //    (2) berapa KOC yang kodenya sudah menghasilkan pasien.
    //
    //  3-5 query, tanpa loop per-KOC. Arah JOIN digiring dari RESERVASI
    //  (1 reservasi -> 1 promo -> 1 KOC) supaya KOC yang punya banyak kode
    //  promo tidak terhitung berkali-kali; kalau dibalik (KOC -> promos ->
    //  reservations, seperti tabel di halaman ini yang me-loop $row->promos)
    //  tiap reservasi terhitung sekali PER KODE.
    //
    //  Periode memakai raw_cus_reservation.date (tanggal kunjungan), bukan
    //  created_at (waktu input sales).
    //
    //  CATATAN GAP yang diketahui & sengaja dibiarkan: lead dari form Pasien
    //  Baru (raw_customer.raw_promo_id) yang belum dibuatkan reservasi TIDAK
    //  terhitung di sini. Kalau Marketing nanti minta angka lead, itu kolom
    //  ketiga dan keputusan baru — jangan diam-diam dicampur ke dua angka ini.
    // ════════════════════════════════════════════════════════════════
    /**
     * Ringkasan aktivasi login untuk sekumpulan KOC (tiket IT-2026-045).
     *
     * "Bisa login" = approved + aktif. KOC pending/rejected/nonaktif memang
     * DITOLAK AffiliatePortalController::loginProcess(), jadi memasukkannya ke
     * penyebut membuat persentase aktivasi tak pernah bisa 100%. Filter yang
     * sama dipakai untuk `sudah` supaya sudah <= eligible dijamin — KOC yang
     * dinonaktifkan SESUDAH pernah login tidak membuat persentase > 100%.
     */
    private function ringkasanAktivasi(array $kocIds): array
    {
        $kosong = ['tersedia' => false, 'total' => 0, 'eligible' => 0,
            'sudah_login' => 0, 'belum_login' => 0, 'pct_login' => 0, 'login_terakhir' => null];

        // Pagar deploy: tanpa ini, kode yang naik sebelum SQL-nya dijalankan
        // membuat SELURUH halaman KOC error — bukan cuma kolom baru ini.
        if (! Schema::hasColumn('raw_affiliate', 'last_login')) {
            return $kosong;
        }
        if (! $kocIds) {
            return array_merge($kosong, ['tersedia' => true]);
        }

        $r = DB::table('raw_affiliate')->whereIn('id', $kocIds)
            ->selectRaw("
                COUNT(*)                                                     AS total,
                SUM(`registration_status` = 'approved' AND `is_active` = 1)  AS eligible,
                SUM(`last_login` IS NOT NULL
                    AND `registration_status` = 'approved'
                    AND `is_active` = 1)                                     AS sudah,
                MAX(`last_login`)                                            AS terakhir
            ")->first();

        $eligible = (int) ($r->eligible ?? 0);
        $sudah    = (int) ($r->sudah ?? 0);

        return [
            'tersedia'       => true,
            'total'          => (int) ($r->total ?? 0),
            'eligible'       => $eligible,
            'sudah_login'    => $sudah,
            'belum_login'    => max(0, $eligible - $sudah),
            'pct_login'      => $eligible > 0 ? (int) round($sudah / $eligible * 100) : 0,
            'login_terakhir' => $this->tanggalPendek($r->terakhir ?? null),
        ];
    }

    /**
     * Hitungan pasien per KOC untuk periode terpilih — SATU query untuk semua
     * KOC yang sedang tersaring, lalu dipetakan di PHP. Bukan per-baris (N+1).
     *
     * COUNT(DISTINCT raw_customer_id), bukan COUNT(*): kolomnya berlabel
     * "Pasien", jadi satu orang yang booking dua kali dalam bulan itu tetap
     * satu pasien. Ini juga yang membuat KOC bermulti-kode tidak terhitung
     * berkali-kali — pelipatan terjadi di GROUP BY raw_affiliate_id.
     *
     * COALESCE(status,'') dipakai sebagai jaring: di skema lokal `status`
     * NOT NULL, tapi kalau server lain ternyata nullable, bentuk polos
     * `status <> 'cancelled'` akan MEMBUANG SENYAP baris ber-status NULL
     * (di SQL, NULL <> 'x' bernilai NULL, bukan true).
     */
    private function hasilKodePerKoc(array $kocIds, array $branchIds, string $start, string $end): array
    {
        if (! $kocIds) return [];

        $peta = [];
        foreach ($this->kocReservationQuery($branchIds, $start, $end)
                    ->whereIn('p.raw_affiliate_id', $kocIds)
                    ->groupBy('p.raw_affiliate_id')
                    ->selectRaw("
                        p.`raw_affiliate_id` AS koc_id,
                        COUNT(DISTINCT CASE WHEN COALESCE(r.`status`, '') <> 'cancelled'
                                            THEN r.`raw_customer_id` END)   AS pasien,
                        COUNT(DISTINCT CASE WHEN r.`status` = 'completed'
                                            THEN r.`raw_customer_id` END)   AS datang,
                        COUNT(DISTINCT CASE WHEN r.`status` = 'cancelled'
                                            THEN r.`raw_customer_id` END)   AS batal,
                        SUM(COALESCE(r.`status`, '') <> 'cancelled')        AS resv
                    ")->get() as $r) {
            $peta[$r->koc_id] = ['pasien' => (int) $r->pasien, 'datang' => (int) $r->datang,
                'batal' => (int) $r->batal, 'resv' => (int) $r->resv];
        }
        return $peta;
    }

    /**
     * Rincian reservasi per KOC — untuk dropdown tiap baris di tab
     * "Hasil Kode Reff". Dikelompokkan berdasarkan pemilik kode.
     *
     * Hanya untuk $kocIds yang sedang tampil di halaman (satu halaman
     * pagination), jadi jumlah barisnya selalu terbatas walau datanya tumbuh.
     * Reservasi batal sengaja tidak masuk, supaya jumlah baris di dropdown
     * cocok dengan angka kolom "Pasien".
     */
    private function rincianPerKoc(array $kocIds, array $branchIds, string $start, string $end, $branches): array
    {
        if (! $kocIds) return [];

        $baris = $this->kocReservationQuery($branchIds, $start, $end)
            ->whereIn('p.raw_affiliate_id', $kocIds)
            ->orderByDesc('r.date')
            ->limit(self::KOC_DETAIL_MAKS * max(1, count($kocIds)))
            ->get(['p.raw_affiliate_id as koc_id', 'r.date', 'r.status', 'r.raw_customer_id',
                   'r.raw_branch_id', 'r.raw_doctor_id', 'r.promo_klaim', 'p.promo_code'])
            ->filter(fn($r) => ($r->status ?? '') !== 'cancelled')
            ->values();

        if ($baris->isEmpty()) return [];

        // Nama pasien & dokter lewat lookup whereIn (kolom lawan NILAI), BUKAN
        // JOIN (kolom lawan kolom). Nama cabang dari $branches yang sudah ada
        // di memori. Disengaja: JOIN raw_branch.id = raw_branch_id adalah kasus
        // #1267 yang sudah pernah menggigit di prod (collation campur), dan
        // tabel dokter/pasien berisiko sama.
        $namaPasien = DB::table('raw_customer')
            ->whereIn('id', $baris->pluck('raw_customer_id')->filter()->unique()->all())
            ->pluck('name', 'id')->all();
        $namaDokter = [];
        $idDokter = $baris->pluck('raw_doctor_id')->filter()->unique()->all();
        if ($idDokter) {
            $namaDokter = DB::table('raw_doctor')->whereIn('id', $idDokter)->pluck('name', 'id')->all();
        }
        $namaCabang = collect($branches)->pluck('name', 'id')->all();

        $peta = [];
        foreach ($baris as $r) {
            $peta[$r->koc_id][] = [
                'tanggal'      => $this->tanggalPendek($r->date, true),
                'pasien'       => $namaPasien[$r->raw_customer_id] ?? '—',
                'cabang'       => $namaCabang[$r->raw_branch_id] ?? '—',
                'dokter'       => $namaDokter[$r->raw_doctor_id] ?? '—',
                'kode'         => $r->promo_code,
                // Status klaim voucher oleh pasien — kolom yang SAMA dengan
                // yang dipakai halaman Report Referral (raw_cus_reservation
                // .promo_klaim), supaya dua tempat tidak pernah memberi
                // jawaban berbeda untuk reservasi yang sama.
                'klaim'        => (int) ($r->promo_klaim ?? 0) === 1,
            ];
        }
        return $peta;
    }

    /**
     * Basis query reservasi ber-kode KOC — dipakai tiga kali (agregat, daftar, rincian).
     *
     * INNER JOIN, bukan LEFT: promo tanpa affiliate dan promo milik KOL
     * otomatis terbuang.
     *
     * Kalau prod melempar #1267 (illegal mix of collations) di salah satu
     * predikat JOIN, obatnya tambahkan COLLATE utf8mb4_bin pada KEDUA sisi
     * predikat itu — jangan diubah jadi whereIn(subquery), karena subquery
     * tetap perbandingan kolom lawan kolom dan bisa melempar galat yang sama.
     */
    private function kocReservationQuery(array $branchIds, string $start, string $end)
    {
        return DB::table('raw_cus_reservation as r')
            ->join('raw_promo as p', 'p.id', '=', 'r.raw_promo_id')
            ->join('raw_affiliate as a', fn($j) => $j->on('a.id', '=', 'p.raw_affiliate_id')
                                                     ->where('a.type', '=', 'koc'))
            ->whereIn('r.raw_branch_id', $branchIds)
            // Waitlist boleh ber-`date` NULL sejak 2026_08_12 — baris itu memang
            // tak punya bulan, jadi tak bisa masuk laporan bulanan mana pun.
            ->whereBetween('r.date', [$start, $end]);
    }

    /**
     * Tanggal ringkas gaya "18 Sep".
     *
     * @param bool $selaluTahun Paksa tahun ikut ditulis. Dipakai tabel rincian
     *                          reservasi: di sana tanggal tanpa tahun menyesatkan
     *                          karena periodenya bisa digeser bebas lewat filter.
     *                          Kalau false, tahun hanya muncul bila bukan tahun ini.
     */
    private function tanggalPendek($ts, bool $selaluTahun = false): ?string
    {
        if (! $ts) return null;
        $d = Carbon::parse($ts);
        $label = $d->day . ' ' . substr(self::BULAN[$d->month], 0, 3);
        return ($selaluTahun || $d->year !== (int) date('Y'))
            ? $label . ' ' . $d->year
            : $label;
    }

    /**
     * Guard endpoint editor-inline (Dokter & Perawatan) — Super Admin saja.
     * Abort 403 kalau bukan.
     */
    private function ensureSuperAdmin(): void
    {
        if (session('group_name') !== 'Super Admin') {
            abort(403, 'Hanya Super Admin yang dapat mengubah data ini.');
        }
    }

    /**
     * Update raw_doctor_id KOC via AJAX (dropdown inline di tabel KOC).
     * URL: PATCH /koc/{id}/doctor
     * Body: { raw_doctor_id: <uuid|""> }
     * Returns JSON: { raw_doctor_id, doctor_name }
     *
     * Catatan: fitur dropdown sementara (Super Admin only). Kalau dropdown
     * nanti dilepas dari UI, nilai raw_doctor_id tetap tersimpan di DB.
     */
    public function updateDoctor(Request $request, $id)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'raw_doctor_id' => 'nullable|exists:raw_doctor,id',
        ]);

        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);
        $koc->raw_doctor_id = $validated['raw_doctor_id'] ?: null;
        $koc->save();

        return response()->json([
            'raw_doctor_id' => $koc->raw_doctor_id,
            'doctor_name'   => $koc->raw_doctor_id ? optional($koc->doctor)->name : null,
        ]);
    }

    /**
     * Update behel_type KOC via AJAX (dropdown inline "Perawatan" di tabel KOC).
     * URL: PATCH /koc/{id}/behel-type
     * Body: { behel_type: <enum key|""> }
     * Returns JSON: { behel_type, label }
     *
     * Catatan: fitur dropdown sementara (Super Admin only). Sumber enum:
     * RawAffiliate::behelTypes(). Nilai tetap tersimpan di DB walaupun
     * dropdown nanti dilepas dari UI.
     */
    public function updateBehelType(Request $request, $id)
    {
        $this->ensureSuperAdmin();

        $allowed = array_keys(RawAffiliate::behelTypes());
        $validated = $request->validate([
            'behel_type' => 'nullable|in:' . implode(',', $allowed),
        ]);

        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);
        $koc->behel_type = $validated['behel_type'] ?: null;
        $koc->save();

        return response()->json([
            'behel_type' => $koc->behel_type,
            'label'      => $koc->behel_type ? (RawAffiliate::behelTypes()[$koc->behel_type] ?? null) : null,
        ]);
    }

    /**
     * Update feedback KOC dari textarea inline di tabel.
     * URL: PATCH /koc/{id}/feedback
     * Body: { feedback: <string|""> }
     * Returns JSON: { feedback }
     */
    public function updateFeedback(Request $request, $id)
    {
        $validated = $request->validate([
            'feedback' => 'nullable|string|max:5000',
        ]);

        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);
        $koc->feedback = trim((string) ($validated['feedback'] ?? '')) ?: null;
        $koc->save();

        return response()->json([
            'feedback' => $koc->feedback,
        ]);
    }

    /**
     * Toggle status koc_sosmed_confirmed via AJAX.
     * URL: PATCH /koc/{id}/confirm-sosmed
     * Body: { confirmed: true|false }
     * Returns JSON: { confirmed, confirmed_at }
     */
    public function toggleSosmedConfirmed(Request $request, $id)
    {
        $validated = $request->validate([
            'confirmed' => 'required|boolean',
        ]);

        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);

        $userId = session('user_id') ?? auth()->id();
        $koc->setSosmedConfirmed((bool) $validated['confirmed'], $userId);

        return response()->json([
            'confirmed'    => $koc->koc_sosmed_confirmed,
            'confirmed_at' => $koc->koc_sosmed_confirmed_at?->translatedFormat('d M Y H:i'),
        ]);
    }

    /**
     * Download template CSV untuk import KOC.
     * URL: GET /koc/csv-template
     */
    public function downloadCsvTemplate()
    {
        $headers = [
            'name', 'branch_name', 'doctor_name', 'phone', 'email', 'feedback',
            'social_platform', 'social_username', 'social_tiktok', 'social_instagram',
            'behel_type', 'reward_program', 'kode_referral',
            'referral_scope', 'is_active',
        ];

        $sample = [
            'Deva Indah Sari', 'Sunter', 'Dokter Fridi', '081234567890', 'deva@example.com', 'Upload Konten Tiktok',
            'tiktok', 'usersdevin', 'https://www.tiktok.com/@usersdevin', '',
            'signature_metal_gp', 'above_12x', 'DEVA11',
            'behel', '1',
        ];

        $callback = function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        };

        return response()->streamDownload($callback, 'template_koc_import.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import KOC dari file CSV.
     * URL: POST /koc/import-csv
     *
     * Header CSV (case-insensitive, spasi → underscore):
     *   name, branch_name, doctor_name, phone, email, feedback,
     *   social_platform, social_username, social_tiktok, social_instagram, social_youtube,
     *   behel_type, reward_program, kode_referral,
     *   referral_scope, is_active
     *
     * Default yang otomatis di-set:
     *   - type='koc', role='pasien', registration_status='approved'
     *
     * kode_referral (kalau diisi) akan auto-create RawPromo dengan
     * discount_type='nominal' & discount_value = scope voucher_amount.
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('csv_file')->getPathname(), 'r');
        if (!$handle) {
            return redirect()->route('koc')->with('error', 'Gagal membuka file CSV.');
        }

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return redirect()->route('koc')->with('error', 'CSV kosong atau tidak valid.');
        }

        // Normalize header: lowercase + spaces → underscore + trim BOM
        $headers = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            return strtolower(trim(str_replace([' ', '-'], '_', $h)));
        }, $rawHeaders);

        // Pre-build lookup maps (sekali, supaya tidak hit DB per-row)
        $branches = RawBranch::all()
            ->mapWithKeys(fn($b) => [strtolower(trim($b->name)) => $b->id])
            ->toArray();
        $doctors = RawDoctor::all()
            ->mapWithKeys(fn($d) => [strtolower(trim($d->name)) => $d->id])
            ->toArray();
        $platformKeys = array_keys(RawAffiliate::socialPlatforms());
        $rewardKeys   = array_keys(RawAffiliate::rewardPrograms());
        $behelKeys    = array_keys(RawAffiliate::behelTypes());
        $scopeKeys    = array_keys(RawAffiliate::referralScopes());

        // Promo code yang sudah ada — supaya kita tidak insert duplicate.
        $existingPromoCodes = RawPromo::pluck('promo_code')
            ->map(fn($c) => strtoupper(trim($c)))
            ->flip()
            ->toArray();

        $created = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1; // header di row 1

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row  = array_pad($row, count($headers), '');
            $data = array_combine($headers, array_slice($row, 0, count($headers)));

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                $errors[] = "Baris {$rowNum}: nama kosong, dilewati.";
                continue;
            }

            // Enum coercion — empty atau invalid → null (silent).
            $platform = trim((string) ($data['social_platform'] ?? '')) ?: null;
            $reward   = trim((string) ($data['reward_program']  ?? '')) ?: null;
            $scope    = trim((string) ($data['referral_scope']  ?? '')) ?: null;
            $behel    = $this->normalizeBehelType((string) ($data['behel_type'] ?? ''));

            if ($platform && !in_array($platform, $platformKeys, true)) $platform = null;
            if ($reward   && !in_array($reward,   $rewardKeys,   true)) $reward   = null;
            if ($scope    && !in_array($scope,    $scopeKeys,    true)) $scope    = null;
            if ($behel    && !in_array($behel,    $behelKeys,    true)) $behel    = null;

            // Lookup branch & doctor (case-insensitive)
            $branchName = trim((string) ($data['branch_name'] ?? ''));
            $branchId   = $branchName ? ($branches[strtolower($branchName)] ?? null) : null;

            $doctorName = trim((string) ($data['doctor_name'] ?? ''));
            $doctorId   = $doctorName ? ($doctors[strtolower($doctorName)] ?? null) : null;

            // is_active: terima 1/0/true/false/yes/no/empty
            $isActiveRaw = strtolower(trim((string) ($data['is_active'] ?? '1')));
            $isActive    = in_array($isActiveRaw, ['0', 'false', 'no', 'tidak'], true) ? 0 : 1;

            $kodeReferral = strtoupper(trim((string) ($data['kode_referral'] ?? '')));

            try {
                $affiliateId = (string) Str::uuid();

                RawAffiliate::create([
                    'id'                  => $affiliateId,
                    'name'                => $name,
                    'type'                => 'koc',
                    'role'                => 'pasien',
                    'raw_branch_id'       => $branchId,
                    'raw_doctor_id'       => $doctorId,
                    'phone'               => trim((string) ($data['phone']            ?? '')) ?: null,
                    'email'               => trim((string) ($data['email']            ?? '')) ?: null,
                    'social_platform'     => $platform,
                    'social_username'     => trim((string) ($data['social_username']  ?? '')) ?: null,
                    'social_instagram'    => trim((string) ($data['social_instagram'] ?? '')) ?: null,
                    'social_tiktok'       => trim((string) ($data['social_tiktok']    ?? '')) ?: null,
                    'social_youtube'      => trim((string) ($data['social_youtube']   ?? '')) ?: null,
                    'feedback'            => trim((string) ($data['feedback']         ?? '')) ?: null,
                    'behel_type'          => $behel,
                    'reward_program'      => $reward,
                    'referral_scope'      => $scope,
                    'commission_rate'     => 0,
                    'is_active'           => $isActive,
                    'registration_status' => RawAffiliate::REG_STATUS_APPROVED,
                    'created_at'          => now(),
                ]);

                // Auto-create RawPromo kalau kode_referral diisi & belum ada.
                if ($kodeReferral !== '') {
                    if (!isset($existingPromoCodes[$kodeReferral])) {
                        // Discount value default dari scope (Rp 100k behel / Rp 50k non-behel).
                        $voucher = $scope ? (int) (RawAffiliate::referralScopes()[$scope]['voucher_amount'] ?? 0) : 0;

                        RawPromo::create([
                            'id'               => (string) Str::uuid(),
                            'raw_affiliate_id' => $affiliateId,
                            'referral_scope'   => $scope,
                            'promo_code'       => $kodeReferral,
                            'discount_type'    => 'nominal',
                            'discount_value'   => $voucher,
                            'valid_until'      => null,
                            'is_active'        => 1,
                            'created_at'       => now(),
                        ]);
                        $existingPromoCodes[$kodeReferral] = true;
                    }
                }

                $created++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $msg = "Import selesai: {$created} KOC ditambahkan, {$skipped} dilewati.";
        if (!empty($errors)) {
            $msg .= ' Catatan: ' . implode(' | ', array_slice($errors, 0, 3));
            if (count($errors) > 3) $msg .= ' (+' . (count($errors) - 3) . ' error lain)';
        }

        return redirect()->route('koc')->with($created > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Mapping flexible untuk kolom behel_type:
     *   - Terima enum key langsung (e.g. "signature_metal_sp")
     *   - Terima label bebas (e.g. "Signature Metal", "Signature Metal SP",
     *     "Self Ligating Metal", "Deluxe Metal", "Ceramic Braces")
     *   - SP/GP ambigu → default GP
     *   - Tidak ketemu → null
     */
    private function normalizeBehelType(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;

        // Direct enum key match (case-sensitive supaya konsisten dengan DB)
        if (array_key_exists($value, RawAffiliate::behelTypes())) {
            return $value;
        }
        if (array_key_exists(strtolower($value), RawAffiliate::behelTypes())) {
            return strtolower($value);
        }

        $v = strtolower(preg_replace('/\s+/', ' ', $value));

        // Detect suffix — default GP
        $isSp   = preg_match('/\b(sp|spesialis)\b/', $v) === 1;
        $suffix = $isSp ? 'sp' : 'gp';

        if (str_contains($v, 'self ligating') || str_contains($v, 'self-ligating')) {
            return "self_ligating_metal_{$suffix}";
        }
        if (str_contains($v, 'stellar')) {
            return "stellar_metal_{$suffix}";
        }
        if (str_contains($v, 'signature ceramic')) {
            return "signature_ceramic_{$suffix}";
        }
        if (str_contains($v, 'signature') && str_contains($v, 'metal')) {
            return "signature_metal_{$suffix}";
        }
        if (str_contains($v, 'deluxe')) {
            return 'deluxe_metal';
        }
        if (str_contains($v, 'ceramic')) {
            return 'ceramic_braces_gp';
        }

        return null;
    }

    /**
     * Return list konten KOC sebagai JSON untuk modal "Lihat Konten".
     * URL: GET /koc/{id}/contents
     */
    public function contents($id)
    {
        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);

        $contents = AffiliateContent::where('raw_affiliate_id', $koc->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($c) {
                $statusOpt = AffiliateContent::statusOptions()[$c->status] ?? null;
                return [
                    'id'           => $c->id,
                    'platform'     => $c->platform,
                    'platform_lbl' => AffiliateContent::platformOptions()[$c->platform] ?? $c->platform,
                    'content_type' => $c->content_type,
                    'content_url'  => $c->content_url,
                    'caption'      => $c->caption,
                    'status'       => $c->status,
                    'status_lbl'   => $statusOpt['label'] ?? $c->status,
                    'status_color' => $statusOpt['color'] ?? 'neutral',
                    'created_at'   => $c->created_at?->translatedFormat('d M Y'),
                    'edit_url'     => route('content.review.edit', $c->id),
                ];
            });

        return response()->json([
            'koc' => [
                'id'   => $koc->id,
                'name' => $koc->name,
            ],
            'contents'    => $contents,
            'review_url'  => route('content.review', ['affiliate_id' => $koc->id]),
        ]);
    }

    /**
     * Layar pilih rekam pasien untuk satu KOC/KOL — tempat manusia memutus
     * kasus yang tak bisa diputus mesin (nomor HP cocok ke dua pasien, atau
     * tak cocok ke mana pun). Berlaku juga untuk KOL: Review Konten &
     * SweetMates memuat keduanya.
     * URL: GET /koc/{id}/pasien   (?q= cari pasien lewat nama / HP lain)
     */
    public function pasien($id, KocPasien $layanan)
    {
        $affiliate = RawAffiliate::with('customer')->findOrFail($id);

        $kandidat = $layanan->kandidat($affiliate);

        $cari = trim((string) request('q', ''));
        $hasilCari = collect();
        if (mb_strlen($cari) >= 3) {
            $hasilCari = \App\Models\RawCustomer::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('phone', 'like', "%{$cari}%"))
                ->whereNotIn('id', $kandidat->pluck('id'))
                ->orderBy('name')
                ->orderBy('id')
                ->limit(30)
                ->get();
        }

        $semuaId = $kandidat->pluck('id')
            ->merge($hasilCari->pluck('id'))
            ->when($affiliate->raw_customer_id, fn ($c) => $c->push($affiliate->raw_customer_id))
            ->unique()->values()->all();

        $kontrolPasien = $layanan->kontrolPerPasien($semuaId);
        $pidPasien     = $layanan->pidPerPasien($semuaId);
        $kembali       = $this->urlKembali();

        return view('crud.koc.pasien', compact(
            'affiliate', 'kandidat', 'hasilCari', 'cari', 'kontrolPasien', 'pidPasien', 'kembali'
        ));
    }

    /**
     * Simpan pilihan manusia. Melepas tautan harus lewat tombol `lepas`
     * yang eksplisit — "Simpan" tanpa pilihan DITOLAK, bukan diartikan lepas.
     * URL: PATCH /koc/{id}/pasien
     */
    public function simpanPasien(Request $request, $id, KocPasien $layanan)
    {
        $lepas = $request->boolean('lepas');
        $validated = $request->validate([
            'raw_customer_id' => ($lepas ? 'nullable' : 'required') . '|string|max:36',
        ], [
            'raw_customer_id.required' => 'Pilih salah satu pasien dulu.',
        ]);

        $affiliate = RawAffiliate::findOrFail($id);

        try {
            $layanan->tautkan($affiliate, $lepas ? null : $validated['raw_customer_id']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to($this->urlKembali())->with('success', $affiliate->raw_customer_id
            ? 'Rekam pasien ' . ($affiliate->name ?: 'KOC') . ' tersimpan.'
            : 'Tautan pasien ' . ($affiliate->name ?: 'KOC') . ' dilepas.');
    }

    /**
     * ?return= hanya boleh URL aplikasi sendiri (cegah open redirect).
     * Dibandingkan HOST-nya, bukan awalan string: awalan `https://app.sweettreats.id`
     * juga cocok dengan `https://app.sweettreats.id.evil.com` dan
     * `https://app.sweettreats.id@evil.com`.
     */
    private function urlKembali(): string
    {
        $ret   = (string) request('return', '');
        $parts = $ret !== '' ? parse_url($ret) : false;

        $aman = is_array($parts)
            && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && !isset($parts['user']) && !isset($parts['pass'])
            && strcasecmp($parts['host'] ?? '', request()->getHost()) === 0;

        return $aman ? $ret : route('koc');
    }
}
