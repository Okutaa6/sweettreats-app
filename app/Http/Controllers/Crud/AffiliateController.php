<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawAffiliate;

class AffiliateController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;
        $affiliates = RawAffiliate::withCount('promos', 'commissions')
            ->orderBy('name')
            ->paginate($perPage);

        return view('crud.affiliate.list', compact('affiliates'));
    }

    public function create()
    {
        return view('crud.affiliate.new');
    }

    public function store()
    {
        request()->validate([
            'name'            => 'required|max:255',
            'phone'           => 'nullable|max:20',
            'bank_account_info' => 'nullable|max:255',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'is_active'       => 'nullable',
        ]);

        RawAffiliate::create([
            'id'               => \Illuminate\Support\Str::uuid()->toString(),
            'name'             => request('name'),
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
            'name'            => 'required|max:255',
            'phone'           => 'nullable|max:20',
            'bank_account_info' => 'nullable|max:255',
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        $affiliate = RawAffiliate::findOrFail($id);
        $affiliate->update([
            'name'             => request('name'),
            'phone'            => request('phone'),
            'bank_account_info'=> request('bank_account_info'),
            'commission_rate'  => request('commission_rate'),
            'is_active'        => request('is_active', 0),
        ]);

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
}