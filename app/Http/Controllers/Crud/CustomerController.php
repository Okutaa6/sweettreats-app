<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\RawCustomer;
use App\Models\RawDocService;
use App\Models\RawDoctor;
use App\Models\RawPromo;

class CustomerController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $filterSearch  = request('search');
        $filterService = request('service_id');
        $filterDoctor  = request('doctor_id');

        $query = RawCustomer::with([
            'reservations' => function ($q) {
                $q->orderBy('date', 'desc');
            },
            'reservations.branch',
            'reservations.doctor',
            'reservations.services.docService',
        ])->withCount('reservations');

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

        $customers = $query->orderBy('name')->paginate($perPage)->withQueryString();
        $services  = RawDocService::orderBy('name')->get();
        $doctors   = RawDoctor::orderBy('name')->get();

        return view('crud.customer.list', compact('customers', 'services', 'doctors', 'filterSearch', 'filterService', 'filterDoctor'));
    }

    public function create()
    {
        $branches = RawBranch::orderBy('name')->get();
        $services = RawDocService::orderBy('name')->get();
        $doctors  = RawDoctor::orderBy('name')->get();
        $promos   = RawPromo::where('is_active', 1)->orderBy('promo_code')->get();

        return view('crud.customer.new', compact('branches', 'services', 'doctors', 'promos'));
    }

    public function store()
    {
        request()->validate([
            'name'       => 'required|max:255',
            'phone'      => 'nullable|max:20',
            'source_app' => 'nullable|max:255',
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

        return redirect()->route('rawcustomer')->with('success', 'Customer berhasil ditambahkan');
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

        $branches = RawBranch::orderBy('name')->get();
        $services = RawDocService::orderBy('name')->get();
        $doctors  = RawDoctor::orderBy('name')->get();
        $promos   = RawPromo::where('is_active', 1)->orderBy('promo_code')->get();

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

        return view('crud.customer.edit', compact(
            'customer', 'branches', 'services', 'doctors', 'promos',
            'totalKunjungan', 'totalSelesai', 'totalSpending'
        ));
    }

    public function update($id)
    {
        request()->validate([
            'name'       => 'required|max:255',
            'phone'      => 'nullable|max:20',
            'source_app' => 'nullable|max:255',
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

        return redirect()->route('rawcustomer.edit', $id)->with('success', 'Customer berhasil diubah');
    }

    public function destroy($id)
    {
        $customer = RawCustomer::findOrFail($id);

        if ($customer->reservations()->count() > 0) {
            return redirect()->route('rawcustomer')->with('error', 'Data tidak dapat dihapus karena masih memiliki reservasi');
        }

        if ($customer->journeys()->count() > 0) {
            return redirect()->route('rawcustomer')->with('error', 'Data tidak dapat dihapus karena masih memiliki journey');
        }

        $customer->delete();
        return redirect()->route('rawcustomer')->with('success', 'Customer berhasil dihapus');
    }
}
