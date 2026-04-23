<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawAffiliate;
use App\Models\RawPromo;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function index()
    {
        $perPage           = $this->perPage;
        $filterRegStatus   = request('registration_status', 'all');
        $filterType        = request('type', 'all');
        $search            = trim((string) request('q', ''));

        $query = RawAffiliate::with(['branch', 'doctor', 'docService'])
            ->withCount('promos', 'commissions');

        if ($filterRegStatus !== 'all') {
            $query->where('registration_status', $filterRegStatus);
        }

        if ($filterType !== 'all') {
            if ($filterType === 'none') {
                $query->whereNull('type');
            } else {
                $query->where('type', $filterType);
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $affiliates = $query
            ->orderByRaw("CASE WHEN registration_status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate($perPage)
            ->appends([
                'registration_status' => $filterRegStatus,
                'type'                => $filterType,
                'q'                   => $search,
            ]);

        $regCounts = [
            'all'      => RawAffiliate::count(),
            'pending'  => RawAffiliate::where('registration_status', RawAffiliate::REG_STATUS_PENDING)->count(),
            'approved' => RawAffiliate::where('registration_status', RawAffiliate::REG_STATUS_APPROVED)->count(),
            'rejected' => RawAffiliate::where('registration_status', RawAffiliate::REG_STATUS_REJECTED)->count(),
        ];

        return view('crud.affiliate.list', compact(
            'affiliates',
            'filterRegStatus',
            'filterType',
            'search',
            'regCounts'
        ));
    }

    public function create()
    {
        return view('crud.affiliate.new');
    }   

    public function store()
    {
        request()->validate([
            'name'             => 'required|max:255',
            'role' => 'required|in:' . implode(',', array_keys(RawAffiliate::roles())),
            'phone'            => 'nullable|max:20',
            'bank_account_info'=> 'nullable|max:255',
            'commission_rate'  => 'required|numeric|min:0|max:100',
            'is_active'        => 'nullable',
        ]);

        RawAffiliate::create([
            'id'               => \Illuminate\Support\Str::uuid()->toString(),
            'name'             => request('name'),
            'role'             => request('role', 'pasien'),
            'phone'            => request('phone'),
            'bank_account_info'=> request('bank_account_info'),
            'commission_rate'  => request('commission_rate'),
            'is_active'        => request('is_active', 1),
            'created_at'       => now(),
        ]);

        return redirect()->route('rawaffiliate')->with('success', 'Affiliate berhasil ditambahkan');
    }

    public function edit($id)
    {
        $affiliate = RawAffiliate::with('promos', 'commissions.reservation')->findOrFail($id);
        return view('crud.affiliate.edit', compact('affiliate'));
    }

    public function update($id)
    {
        request()->validate([
            'name'             => 'required|max:255',
            'role' => 'required|in:' . implode(',', array_keys(RawAffiliate::roles())),
            'phone'            => 'nullable|max:20',
            'bank_account_info'=> 'nullable|max:255',
            'commission_rate'  => 'required|numeric|min:0|max:100',
            'pin'              => 'nullable|digits:6',
        ]);

        $affiliate = RawAffiliate::findOrFail($id);
        $data = [
            'name'             => request('name'),
            'role'             => request('role', $affiliate->role),
            'phone'            => request('phone'),
            'bank_account_info'=> request('bank_account_info'),
            'commission_rate'  => request('commission_rate'),
            'is_active'        => request('is_active', 0),
        ];

        if (request('pin')) {
            $data['pin'] = bcrypt(request('pin'));
        }

        $affiliate->update($data);

        return redirect()->route('rawaffiliate.edit', $id)->with('success', 'Affiliate berhasil diubah');
    }

    public function destroy($id)
    {
        $affiliate = RawAffiliate::findOrFail($id);

        if ($affiliate->promos()->count() > 0) {
            return redirect()->route('rawaffiliate')->with('error', 'Tidak bisa dihapus, masih memiliki promo aktif');
        }

        $affiliate->delete();
        return redirect()->route('rawaffiliate')->with('success', 'Affiliate berhasil dihapus');
    }

    public function approve($id)
    {
        request()->validate([
            'pin'  => 'required|digits:6',
            'type' => 'required|in:koc,kol',
        ], [
            'pin.required'  => 'PIN 6 digit wajib diisi.',
            'pin.digits'    => 'PIN harus tepat 6 digit angka.',
            'type.required' => 'Type (KOC/KOL) wajib dipilih.',
        ]);

        $affiliate = RawAffiliate::findOrFail($id);

        if ($affiliate->registration_status !== RawAffiliate::REG_STATUS_PENDING) {
            return redirect()->route('rawaffiliate')->with('error', 'Affiliate ini sudah direview sebelumnya.');
        }

        $affiliate->registration_status = RawAffiliate::REG_STATUS_APPROVED;
        $affiliate->type                = request('type');
        $affiliate->pin                 = bcrypt(request('pin'));
        $affiliate->role                = $affiliate->role ?: 'pasien';
        $affiliate->is_active           = 1;
        $affiliate->save();

        $promoCode = $this->generatePromoCode($affiliate);

        RawPromo::create([
            'id'               => Str::uuid()->toString(),
            'raw_affiliate_id' => $affiliate->id,
            'promo_code'       => $promoCode,
            'discount_type'    => 'nominal',
            'discount_value'   => 100000,
            'is_active'        => 1,
            'created_at'       => now(),
        ]);

        return redirect()->route('rawaffiliate')
            ->with('success', "Pendaftaran {$affiliate->name} disetujui. Promo code: {$promoCode} | PIN sudah di-set, share via WA ke pasien.");
    }

    public function reject($id)
    {
        request()->validate([
            'registration_notes' => 'required|min:3|max:1000',
        ]);

        $affiliate = RawAffiliate::findOrFail($id);

        if ($affiliate->registration_status !== RawAffiliate::REG_STATUS_PENDING) {
            return redirect()->route('rawaffiliate')->with('error', 'Affiliate ini sudah direview sebelumnya.');
        }

        $affiliate->registration_status = RawAffiliate::REG_STATUS_REJECTED;
        $affiliate->registration_notes  = request('registration_notes');
        $affiliate->is_active           = 0;
        $affiliate->save();

        return redirect()->route('rawaffiliate')
            ->with('success', "Pendaftaran {$affiliate->name} ditolak.");
    }

    private function generatePromoCode(RawAffiliate $affiliate): string
    {
        $firstWord = explode(' ', trim($affiliate->name))[0];
        $base      = preg_replace('/[^A-Z]/', '', strtoupper($firstWord));

        if ($base === '' || $base === null) {
            $base = 'PROMO';
        }

        foreach (['100', '200', '300', '400', '500'] as $suffix) {
            $code = $base . $suffix;
            if (!RawPromo::where('promo_code', $code)->exists()) {
                return $code;
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $code = $base . '-' . strtoupper(Str::random(2));
            if (!RawPromo::where('promo_code', $code)->exists()) {
                return $code;
            }
        }

        return $base . '-' . strtoupper(substr(Str::uuid()->toString(), 0, 4));
    }
}