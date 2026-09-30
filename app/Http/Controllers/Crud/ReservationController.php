<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPatientContact;
use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Support\Phone;
use App\Models\RawBranch;
use App\Models\RawCustomer;
use App\Models\RawCusReservation;
use App\Models\RawCusResService;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawDocService;
use App\Models\RawAffiliate;
use App\Models\RawCommission;
use App\Models\RawPromo;
use App\Models\MarketingPromo;

class ReservationController extends Controller
{
    use ResolvesPatientContact;
    use ResolvesDoctorAvailability;

    public function index()
    {
        // Mode akses View Only: kalau role user di-set view_only via
        // GroupPageAccessMode → kontrol mutasi di hide & tab Waiting List
        // disembunyikan. Super Admin / Admin General override → selalu full.
        $isViewOnly = isReservationViewOnly();

        // Tab view: 'list' (default) | 'cal' | 'waitlist' | 'pasienbaru' | 'jadwal'.
        // 'new' = filter chip, bukan tab terpisah.
        $view = request('view', 'list');
        // View-only tidak boleh akses tab waitlist & pasienbaru (defensive — UI
        // sudah hide). Tab Pasien Baru memuat nomor HP + keluhan calon pasien,
        // jadi role pemantau tidak diberi akses sama sekali.
        if ($isViewOnly && in_array($view, ['waitlist', 'pasienbaru'], true)) {
            $view = 'list';
        }

        // Default per_page lebih besar di tab Jadwal Dokter supaya screenshot satu halaman muat banyak baris.
        $defaultPerPage = $view === 'jadwal' ? 50 : 10;
        $perPage = in_array((int) request('per_page'), [10, 25, 50, 100]) ? (int) request('per_page') : $defaultPerPage;

        // Ambil input filter
        $filterSearch   = request('search');
        $filterBranch   = request('branch_id');
        $filterDoctor   = request('doctor_id');
        $filterStatus   = request('status');
        $filterDateFrom = request('date_from');
        $filterDateTo   = request('date_to');
        $filterPromo     = request('promo_id');
        $filterAffiliate = request('affiliate_id');
        $filterNew       = request('new');

        // Tab Jadwal Dokter: kosong sampai dokter dipilih. Default range tanggal
        // hari ini s/d 30 hari ke depan supaya dokter lihat jadwal yang akan datang.
        // Default exclude status 'cancelled' supaya screenshot bersih (sales bisa
        // override via filter status manual).
        $jadwalEmptyState = false;
        if ($view === 'jadwal') {
            if (! $filterDoctor) {
                $jadwalEmptyState = true;
            }
            if (! $filterDateFrom) {
                $filterDateFrom = now()->toDateString();
            }
            if (! $filterDateTo) {
                $filterDateTo = now()->addDays(30)->toDateString();
            }
        }

        $query = RawCusReservation::with('branch', 'customer', 'doctor', 'services.docService', 'promo.affiliate')
            ->withCount('services');

        // ── Filter cabang berdasarkan session user ───────────────────────────
        // Kalau user punya raw_branch_id di session → paksa filter ke cabang itu
        // Kalau NULL (admin/superadmin) → bebas lihat semua
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch) {
            $query->where('raw_branch_id', $sessionBranch);
            $filterBranch = $sessionBranch; // supaya dropdown filter ikut terkunci
        }

        // Terapkan Filter
        if ($filterSearch) {
            $query->whereHas('customer', function($q) use ($filterSearch) {
                $q->where('name', 'like', "%{$filterSearch}%")
                  ->orWhere('phone', 'like', "%{$filterSearch}%");
            });
        }
        if ($filterBranch) {
            $query->where('raw_branch_id', $filterBranch);
        }
        if ($filterDoctor) {
            $query->where('raw_doctor_id', $filterDoctor);
        }
        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }
        // Tab Jadwal Dokter: default sembunyikan reservasi dibatalkan supaya
        // screenshot bersih. Sales bisa override via filter status manual.
        if ($view === 'jadwal' && ! $filterStatus) {
            $query->where(function ($q) {
                $q->where('status', '!=', RawCusReservation::STATUS_CANCELLED)
                  ->orWhereNull('status');
            });
        }
        if ($filterDateFrom) {
            $query->whereDate('date', '>=', $filterDateFrom);
        }
        if ($filterDateTo) {
            $query->whereDate('date', '<=', $filterDateTo);
        }
        if ($filterPromo) {
            $query->where('raw_promo_id', $filterPromo);
        }
        if ($filterAffiliate) {
            $query->whereHas('promo', function($q) use ($filterAffiliate) {
                $q->where('raw_affiliate_id', $filterAffiliate);
            });
        }
        if ($filterNew) {
            $query->where('created_at', '>=', now()->subHours(6));
        }
        $filterReservationId = request('reservation_id');
        if ($filterReservationId) {
            $query->where('id', $filterReservationId);
        }

        // ── Tab waitlist segregation ─────────────────────────────────────────
        // Tab 'waitlist' = HANYA status waitlist. Tab lain (list/cal/new) =
        // exclude waitlist supaya pasien yg masih antri tidak muncul di list utama.
        if ($view === 'waitlist') {
            $query->where('status', RawCusReservation::STATUS_WAITLIST);
        } else {
            $query->where(function ($q) {
                $q->where('status', '!=', RawCusReservation::STATUS_WAITLIST)
                  ->orWhereNull('status');
            });
        }

        // Clone the query for calendar view before applying pagination
        // (clone DI SINI supaya $calendarQuery ikut exclude waitlist)
        $calendarQuery = clone $query;

        if ($view === 'waitlist') {
            // Waitlist tidak punya start_hour → urutkan FIFO per tanggal
            $reservations = $query->orderBy('date', 'asc')
                ->orderBy('created_at', 'asc')
                ->paginate($perPage);

            // Tandai pasien waitlist yang dokter+tanggalnya KINI punya slot
            // kosong (mis. akibat reservasi lain di-cancel). Hanya halaman aktif
            // yang dihitung supaya tidak berat. resolveBookedRanges sudah
            // mengabaikan reservasi cancelled, jadi slot yg bebas ikut terdeteksi.
            foreach ($reservations as $res) {
                $res->slot_available = false;
                $res->slot_start = null;

                if (! $res->raw_doctor_id || ! $res->date) {
                    continue;
                }

                $serviceIds = $res->services->pluck('raw_doc_service_id')->filter()->all();
                $duration   = $this->patientDuration(
                    $res->raw_doctor_id,
                    $serviceIds,
                    ! empty($res->lainnya_text),
                    $res->raw_branch_id
                );

                $assigned = $this->assignFamilySlots(
                    $res->raw_branch_id,
                    $res->date->format('Y-m-d'),
                    [['key' => 0, 'doctor_id' => $res->raw_doctor_id, 'duration' => $duration]]
                );

                $slot = $assigned[0] ?? ['waiting' => true, 'start' => null];
                if (empty($slot['waiting'])) {
                    $res->slot_available = true;
                    $res->slot_start = $slot['start'] ?? null;
                }
            }
        } elseif ($view === 'jadwal') {
            // Tab Jadwal: 3 mode sort lewat query ?sort_jam=
            //   - (kosong) default: per tanggal kronologis (date ASC, jam ASC)
            //   - asc:  jam pagi dulu (start_hour ASC), tanggal tie-break ASC
            //   - desc: jam malam dulu (start_hour DESC), tanggal tie-break ASC
            // Toggle dipicu lewat klik kolom header JAM di view.
            if ($jadwalEmptyState) {
                $reservations = new \Illuminate\Pagination\LengthAwarePaginator(
                    [], 0, $perPage, 1,
                    ['path' => request()->url(), 'query' => request()->query()]
                );
            } else {
                $sortJam = request('sort_jam');
                if ($sortJam === 'asc') {
                    $reservations = $query->orderBy('start_hour', 'asc')
                        ->orderBy('date', 'asc')
                        ->paginate($perPage);
                } elseif ($sortJam === 'desc') {
                    $reservations = $query->orderBy('start_hour', 'desc')
                        ->orderBy('date', 'asc')
                        ->paginate($perPage);
                } else {
                    $reservations = $query->orderBy('date', 'asc')
                        ->orderBy('start_hour', 'asc')
                        ->paginate($perPage);
                }
            }
        } else {
            // Tab List: default per-tanggal (date DESC). Sort jam (start_hour) bisa
            // di-toggle lewat ?sort_jam= TANPA mengubah grouping per-hari di view —
            // `date DESC` tetap primary sort supaya day-header tetap ter-emit.
            //   - (kosong) default: date DESC, doctor, start_hour DESC (urutan eksisting)
            //   - asc:  date DESC, start_hour ASC (pagi → malam di dalam tiap hari)
            //   - desc: date DESC, start_hour DESC (malam → pagi di dalam tiap hari)
            //
            // Opt-in ?sort_date=today → ubah primary sort jadi "smart date order":
            // hari ini & masa depan ASC di atas (hari ini → besok → lusa → …),
            // masa lampau DESC di bawah (kemarin → 2 hari lalu → …). Dipicu lewat
            // chip "↑ Hari ini di atas" di quick segments bar. Day-header grouping
            // tetap aman karena `date` monoton di tiap segmen; transisi future→past
            // cuma satu pergantian tanggal yang otomatis memunculkan header baru.
            $sortDate = request('sort_date');
            if ($sortDate === 'today') {
                $today = now()->toDateString();
                $query->orderByRaw('CASE WHEN `date` >= ? THEN 0 ELSE 1 END ASC', [$today])
                      ->orderByRaw('CASE WHEN `date` >= ? THEN `date` END ASC',   [$today])
                      ->orderByRaw('CASE WHEN `date` <  ? THEN `date` END DESC',  [$today]);
            } else {
                $query->orderBy('date', 'desc');
            }

            $sortJam = request('sort_jam');
            if ($sortJam === 'asc') {
                $reservations = $query->orderBy('start_hour', 'asc')
                    ->orderBy('end_hour', 'asc')
                    ->paginate($perPage);
            } elseif ($sortJam === 'desc') {
                $reservations = $query->orderBy('start_hour', 'desc')
                    ->orderBy('end_hour', 'desc')
                    ->paginate($perPage);
            } else {
                $reservations = $query->orderBy('raw_doctor_id')
                    ->orderBy('start_hour', 'desc')
                    ->paginate($perPage);
            }
        }

        // Kalau user terkunci ke 1 cabang, dropdown hanya tampilkan cabang itu
        $branches = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        if ($sessionBranch) {
            $doctorIds = RawDoctorSchedule::where('raw_branch_id', $sessionBranch)
                ->distinct()->pluck('raw_doctor_id');
            $doctors = RawDoctor::whereIn('id', $doctorIds)->orderBy('name')->get();
        } else {
            $doctors = RawDoctor::orderBy('name')->get();
        }

        $statuses = RawCusReservation::statusOptions();
        $calendarReservations = $calendarQuery->orderBy('date', 'asc')->orderBy('raw_doctor_id')->orderBy('start_hour', 'asc')->get();

        $promos     = \App\Models\RawPromo::orderBy('promo_code')->get();
        $affiliates = \App\Models\RawAffiliate::orderBy('name')->get();

        // Badge count untuk tab Waiting List (di-scope ke session branch)
        $waitlistCount = RawCusReservation::query()
            ->when($sessionBranch, fn ($q) => $q->where('raw_branch_id', $sessionBranch))
            ->where('status', RawCusReservation::STATUS_WAITLIST)
            ->count();

        // Badge count untuk tab Reservasi Baru (created < 6 jam, exclude waitlist)
        $newCount = RawCusReservation::query()
            ->when($sessionBranch, fn ($q) => $q->where('raw_branch_id', $sessionBranch))
            ->where('created_at', '>=', now()->subHours(6))
            ->where(function ($q) {
                $q->where('status', '!=', RawCusReservation::STATUS_WAITLIST)
                  ->orWhereNull('status');
            })
            ->count();

        // Badge count untuk tombol Impor Sheets (hanya di-hitung untuk Super
        // Admin karena tombolnya cuma muncul untuk role itu). Defensive:
        // kalau tabel staging belum di-migrate, fallback ke 0.
        $importPendingCount = 0;
        if (isSuperAdmin()) {
            try {
                $importPendingCount = \App\Models\RawCusReservationStaging::query()
                    ->when($sessionBranch, fn ($q) => $q->where('raw_branch_id', $sessionBranch))
                    ->where('state', 'pending')
                    ->count();
            } catch (\Throwable $e) {
                $importPendingCount = 0;
            }
        }

        // Pre-render data "Kelola Halaman" — hanya untuk admin yang boleh kelola.
        // Pakai DB facade karena AccessPath / GroupPath models pakai string PK
        // & legacy schema (lihat database/migrations/*_access_path & *_group_path).
        // Daftar group yang punya akses ke rawreservation lewat group_access JOIN
        // access_path WHERE access_path.link = 'rawreservation'. Sertakan juga
        // kolom theme dari group_path untuk pre-fill dropdown tema.
        $accessModeGroups = [];
        if (canManagePageAccess()) {
            $rows = \Illuminate\Support\Facades\DB::table('group_access')
                ->join('access_path', 'group_access.access_id', '=', 'access_path.id')
                ->join('group_path',  'group_access.group_id',  '=', 'group_path.id')
                ->where('access_path.link', 'rawreservation')
                ->select('group_path.id as id', 'group_path.nama as nama', 'group_path.theme as theme')
                ->orderBy('group_path.nama')
                ->get();

            $currentModes = \App\Models\GroupPageAccessMode::where('page_key', 'rawreservation')
                ->pluck('access_mode', 'group_id');

            foreach ($rows as $row) {
                $accessModeGroups[] = (object) [
                    'id'    => $row->id,
                    'nama'  => $row->nama,
                    'mode'  => $currentModes[$row->id] ?? \App\Models\GroupPageAccessMode::MODE_FULL,
                    'theme' => $row->theme ?: 'theme-default',
                ];
            }
        }

        // ── Tab "Pasien Baru" (lead dari form publik) ────────────────────────
        // Query RawCustomer (BUKAN reservation): lead source_app='pasien_baru',
        // belum 'batal', DAN belum punya reservasi. Begitu sales membuatkan
        // reservasi, lead otomatis hilang dari tab (whereDoesntHave).
        // Cabang: user yang terkunci cabang pakai session; Super Admin / Admin
        // General ikut dropdown filter cabang seperti tab reservasi.
        $leadBase = RawCustomer::query()
            ->where('source_app', RawCustomer::SOURCE_PASIEN_BARU)
            ->where(function ($q) {
                $q->whereNull('lead_status')->orWhere('lead_status', '!=', 'batal');
            })
            ->whereDoesntHave('reservations')
            ->when($sessionBranch, fn ($q) => $q->where('raw_branch_id', $sessionBranch))
            ->when(! $sessionBranch && $filterBranch, fn ($q) => $q->where('raw_branch_id', $filterBranch));

        // Badge dihitung SEBELUM filter pencarian ditempel, supaya angkanya
        // tidak ikut berubah saat user mengetik di kolom cari pada tab lain.
        $pasienBaruCount = $isViewOnly ? 0 : (clone $leadBase)->count();

        $leads = $view === 'pasienbaru'
            ? $leadBase->with(['branch', 'promo'])
                ->when($filterSearch, function ($q) use ($filterSearch) {
                    $q->where(function ($qq) use ($filterSearch) {
                        $qq->where('name', 'like', "%{$filterSearch}%")
                           ->orWhere('phone', 'like', "%{$filterSearch}%");
                    });
                })
                ->orderByDesc('tanggal_daftar')
                // Belum pernah di-follow-up (NULL) naik ke atas dalam hari yang
                // sama; tie-break `id` supaya pagination stabil.
                ->orderByRaw('lead_followed_up_at IS NULL DESC')
                ->orderByDesc('lead_followed_up_at')
                ->orderBy('id')
                ->paginate($perPage)
                ->withQueryString()
            : null;

        /*
         * Penanda "Ortho" di kolom Pasien: reservasi yang punya baris
         * ort_control, yaitu yang lahir dari Tracking Pasien Ortho.
         *
         * ⚠ Dikumpulkan lewat whereIn atas id HALAMAN INI saja, bukan JOIN.
         *   Di prod collation ort_* dan raw_* beda keluarga, dan JOIN lintas
         *   keluarga meledak #1267. Membandingkan kolom dengan daftar literal
         *   aman karena literalnya ikut collation kolomnya.
         *
         * Seluruhnya dibungkus supaya halaman Reservasi tidak pernah gagal
         * hanya karena modul ortho belum di-deploy di suatu environment.
         */
        $ortoIds = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ort_control') && $reservations) {
                $idHalaman = collect($reservations->items())->pluck('id')->filter()->all();
                if ($idHalaman) {
                    $ortoIds = array_flip(
                        \Illuminate\Support\Facades\DB::table('ort_control')
                            ->whereIn('reservation_id', $idHalaman)
                            ->pluck('reservation_id')
                            ->all()
                    );
                }
            }
        } catch (\Throwable $e) {
            $ortoIds = [];
        }

        return view('crud.reservation.list', compact(
            'reservations',
            'ortoIds',
            'leads',
            'pasienBaruCount',
            'filterSearch',
            'filterBranch',
            'filterDoctor',
            'filterStatus',
            'filterDateFrom',
            'filterDateTo',
            'filterPromo',      // new
            'filterAffiliate',  // new
            'branches',
            'doctors',
            'statuses',
            'calendarReservations',
            'promos',           // new
            'affiliates',        // new
            'perPage',
            'view',
            'waitlistCount',
            'newCount',
            'importPendingCount',
            'jadwalEmptyState',
            'isViewOnly',
            'accessModeGroups'
        ));
    }

    /**
     * POST /rawreservation/access-modes — simpan mode akses & tema warna per
     * role untuk halaman Raw Reservation. Hanya untuk Super Admin / Admin General.
     *
     * Request body:
     *   - modes[<group_id>]  = 'full' | 'view_only'
     *   - themes[<group_id>] = 'theme-default' | 'theme-blue' | dst (lihat availableThemes())
     */
    public function saveAccessModes()
    {
        if (! canManagePageAccess()) {
            abort(403, 'Hanya Super Admin / Admin General yang boleh mengatur mode akses.');
        }

        // ── Mode akses (full / view_only) ──
        $modes = (array) request()->input('modes', []);
        $clean = [];
        foreach ($modes as $gid => $mode) {
            $gid = (string) $gid;
            if ($gid === '') continue;
            $clean[$gid] = $mode === \App\Models\GroupPageAccessMode::MODE_VIEW_ONLY
                ? \App\Models\GroupPageAccessMode::MODE_VIEW_ONLY
                : \App\Models\GroupPageAccessMode::MODE_FULL;
        }
        \App\Models\GroupPageAccessMode::bulkSet('rawreservation', $clean);

        // ── Tema warna brand per role (global, disimpan di group_path.theme) ──
        $themes = (array) request()->input('themes', []);
        $validThemes = array_keys(availableThemes());
        foreach ($themes as $gid => $theme) {
            $gid = (string) $gid;
            if ($gid === '') continue;
            $theme = in_array($theme, $validThemes, true) ? $theme : 'theme-default';
            \Illuminate\Support\Facades\DB::table('group_path')
                ->where('id', $gid)
                ->update(['theme' => $theme]);
        }

        // Kalau admin yang lagi save adalah anggota salah satu role yang
        // theme-nya berubah, sync session-nya juga supaya tema langsung apply
        // tanpa harus logout-login.
        $myGroupId = session('group_id');
        if ($myGroupId && isset($themes[$myGroupId])) {
            $newTheme = in_array($themes[$myGroupId], $validThemes, true)
                ? $themes[$myGroupId]
                : 'theme-default';
            session(['group_theme' => $newTheme]);
        }

        return redirect()
            ->route('rawreservation')
            ->with('success', 'Pengaturan akses & tema halaman Raw Reservation berhasil disimpan.');
    }

    /**
     * Helper internal: abort kalau user adalah view-only. Dipakai di semua
     * method mutasi (create/store/update/destroy/inline/klaim/service).
     */
    private function abortIfViewOnly(): void
    {
        if (isReservationViewOnly()) {
            abort(403, 'Akses Anda hanya view-only — tidak dapat melakukan perubahan.');
        }
    }

    /**
     * Guard anti double-book: true bila rentang [start,end] bentrok dengan
     * reservasi lain pada dokter+tanggal yang sama. Reuse resolveBookedRanges()
     * (trait ResolvesDoctorAvailability) supaya konsisten dengan slot-grid:
     * skip reservasi DIBATALKAN, dan exclude reservasi yang sedang diedit.
     */
    private function slotConflict($doctorId, $date, $start, $end, $excludeId = null): bool
    {
        if (!$doctorId || !$date || !$start || !$end) {
            return false;
        }
        $toMin = function ($t) {
            $c = \Carbon\Carbon::parse($t);
            return $c->hour * 60 + $c->minute;
        };
        $s = $toMin($start);
        $e = $toMin($end);
        foreach ($this->resolveBookedRanges($doctorId, $date, 'raw_cus_reservation', $excludeId) as $r) {
            if ($s < $toMin($r['end']) && $e > $toMin($r['start'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Apakah request ini mengharap response JSON (mis. submit dari drawer via
     * AJAX). Kalau ya, store()/update() balas JSON; kalau tidak, tetap redirect
     * halaman seperti biasa (fallback page standalone).
     */
    private function wantsJsonResponse(): bool
    {
        return request()->ajax() || request()->wantsJson();
    }

    /**
     * Error validasi AJAX-aware: 422 JSON {ok:false, errors} untuk drawer,
     * selain itu back()->withInput()->withErrors() (fallback halaman).
     */
    private function validationErrorResponse(array $errors)
    {
        if ($this->wantsJsonResponse()) {
            return response()->json(['ok' => false, 'errors' => $errors], 422);
        }
        return back()->withInput()->withErrors($errors);
    }

    // ════════════════════════════════════════════════════════════════════════
    // JEJAK RESERVASI — alasan batal + riwayat perubahan
    // (docs/handoff/data-reservasi-2026-09-11.md §7.4, §7.5)
    // ════════════════════════════════════════════════════════════════════════

    /** Kolom yang perubahannya layak masuk riwayat. Sisanya derau. */
    private const JEJAK_FIELDS = [
        'status', 'date', 'start_hour', 'end_hour',
        'raw_doctor_id', 'raw_branch_id', 'cancel_reason', 'cancel_reason_note',
    ];

    /**
     * Validasi alasan batal. Dipanggil dari SEMUA layar yang bisa membatalkan
     * (form Edit + inline) — memagari cuma satu di antaranya sama saja dengan
     * tidak memagari: 652 pembatalan tanpa alasan lahir dari lubang seperti itu.
     *
     * Alasan diwajibkan hanya pada TRANSISI masuk ke `cancelled`. Menyunting
     * ulang reservasi yang sudah batal (termasuk 652 baris lama yang alasannya
     * tak mungkin diisi mundur) tidak dipaksa — memaksanya cuma memblokir
     * pekerjaan lain di baris yang sama.
     *
     * @return array<string,string>|null  errors, atau null kalau lolos
     */
    private function validateCancelReason(RawCusReservation $reservation, ?string $newStatus): ?array
    {
        $masukBatal = $newStatus === RawCusReservation::STATUS_CANCELLED
            && $reservation->status !== RawCusReservation::STATUS_CANCELLED;

        if (! $masukBatal) {
            return null;
        }

        $reason = trim((string) request('cancel_reason'));
        $note   = trim((string) request('cancel_reason_note'));

        if ($reason === '') {
            return ['cancel_reason' => 'Pilih alasan pembatalan — reservasi tidak bisa dibatalkan tanpa alasan.'];
        }
        if (! array_key_exists($reason, RawCusReservation::cancelReasonOptions())) {
            return ['cancel_reason' => 'Alasan pembatalan tidak dikenal.'];
        }
        // "Lainnya" tanpa penjelasan = sama tak terpakainya dengan tidak ada
        // alasan sama sekali; itu justru jadi tempat sampah yang dipilih semua
        // orang karena paling cepat.
        if ($reason === RawCusReservation::CANCEL_LAINNYA && $note === '') {
            return ['cancel_reason_note' => 'Alasan "Lainnya" wajib dijelaskan singkat.'];
        }

        return null;
    }

    /**
     * Nilai kolom alasan batal untuk sebuah perpindahan status.
     * Keluar dari `cancelled` MENGOSONGKAN alasannya — alasan batal yang
     * menempel di reservasi yang sudah aktif lagi adalah kebohongan diam-diam.
     */
    private function cancelReasonPayload(RawCusReservation $reservation, ?string $newStatus): array
    {
        if ($newStatus !== RawCusReservation::STATUS_CANCELLED) {
            return $reservation->cancel_reason !== null || $reservation->cancel_reason_note !== null
                ? ['cancel_reason' => null, 'cancel_reason_note' => null]
                : [];
        }

        $reason = trim((string) request('cancel_reason'));
        if ($reason === '') {
            return []; // reservasi yang memang sudah batal & tak diubah alasannya
        }

        $note = trim((string) request('cancel_reason_note'));
        return [
            'cancel_reason'      => $reason,
            'cancel_reason_note' => $note !== '' ? mb_substr($note, 0, 255) : null,
        ];
    }

    /**
     * Catat perubahan reservasi ke `audit_log`.
     *
     * SENGAJA tidak bikin tabel baru: audit_log sudah punya persis kolom yang
     * dibutuhkan (user_id, user_name, action, model_type, model_id, description,
     * old_values, new_values, ip_address, created_at) dan sudah dipakai modul
     * lain, jadi satu layar riwayat kelak bisa membaca semuanya sekaligus.
     *
     * ⚠️ Hanya perubahan SESUDAH hari fitur ini naik yang tercatat. audit_log
     * punya nol baris reservasi — 6.400 reservasi lama tak bisa diisi mundur.
     *
     * Audit gagal ≠ aksi bisnis gagal (pola sama dgn AuditTrailObserver::aman):
     * kalau insert log error, reservasinya tetap tersimpan.
     */
    private function catatJejak(RawCusReservation $reservation, array $sebelum, string $action = 'update'): void
    {
        try {
            $berubah = [];
            foreach (self::JEJAK_FIELDS as $f) {
                $lama = $sebelum[$f] ?? null;
                $baru = $reservation->getAttribute($f);
                // date di-cast Carbon → bandingkan sebagai string supaya
                // "2026-09-11" == Carbon(2026-09-11) tidak terbaca berubah.
                $lamaS = $lama instanceof \DateTimeInterface ? $lama->format('Y-m-d H:i:s') : (string) $lama;
                $baruS = $baru instanceof \DateTimeInterface ? $baru->format('Y-m-d H:i:s') : (string) $baru;
                if ($lamaS !== $baruS) {
                    $berubah[$f] = ['dari' => $lamaS === '' ? null : $lamaS, 'jadi' => $baruS === '' ? null : $baruS];
                }
            }

            if ($action === 'update' && ! $berubah) {
                return; // penyuntingan yang tak menggeser jadwal/status: bukan jejak
            }

            \App\Models\AuditLog::create([
                'user_id'    => session('id'),
                'user_name'  => session('name'),
                'action'     => $action,
                'model_type' => 'RawCusReservation',
                'model_id'   => $reservation->getKey(),
                'description'=> $this->jejakDeskripsi($reservation, $berubah, $action),
                'old_values' => array_map(fn ($v) => $v['dari'], $berubah) ?: null,
                'new_values' => array_map(fn ($v) => $v['jadi'], $berubah) ?: null,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Deskripsi awam — supaya riwayat terbaca tanpa membuka JSON-nya. */
    private function jejakDeskripsi(RawCusReservation $reservation, array $berubah, string $action): string
    {
        $pasien = $reservation->customer?->name ?: 'Pasien';

        if ($action === 'delete') {
            return 'Reservasi dihapus: ' . $pasien;
        }

        $kata = [];
        if (isset($berubah['status'])) {
            $opts  = RawCusReservation::statusOptions();
            $label = fn ($s) => $opts[$s]['label'] ?? ($s ?: '-');
            $t = 'status ' . $label($berubah['status']['dari']) . ' → ' . $label($berubah['status']['jadi']);
            if ($berubah['status']['jadi'] === RawCusReservation::STATUS_CANCELLED && $reservation->cancel_reason) {
                $t .= ' (' . $reservation->cancel_reason_label . ')';
            }
            $kata[] = $t;
        }
        if (isset($berubah['date']))          $kata[] = 'tanggal ' . substr((string) $berubah['date']['dari'], 0, 10) . ' → ' . substr((string) $berubah['date']['jadi'], 0, 10);
        if (isset($berubah['start_hour']))    $kata[] = 'jam ' . ($berubah['start_hour']['dari'] ?: '-') . ' → ' . ($berubah['start_hour']['jadi'] ?: '-');
        if (isset($berubah['raw_doctor_id'])) $kata[] = 'dokter diganti';
        if (isset($berubah['raw_branch_id'])) $kata[] = 'cabang dipindah';

        return 'Reservasi ' . $pasien . ': ' . ($kata ? implode(', ', $kata) : 'diubah');
    }

    public function create()
    {
        $this->abortIfViewOnly();

        // ── Bridge dari staging row (Impor Sheets) ──
        // Kalau dipanggil dgn ?staging_id=X, pre-fill form dgn data dari staging
        // lalu redirect ke URL bersih supaya old() ke-populate via flashInput.
        // Hidden field `staging_id` akan ikut ter-submit → store() akan tandai
        // staging row promoted setelah reservasi sukses dibuat.
        if (($stagingId = request('staging_id')) && ! request()->session()->has('_old_input')) {
            $staging = \App\Models\RawCusReservationStaging::find($stagingId);
            if ($staging) {
                // Cari customer existing by phone (kalau ada match)
                $matchedCustomer = null;
                if ($staging->patient_phone) {
                    $matchedCustomer = RawCustomer::where('phone', $staging->patient_phone)->first();
                }

                // ── Auto-create RawCustomer kalau pasien baru & data cukup ──
                // Tujuan: pengguna tidak perlu klik "Tambah pasien baru di
                // nomor ini" manual di form reservasi — record langsung dibuat
                // dari data staging (yg sudah user lengkapi di drawer
                // "Edit Staging Row"). Idempotent: re-klik = re-use customer
                // yg sama via phone match → no duplicate.
                $autoCreatedCustomer = false;
                if (! $matchedCustomer && $staging->patient_name && $staging->patient_phone) {
                    try {
                        $matchedCustomer = RawCustomer::create([
                            'id'               => (string) \Illuminate\Support\Str::uuid(),
                            'source_app'       => 'csv_import',
                            'channel'          => 'csv_import',
                            'name'             => $staging->patient_name,
                            'phone'            => $staging->patient_phone,
                            'email'            => $staging->patient_email,
                            'nik'              => $staging->patient_nik,
                            'raw_branch_id'    => $staging->raw_branch_id,
                            'tanggal_daftar'   => now()->toDateString(),
                            'pasien_baru_lama' => 'baru',
                        ]);
                        $autoCreatedCustomer = true;
                    } catch (\Throwable $e) {
                        // Kalau gagal create (mis. constraint), fall back:
                        // user bisa pakai tombol "Tambah pasien baru di nomor ini"
                        // di form secara manual.
                        $matchedCustomer = null;
                    }
                }

                $prefill = [
                    'staging_id'      => $stagingId,
                    'raw_branch_id'   => $staging->raw_branch_id,
                    'raw_doctor_id'   => $staging->resolved_raw_doctor_id,
                    'date'            => $staging->treatment_date?->format('Y-m-d'),
                    'start_hour'      => $staging->start_hour ? substr($staging->start_hour, 0, 5) : '',
                    'keterangan'      => $staging->tindakan_text,
                    'is_waiting'      => (int) $staging->is_waiting,
                ];
                if ($matchedCustomer) {
                    $prefill['raw_customer_id'] = $matchedCustomer->id;
                }

                // Pass info pasien staging buat banner di form. Status pasien:
                //   - has_customer=true → pasien sudah ada / atau auto-created
                //   - auto_created=true → record baru dibuat barusan
                session()->flash('_staging_source', [
                    'staging_id'    => $stagingId,
                    'patient_name'  => $staging->patient_name,
                    'patient_phone' => $staging->patient_phone,
                    'has_customer'  => (bool) $matchedCustomer,
                    'auto_created'  => $autoCreatedCustomer,
                ]);

                session()->flashInput($prefill);
                // Preserve ?drawer=1 lewat redirect — kalau request datang dari
                // rrOpenFormDrawer() (AJAX fetch dgn flag drawer), bare layout
                // harus tetap dipakai supaya yg ke-inject hanya form, bukan
                // shell penuh (sidebar/header).
                $redirectParams = request()->boolean('drawer') ? ['drawer' => 1] : [];
                return redirect()->route('rawreservation.new', $redirectParams);
            }
        }

        // ── Bridge dari kartu Lead "Pasien Baru" ──
        // Dipanggil ?lead_id=X dari tab Pasien Baru → pre-fill pasien + cabang
        // + keluhan (catatan) + kode promo ke form reservasi. Pasien sudah
        // tersimpan sejak submit form publik, jadi cukup pre-select.
        // Idempotent via flashInput/old().
        if (($leadId = request('lead_id')) && ! request()->session()->has('_old_input')) {
            $lead = RawCustomer::with('promo')
                ->where('id', $leadId)
                ->where('source_app', RawCustomer::SOURCE_PASIEN_BARU)
                ->first();
            if ($lead) {
                session()->flashInput([
                    'raw_customer_id' => $lead->id,
                    'raw_branch_id'   => $lead->raw_branch_id,
                    'keterangan'      => $lead->catatan,
                    // Kode promo yang diisi pasien di form publik ikut terpilih
                    // di dropdown "Gunakan Kode Referral". Kalau promonya sudah
                    // dinonaktifkan, dropdown tidak memuatnya → pilihan jatuh ke
                    // kosong, dan kodenya tetap terbaca di banner lead di atas.
                    'raw_promo_id'    => $lead->raw_promo_id,
                ]);
                session()->flash('_lead_source', [
                    'name'    => $lead->name,
                    'phone'   => $lead->phone,
                    'keluhan' => $lead->catatan,
                    'promo'   => $lead->promo?->promo_code,
                ]);
                $redirectParams = request()->boolean('drawer') ? ['drawer' => 1] : [];
                return redirect()->route('rawreservation.new', $redirectParams);
            }
        }

        // ── Bridge dari Tracking Ortho ──
        // Dipanggil ?ort_control_id=X dari lembar "Buat Reservasi" di modul
        // Ortho. Pasien, cabang, dan tanggal kontrolnya sudah diketahui, jadi
        // form ini dibuka ter-isi — Sales tinggal memilih jam, dokter, dan
        // layanan. Hidden `ort_control_id` ikut ter-submit; store() memakainya
        // untuk menautkan reservasi balik ke baris ortho-nya.
        //
        // Idempotent lewat flashInput/old(), pola sama dengan dua bridge di atas.
        if (($ortControlId = request('ort_control_id')) && ! request()->session()->has('_old_input')) {
            $ortOk = \Illuminate\Support\Facades\Schema::hasTable('ort_control')
                  && \Illuminate\Support\Facades\Schema::hasTable('ort_case');

            $ortCtl = $ortOk ? \App\Models\OrtControl::find($ortControlId) : null;
            $ortCase = $ortCtl ? \App\Models\OrtCase::find($ortCtl->ort_case_id) : null;

            // Tanpa raw_customer_id, form tidak bisa di-prefill pasiennya —
            // biarkan terbuka kosong daripada setengah terisi dan menyesatkan.
            if ($ortCase && $ortCase->raw_customer_id) {
                $ortBranchId = app(\App\Services\Ortho\OrtBranches::class)->branchId((string) $ortCtl->place_id);

                // ort_case.doctor_ref_id itu kode Omnicare, BUKAN raw_doctor.id.
                // Dioper mentah, form menerima dokter yang tidak ada, lalu JS-nya
                // menyimpulkan "dokter ini tidak bertugas di cabang itu" dan
                // MENGOSONGKAN pilihan cabang — prefill-nya malah bikin rusak.
                //
                // Jembatannya raw_doctor.omnicare_doc_id. Brief Lampiran A
                // melarang tegas mencocokkan dokter lewat NAMA ("sudah terbukti
                // nyasar ke rekam dokter lain"), jadi kode itu yang dipakai
                // lebih dulu; nama cuma cadangan untuk dokter yang kolom
                // omnicare_doc_id-nya belum terisi.
                //
                // ⚠ Kandidatnya SELALU jamak, tidak pernah ->value('id') dari
                //   baris pertama. raw_doctor bisa punya beberapa baris bernama
                //   sama (lokal: 2), dan versi lama mengambil yang pertama lalu
                //   menguji baris ITU — kalau yang pertama kebetulan baris yang
                //   salah, kolom dokter dikosongkan diam-diam padahal ada baris
                //   lain bernama sama yang memang bertugas di sana. Jadwal
                //   cabang dipakai untuk MEMILIH di antara kandidat, bukan
                //   untuk memveto satu tebakan.
                $ortDoctorId = null;
                if ($ortBranchId) {
                    // Pilih dokter yang benar-benar bertugas di cabang itu, dari
                    // sekumpulan kandidat. Mengembalikan null kalau tak satu pun
                    // bertugas — lebih baik kolomnya kosong daripada terisi
                    // dokter yang tidak praktek di sana.
                    $yangBertugas = fn ($ids) => $ids->isEmpty() ? null
                        : RawDoctorSchedule::where('raw_branch_id', $ortBranchId)
                            ->whereIn('raw_doctor_id', $ids)
                            ->value('raw_doctor_id');

                    if ($ortCase->doctor_ref_id) {
                        $ortDoctorId = $yangBertugas(
                            RawDoctor::where('omnicare_doc_id', $ortCase->doctor_ref_id)->pluck('id')
                        );
                    }

                    // Cadangan dijalankan kalau jalur kode tidak MENGHASILKAN
                    // dokter — bukan cuma kalau kandidatnya kosong. Kode bisa
                    // menunjuk baris raw_doctor yang ada tapi tak punya jadwal
                    // di cabang itu (lokal: baris seed dummy Komisi Dokter);
                    // berhenti di situ berarti membuang dokter asli yang
                    // bernama sama dan memang praktek di sana.
                    if (! $ortDoctorId && $ortCase->doctor_name) {
                        $ortDoctorId = $yangBertugas(
                            RawDoctor::where('name', trim($ortCase->doctor_name))->pluck('id')
                        );
                    }
                }

                session()->flashInput([
                    'raw_customer_id' => $ortCase->raw_customer_id,
                    'raw_branch_id'   => $ortBranchId,
                    'raw_doctor_id'   => $ortDoctorId,
                    'date'            => request('tanggal') ?: optional($ortCtl->sch_date)->toDateString(),
                    'keterangan'      => trim('Kontrol ortho ke-' . $ortCtl->seq
                                          . ($ortCase->bracket_type ? ' · ' . $ortCase->bracket_type : '')
                                          . ($ortCase->bracket_arch ? ' · ' . $ortCase->bracket_arch : '')
                                          . (request('catatan') ? ' · ' . trim((string) request('catatan')) : '')),
                    'ort_control_id'  => $ortCtl->id,
                ]);

                session()->flash('_ortho_source', [
                    'nama'    => $ortCase->patient_name,
                    'pid'     => $ortCase->omnicare_pid,
                    'seq'     => $ortCtl->seq,
                    'jadwal'  => optional($ortCtl->sch_date)->translatedFormat("j M 'y"),
                    'dokter'  => $ortCase->doctor_name,
                    'bracket' => $ortCase->bracket_type,
                ]);

                $redirectParams = request()->boolean('drawer') ? ['drawer' => 1] : [];
                return redirect()->route('rawreservation.new', $redirectParams);
            }
        }


        $sessionBranch = session('raw_branch_id');

        // Fallback Database
        $userId = session('id');
        if (!$sessionBranch && $userId) {
            $user = \App\Models\Users::find($userId);
            $sessionBranch = $user->raw_branch_id ?? null;
        }

        // Kalau user terkunci ke 1 cabang, dropdown hanya tampilkan cabang itu
        $branches  = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        // Doctor: kalau user terkunci cabang → hanya dokter yang punya schedule di cabang itu.
        // Pakai pola sama dengan index() supaya konsisten.
        // Form reservasi BARU hanya menampilkan dokter aktif (->active()).
        if ($sessionBranch) {
            $doctorIds = RawDoctorSchedule::where('raw_branch_id', $sessionBranch)
                ->distinct()->pluck('raw_doctor_id');
            $doctors = RawDoctor::whereIn('id', $doctorIds)->active()->orderBy('name')->get();
        } else {
            $doctors = RawDoctor::active()->orderBy('name')->get();
        }

        // Customer: cross-cabang — staff cabang mana pun bisa lihat & pilih
        // pasien dari cabang lain (cegah duplikasi saat pasien pindah cabang).
        // Pre-load ini hanya untuk old() repopulation; lookup utama via
        // select2 AJAX ke CustomerController::search yang juga cross-cabang.
        $customers = RawCustomer::orderBy('name')->get();

        return view('crud.reservation.new', compact('branches', 'doctors', 'customers'));
    }

    /**
     * Daftarkan pasien baru DI sebuah nomor (bisa nomor yang sudah punya
     * pasien lain). Dipakai tombol "+ Pasien baru di nomor ini" pada form
     * tambah/edit reservasi. Return JSON {id,text} untuk di-inject ke
     * select2 customer.
     */
    public function storeQuickPatient()
    {
        $this->abortIfViewOnly();

        // Normalisasi dulu baru validasi — sales sering menempel nomor dari
        // WhatsApp dalam format "+62812…" / "0812-3456-7890"; regex di bawah
        // hanya penjaga akhir. Sama persis dengan PasienBaruController::store().
        request()->merge(['phone' => Phone::normalize(request('phone')) ?? request('phone')]);

        $data = request()->validate([
            'name'             => 'required|max:255',
            'phone'            => 'required|regex:/^0[0-9]{9,14}$/',
            'email'            => 'nullable|email|max:100',
            'jenis_kelamin'    => 'nullable|in:L,P',
            'tanggal_lahir'    => 'nullable|date|before:today',
            'relasi'           => 'nullable|in:diri_sendiri,pasangan,anak,orang_tua,lainnya',
            'nik'              => 'nullable|string|max:20',
            'alamat'           => 'nullable|string|max:65535',
            'tempat_lahir'     => 'nullable|string|max:255',
            'source_app'       => 'nullable|string|max:255',
            'pasien_baru_lama' => 'nullable|in:baru,lama',
        ], [
            'name.required'  => 'Nama pasien wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.regex'    => 'Nomor HP tidak valid. Contoh: 081234567890.',
        ]);

        $sessionBranch = session('raw_branch_id');
        $phone = $data['phone'];

        $customer = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $phone, $sessionBranch) {
            $contact = $this->resolveContact($phone, $data['name'], 'admin', 'admin');

            return RawCustomer::create([
                'raw_contact_id'   => $contact->id,
                'relasi'           => $data['relasi'] ?? 'diri_sendiri',
                'name'             => $data['name'],
                'phone'            => $phone,
                'email'            => $data['email'] ?? null,
                'jenis_kelamin'    => $data['jenis_kelamin'] ?? null,
                'tanggal_lahir'    => $data['tanggal_lahir'] ?? null,
                'nik'              => $data['nik'] ?? null,
                'alamat'           => $data['alamat'] ?? null,
                'tempat_lahir'     => $data['tempat_lahir'] ?? null,
                'tanggal_daftar'   => now()->toDateString(),
                'pasien_baru_lama' => $data['pasien_baru_lama'] ?? 'baru',
                'raw_branch_id'    => $sessionBranch ?: null,
                'source_app'       => $data['source_app'] ?? 'admin',
                'channel'          => 'admin',
            ]);
        });

        $relLabel = [
            'diri_sendiri' => 'Diri sendiri', 'pasangan' => 'Pasangan',
            'anak' => 'Anak', 'orang_tua' => 'Orang tua', 'lainnya' => 'Lainnya',
        ][$customer->relasi] ?? null;

        return response()->json([
            'id'   => $customer->id,
            'text' => $customer->name
                . ($relLabel ? ' · ' . $relLabel : '')
                . ' (' . $customer->phone . ')',
        ]);
    }

    /**
     * PATCH /rawreservation/lead/{id}/status — ubah status follow-up lead
     * "Pasien Baru" (baru → dihubungi / batal). Dipakai tombol di kartu tab.
     */
    public function updateLeadStatus($id)
    {
        $this->abortIfViewOnly();
        $data = request()->validate([
            'lead_status' => 'required|in:baru,dihubungi,dijadwalkan,batal',
        ]);

        // Scope cabang: user yang terkunci cabang tidak boleh mengubah status
        // lead cabang lain walau tahu UUID-nya.
        $lead = RawCustomer::where('id', $id)
            ->where('source_app', RawCustomer::SOURCE_PASIEN_BARU)
            ->when(session('raw_branch_id'), fn ($q, $b) => $q->where('raw_branch_id', $b))
            ->firstOrFail();

        $lead->update([
            'lead_status'         => $data['lead_status'],
            'lead_followed_up_at' => now(),
            'lead_followed_up_by' => session('id'),
        ]);

        if ($this->wantsJsonResponse()) {
            return response()->json(['ok' => true, 'lead_status' => $lead->lead_status]);
        }
        return back()->with('success', 'Status lead diperbarui.');
    }

    public function store()
    {
        $this->abortIfViewOnly();
        $isWaiting = (int) request('is_waiting', 0) === 1;

        request()->validate([
            'raw_branch_id'      => 'required',
            'raw_customer_id'    => 'required',
            'raw_doctor_id'      => 'required',
            'date'               => 'nullable|date|required_unless:is_waiting,1',
            'start_hour'         => 'required_unless:is_waiting,1',
            'end_hour'           => 'required_unless:is_waiting,1',
            'keterangan'         => 'nullable|string|max:65535',
            'raw_promo_id'       => 'nullable|exists:raw_promo,id',
            'marketing_promo_id' => 'nullable|exists:marketing_promo,id',
        ]);

        // Validasi scope kode referral vs services yang dipilih.
        if (request('raw_promo_id')) {
            $scopeError = $this->validateReferralScopeMatch(
                request('raw_promo_id'),
                request('services', [])
            );
            if ($scopeError) {
                return $this->validationErrorResponse(['raw_promo_id' => $scopeError]);
            }
        }

        // Guard server-side anti double-book (benteng asli di lapisan data — tak
        // bergantung slot-grid frontend). Reuse resolveBookedRanges() → konsisten.
        if (!$isWaiting && $this->slotConflict(request('raw_doctor_id'), request('date'), request('start_hour'), request('end_hour'))) {
            return $this->validationErrorResponse(['start_hour' => 'Jam tersebut sudah terisi untuk dokter ini pada tanggal itu. Silakan pilih slot lain.']);
        }

        $reservationId = null;

        \Illuminate\Support\Facades\DB::transaction(function () use (&$reservationId, $isWaiting) {
            $reservation = RawCusReservation::create([
                'raw_branch_id'      => request('raw_branch_id'),
                'raw_customer_id'    => request('raw_customer_id'),
                'raw_doctor_id'      => request('raw_doctor_id'),
                'date'               => request('date') ?: null, // waitlist boleh tanpa tanggal → NULL
                'start_hour'         => $isWaiting ? null : request('start_hour'),
                'end_hour'           => $isWaiting ? null : request('end_hour'),
                'keterangan'         => request('keterangan'),
                'is_waiting'         => $isWaiting ? 1 : 0,
                'status'             => $isWaiting
                    ? RawCusReservation::STATUS_WAITLIST
                    : RawCusReservation::STATUS_PENDING,
                'raw_promo_id'       => request('raw_promo_id'),
                'marketing_promo_id' => request('marketing_promo_id'),
                'created_at'         => now(),
                // Jejak: siapa yang MEMBUAT. `updated_by` yang sudah ada cuma
                // menyimpan penyunting TERAKHIR, jadi selama ini pembuatnya
                // hilang begitu orang lain menyentuh barisnya.
                'created_by'         => session('id'),
                'created_via'        => RawCusReservation::VIA_ADMIN,
            ]);
            $reservationId = $reservation->id;

            // Auto-assign cabang ke pasien kalau dia masih orphan (raw_branch_id NULL).
            // Cegah bug "pasien hilang" di filter Sales karena exact-match `WHERE raw_branch_id = X`.
            RawCustomer::where('id', request('raw_customer_id'))
                ->whereNull('raw_branch_id')
                ->update(['raw_branch_id' => request('raw_branch_id')]);

            // Kalau pasien ini berasal dari lead "Pasien Baru" (form publik),
            // tandai sudah DIJADWALKAN sebagai jejak follow-up. Tab Pasien Baru
            // sendiri otomatis menyembunyikannya begitu punya reservasi
            // (whereDoesntHave('reservations')), ini hanya untuk audit trail.
            RawCustomer::where('id', request('raw_customer_id'))
                ->where('source_app', RawCustomer::SOURCE_PASIEN_BARU)
                ->update([
                    'lead_status'         => 'dijadwalkan',
                    'lead_followed_up_at' => now(),
                    'lead_followed_up_by' => session('id'),
                ]);

            // Insert layanan terpilih (sama logika dengan update()).
            $servicesInput = request('services', []);
            foreach ($servicesInput as $svcId => $data) {
                if (!isset($data['checked'])) continue;

                $detailIds = $data['detail_id'] ?? [];

                if (!empty($detailIds) && is_array($detailIds)) {
                    foreach ($detailIds as $detailId) {
                        $qty = $data['qty'][$detailId] ?? 1;
                        RawCusResService::create([
                            'raw_cus_reservation_id'    => $reservation->id,
                            'raw_doctor_id'             => request('raw_doctor_id'),
                            'raw_doc_service_id'        => $svcId,
                            'raw_doc_service_detail_id' => $detailId,
                            'quantity'                  => $qty,
                        ]);
                    }
                } else {
                    RawCusResService::create([
                        'raw_cus_reservation_id'    => $reservation->id,
                        'raw_doctor_id'             => request('raw_doctor_id'),
                        'raw_doc_service_id'        => $svcId,
                        'raw_doc_service_detail_id' => null,
                        'quantity'                  => 1,
                    ]);
                }
            }

            // Buat komisi affiliate jika promo dipilih.
            $promoId = request('raw_promo_id');
            if ($promoId) {
                $promo = RawPromo::with('affiliate')->find($promoId);
                if ($promo && $promo->affiliate) {
                    $commissionAmount = $promo->affiliate->commissionAmountPerPatient();

                    // Fallback legacy (rate × total) kalau scope belum di-set.
                    if ($commissionAmount === 0) {
                        $totalHarga = RawCusResService::where('raw_cus_reservation_id', $reservation->id)
                            ->get()
                            ->sum(function ($svc) {
                                $detail = $svc->raw_doc_service_detail_id
                                    ? \App\Models\RawDocServiceDetail::find($svc->raw_doc_service_detail_id)
                                    : null;
                                return $detail ? (($detail->price ?? 0) * ($svc->quantity ?? 1)) : 0;
                            });
                        $commissionAmount = round($totalHarga * (($promo->affiliate->commission_rate ?? 0) / 100), 2);
                    }

                    RawCommission::create([
                        'id'                     => \Illuminate\Support\Str::uuid()->toString(),
                        'raw_affiliate_id'       => $promo->affiliate->id,
                        'raw_cus_reservation_id' => $reservation->id,
                        'amount'                 => $commissionAmount,
                        'status'                 => 'pending',
                        'created_at'             => now(),
                    ]);
                }
            }
        });

        // ── Bridge balik ke staging Impor Sheets ──
        // Kalau form berasal dari staging row (hidden input `staging_id`),
        // tandai staging promoted + increment batch counter + redirect balik
        // ke halaman impor supaya user lanjut row berikutnya tanpa nyasar.
        $stagingId = request('staging_id');
        $stagingBatchId = null;
        if ($stagingId && $reservationId) {
            $staging = \App\Models\RawCusReservationStaging::find($stagingId);
            if ($staging && $staging->state === \App\Models\RawCusReservationStaging::STATE_PENDING) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($staging, $reservationId) {
                    $staging->state = \App\Models\RawCusReservationStaging::STATE_PROMOTED;
                    $staging->promoted_to_id = $reservationId;
                    $staging->updated_at = now();
                    $staging->updated_by = session('id');
                    $staging->save();
                    \Illuminate\Support\Facades\DB::table('raw_reservation_import_batch')
                        ->where('id', $staging->batch_id)
                        ->increment('promoted_rows');
                });
                $stagingBatchId = $staging->batch_id;
            }
        }

        // ── Bridge balik ke Tracking Ortho ──
        // Menautkan reservasi yang baru dibuat ke baris ort_control asalnya.
        //
        // ⚠ DIBUNGKUS try/catch DENGAN SENGAJA: reservasi TIDAK BOLEH gagal
        //   gara-gara tautan ortho gagal. Modul Reservasi dipakai tiap hari
        //   oleh semua cabang; modul Ortho baru satu cabang. Kalau tabel
        //   ort_control belum ada di environment ini (SQL-nya manual), atau
        //   barisnya keburu dihapus, reservasinya tetap sah — cuma tautannya
        //   yang hilang, dan itu bisa diperbaiki belakangan.
        $ortControlId = request('ort_control_id');
        if ($ortControlId && $reservationId) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('ort_control')) {
                    $ortCtl = \App\Models\OrtControl::find($ortControlId);

                    // Hanya kontrol yang masih terbuka dan belum punya reservasi.
                    // Tanpa penjaga ini, submit ulang form akan menimpa tautan
                    // yang sudah benar dengan reservasi kedua.
                    // Cabang reservasi WAJIB sama dengan cabang pemegang kontrol.
                    // Tanpa ini, reservasi Cinere bisa tertaut ke kontrol Bintaro
                    // — lewat POST langsung, atau sekadar salah pilih cabang di
                    // form. Yang tertaut salah lebih berbahaya daripada yang
                    // tidak tertaut sama sekali: kolom Reservasi jadi berbohong.
                    $ortBranchOk = $ortCtl && app(\App\Services\Ortho\OrtBranches::class)
                        ->branchId((string) $ortCtl->place_id) === request('raw_branch_id');

                    if ($ortCtl && $ortBranchOk && $ortCtl->closed_at === null && ! $ortCtl->reservation_id) {
                        $ortCtl->fill([
                            'reservation_id' => $reservationId,
                            'rsv_date'       => request('date') ?: null,
                            'rsv_status'     => (int) request('is_waiting', 0) === 1
                                ? \App\Models\RawCusReservation::STATUS_WAITLIST
                                : \App\Models\RawCusReservation::STATUS_PENDING,
                            'rsv_synced_at'  => now(),
                            'updated_by'     => session('id'),
                        ])->save();

                        app(\App\Services\Ortho\OrtRollup::class)->refresh($ortCtl->ort_case_id);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Tautan ortho gagal, reservasi tetap dibuat', [
                    'reservation_id' => $reservationId,
                    'ort_control_id' => $ortControlId,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        if ($this->wantsJsonResponse()) {
            return response()->json(['ok' => true, 'id' => $reservationId, 'message' => 'Reservasi berhasil ditambahkan']);
        }

        if ($stagingBatchId) {
            return redirect()->route('rawreservation.import', ['batch_id' => $stagingBatchId])
                ->with('success', 'Reservasi berhasil dijadwalkan dari staging row.');
        }

        return redirect()->route('rawreservation')->with('success', 'Reservasi berhasil ditambahkan');
    }

    public function edit($id)
    {
        $this->abortIfViewOnly();
        $reservation = RawCusReservation::with('branch', 'customer', 'doctor', 'promo', 'marketingPromo', 'commission.affiliate')->findOrFail($id);

        $sessionBranch = session('raw_branch_id');

        // Fallback Database
        $userId = session('id');
        if (!$sessionBranch && $userId) {
            $user = \App\Models\Users::find($userId);
            $sessionBranch = $user->raw_branch_id ?? null;
        }

        // Kalau user terkunci ke 1 cabang, dropdown hanya tampilkan cabang itu
        $branches  = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        // Edit reservasi: tampilkan dokter aktif + dokter yang sedang ditugaskan
        // di reservasi ini (walau sudah nonaktif) supaya pilihan lama tetap valid.
        $doctors     = RawDoctor::where(function ($q) use ($reservation) {
                $q->where('is_active', 1)
                  ->orWhere('id', $reservation->raw_doctor_id);
            })
            ->orderBy('name')->get();
        $docServices = RawDocService::orderBy('name')->get();
        $promos      = \App\Models\RawPromo::where('is_active', 1)->orderBy('promo_code')->get();

        // Promo marketing yang aktif untuk cabang reservasi ini & masih dalam periode
        $marketingPromos = MarketingPromo::aktif()
            ->forBranch($reservation->raw_branch_id)
            ->withinPeriode($reservation->date)
            ->orderBy('nama_promo')
            ->get();

        $services = RawCusResService::with('doctor', 'docService', 'serviceDetail')
            ->where('raw_cus_reservation_id', $id)
            ->get();

        // Resolve durasi per item — override durasi dokter PER-CABANG
        // (raw_branch_id reservasi ini). Priority: (sub-detail override
        // per-cabang) → sub-detail global → (parent override per-cabang) → parent global.
        $doctorIds = $services->pluck('raw_doctor_id')->filter()->unique();
        $resvBranchId = $reservation->raw_branch_id;

        $pivotDurations = \Illuminate\Support\Facades\DB::table('raw_doctor_branch_service_duration')
            ->whereIn('raw_doctor_id', $doctorIds)
            ->where('raw_branch_id', $resvBranchId)
            ->get(['raw_doctor_id', 'raw_doc_service_id', 'duration_minutes'])
            ->keyBy(fn($row) => $row->raw_doctor_id . '|' . $row->raw_doc_service_id);

        $detailPivotDurations = collect();
        if (config('features.flexible_scheduling')) {
            $detailPivotDurations = \Illuminate\Support\Facades\DB::table('raw_doctor_branch_detail_duration')
                ->whereIn('raw_doctor_id', $doctorIds)
                ->where('raw_branch_id', $resvBranchId)
                ->get(['raw_doctor_id', 'raw_doc_service_detail_id', 'duration_minutes'])
                ->keyBy(fn($row) => $row->raw_doctor_id . '|' . $row->raw_doc_service_detail_id);
        }

        $totalDuration = $services->sum(function($item) use ($pivotDurations, $detailPivotDurations) {
            if ($item->serviceDetail) {
                $detailKey      = $item->raw_doctor_id . '|' . $item->raw_doc_service_detail_id;
                $detailOverride = $detailPivotDurations[$detailKey]->duration_minutes ?? null;
                if ($detailOverride !== null) {
                    return $detailOverride;
                }
                return $item->serviceDetail->duration_minutes ?? 0;
            }
            $pivotKey = $item->raw_doctor_id . '|' . $item->raw_doc_service_id;
            $override = $pivotDurations[$pivotKey]->duration_minutes ?? null;
            return $override ?? ($item->docService->duration_minutes ?? 0);
        });

        return view('crud.reservation.edit', compact(
            'reservation',
            'branches',
            'doctors',
            'docServices',
            'promos',
            'marketingPromos',
            'services',
            'totalDuration'
        ));
    }

    public function update($id)
    {
        $this->abortIfViewOnly();
        request()->validate([
            'raw_branch_id'   => 'required',
            'raw_customer_id' => 'required',
            'raw_doctor_id'   => 'required',
            'date'            => 'required|date',
            'start_hour'      => 'required',
            'end_hour'        => 'required',
            'keterangan'         => 'nullable|string|max:65535',
            'raw_promo_id'       => 'nullable|exists:raw_promo,id',
            'marketing_promo_id' => 'nullable|exists:marketing_promo,id',
        ]);

        // Validasi scope kode referral vs services yang dipilih (Fase 2B).
        // Kalau gagal → return validation error sebelum nyentuh DB.
        if (request('raw_promo_id')) {
            $scopeError = $this->validateReferralScopeMatch(
                request('raw_promo_id'),
                request('services', [])
            );
            if ($scopeError) {
                return $this->validationErrorResponse(['raw_promo_id' => $scopeError]);
            }
        }

        // Guard server-side anti double-book (kecuali reservasi di-set Batal —
        // yang batal tak perlu slot kosong). Exclude reservasi yg sedang diedit.
        if (request('status') !== RawCusReservation::STATUS_CANCELLED
            && $this->slotConflict(request('raw_doctor_id'), request('date'), request('start_hour'), request('end_hour'), $id)) {
            return $this->validationErrorResponse(['start_hour' => 'Jam tersebut sudah terisi untuk dokter ini pada tanggal itu. Silakan pilih slot lain.']);
        }

        $reservation = RawCusReservation::findOrFail($id);

        // Auto-convert waiting list → pending saat slot jam nyata di-assign ("Tawarkan
        // slot"). Form Edit mewajibkan start_hour (checkbox is_waiting hanya ada di form
        // Tambah), jadi reservasi waitlist yang di-edit pasti sudah punya slot → bukan
        // "antri" lagi. Scope KETAT: hanya kalau status SEBELUMNYA waitlist DAN status
        // yang dikirim masih waitlist (kalau staff sengaja pilih status lain → dihormati;
        // reservasi non-waitlist tak tersentuh).
        $newStatus = request('status', 'pending');
        if ($reservation->status === RawCusReservation::STATUS_WAITLIST
            && $newStatus === RawCusReservation::STATUS_WAITLIST
            && request()->filled('start_hour')) {
            $newStatus = RawCusReservation::STATUS_PENDING;
        }

        // Pembatalan wajib beralasan — dari layar MANA PUN (lihat juga
        // updateInline()). Dicek sebelum menyentuh DB.
        if ($errors = $this->validateCancelReason($reservation, $newStatus)) {
            return $this->validationErrorResponse($errors);
        }

        // Potret nilai lama untuk riwayat — HARUS diambil sebelum update().
        $sebelum = $reservation->getOriginal();
        $cancelPayload = $this->cancelReasonPayload($reservation, $newStatus);

        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $id, $newStatus, $cancelPayload) {
            $reservation->update($cancelPayload + [
                'raw_branch_id'   => request('raw_branch_id'),
                'raw_customer_id' => request('raw_customer_id'),
                'raw_doctor_id'   => request('raw_doctor_id'),
                'date'            => request('date'),
                'start_hour'      => request('start_hour'),
                'end_hour'        => request('end_hour'),
                'status'          => $newStatus,
                'keterangan'         => request('keterangan'),
                'raw_promo_id'       => request('raw_promo_id'),
                'marketing_promo_id' => request('marketing_promo_id'),
                'is_waiting'         => request('is_waiting', 0),
                'updated_at'      => now(),
                'updated_by'      => session('id'),
            ]);

            // Auto-assign cabang ke pasien kalau masih orphan (raw_branch_id NULL).
            // Cegah bug "pasien hilang" di filter Sales.
            RawCustomer::where('id', request('raw_customer_id'))
                ->whereNull('raw_branch_id')
                ->update(['raw_branch_id' => request('raw_branch_id')]);

            // Hapus semua layanan yang ada untuk reservasi ini
            RawCusResService::where('raw_cus_reservation_id', $id)->delete();
            $servicesInput = request('services', []);

            foreach ($servicesInput as $svcId => $data) {
                // Jika checkbox layanan utama dicentang
                if (isset($data['checked'])) {
                    $detailIds = $data['detail_id'] ?? [];

                    // Jika ada sub-layanan yang dipilih (berupa array checkbox)
                    if (!empty($detailIds) && is_array($detailIds)) {
                        foreach ($detailIds as $detailId) {
                            $qty = $data['qty'][$detailId] ?? 1;
                            RawCusResService::create([
                                'raw_cus_reservation_id'    => $id,
                                'raw_doctor_id'             => request('raw_doctor_id'),
                                'raw_doc_service_id'        => $svcId,
                                'raw_doc_service_detail_id' => $detailId,
                                'quantity'                  => $qty,
                            ]);
                        }
                    } else {
                        // Jika tidak ada sub-layanan yang dipilih, simpan layanan utamanya saja
                        RawCusResService::create([
                            'raw_cus_reservation_id'    => $id,
                            'raw_doctor_id'             => request('raw_doctor_id'),
                            'raw_doc_service_id'        => $svcId,
                            'raw_doc_service_detail_id' => null,
                            'quantity'                  => 1,
                        ]);
                    }
                }
            }

            // Buat komisi affiliate jika promo dipilih dan belum ada commission.
            // Fase 2B: amount = nominal fixed per scope (100k behel, 50k non-behel),
            // bukan lagi total × rate%.
            $promoId = request('raw_promo_id');
            $existingCommission = RawCommission::where('raw_cus_reservation_id', $id)->first();

            if ($promoId && !$existingCommission) {
                $promo = RawPromo::with('affiliate')->find($promoId);
                if ($promo && $promo->affiliate) {
                    $commissionAmount = $promo->affiliate->commissionAmountPerPatient();

                    // Fallback ke logika lama (rate × total) hanya kalau scope belum di-set
                    // (mis. data legacy yang belum di-backfill).
                    if ($commissionAmount === 0) {
                        $totalHarga = RawCusResService::where('raw_cus_reservation_id', $id)
                            ->get()
                            ->sum(function ($svc) {
                                $detail = $svc->raw_doc_service_detail_id
                                    ? \App\Models\RawDocServiceDetail::find($svc->raw_doc_service_detail_id)
                                    : null;
                                return $detail ? (($detail->price ?? 0) * ($svc->quantity ?? 1)) : 0;
                            });
                        $commissionAmount = round($totalHarga * (($promo->affiliate->commission_rate ?? 0) / 100), 2);
                    }

                    RawCommission::create([
                        'id'                     => \Illuminate\Support\Str::uuid()->toString(),
                        'raw_affiliate_id'       => $promo->affiliate->id,
                        'raw_cus_reservation_id' => $id,
                        'amount'                 => $commissionAmount,
                        'status'                 => 'pending',
                        'created_at'             => now(),
                    ]);
                }
            } elseif (!$promoId && $existingCommission) {
                // Hapus komisi jika promo dihapus
                $existingCommission->delete();
            }
        });

        // Kalau reservasi baru saja DIBATALKAN, slotnya kosong → tawarkan
        // assign pasien waiting list di dokter+tanggal yang sama. Dihitung sekali,
        // dipakai untuk response JSON (drawer) maupun redirect (page).
        // Riwayat: satu baris audit_log kalau jadwal/status benar-benar bergeser.
        $this->catatJejak($reservation, $sebelum);

        $waitlistPrompt = null;
        if (request('status') === RawCusReservation::STATUS_CANCELLED) {
            $reservation->refresh();
            $count = RawCusReservation::waitlistCandidatesFor($reservation->raw_doctor_id, $reservation->date);
            if ($count > 0) {
                $waitlistPrompt = [
                    'count'  => $count,
                    'doctor' => $reservation->doctor->name ?? 'dokter ini',
                    'date'   => $reservation->date ? \Carbon\Carbon::parse($reservation->date)->format('d M Y') : '-',
                    'url'    => route('rawreservation', [
                        'view'      => 'waitlist',
                        'doctor_id' => $reservation->raw_doctor_id,
                        'date_from' => $reservation->date ? \Carbon\Carbon::parse($reservation->date)->format('Y-m-d') : null,
                        'date_to'   => $reservation->date ? \Carbon\Carbon::parse($reservation->date)->format('Y-m-d') : null,
                    ]),
                ];
            }
        }

        if ($this->wantsJsonResponse()) {
            $payload = ['ok' => true, 'id' => $id, 'message' => 'Reservasi berhasil diubah'];
            if ($waitlistPrompt) $payload['waitlist_prompt'] = $waitlistPrompt;
            return response()->json($payload);
        }

        $redirect = redirect()->route('rawreservation.edit', $id)->with('success', 'Reservasi berhasil diubah');
        if ($waitlistPrompt) $redirect->with('waitlist_prompt', $waitlistPrompt);
        return $redirect;
    }

    /**
     * Validasi: kode referral yang dipilih HARUS match scope service yang dipesan.
     * - Scope behel    → harus include service kategori 'behel'
     * - Scope non_behel → harus include service di allowed_service_names (Scaling/Whitening/PSA/Veneer)
     *
     * Returns null kalau valid, atau pesan error string kalau gagal.
     */
    private function validateReferralScopeMatch(string $promoId, array $servicesInput): ?string
    {
        $promo = RawPromo::with('affiliate')->find($promoId);
        if (!$promo || !$promo->affiliate) return null; // promo invalid akan ditangkap by exists rule
        if (!$promo->referral_scope) return null;       // scope belum di-set → skip validasi (data legacy)

        // Extract service IDs yang dicentang
        $selectedSvcIds = collect($servicesInput)
            ->filter(fn($d) => isset($d['checked']))
            ->keys()
            ->all();

        if (empty($selectedSvcIds)) {
            return 'Pilih minimal satu layanan sebelum pakai kode referral.';
        }

        $services = RawDocService::whereIn('id', $selectedSvcIds)->get(['id', 'name', 'category']);
        $scope    = $promo->referral_scope;

        if ($scope === RawAffiliate::SCOPE_BEHEL) {
            $hasBehel = $services->contains(fn($s) => $s->category === 'behel');
            if (!$hasBehel) {
                return 'Kode "' . $promo->promo_code . '" hanya valid untuk perawatan PEMASANGAN BEHEL. Layanan yang dipilih saat ini tidak termasuk behel.';
            }
            return null;
        }

        if ($scope === RawAffiliate::SCOPE_NON_BEHEL) {
            $allowedNames = RawAffiliate::referralScopes()[RawAffiliate::SCOPE_NON_BEHEL]['allowed_service_names'] ?? [];
            $hasAllowed   = $services->contains(fn($s) => in_array($s->name, $allowedNames));
            if (!$hasAllowed) {
                return 'Kode "' . $promo->promo_code . '" hanya valid untuk perawatan: ' . implode(', ', $allowedNames) . '. Pilih salah satunya untuk pakai kode ini.';
            }
            return null;
        }

        return null;
    }

    public function updateInline($id)
    {
        if (isReservationViewOnly()) {
            return response()->json(['ok' => false, 'message' => 'Akses Anda hanya view-only.'], 403);
        }
        $reservation = RawCusReservation::findOrFail($id);

        $field   = request('field');
        $value   = request('value');
        $allowed = ['status', 'follow_up_h_min_1', 'follow_up_h', 'keterangan_kehadiran', 'promo_klaim', 'keterangan'];        
        if (!in_array($field, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Field tidak valid'], 422);
        }
    
        // Pembatalan wajib beralasan JUGA dari jalur inline. Jalur ini yang
        // paling sering dipakai (drawer Detail + tombol Batal di footer), jadi
        // memagari cuma form Edit berarti pagarnya tak pernah kena apa-apa.
        if ($field === 'status') {
            if ($errors = $this->validateCancelReason($reservation, (string) $value)) {
                return response()->json([
                    'success'       => false,
                    'ok'            => false,
                    'need_reason'   => $reservation->status !== RawCusReservation::STATUS_CANCELLED,
                    'message'       => reset($errors),
                    'errors'        => $errors,
                ], 422);
            }
        }

        // Potret nilai lama untuk riwayat — sebelum update().
        $sebelum = $reservation->getOriginal();

        $updates = [
            $field       => $value,
            'updated_at' => now(),
            'updated_by' => session('id'),
        ];

        if ($field === 'status') {
            $updates += $this->cancelReasonPayload($reservation, (string) $value);
        }

        // Saat status diubah ke Waiting List: bebaskan slot jamnya (samakan dgn
        // cara reservasi waitlist dibuat — tanpa jam, is_waiting=1) supaya jam itu
        // langsung kosong untuk pasien lain. Keluar dari waitlist → lepas flag
        // antri (jam tetap kosong; slot dipilih lagi lewat Edit).
        if ($field === 'status') {
            if ($value === RawCusReservation::STATUS_WAITLIST) {
                $updates['start_hour'] = null;
                $updates['end_hour']   = null;
                $updates['is_waiting'] = 1;
            } else {
                $updates['is_waiting'] = 0;
            }
        }

        $reservation->update($updates);

        $this->catatJejak($reservation, $sebelum);

        return response()->json(['success' => true]);
    }

    public function klaimPromo($id)
{
    $this->abortIfViewOnly();
    $reservation = RawCusReservation::findOrFail($id);

    // Cek apa reservasi punya promo
    if (!$reservation->raw_promo_id) {
        return back()->with('error', 'Reservasi ini tidak menggunakan promo');
    }

    // Cek apa udah pernah diklaim
    if ($reservation->promo_klaim) {
        return back()->with('error', 'Promo ini sudah diklaim sebelumnya');
    }

    $reservation->update([
        'promo_klaim'    => 1,
        'promo_klaim_by' => session('id'),
        'updated_at'     => now(),
        'updated_by'     => session('id'),
    ]);

    return back()->with('success', 'Promo berhasil diklaim!');
}

    public function destroy($id)
    {
        $this->abortIfViewOnly();
        $reservation = RawCusReservation::with('commission', 'services', 'customer')->findOrFail($id);

        // Jejak dicatat SEBELUM baris hilang — sesudah delete, tak ada lagi yang
        // bisa dibaca. Penghapusan adalah satu-satunya perubahan yang tak
        // meninggalkan bukti apa pun di tabelnya sendiri.
        $this->catatJejak($reservation, $reservation->getOriginal(), 'delete');

        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation) {
            // 1. Hapus komisi yang terkait (penyebab utama error)
            if ($reservation->commission) {
                $reservation->commission->delete();
            }

            // 2. Hapus layanan yang terkait
            $reservation->services()->delete();

            // 3. Hapus reservasi itu sendiri
            $reservation->delete();
        });

        return redirect()->route('rawreservation')->with('success', 'Reservasi berhasil dihapus.');
    }

    public function commissionApprove($reservationId, $commissionId)
    {
        $this->abortIfViewOnly();
        $commission = \App\Models\RawCommission::where('raw_cus_reservation_id', $reservationId)
            ->findOrFail($commissionId);

        if ($commission->status !== 'pending') {
            if (request()->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Komisi harus berstatus pending untuk di-approve'], 422);
            }
            return redirect()->route('rawreservation.edit', $reservationId)
                ->with('error', 'Komisi harus berstatus pending untuk di-approve');
        }

        $commission->update(['status' => 'approved', 'updated_at' => now()]);

        \App\Models\AdminNotification::notify(
            'commission',
            'Komisi Di-approve',
            'Komisi Rp ' . number_format($commission->amount) . ' telah di-approve oleh ' . session('name'),
            url("crud/reservation/{$reservationId}/edit")
        );

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Komisi berhasil di-approve']);
        }

        return redirect()->route('rawreservation.edit', $reservationId)
            ->with('success', 'Komisi berhasil di-approve');
    }

    public function commissionPay($reservationId, $commissionId)
    {
        $this->abortIfViewOnly();
        $commission = \App\Models\RawCommission::where('raw_cus_reservation_id', $reservationId)
            ->findOrFail($commissionId);

        if ($commission->status !== 'approved') {
            if (request()->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Komisi harus berstatus approved sebelum dibayar'], 422);
            }
            return redirect()->route('rawreservation.edit', $reservationId)
                ->with('error', 'Komisi harus berstatus approved sebelum dibayar');
        }

        $commission->update(['status' => 'paid', 'updated_at' => now()]);

        \App\Models\AdminNotification::notify(
            'commission',
            'Komisi Dibayar',
            'Komisi Rp ' . number_format($commission->amount) . ' telah dibayar oleh ' . session('name'),
            url("crud/reservation/{$reservationId}/edit")
        );

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Komisi ditandai sudah dibayar']);
        }

        return redirect()->route('rawreservation.edit', $reservationId)
            ->with('success', 'Komisi ditandai sudah dibayar');
    }

    public function serviceStore($reservationId)
    {
        $this->abortIfViewOnly();
        request()->validate([
            'raw_doctor_id'      => 'required',
            'raw_doc_service_id' => 'required',
        ]);

        RawCusResService::create([
            'raw_cus_reservation_id' => $reservationId,
            'raw_doctor_id'          => request('raw_doctor_id'),
            'raw_doc_service_id'     => request('raw_doc_service_id'),
        ]);

        return redirect()->route('rawreservation.edit', $reservationId)->with('success', 'Layanan berhasil ditambahkan');
    }

    public function serviceDestroy($reservationId, $serviceId)
    {
        $this->abortIfViewOnly();
        $service = RawCusResService::where('raw_cus_reservation_id', $reservationId)
            ->findOrFail($serviceId);

        $service->delete();

        return redirect()->route('rawreservation.edit', $reservationId)->with('success', 'Layanan berhasil dihapus');
    }
}