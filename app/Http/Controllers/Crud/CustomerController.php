<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\RawCustomer;
use App\Models\RawDocService;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawPromo;

class CustomerController extends Controller
{
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

        // Sales hanya lihat pasien cabangnya sendiri.
        // Termasuk pasien yang raw_branch_id-nya NULL tapi pernah ada reservasi
        // di cabang ini — safety net untuk data lama yang belum ter-migrasi.
        if ($sessionBranch) {
            $query->where(function ($q) use ($sessionBranch) {
                $q->where('raw_branch_id', $sessionBranch)
                  ->orWhereHas('reservations', function ($r) use ($sessionBranch) {
                      $r->where('raw_branch_id', $sessionBranch);
                  });
            });
        }

        // Admin: filter cabang manual
        if (!$sessionBranch && $filterBranch) {
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

        $customers = $query
            ->addSelect(['latest_reservation_date' => \App\Models\RawCusReservation::select('date')
                ->whereColumn('raw_customer_id', 'raw_customer.id')
                ->orderByDesc('date')
                ->limit(1)
            ])
            ->orderByDesc('latest_reservation_date')
            ->orderBy('created_at')
            ->paginate($perPage)->withQueryString();
        $services  = RawDocService::orderBy('name')->get();
        $branches  = $sessionBranch ? collect() : RawBranch::orderBy('name')->get();

        if ($sessionBranch) {
            $doctorIds = RawDoctorSchedule::where('raw_branch_id', $sessionBranch)
                ->distinct()->pluck('raw_doctor_id');
            $doctors = RawDoctor::whereIn('id', $doctorIds)->orderBy('name')->get();
        } else {
            $doctors = RawDoctor::orderBy('name')->get();
        }

        return view('crud.customer.list', compact('customers', 'services', 'doctors', 'branches', 'filterSearch', 'filterService', 'filterDoctor', 'filterBranch'));
    }

    public function search()
    {
        $term = request('q', '');
        $query = RawCustomer::query();

        // Scope ke cabang user kalau dia terkunci ke 1 cabang.
        // Sales cabang Sunter hanya bisa cari pasien Sunter, dst.
        // Termasuk pasien orphan (raw_branch_id NULL) yang pernah reservasi di cabang ini.
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch) {
            $query->where(function ($q) use ($sessionBranch) {
                $q->where('raw_branch_id', $sessionBranch)
                  ->orWhereHas('reservations', function ($r) use ($sessionBranch) {
                      $r->where('raw_branch_id', $sessionBranch);
                  });
            });
        }

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
            $doctors = RawDoctor::whereIn('id', $doctorIds)->orderBy('name')->get();
        } else {
            $doctors = RawDoctor::orderBy('name')->get();
        }

        return view('crud.customer.new', compact('branches', 'services', 'doctors', 'promos', 'userBranch'));
    }

    public function store()
    {
        request()->validate([
            'name'       => 'required|max:255',
            'phone'      => 'required|regex:/^0[0-9]{9,14}$/|unique:raw_customer,phone',
            'source_app' => 'nullable|max:255',
        ], [
            'phone.required' => 'No. WhatsApp wajib diisi.',
            'phone.regex'  => 'Format nomor tidak valid. Harus diawali 0 dan minimal 10 digit (contoh: 08123456789).',
            'phone.unique' => 'Nomor ini sudah terdaftar di sistem.',
        ]);

        RawCustomer::create([
            'name'               => request('name'),
            'phone'              => request('phone'),
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

        // Sales tidak boleh akses pasien cabang lain.
        // Allow kalau: (a) pasien punya branch sama dengan session, ATAU
        //              (b) pasien orphan (NULL) tapi pernah reservasi di cabang session.
        if ($sessionBranch) {
            $sameBranch     = $customer->raw_branch_id === $sessionBranch;
            $hasReservation = $customer->reservations->contains('raw_branch_id', $sessionBranch);
            if (!$sameBranch && !$hasReservation) {
                return redirect()->route('rawcustomer')->with('error', 'Anda tidak memiliki akses ke pasien cabang lain.');
            }
        }

        $branches = RawBranch::orderBy('name')->get();
        $services = RawDocService::orderBy('name')->get();
        $promos   = RawPromo::where('is_active', 1)->orderBy('promo_code')->get();

        if ($userBranch) {
            $doctorIds = RawDoctorSchedule::where('raw_branch_id', $userBranch->id)
                ->distinct()->pluck('raw_doctor_id');
            $doctors = RawDoctor::whereIn('id', $doctorIds)->orderBy('name')->get();
        } else {
            $doctors = RawDoctor::orderBy('name')->get();
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

        // Journey timeline
        $journeys = \Illuminate\Support\Facades\DB::table('raw_cus_journey')
            ->where('raw_customer_id', $id)
            ->orderByDesc('created_at')
            ->get();

        // Also get contact-based journeys if phone matches
        $contactJourneys = collect();
        if ($customer->phone) {
            $contact = \Illuminate\Support\Facades\DB::table('raw_contact')
                ->where('phone', $customer->phone)
                ->first();
            if ($contact) {
                $contactJourneys = \Illuminate\Support\Facades\DB::table('raw_cus_journey')
                    ->where('raw_contact_id', $contact->id)
                    ->orderByDesc('created_at')
                    ->get();
            }
        }

        $timeline = $journeys->merge($contactJourneys)
            ->sortByDesc('created_at')
            ->values();

        return view('crud.customer.edit', compact(
            'customer', 'branches', 'services', 'doctors', 'promos',
            'totalKunjungan', 'totalSelesai', 'totalSpending', 'timeline',
            'userBranch'
        ));
    }

    public function update($id)
    {
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch) {
            $customer = RawCustomer::with('reservations')->findOrFail($id);
            $sameBranch     = $customer->raw_branch_id === $sessionBranch;
            $hasReservation = $customer->reservations->contains('raw_branch_id', $sessionBranch);
            if (!$sameBranch && !$hasReservation) {
                return redirect()->route('rawcustomer')->with('error', 'Anda tidak memiliki akses ke pasien cabang lain.');
            }
        }

        request()->validate([
            'name'       => 'required|max:255',
            'phone'      => 'nullable|regex:/^0[0-9]{9,14}$/|unique:raw_customer,phone,' . $id,
            'source_app' => 'nullable|max:255',
        ], [
            'phone.regex'  => 'Format nomor tidak valid. Harus diawali 0 dan minimal 10 digit (contoh: 08123456789).',
            'phone.unique' => 'Nomor ini sudah terdaftar di pasien lain.',
        ]);

        $customer = RawCustomer::findOrFail($id);
        $customer->update([
            'name'               => request('name'),
            'phone'              => request('phone'),
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

        return redirect()->route('rawcustomer.edit', $id)->with('success', 'Pasien berhasil diubah');
    }

    public function destroy($id)
    {
        $sessionBranch = session('raw_branch_id');
        $customer = RawCustomer::with('reservations')->findOrFail($id);

        if ($sessionBranch) {
            $sameBranch     = $customer->raw_branch_id === $sessionBranch;
            $hasReservation = $customer->reservations->contains('raw_branch_id', $sessionBranch);
            if (!$sameBranch && !$hasReservation) {
                return redirect()->route('rawcustomer')->with('error', 'Anda tidak memiliki akses ke pasien cabang lain.');
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
