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

        $query = RawCusReservation::with('branch', 'customer', 'doctor', 'services.docService', 'promo')
            ->withCount('services');

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

        $reservations = $query->orderBy('date', 'desc')->paginate($perPage);

        // Data pendukung untuk dropdown & calendar
        $branches = RawBranch::orderBy('name')->get();
        $doctors  = RawDoctor::orderBy('name')->get();
        $statuses = RawCusReservation::statusOptions();
        $calendarReservations = $query->get(); // Ambil semua data terfilter untuk kalender

        return view('crud.reservation.list', compact(
            'reservations',
            'filterSearch',
            'filterBranch',
            'filterDoctor',
            'filterStatus',
            'filterDateFrom',
            'filterDateTo',
            'branches',
            'doctors',
            'statuses',
            'calendarReservations'
        ));
    }

    

    public function create()
    {
        $branches = RawBranch::orderBy('name')->get();
        $customers = RawCustomer::orderBy('name')->get();
        $doctors = RawDoctor::orderBy('name')->get();

        return view('crud.reservation.new', compact('branches', 'customers', 'doctors'));
    }

    public function store()
    {
        request()->validate([
            'raw_branch_id' => 'required',
            'raw_customer_id' => 'required',
            'raw_doctor_id' => 'required',
            'date' => 'required|date',
            'start_hour' => 'required',
            'end_hour' => 'required',
        ]);

        RawCusReservation::create([
            'raw_branch_id' => request('raw_branch_id'),
            'raw_customer_id' => request('raw_customer_id'),
            'raw_doctor_id' => request('raw_doctor_id'),
            'date' => request('date'),
            'start_hour' => request('start_hour'),
            'end_hour' => request('end_hour'),
            'is_waiting' => request('is_waiting', 0),
            'created_at' => now(),
        ]);

        return redirect()->route('rawreservation')->with('success', 'Reservasi berhasil ditambahkan');
    }

    public function edit($id)
    {
        $reservation = RawCusReservation::with('branch', 'customer', 'doctor', 'promo')->findOrFail($id);
        $branches = RawBranch::orderBy('name')->get();
        $customers = RawCustomer::orderBy('name')->get();
        $doctors = RawDoctor::orderBy('name')->get();
        $docServices = RawDocService::orderBy('name')->get();

        $services = RawCusResService::with('doctor', 'docService', 'serviceDetail')
            ->where('raw_cus_reservation_id', $id)
            ->get();

        // Hitung total durasi berdasarkan sub-detail (jika ada) atau durasi default layanan
        $totalDuration = $services->sum(function($item) {
            return $item->serviceDetail ? $item->serviceDetail->duration_minutes : ($item->docService->duration_minutes ?? 0);
        });

        return view('crud.reservation.edit', compact(
            'reservation',
            'branches',
            'customers',
            'doctors',
            'docServices',
            'services',
            'totalDuration'
        ));
    }

    public function update($id)
    {
        request()->validate([
            'raw_branch_id' => 'required',
            'raw_customer_id' => 'required',
            'raw_doctor_id' => 'required',
            'date' => 'required|date',
            'start_hour' => 'required',
            'end_hour' => 'required',
        ]);

        $reservation = RawCusReservation::findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $id) {
            $reservation->update([
                'raw_branch_id' => request('raw_branch_id'),
                'raw_customer_id' => request('raw_customer_id'),
                'raw_doctor_id' => request('raw_doctor_id'),
                'date' => request('date'),
                'start_hour' => request('start_hour'),
                'end_hour' => request('end_hour'),
                'status' => request('status', 'pending'),
                'is_waiting' => request('is_waiting', 0),
                'updated_at' => now(),
                'updated_by' => session('user_id'),
            ]);

            // Sinkronisasi Layanan (Sync Services)
            RawCusResService::where('raw_cus_reservation_id', $id)->delete();
            $servicesInput = request('services', []);
            foreach ($servicesInput as $svcId => $data) {
                if (isset($data['checked'])) {
                    RawCusResService::create([
                        'raw_cus_reservation_id'    => $id,
                        'raw_doctor_id'             => request('raw_doctor_id'),
                        'raw_doc_service_id'        => $svcId,
                        'raw_doc_service_detail_id' => $data['detail_id'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('rawreservation.edit', $id)->with('success', 'Reservasi berhasil diubah');
    }

    //Update field status, follow_up_h_min_1, follow_up_h, follow_up_h_min_1_jam, keterangan_kehadiran secara inline (AJAX)
    public function updateInline($id)
    {
        $reservation = RawCusReservation::findOrFail($id);

        $field = request('field');
        $value = request('value');

        $allowed = ['status', 'follow_up_h_min_1', 'follow_up_h', 'follow_up_h_min_1_jam', 'keterangan_kehadiran'];

        if (!in_array($field, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Field tidak valid'], 422);
        }

        $reservation->update([
            $field      => $value,
            'updated_at' => now(),
            'updated_by' => session('user_id'),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $reservation = RawCusReservation::findOrFail($id);

        if ($reservation->services()->count() > 0) {
            return redirect()->route('rawreservation')->with('error', 'Data tidak dapat dihapus karena masih memiliki layanan');
        }

        $reservation->delete();
        return redirect()->route('rawreservation')->with('success', 'Reservasi berhasil dihapus');
    }

    //komisi approve & pay (new)
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

    // === Reservation Service (Child CRUD) ===

    public function serviceStore($reservationId)
    {
        request()->validate([
            'raw_doctor_id' => 'required',
            'raw_doc_service_id' => 'required',
        ]);

        RawCusResService::create([
            'raw_cus_reservation_id' => $reservationId,
            'raw_doctor_id' => request('raw_doctor_id'),
            'raw_doc_service_id' => request('raw_doc_service_id'),
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
