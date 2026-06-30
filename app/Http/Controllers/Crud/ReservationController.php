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

        // Tab view: 'list' (default) | 'cal' | 'waitlist' | 'jadwal'. 'new' = filter chip, bukan tab terpisah.
        $view = request('view', 'list');
        // View-only tidak boleh akses tab waitlist (defensive — UI sudah hide).
        if ($isViewOnly && $view === 'waitlist') {
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
            $sortJam = request('sort_jam');
            if ($sortJam === 'asc') {
                $reservations = $query->orderBy('date', 'desc')
                    ->orderBy('start_hour', 'asc')
                    ->orderBy('end_hour', 'asc')
                    ->paginate($perPage);
            } elseif ($sortJam === 'desc') {
                $reservations = $query->orderBy('date', 'desc')
                    ->orderBy('start_hour', 'desc')
                    ->orderBy('end_hour', 'desc')
                    ->paginate($perPage);
            } else {
                $reservations = $query->orderBy('date', 'desc')
                    ->orderBy('raw_doctor_id')
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

        return view('crud.reservation.list', compact(
            'reservations',
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

    public function create()
    {
        $this->abortIfViewOnly();
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
        $data = request()->validate([
            'name'          => 'required|max:255',
            'phone'         => 'required|regex:/^0[0-9]{9,14}$/',
            'email'         => 'nullable|email|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tanggal_lahir' => 'nullable|date|before:today',
            'relasi'        => 'nullable|in:diri_sendiri,pasangan,anak,orang_tua,lainnya',
        ], [
            'name.required'  => 'Nama pasien wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.regex'    => 'Nomor tidak valid (diawali 0, 10-15 digit).',
        ]);

        $sessionBranch = session('raw_branch_id');
        $phone = Phone::normalize($data['phone']) ?: $data['phone'];

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
                'tanggal_daftar'   => now()->toDateString(),
                'pasien_baru_lama' => 'baru',
                'raw_branch_id'    => $sessionBranch ?: null,
                'source_app'       => 'admin',
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

    public function store()
    {
        $this->abortIfViewOnly();
        $isWaiting = (int) request('is_waiting', 0) === 1;

        request()->validate([
            'raw_branch_id'      => 'required',
            'raw_customer_id'    => 'required',
            'raw_doctor_id'      => 'required',
            'date'               => 'required|date',
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
                'date'               => request('date'),
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
            ]);
            $reservationId = $reservation->id;

            // Auto-assign cabang ke pasien kalau dia masih orphan (raw_branch_id NULL).
            // Cegah bug "pasien hilang" di filter Sales karena exact-match `WHERE raw_branch_id = X`.
            RawCustomer::where('id', request('raw_customer_id'))
                ->whereNull('raw_branch_id')
                ->update(['raw_branch_id' => request('raw_branch_id')]);

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

        if ($this->wantsJsonResponse()) {
            return response()->json(['ok' => true, 'id' => $reservationId, 'message' => 'Reservasi berhasil ditambahkan']);
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $id, $newStatus) {
            $reservation->update([
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
    
        $reservation->update([
            $field       => $value,
            'updated_at' => now(),
            'updated_by' => session('id'),
        ]);

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
        $reservation = RawCusReservation::with('commission', 'services')->findOrFail($id);

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

        return redirect()->route('rawreservation.edit', $reservationId)
            ->with('success', 'Komisi berhasil di-approve');
    }

    public function commissionPay($reservationId, $commissionId)
    {
        $this->abortIfViewOnly();
        $commission = \App\Models\RawCommission::where('raw_cus_reservation_id', $reservationId)
            ->findOrFail($commissionId);

        if ($commission->status !== 'approved') {
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