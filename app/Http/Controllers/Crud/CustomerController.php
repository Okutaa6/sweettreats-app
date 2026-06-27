<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPatientContact;
use App\Models\RawBranch;
use App\Models\RawCustomer;
use App\Models\RawDocService;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawPromo;
use App\Support\OmnicareEncounters;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerController extends Controller
{
    use ResolvesPatientContact;

    public function index()
    {
        $perPage = $this->perPage;

        $filterSearch  = request('search');
        $filterService = request('service_id');
        $filterDoctor  = request('doctor_id');
        $filterBranch  = request('branch_id');

        $sessionBranch = session('raw_branch_id');

        $query = RawCustomer::with([
            'branch',
            'latestReservation.branch',
            'latestReservation.doctor',
            'latestReservation.services.docService',
        ])->withCount('reservations');

        // Akses cross-cabang: SEMUA staff cabang bisa melihat pasien cabang
        // mana pun (cegah duplikasi saat pasien pindah cabang & jaga
        // konsistensi data utk Omnicare matching). Filter manual per cabang
        // tetap tersedia di UI untuk siapa pun.
        if ($filterBranch) {
            $query->where('raw_branch_id', $filterBranch);
        }

        if ($filterSearch) {
            $query->where(function($q) use ($filterSearch) {
                $q->where('name', 'like', "%{$filterSearch}%")
                  ->orWhere('phone', 'like', "%{$filterSearch}%")
                  ->orWhere('email', 'like', "%{$filterSearch}%");
            });
        }

        if ($filterService) {
            $query->whereHas('reservations.services', function($q) use ($filterService) {
                $q->where('raw_doc_service_id', $filterService);
            });
        }

        if ($filterDoctor) {
            $query->whereHas('reservations', function($q) use ($filterDoctor) {
                $q->where('raw_doctor_id', $filterDoctor);
            });
        }

        $now = now();

        // ── Agregat LTV/kunjungan per pasien via Omnicare (link → sales). SATU query,
        //    dipakai utk KPI clinic-wide, enrich baris, DAN turunan segment. Cache 30 mnt
        //    (clinic-wide; sync Omnicare harian). TODO prod: pindah ke job/materialized. ──
        $agg = \Illuminate\Support\Facades\Cache::remember('pasien_ltv_agg', 1800, function () {
            return \Illuminate\Support\Facades\DB::table('customer_omnicare_link as l')
                ->leftJoin('omnicare_sales_detail as s', 's.p_id', '=', 'l.omnicare_pid')
                ->select('l.raw_customer_id',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(s.net_sell_price),0) as ltv'),
                    \Illuminate\Support\Facades\DB::raw('MAX(s.trx_date) as last_visit'),
                    \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT s.trx_date) as visits'))
                ->groupBy('l.raw_customer_id')
                ->get()->keyBy('raw_customer_id');
        });

        // visitedIds: pasien yg pernah transaksi (visits>0) → "Belum Datang" = NOT IN ini.
        $visitedIds = $agg->filter(fn ($a) => $a->visits > 0)->keys()->all();

        // ── Segment worklist (FINAL, data-driven): semua | baru | belum_datang | ultah.
        //    Recall/Behel/Piutang = fitur/page terpisah. Counts atas base ter-filter.
        //    "baru" = ditambah ke sistem bulan ini (created_at). CATATAN: base pasien hasil
        //    import massal (created_at ke-cluster) → sekarang tipis (~1); ini FUTURE-FACING,
        //    tumbuh sendiri seiring pasien baru ditambah lewat form Tambah. Sumber lain
        //    (tanggal_daftar / Omnicare is_new) sama jeleknya / linking nge-lag. ──
        $applySegment = function ($q, $seg) use ($visitedIds) {
            if ($seg === 'baru') {
                $q->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month);
            } elseif ($seg === 'belum_datang') {
                $q->whereNotIn('id', $visitedIds ?: ['-']);
            } elseif ($seg === 'ultah') {
                $q->whereMonth('tanggal_lahir', now()->month);
            }
            return $q;
        };
        $seg = in_array(request('seg'), ['baru', 'belum_datang', 'ultah'], true) ? request('seg') : 'semua';

        $baseForCounts = clone $query;
        $segCounts = [
            'semua'        => (clone $baseForCounts)->count(),
            'baru'         => $applySegment((clone $baseForCounts), 'baru')->count(),
            'belum_datang' => $applySegment((clone $baseForCounts), 'belum_datang')->count(),
            'ultah'        => $applySegment((clone $baseForCounts), 'ultah')->count(),
        ];

        $applySegment($query, $seg);

        // ── Sort by KUNJUNGAN TERAKHIR (Omnicare last_visit), bukan last_reservation
        //    (sparse: mayoritas pasien tak punya reservasi di sistem kita). Correlated
        //    subselect; yg belum pernah datang (NULL) ditaruh paling bawah. ──
        $customers = $query
            ->addSelect(['last_visit_sort' => \Illuminate\Support\Facades\DB::table('omnicare_sales_detail as s')
                ->join('customer_omnicare_link as l', 'l.omnicare_pid', '=', 's.p_id')
                ->whereColumn('l.raw_customer_id', 'raw_customer.id')
                ->selectRaw('MAX(s.trx_date)')
            ])
            ->orderByRaw('last_visit_sort IS NULL, last_visit_sort DESC')
            ->orderBy('created_at')
            ->paginate($perPage)->withQueryString();

        // KPI clinic-wide (insight strip).
        $sixMonthsAgo = $now->copy()->subMonths(6)->toDateString();
        $totalAktif = 0; $sumLtv = 0; $retained = 0;
        foreach ($agg as $a) {
            if ($a->visits > 0) {
                $totalAktif++;
                $sumLtv += $a->ltv;
                if ($a->last_visit && $a->last_visit >= $sixMonthsAgo) $retained++;
            }
        }
        // Sumber akuisisi — pakai source_app (data akuisisi ASLI; channel kosong→Lainnya 100%).
        $src = RawCustomer::select('source_app', \Illuminate\Support\Facades\DB::raw('count(*) as c'))
            ->groupBy('source_app')->pluck('c', 'source_app');
        $acqTotal = max(1, (int) $src->sum());
        $acqBucket = ['Instagram' => 0, 'TikTok' => 0, 'Teman' => 0, 'Lainnya' => 0];
        foreach ($src as $k => $c) {
            $u = strtoupper(trim((string) $k));
            if (str_contains($u, 'INSTA') || str_contains($u, 'INTAG') || str_contains($u, 'INSTG') || $u === 'IG') $acqBucket['Instagram'] += $c;
            elseif (str_contains($u, 'TIKTOK') || str_contains($u, 'TITOK') || str_contains($u, 'FYP')) $acqBucket['TikTok'] += $c;
            elseif (str_contains($u, 'TEMAN') || str_contains($u, 'KELUARGA') || str_contains($u, 'KERABAT')) $acqBucket['Teman'] += $c;
            else $acqBucket['Lainnya'] += $c;
        }
        $acquisition = [];
        foreach ($acqBucket as $label => $c) {
            $acquisition[] = ['label' => $label, 'pct' => (int) round($c / $acqTotal * 100)];
        }
        $kpi = [
            'total_aktif' => $totalAktif,
            'avg_ltv'     => $totalAktif ? (int) round($sumLtv / $totalAktif) : 0,
            'retensi'     => $totalAktif ? (int) round($retained / $totalAktif * 100) : 0,
            'acquisition' => $acquisition,
        ];

        // ── Enrich tiap baris: LTV, kunjungan terakhir, value tier, field hilang (utk detail). ──
        foreach ($customers as $c) {
            $a = $agg[$c->id] ?? null;
            $c->ltv         = $a ? (int) $a->ltv : 0;
            $c->last_visit  = $a->last_visit ?? null;
            $c->visit_count = $a ? (int) $a->visits : 0;
            $c->tier        = $c->ltv >= 2500000 ? 'vip' : ($c->ltv >= 1000000 ? 'bernilai' : 'reguler');
            $missing = [];
            if (empty($c->email) || $c->email === '-') $missing[] = 'Email';
            if (empty($c->nik)) $missing[] = 'NIK';
            $c->missing_fields = $missing;
        }

        $services = RawDocService::orderBy('name')->get();
        // Dropdown filter cabang & dokter selalu tampil untuk semua user
        // (Sales pun boleh filter cross-cabang).
        $branches = RawBranch::orderBy('name')->get();
        $doctors  = RawDoctor::orderBy('name')->get();

        return view('crud.customer.list', compact(
            'customers', 'services', 'doctors', 'branches',
            'filterSearch', 'filterService', 'filterDoctor', 'filterBranch',
            'seg', 'segCounts', 'kpi'
        ));
    }

    public function search()
    {
        $term = request('q', '');
        $query = RawCustomer::query();

        // Cross-cabang: staff cabang mana pun bisa menemukan pasien cabang
        // mana pun (cegah duplikasi saat pasien pindah & masalah matching
        // Omnicare). Tidak ada filter per session branch di sini.

        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        $customers = $query->orderBy('name')->limit(20)->get(['id', 'name', 'phone', 'relasi']);

        $relasiLabel = [
            'diri_sendiri' => 'Diri sendiri',
            'pasangan'     => 'Pasangan',
            'anak'         => 'Anak',
            'orang_tua'    => 'Orang tua',
            'lainnya'      => 'Lainnya',
        ];

        return response()->json($customers->map(function ($c) use ($relasiLabel) {
            $rel = $relasiLabel[$c->relasi] ?? null;
            return [
                'id'   => $c->id,
                'text' => $c->name
                    . ($rel ? ' · ' . $rel : '')
                    . ' (' . ($c->phone ?: '-') . ')',
            ];
        }));
    }

    public function create()
    {
        $sessionBranch = session('raw_branch_id');
        $branches = RawBranch::orderBy('name')->get();
        $services = RawDocService::orderBy('name')->get();
        $promos   = RawPromo::where('is_active', 1)->orderBy('promo_code')->get();
        $userBranch = $sessionBranch ? RawBranch::find($sessionBranch) : null;

        if ($userBranch) {
            $doctorIds = RawDoctorSchedule::where('raw_branch_id', $userBranch->id)
                ->distinct()->pluck('raw_doctor_id');
            $doctors = RawDoctor::whereIn('id', $doctorIds)->active()->orderBy('name')->get();
        } else {
            $doctors = RawDoctor::active()->orderBy('name')->get();
        }

        return view('crud.customer.new', compact('branches', 'services', 'doctors', 'promos', 'userBranch'));
    }

    public function store()
    {
        // Model 1-nomor-banyak-pasien: nomor BOLEH duplikat (keluarga / pasien
        // pindah cabang). Tidak ada `unique:raw_customer,phone` lagi — pasien
        // baru dilink ke raw_contact existing via ResolvesPatientContact.
        request()->validate([
            'name'       => 'required|max:255',
            'phone'      => 'required|regex:/^0[0-9]{9,14}$/',
            'source_app' => 'nullable|max:255',
            'relasi'     => 'nullable|in:diri_sendiri,pasangan,anak,orang_tua,lainnya',
        ], [
            'phone.required' => 'No. WhatsApp wajib diisi.',
            'phone.regex'    => 'Format nomor tidak valid. Harus diawali 0 dan minimal 10 digit (contoh: 08123456789).',
        ]);

        $phone = Phone::normalize(request('phone')) ?: request('phone');

        DB::transaction(function () use ($phone) {
            $contact = $this->resolveContact($phone, request('name'), 'admin', 'admin');

            RawCustomer::create([
                'raw_contact_id'     => $contact->id,
                'relasi'             => request('relasi') ?: 'diri_sendiri',
                'name'               => request('name'),
                'phone'              => $phone,
                'email'              => request('email'),
                'jenis_kelamin'      => request('jenis_kelamin'),
                'tanggal_lahir'      => request('tanggal_lahir') ?: null,
                'nik'                => request('nik'),
                'tempat_lahir'       => request('tempat_lahir'),
                'alamat'             => request('alamat'),
                'tanggal_daftar'     => request('tanggal_daftar') ?: null,
                'source_app'         => request('source_app'),
                'raw_branch_id'      => request('raw_branch_id') ?: null,
                'raw_doc_service_id' => request('raw_doc_service_id') ?: null,
                'raw_doctor_id'      => request('raw_doctor_id') ?: null,
                'pasien_baru_lama'   => request('pasien_baru_lama'),
                'raw_promo_id'       => request('raw_promo_id') ?: null,
                'catatan'            => request('catatan'),
            ]);
        });

        return redirect()->route('rawcustomer')->with('success', 'Pasien berhasil ditambahkan');
    }

    public function edit($id)
    {
        $customer = RawCustomer::with([
            'reservations' => function ($q) {
                $q->orderBy('date', 'desc');
            },
            'reservations.branch',
            'reservations.doctor',
            'reservations.services.docService',
        ])->findOrFail($id);

        $sessionBranch = session('raw_branch_id');
        $userBranch = $sessionBranch ? RawBranch::find($sessionBranch) : null;

        // Akses cross-cabang: edit pasien diizinkan dari cabang mana pun
        // (cegah duplikasi saat pasien pindah & konsistensi Omnicare).
        // Hapus (destroy) tetap terkunci ke cabang asal.

        $branches = RawBranch::orderBy('name')->get();
        $services = RawDocService::orderBy('name')->get();
        $promos   = RawPromo::where('is_active', 1)->orderBy('promo_code')->get();

        if ($userBranch) {
            $doctorIds = RawDoctorSchedule::where('raw_branch_id', $userBranch->id)
                ->distinct()->pluck('raw_doctor_id');
            $doctors = RawDoctor::whereIn('id', $doctorIds)->active()->orderBy('name')->get();
        } else {
            $doctors = RawDoctor::active()->orderBy('name')->get();
        }

        $totalKunjungan = $customer->reservations->count();
        $totalSelesai   = $customer->reservations->where('status', 'completed')->count();
        $totalSpending  = $customer->reservations->where('status', 'completed')->sum('total_reservasi');

        // Auto-fill dari reservasi terakhir jika profil masih kosong
        $latestReservation = $customer->reservations->first();
        if ($latestReservation) {
            if (!$customer->raw_branch_id)      $customer->raw_branch_id      = $latestReservation->raw_branch_id;
            if (!$customer->raw_doctor_id)      $customer->raw_doctor_id      = $latestReservation->raw_doctor_id;
            if (!$customer->raw_doc_service_id) {
                $firstService = $latestReservation->services->first();
                if ($firstService) $customer->raw_doc_service_id = $firstService->raw_doc_service_id;
            }
        }

        // Auto-fill pasien baru/lama jika masih kosong
        if (!$customer->pasien_baru_lama) {
            $hasCompleted = $customer->reservations->where('status', 'completed')->count() > 0;
            $customer->pasien_baru_lama = $hasCompleted ? 'lama' : 'baru';
        }

        // Timeline perjalanan pasien — gabungan lintas sistem
        // (journey/lead + reservasi Data Center + perawatan nyata Omnicare).
        $timeline = $this->buildTimeline($customer);

        // Rekam medis lengkap (Omnicare) untuk accordion di profil pasien.
        // Schema guard supaya tidak crash di env yang belum punya tabelnya.
        $medicalRecords = collect();
        if (Schema::hasTable('customer_omnicare_link') && Schema::hasTable('omnicare_medical_record')) {
            $pid = DB::table('customer_omnicare_link')
                ->where('raw_customer_id', $customer->id)->value('omnicare_pid');
            if ($pid) {
                $medicalRecords = DB::table('omnicare_medical_record')
                    ->where('p_id', $pid)
                    ->orderByDesc('date')
                    ->orderByDesc('time_in')
                    ->get();
            }
        }

        return view('crud.customer.edit', compact(
            'customer', 'branches', 'services', 'doctors', 'promos',
            'totalKunjungan', 'totalSelesai', 'totalSpending', 'timeline',
            'medicalRecords', 'userBranch'
        ));
    }

    /**
     * Bangun timeline kronologis pasien dari semua sumber yang ada:
     *   - journey/lead  (raw_cus_journey — funnel marketing/sales)
     *   - reservasi     (raw_cus_reservation — Data Center)
     *   - perawatan     (omnicare_sales_detail via customer_omnicare_link)
     *
     * Event Omnicare di-skip dengan aman bila tabel integrasi belum ada
     * (mis. kode ter-upload tapi SQL belum dijalankan).
     *
     * @return array<int,object> diurut terbaru dulu
     */
    private function buildTimeline(RawCustomer $customer): array
    {
        $events = [];
        $mk = fn($date, $type, $title, $detail, $icon, $color, $bg, $amount = null, $tag = null, $medical = null, $clinicalSummary = '') => (object) [
            'date'             => $date instanceof Carbon ? $date : Carbon::parse($date),
            'type'             => $type,
            'title'            => $title,
            'detail'           => $detail,
            'icon'             => $icon,
            'color'            => $color,
            'bg'               => $bg,
            'amount'           => $amount,
            'tag'              => $tag,
            'medical'          => $medical,           // baris omnicare_medical_record (atau null)
            'clinical_summary' => $clinicalSummary,   // ringkasan 1 baris untuk preview
        ];

        // ── Journey / lead ────────────────────────────────────────────────
        $journeyCfg = [
            'Stat_New'   => ['Tercatat sebagai kontak baru', 'fa-user-plus', '#1E5A8C', '#E8F0F5'],
            'Stat_Leads' => ['Menjadi leads',                'fa-comments',  '#B26B00', '#FFF4E1'],
            'Stat_Rsv'   => ['Tercatat membuat reservasi',   'fa-bullseye',  '#2E7D32', '#E8F5E9'],
        ];
        $contactId = $customer->raw_contact_id;
        if (! $contactId && $customer->phone) {
            $contactId = DB::table('raw_contact')->where('phone', $customer->phone)->value('id');
        }
        $journeys = DB::table('raw_cus_journey')
            ->where('raw_customer_id', $customer->id)
            ->when($contactId, fn($q) => $q->orWhere('raw_contact_id', $contactId))
            ->get();
        foreach ($journeys as $j) {
            $cfg = $journeyCfg[$j->journey_label]
                ?? [$j->journey_label, 'fa-circle', '#6B4423', '#F5F0EA'];
            $tag = ($j->raw_contact_id && ! $j->raw_customer_id) ? 'via Contact' : null;
            $events[] = $mk($j->created_at, 'journey', $cfg[0],
                'Tahap funnel marketing/sales', $cfg[1], $cfg[2], $cfg[3], null, $tag);
        }

        // ── Reservasi Data Center ─────────────────────────────────────────
        foreach ($customer->reservations as $r) {
            $detail = $r->doctor->name ?? 'Dokter belum ditentukan';
            if ($r->status) {
                $detail .= ' · status: ' . $r->status;
            }
            $events[] = $mk($r->date, 'reservation',
                'Reservasi' . ($r->branch->name ? ' — ' . $r->branch->name : ''),
                $detail, 'fa-calendar-day', '#6B4423', '#F5F0EA');
        }

        // ── Perawatan nyata (Omnicare) ────────────────────────────────────
        if (Schema::hasTable('customer_omnicare_link') && Schema::hasTable('omnicare_sales_detail')) {
            $pid = DB::table('customer_omnicare_link')
                ->where('raw_customer_id', $customer->id)->value('omnicare_pid');
            if ($pid) {
                $lines = DB::table('omnicare_sales_detail')
                    ->where('p_id', $pid)
                    ->whereNotNull('encounter_id')->where('encounter_id', '<>', '')
                    ->select('encounter_id', 'trx_date', 'place_id', 'branch_name', 'doctor',
                             'net_sell_price', 'item_type', 'item_name', 'qty_sold', 'payment_status')
                    ->get();

                // Pre-fetch rekam medis per encounter_id (kalau tabel sudah ada).
                // Key composite (record_id, place_id) — mirror PK omnicare_medical_record.
                $medByEnc = [];
                if (Schema::hasTable('omnicare_medical_record')) {
                    $encIds = $lines->pluck('encounter_id')->unique()->filter()->values();
                    if ($encIds->isNotEmpty()) {
                        $meds = DB::table('omnicare_medical_record')
                            ->where('p_id', $pid)
                            ->whereIn('record_id', $encIds)
                            ->get();
                        foreach ($meds as $m) {
                            $medByEnc[$m->record_id . '@' . $m->branch_id] = $m;
                        }
                    }
                }

                foreach (OmnicareEncounters::group($lines) as $enc) {
                    $mKey = $enc['encounter_id'] . '@' . ($enc['place_id'] ?? '');
                    $med = $medByEnc[$mKey] ?? null;
                    $summary = $med ? OmnicareEncounters::buildClinicalSummary($med) : '';
                    $detail = $summary !== ''
                        ? $summary
                        : \Illuminate\Support\Str::limit(implode(' · ', $enc['items']), 150);

                    $events[] = $mk($enc['trx_date'], 'treatment',
                        'Perawatan' . ($enc['branch_name'] ? ' — ' . $enc['branch_name'] : ''),
                        $detail,
                        'fa-tooth', '#1f7a3d', '#E8F5E9',
                        $enc['revenue'], $enc['doctor'] ?: null,
                        $med, $summary);
                }
            }
        }

        // Terbaru dulu (konsisten dengan tampilan sebelumnya)
        usort($events, fn($a, $b) => $b->date->timestamp <=> $a->date->timestamp);

        return $events;
    }

    public function update($id)
    {
        // Akses cross-cabang: update pasien diizinkan dari cabang mana pun.
        // (Hapus tetap terkunci — lihat destroy().)
        //
        // Model 1-nomor-banyak-pasien: nomor BOLEH duplikat antar pasien
        // (keluarga / pindah cabang). Tidak ada unique constraint lagi —
        // raw_contact_id di-resolve via ResolvesPatientContact.
        request()->validate([
            'name'       => 'required|max:255',
            'phone'      => 'nullable|regex:/^0[0-9]{9,14}$/',
            'source_app' => 'nullable|max:255',
            'relasi'     => 'nullable|in:diri_sendiri,pasangan,anak,orang_tua,lainnya',
        ], [
            'phone.regex' => 'Format nomor tidak valid. Harus diawali 0 dan minimal 10 digit (contoh: 08123456789).',
        ]);

        $customer  = RawCustomer::findOrFail($id);
        $rawPhone  = request('phone');
        $newPhone  = $rawPhone ? (Phone::normalize($rawPhone) ?: $rawPhone) : null;
        $phoneChanged = $newPhone !== $customer->phone;

        DB::transaction(function () use ($customer, $newPhone, $phoneChanged) {
            $contactId = $customer->raw_contact_id;

            // Re-resolve contact kalau nomor berubah atau pasien masih orphan.
            if ($newPhone && ($phoneChanged || ! $contactId)) {
                $contact   = $this->resolveContact($newPhone, request('name'), 'admin', 'admin');
                $contactId = $contact->id;
            }

            $customer->update([
                'raw_contact_id'     => $contactId,
                'relasi'             => request('relasi') ?: ($customer->relasi ?: 'diri_sendiri'),
                'name'               => request('name'),
                'phone'              => $newPhone,
                'email'              => request('email'),
                'jenis_kelamin'      => request('jenis_kelamin'),
                'tanggal_lahir'      => request('tanggal_lahir') ?: null,
                'nik'                => request('nik'),
                'tempat_lahir'       => request('tempat_lahir'),
                'alamat'             => request('alamat'),
                'tanggal_daftar'     => request('tanggal_daftar') ?: null,
                'source_app'         => request('source_app'),
                'raw_branch_id'      => request('raw_branch_id') ?: null,
                'raw_doc_service_id' => request('raw_doc_service_id') ?: null,
                'raw_doctor_id'      => request('raw_doctor_id') ?: null,
                'pasien_baru_lama'   => request('pasien_baru_lama'),
                'raw_promo_id'       => request('raw_promo_id') ?: null,
                'catatan'            => request('catatan'),
            ]);
        });

        return redirect()->route('rawcustomer.edit', $id)->with('success', 'Pasien berhasil diubah');
    }

    public function destroy($id)
    {
        $sessionBranch = session('raw_branch_id');
        $customer = RawCustomer::with('reservations')->findOrFail($id);

        // HAPUS tetap terkunci ke cabang asal (safety net cross-cabang).
        // Akses lihat/edit/pakai pasien diizinkan dari cabang mana pun,
        // tapi delete-permanen hanya boleh oleh staff cabang asal pasien.
        if ($sessionBranch) {
            $sameBranch     = $customer->raw_branch_id === $sessionBranch;
            $hasReservation = $customer->reservations->contains('raw_branch_id', $sessionBranch);
            if (!$sameBranch && !$hasReservation) {
                return redirect()->route('rawcustomer')->with('error', 'Hapus pasien hanya bisa oleh staff cabang asal pasien.');
            }
        }

        // Detach reservasi (set customer ke NULL, reservasi tetap ada)
        $customer->reservations()->update(['raw_customer_id' => null]);

        // Detach journey
        \Illuminate\Support\Facades\DB::table('raw_cus_journey')
            ->where('raw_customer_id', $id)
            ->update(['raw_customer_id' => null]);

        $customer->delete();
        return redirect()->route('rawcustomer')->with('success', 'Pasien berhasil dihapus. Reservasi terkait tetap tersimpan.');
    }
}
