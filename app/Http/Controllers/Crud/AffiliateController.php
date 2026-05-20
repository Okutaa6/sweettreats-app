<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawAffiliate;
use App\Models\RawBranch;
use App\Models\RawPromo;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function index()
    {
        $perPage           = $this->perPage;
        $filterRegStatus   = request('registration_status', 'all');
        $filterType        = request('type', 'all');
        $filterKolProgress = request('kol_progress', 'all');
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

        if ($filterKolProgress !== 'all') {
            $query->where('kol_progress', $filterKolProgress);
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
                'kol_progress'        => $filterKolProgress,
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
            'filterKolProgress',
            'search',
            'regCounts'
        ));
    }

    public function create()
    {
        $branches = RawBranch::orderBy('name')->get();
        return view('crud.affiliate.new', compact('branches'));
    }

    public function store()
    {
        $scopeKeys      = array_keys(RawAffiliate::referralScopes());
        $kategoriKeys   = array_keys(RawAffiliate::kolKategoriOptions());
        $progressKeys   = array_keys(RawAffiliate::kolProgressOptions());
        $statusKeys     = array_keys(RawAffiliate::kolStatusRelasiOptions());

        request()->validate([
            'name'              => 'required|max:255',
            'role'              => 'required|in:' . implode(',', array_keys(RawAffiliate::roles())),
            'type'              => 'nullable|in:koc,kol',
            'raw_branch_id'     => 'nullable|exists:raw_branch,id',
            'phone'             => 'nullable|max:20',
            'email'             => 'nullable|email|max:255',
            'bank_account_info' => 'nullable|max:255',
            'referral_scope'    => 'nullable|in:' . implode(',', $scopeKeys),
            // KOL-specific
            'kol_kategori'      => 'nullable|in:' . implode(',', $kategoriKeys),
            'kol_progress'      => 'nullable|in:' . implode(',', $progressKeys),
            'kol_status_relasi' => 'nullable|in:' . implode(',', $statusKeys),
            'followers_count'   => 'nullable|integer|min:0',
            'rate_card'         => 'nullable|max:255',
            'contact_person'    => 'nullable|max:255',
            'tanggal_approach'  => 'nullable|date',
            'social_platform'   => 'nullable|in:' . implode(',', array_keys(RawAffiliate::socialPlatforms())),
            'social_username'   => 'nullable|max:100',
            'feedback'          => 'nullable|string|max:5000',
        ]);

        RawAffiliate::create([
            'id'                  => \Illuminate\Support\Str::uuid()->toString(),
            'name'                => request('name'),
            'role'                => request('role', 'pasien'),
            'type'                => request('type'),
            'raw_branch_id'       => request('raw_branch_id') ?: null,
            'phone'               => request('phone'),
            'email'               => request('email'),
            'bank_account_info'   => request('bank_account_info'),
            'referral_scope'      => request('referral_scope'),
            'kol_kategori'        => request('kol_kategori'),
            'kol_progress'        => request('kol_progress'),
            'kol_status_relasi'   => request('kol_status_relasi'),
            'followers_count'     => request('followers_count') ?: null,
            'rate_card'           => request('rate_card'),
            'contact_person'      => request('contact_person'),
            'tanggal_approach'    => request('tanggal_approach') ?: null,
            'social_platform'     => request('social_platform'),
            'social_username'     => request('social_username'),
            'feedback'            => request('feedback'),
            'commission_rate'     => 0,
            'is_active'           => request('is_active', 1),
            'registration_status' => RawAffiliate::REG_STATUS_APPROVED, // manual entry → approved
            'created_at'          => now(),
        ]);

        return redirect()->route('rawaffiliate')->with('success', 'Affiliate berhasil ditambahkan');
    }

    public function edit($id)
    {
        $affiliate = RawAffiliate::with('promos', 'commissions.reservation')->findOrFail($id);
        $branches  = RawBranch::orderBy('name')->get();
        return view('crud.affiliate.edit', compact('affiliate', 'branches'));
    }

    public function update($id)
    {
        $scopeKeys      = array_keys(RawAffiliate::referralScopes());
        $kategoriKeys   = array_keys(RawAffiliate::kolKategoriOptions());
        $progressKeys   = array_keys(RawAffiliate::kolProgressOptions());
        $statusKeys     = array_keys(RawAffiliate::kolStatusRelasiOptions());

        request()->validate([
            'name'              => 'required|max:255',
            'role'              => 'required|in:' . implode(',', array_keys(RawAffiliate::roles())),
            'raw_branch_id'     => 'nullable|exists:raw_branch,id',
            'phone'             => 'nullable|max:20',
            'email'             => 'nullable|email|max:255',
            'bank_account_info' => 'nullable|max:255',
            'pin'               => 'nullable|digits:6',
            'referral_scope'    => 'nullable|in:' . implode(',', $scopeKeys),
            // KOL-specific
            'kol_kategori'      => 'nullable|in:' . implode(',', $kategoriKeys),
            'kol_progress'      => 'nullable|in:' . implode(',', $progressKeys),
            'kol_status_relasi' => 'nullable|in:' . implode(',', $statusKeys),
            'followers_count'   => 'nullable|integer|min:0',
            'rate_card'         => 'nullable|max:255',
            'contact_person'    => 'nullable|max:255',
            'tanggal_approach'  => 'nullable|date',
            'feedback'          => 'nullable|string|max:5000',
        ]);

        $affiliate = RawAffiliate::findOrFail($id);
        $data = [
            'name'              => request('name'),
            'role'              => request('role', $affiliate->role),
            'raw_branch_id'     => request('raw_branch_id') ?: null,
            'phone'             => request('phone'),
            'email'             => request('email', $affiliate->email),
            'bank_account_info' => request('bank_account_info'),
            'referral_scope'    => request('referral_scope') ?: $affiliate->referral_scope,
            // KOL-specific (nullable, biarkan kosong tetap NULL)
            'kol_kategori'      => request('kol_kategori') ?: null,
            'kol_progress'      => request('kol_progress') ?: null,
            'kol_status_relasi' => request('kol_status_relasi') ?: null,
            'followers_count'   => request('followers_count') !== null && request('followers_count') !== '' ? (int) request('followers_count') : null,
            'rate_card'         => request('rate_card'),
            'contact_person'    => request('contact_person'),
            'tanggal_approach'  => request('tanggal_approach') ?: null,
            'feedback'          => request('feedback'),
            'is_active'         => request('is_active', 0),
        ];

        if (request('pin')) {
            $data['pin'] = bcrypt(request('pin'));
        }

        $affiliate->update($data);

        // Sync denormalized scope ke semua promo milik affiliate ini.
        if ($data['referral_scope'] !== null && $data['referral_scope'] !== '') {
            RawPromo::where('raw_affiliate_id', $affiliate->id)
                ->update(['referral_scope' => $data['referral_scope']]);
        }

        // Preserve ?return= supaya tombol Kembali tetap mengarah ke halaman asal
        // (mis. /kol, /koc, /sweet-mates) walaupun sudah save & re-render.
        $redirectParams = ['id' => $id];
        if (request('return')) {
            $redirectParams['return'] = request('return');
        }
        return redirect()->route('rawaffiliate.edit', $redirectParams)->with('success', 'Affiliate berhasil diubah');
    }

    /**
     * Tambah kode referral baru langsung dari halaman edit affiliate.
     * Discount value & scope auto-derive dari affiliate (sesuai scope-nya).
     */
    public function addPromo($id)
    {
        $affiliate = RawAffiliate::findOrFail($id);

        request()->validate([
            'promo_code'  => 'nullable|max:50|unique:raw_promo,promo_code',
            'valid_until' => 'nullable|date|after:today',
        ]);

        if (!$affiliate->referral_scope) {
            return redirect()->route('rawaffiliate.edit', $id)
                ->with('error', 'Set Referral Scope dulu sebelum tambah kode promo (Behel/Non-Behel).');
        }

        $code = request('promo_code') ? strtoupper(trim(request('promo_code'))) : $this->generatePromoCode($affiliate);

        RawPromo::create([
            'id'               => Str::uuid()->toString(),
            'raw_affiliate_id' => $affiliate->id,
            'referral_scope'   => $affiliate->referral_scope,
            'promo_code'       => $code,
            'discount_type'    => 'nominal',
            'discount_value'   => $affiliate->voucherAmount(),
            'valid_until'      => request('valid_until') ?: null,
            'is_active'        => 1,
            'created_at'       => now(),
        ]);

        $voucherFmt = number_format($affiliate->voucherAmount(), 0, ',', '.');
        return redirect()->route('rawaffiliate.edit', $id)
            ->with('success', "Kode referral baru ditambahkan: {$code} (voucher Rp {$voucherFmt}).");
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
        $scopeKeys = array_keys(RawAffiliate::referralScopes());

        request()->validate([
            'pin'            => 'required|digits:6',
            'type'           => 'required|in:koc,kol',
            'referral_scope' => 'required|in:' . implode(',', $scopeKeys),
        ], [
            'pin.required'              => 'PIN 6 digit wajib diisi.',
            'pin.digits'                => 'PIN harus tepat 6 digit angka.',
            'type.required'             => 'Type (KOC/KOL) wajib dipilih.',
            'referral_scope.required'   => 'Referral scope (Behel/Non-Behel) wajib dipilih.',
        ]);

        $affiliate = RawAffiliate::findOrFail($id);

        if ($affiliate->registration_status !== RawAffiliate::REG_STATUS_PENDING) {
            return redirect()->route('rawaffiliate')->with('error', 'Affiliate ini sudah direview sebelumnya.');
        }

        $affiliate->registration_status = RawAffiliate::REG_STATUS_APPROVED;
        $affiliate->type                = request('type');
        $affiliate->referral_scope      = request('referral_scope');
        $affiliate->pin                 = bcrypt(request('pin'));
        $affiliate->role                = $affiliate->role ?: 'pasien';
        $affiliate->is_active           = 1;
        // Fase 2C: kalau behel & sudah pilih reward_program saat daftar, mulai counter sekarang.
        if ($affiliate->reward_program && !$affiliate->reward_program_started_at) {
            $affiliate->reward_program_started_at = now();
        }
        $affiliate->save();

        // Voucher amount per scope: behel = 100k, non_behel = 50k
        $voucherAmount = $affiliate->voucherAmount();

        $promoCode = $this->generatePromoCode($affiliate);

        RawPromo::create([
            'id'               => Str::uuid()->toString(),
            'raw_affiliate_id' => $affiliate->id,
            'referral_scope'   => $affiliate->referral_scope,
            'promo_code'       => $promoCode,
            'discount_type'    => 'nominal',
            'discount_value'   => $voucherAmount,
            'is_active'        => 1,
            'created_at'       => now(),
        ]);

        $voucherFmt = number_format($voucherAmount, 0, ',', '.');
        return redirect()->route('rawaffiliate')
            ->with('success', "Pendaftaran {$affiliate->name} disetujui ({$affiliate->referral_scope}). Promo code: {$promoCode} (voucher Rp {$voucherFmt}) | PIN sudah di-set, share via WA ke pasien.");
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