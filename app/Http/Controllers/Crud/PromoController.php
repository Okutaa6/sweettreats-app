<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawPromo;
use App\Models\RawAffiliate;

class PromoController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;
        $promos = RawPromo::with('affiliate')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return view('crud.promo.list', compact('promos'));
    }

    public function create()
    {
        $affiliates = RawAffiliate::where('is_active', 1)->orderBy('name')->get();
        return view('crud.promo.new', compact('affiliates'));
    }

    public function store()
    {
        request()->validate([
            'promo_code'     => 'required|max:50|unique:raw_promo,promo_code',
            'discount_type'  => 'required|in:nominal,percent',
            'discount_value' => 'required|numeric|min:0',
            'valid_until'    => 'nullable|date|after:today',
            'raw_affiliate_id' => 'nullable|exists:raw_affiliate,id',
            'is_active'      => 'nullable',
        ]);

        RawPromo::create([
            'id'               => \Illuminate\Support\Str::uuid()->toString(),
            'raw_affiliate_id' => request('raw_affiliate_id') ?: null,
            'promo_code'       => strtoupper(request('promo_code')),
            'discount_type'    => request('discount_type'),
            'discount_value'   => request('discount_value'),
            'valid_until'      => request('valid_until') ?: null,
            'is_active'        => request('is_active', 1),
            'created_at'       => now(),
        ]);

        return redirect()->route('rawpromo')->with('success', 'Promo berhasil ditambahkan');
    }

    public function edit($id)
    {
        $promo = RawPromo::with('affiliate')->findOrFail($id);
        $affiliates = RawAffiliate::where('is_active', 1)->orderBy('name')->get();
        return view('crud.promo.edit', compact('promo', 'affiliates'));
    }

    public function update($id)
    {
        request()->validate([
            'promo_code'     => 'required|max:50|unique:raw_promo,promo_code,' . $id,
            'discount_type'  => 'required|in:nominal,percent',
            'discount_value' => 'required|numeric|min:0',
            'valid_until'    => 'nullable|date',
            'raw_affiliate_id' => 'nullable|exists:raw_affiliate,id',
        ]);

        $promo = RawPromo::findOrFail($id);
        $promo->update([
            'raw_affiliate_id' => request('raw_affiliate_id') ?: null,
            'promo_code'       => strtoupper(request('promo_code')),
            'discount_type'    => request('discount_type'),
            'discount_value'   => request('discount_value'),
            'valid_until'      => request('valid_until') ?: null,
            'is_active'        => request('is_active', 0),
        ]);

        return redirect()->route('rawpromo.edit', $id)->with('success', 'Promo berhasil diubah');
    }

    public function destroy($id)
    {
        $promo = RawPromo::findOrFail($id);
        $promo->delete();
        return redirect()->route('rawpromo')->with('success', 'Promo berhasil dihapus');
    }

    // API: validasi promo dari form reservasi
    public function validate_promo()
    {
        $code = strtoupper(request('code'));
        $promo = RawPromo::where('promo_code', $code)
            ->where('is_active', 1)
            ->first();

        if (!$promo) {
            return response()->json(['valid' => false, 'message' => 'Kode promo tidak ditemukan']);
        }

        if ($promo->valid_until && $promo->valid_until->isPast()) {
            return response()->json(['valid' => false, 'message' => 'Kode promo sudah expired']);
        }

        return response()->json([
            'valid'          => true,
            'promo_id'       => $promo->id,
            'discount_type'  => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'valid_until'    => $promo->valid_until ? $promo->valid_until->format('d/m/Y') : null,
            'message'        => $promo->discount_type === 'percent'
                ? 'Diskon ' . $promo->discount_value . '%'
                : 'Diskon Rp ' . number_format($promo->discount_value, 0, ',', '.'),
        ]);
    }
}