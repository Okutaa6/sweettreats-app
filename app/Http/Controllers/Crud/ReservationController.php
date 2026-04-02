<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\RawCustomer;
use App\Models\RawCusReservation;
use App\Models\RawCusResService;
use App\Models\RawDoctor;
use App\Models\RawDocService;

class ReservationController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage ?? 10;

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

        // Clone the query for calendar view before applying pagination
        $calendarQuery = clone $query;
        $reservations = $query->orderBy('date', 'desc')
            ->orderBy('start_hour', 'desc')
            ->paginate($perPage);

        // Kalau user terkunci ke 1 cabang, dropdown hanya tampilkan cabang itu
        $branches = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        $doctors  = RawDoctor::orderBy('name')->get();
        $statuses = RawCusReservation::statusOptions();
        $calendarReservations = $calendarQuery->orderBy('date', 'asc')->orderBy('start_hour', 'asc')->get(); // Get all results for calendar

        $promos     = \App\Models\RawPromo::orderBy('promo_code')->get();
        $affiliates = \App\Models\RawAffiliate::orderBy('name')->get();

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
            'affiliates'         // new
        ));
    }

    public function create()
    {
        $sessionBranch = session('raw_branch_id');

        // Fallback Database
        $userId = session('user_id') ?? session('id');
        if (!$sessionBranch && $userId) {
            $user = \App\Models\Users::find($userId);
            $sessionBranch = $user->raw_branch_id ?? null;
        }

        // Kalau user terkunci ke 1 cabang, dropdown hanya tampilkan cabang itu
        $branches  = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        $customers = RawCustomer::orderBy('name')->get();
        $doctors   = RawDoctor::orderBy('name')->get();

        return view('crud.reservation.new', compact('branches', 'customers', 'doctors'));
    }

    public function store()
    {
        request()->validate([
            'raw_branch_id'   => 'required',
            'raw_customer_id' => 'required',
            'raw_doctor_id'   => 'required',
            'date'            => 'required|date',
            'start_hour'      => 'required',
            'end_hour'        => 'required',
        ]);

        RawCusReservation::create([
            'raw_branch_id'   => request('raw_branch_id'),
            'raw_customer_id' => request('raw_customer_id'),
            'raw_doctor_id'   => request('raw_doctor_id'),
            'date'            => request('date'),
            'start_hour'      => request('start_hour'),
            'end_hour'        => request('end_hour'),
            'keterangan'      => request('keterangan'),
            'is_waiting'      => request('is_waiting', 0),
            'created_at'      => now(),
        ]);

        return redirect()->route('rawreservation')->with('success', 'Reservasi berhasil ditambahkan');
    }

    public function edit($id)
    {
        $reservation = RawCusReservation::with('branch', 'customer', 'doctor', 'promo', 'commission.affiliate')->findOrFail($id);

        $sessionBranch = session('raw_branch_id');

        // Fallback Database
        $userId = session('user_id') ?? session('id');
        if (!$sessionBranch && $userId) {
            $user = \App\Models\Users::find($userId);
            $sessionBranch = $user->raw_branch_id ?? null;
        }

        // Kalau user terkunci ke 1 cabang, dropdown hanya tampilkan cabang itu
        $branches  = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        $customers   = RawCustomer::orderBy('name')->get();
        $doctors     = RawDoctor::orderBy('name')->get();
        $docServices = RawDocService::orderBy('name')->get();
        $promos      = \App\Models\RawPromo::where('is_active', 1)->orderBy('promo_code')->get();

        $services = RawCusResService::with('doctor', 'docService', 'serviceDetail')
            ->where('raw_cus_reservation_id', $id)
            ->get();

        $totalDuration = $services->sum(function($item) {
            return $item->serviceDetail ? $item->serviceDetail->duration_minutes : ($item->docService->duration_minutes ?? 0);
        });

        return view('crud.reservation.edit', compact(
            'reservation',
            'branches',
            'customers',
            'doctors',
            'docServices',
            'promos',
            'services',
            'totalDuration'
        ));
    }

    public function update($id)
    {
        request()->validate([
            'raw_branch_id'   => 'required',
            'raw_customer_id' => 'required',
            'raw_doctor_id'   => 'required',
            'date'            => 'required|date',
            'start_hour'      => 'required',
            'end_hour'        => 'required',
            'keterangan'      => 'nullable|string|max:65535',
            'raw_promo_id'    => 'nullable|exists:raw_promo,id',
        ]);

        $reservation = RawCusReservation::findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $id) {
            $reservation->update([
                'raw_branch_id'   => request('raw_branch_id'),
                'raw_customer_id' => request('raw_customer_id'),
                'raw_doctor_id'   => request('raw_doctor_id'),
                'date'            => request('date'),
                'start_hour'      => request('start_hour'),
                'end_hour'        => request('end_hour'),
                'status'          => request('status', 'pending'),
                'keterangan'      => request('keterangan'),
                'raw_promo_id'    => request('raw_promo_id'),
                'is_waiting'      => request('is_waiting', 0),
                'updated_at'      => now(),
                'updated_by'      => session('user_id'),
            ]);

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
        });

        return redirect()->route('rawreservation.edit', $id)->with('success', 'Reservasi berhasil diubah');
    }

    public function updateInline($id)
    {
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
            'updated_by' => session('user_id'),
        ]);

        return response()->json(['success' => true]);
    }

    public function klaimPromo($id)
{
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
        'promo_klaim_by' => session('user_id'),
        'updated_at'     => now(),
        'updated_by'     => session('user_id'),
    ]);

    return back()->with('success', 'Promo berhasil diklaim!');
}

    public function destroy($id)
    {
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
        $commission = \App\Models\RawCommission::where('raw_cus_reservation_id', $reservationId)
            ->findOrFail($commissionId);

        $commission->update(['status' => 'approved']);

        return redirect()->route('rawreservation.edit', $reservationId)
            ->with('success', 'Komisi berhasil di-approve');
    }

    public function commissionPay($reservationId, $commissionId)
    {
        $commission = \App\Models\RawCommission::where('raw_cus_reservation_id', $reservationId)
            ->findOrFail($commissionId);

        $commission->update(['status' => 'paid']);

        return redirect()->route('rawreservation.edit', $reservationId)
            ->with('success', 'Komisi berhasil ditandai sebagai dibayar');
    }

    public function serviceStore($reservationId)
    {
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
        $service = RawCusResService::where('raw_cus_reservation_id', $reservationId)
            ->findOrFail($serviceId);

        $service->delete();

        return redirect()->route('rawreservation.edit', $reservationId)->with('success', 'Layanan berhasil dihapus');
    }
}