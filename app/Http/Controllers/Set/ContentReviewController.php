<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use App\Models\AffiliateContent;
use App\Models\AffiliateRewardClaim;
use App\Models\AffiliateRewardTier;
use App\Models\RawAffiliate;
use App\Services\KocPasien;
use Illuminate\Support\Str;

class ContentReviewController extends Controller
{
    public function index()
    {
        $filterStatus      = request('status', 'pending');
        $filterType        = request('type', 'all');
        $filterPlatform    = request('platform', 'all');
        $filterAffiliateId = request('affiliate_id');
        $search            = trim((string) request('q', ''));

        // Kalau di-link dari KOC list (?affiliate_id=...) — tampilkan semua status
        // affiliate tsb supaya admin bisa lihat full history-nya.
        if ($filterAffiliateId && !request()->has('status')) {
            $filterStatus = 'all';
        }

        $query = AffiliateContent::with(['affiliate', 'reviewer'])
            ->orderByDesc('created_at');

        if ($filterStatus && $filterStatus !== 'all') {
            $query->where('status', $filterStatus);
        }

        if ($filterType && $filterType !== 'all') {
            if ($filterType === 'none') {
                $query->whereHas('affiliate', fn ($q) => $q->whereNull('type'));
            } else {
                $query->whereHas('affiliate', fn ($q) => $q->where('type', $filterType));
            }
        }

        if ($filterPlatform && $filterPlatform !== 'all') {
            $query->where('platform', $filterPlatform);
        }

        if ($filterAffiliateId) {
            $query->where('raw_affiliate_id', $filterAffiliateId);
        }

        if ($search !== '') {
            $query->whereHas('affiliate', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contents = $query->paginate(20)->appends([
            'status'       => $filterStatus,
            'type'         => $filterType,
            'platform'     => $filterPlatform,
            'affiliate_id' => $filterAffiliateId,
            'q'            => $search,
        ]);

        $counts = [
            'all'      => AffiliateContent::count(),
            'pending'  => AffiliateContent::where('status', AffiliateContent::STATUS_PENDING)->count(),
            'approved' => AffiliateContent::where('status', AffiliateContent::STATUS_APPROVED)->count(),
            'rejected' => AffiliateContent::where('status', AffiliateContent::STATUS_REJECTED)->count(),
        ];

        $typeCounts = [
            'koc'  => RawAffiliate::where('type', 'koc')->count(),
            'kol'  => RawAffiliate::where('type', 'kol')->count(),
            'none' => RawAffiliate::whereNull('type')->count(),
        ];

        $platformCounts = AffiliateContent::selectRaw('platform, COUNT(*) as total')
            ->groupBy('platform')
            ->pluck('total', 'platform')
            ->toArray();

        // Gambaran utuh per affiliate di halaman ini: total konten approved +
        // jumlah kontrol (dari tautan pasien yang DISIMPAN, tak pernah ditebak).
        $affiliatesHalaman = $contents->getCollection()->pluck('affiliate')->filter()->unique('id')->values();
        $kontrol = app(KocPasien::class)->ringkasanKontrol($affiliatesHalaman);
        $kontenApproved = AffiliateContent::whereIn('raw_affiliate_id', $affiliatesHalaman->pluck('id'))
            ->where('status', AffiliateContent::STATUS_APPROVED)
            ->selectRaw('raw_affiliate_id, COUNT(*) as total')
            ->groupBy('raw_affiliate_id')
            ->pluck('total', 'raw_affiliate_id')
            ->toArray();

        return view('set.content-review', compact(
            'contents',
            'filterStatus',
            'filterType',
            'filterPlatform',
            'search',
            'counts',
            'typeCounts',
            'platformCounts',
            'kontrol',
            'kontenApproved'
        ));
    }

    public function approve($id)
    {
        $content = AffiliateContent::find($id);

        if (!$content) {
            return redirect()->route('content.review')->with('error', 'Konten tidak ditemukan.');
        }

        if ($content->status !== AffiliateContent::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Konten ini sudah direview sebelumnya.');
        }

        $content->status       = AffiliateContent::STATUS_APPROVED;
        $content->reviewed_by  = session('id');
        $content->reviewed_at  = now();
        $content->review_notes = request('review_notes');
        $content->save();

        $this->syncRewardEligibility($content->raw_affiliate_id);

        return redirect()->back()->with('success', 'Konten berhasil disetujui.');
    }

    public function reject($id)
    {
        request()->validate([
            'review_notes' => 'required|min:3',
        ]);

        $content = AffiliateContent::find($id);

        if (!$content) {
            return redirect()->route('content.review')->with('error', 'Konten tidak ditemukan.');
        }

        if ($content->status !== AffiliateContent::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Konten ini sudah direview sebelumnya.');
        }

        $content->status       = AffiliateContent::STATUS_REJECTED;
        $content->reviewed_by  = session('id');
        $content->reviewed_at  = now();
        $content->review_notes = request('review_notes');
        $content->save();

        return redirect()->back()->with('success', 'Konten berhasil ditolak.');
    }

    public function edit($id)
    {
        $content = AffiliateContent::with(['affiliate', 'reviewer'])->find($id);

        if (!$content) {
            return redirect()->route('content.review')->with('error', 'Konten tidak ditemukan.');
        }

        return view('set.content-review-edit', compact('content'));
    }

    public function update($id)
    {
        request()->validate([
            'status'       => 'required|in:pending,approved,rejected',
            'review_notes' => 'nullable|max:1000',
        ]);

        $newStatus = request('status');
        if ($newStatus === AffiliateContent::STATUS_REJECTED && !trim((string) request('review_notes'))) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Catatan wajib diisi saat menolak konten.');
        }

        $content = AffiliateContent::find($id);
        if (!$content) {
            return redirect()->route('content.review')->with('error', 'Konten tidak ditemukan.');
        }

        $oldStatus = $content->status;

        $content->status       = $newStatus;
        $content->review_notes = request('review_notes');

        if ($newStatus === AffiliateContent::STATUS_PENDING) {
            $content->reviewed_by = null;
            $content->reviewed_at = null;
        } else {
            $content->reviewed_by = session('id');
            $content->reviewed_at = now();
        }

        $content->save();

        if ($oldStatus !== AffiliateContent::STATUS_APPROVED && $newStatus === AffiliateContent::STATUS_APPROVED) {
            $this->syncRewardEligibility($content->raw_affiliate_id);
        } elseif ($oldStatus === AffiliateContent::STATUS_APPROVED && $newStatus !== AffiliateContent::STATUS_APPROVED) {
            $this->removeIneligibleClaims($content->raw_affiliate_id);
        }

        return redirect()->route('content.review')
            ->with('success', 'Review konten berhasil diupdate.');
    }

    /**
     * Bikin klaim 'eligible' saat konten di-approve — HARUS pakai definisi yang
     * sama dengan portal (AffiliatePortalController::rewards()):
     *   - hitung konten lewat RawAffiliate::approvedContentCount() (saldo:
     *     konten approved yang belum dimakan reward), dan
     *   - hanya untuk TIER YANG DIPILIH affiliate (program lock Fase 2C).
     *
     * Versi lama menghitung SEMUA konten approved tanpa window dan bikin klaim
     * untuk SEMUA tier yang tercapai, mengabaikan tier pilihan. Akibatnya
     * affiliate dapat klaim tier yang tak pernah dipilih (bahkan saat belum
     * pilih tier sama sekali), dan klaim liar itu mengunci canSwitchProgram().
     */
    private function syncRewardEligibility(string $affiliateId): void
    {
        $affiliate = RawAffiliate::find($affiliateId);
        if (! $affiliate) return;

        // Belum pilih tier → tidak ada klaim yang boleh lahir.
        $chosenTier = $affiliate->chosenTier();
        if (! $chosenTier) return;

        if ($affiliate->approvedContentCount() < $chosenTier->required_content_count) return;

        // Siklus ini sudah punya klaim → jangan lahirkan kembarannya. Klaim
        // 'used' dari siklus LAMA tier yang sama (turun kelas) tak menghalangi.
        if ($affiliate->cycleRewardClaim()) return;

        AffiliateRewardClaim::create([
            'id'                       => Str::uuid()->toString(),
            'raw_affiliate_id'         => $affiliate->id,
            'affiliate_reward_tier_id' => $chosenTier->id,
            'status'                   => AffiliateRewardClaim::STATUS_ELIGIBLE,
            'created_at'               => now(),
        ]);
    }

    /**
     * Konten approved dicabut → buang klaim 'eligible' yang syaratnya jadi tak
     * terpenuhi. Pakai approvedContentCount() supaya konsisten dengan portal.
     * Klaim yang sudah 'claimed'/'used' tidak disentuh.
     */
    private function removeIneligibleClaims(string $affiliateId): void
    {
        $affiliate = RawAffiliate::find($affiliateId);
        if (! $affiliate) return;

        $approvedCount = $affiliate->approvedContentCount();

        AffiliateRewardClaim::where('raw_affiliate_id', $affiliateId)
            ->where('status', AffiliateRewardClaim::STATUS_ELIGIBLE)
            ->whereHas('tier', function ($q) use ($approvedCount) {
                $q->where('required_content_count', '>', $approvedCount);
            })
            ->delete();
    }
}
