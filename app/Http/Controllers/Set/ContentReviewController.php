<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use App\Models\AffiliateContent;
use App\Models\AffiliateRewardClaim;
use App\Models\AffiliateRewardTier;
use App\Models\RawAffiliate;
use Illuminate\Support\Str;

class ContentReviewController extends Controller
{
    public function index()
    {
        $filterStatus   = request('status', 'pending');
        $filterType     = request('type', 'all');
        $filterPlatform = request('platform', 'all');
        $search         = trim((string) request('q', ''));

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

        if ($search !== '') {
            $query->whereHas('affiliate', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contents = $query->paginate(20)->appends([
            'status'   => $filterStatus,
            'type'     => $filterType,
            'platform' => $filterPlatform,
            'q'        => $search,
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

        return view('set.content-review', compact(
            'contents',
            'filterStatus',
            'filterType',
            'filterPlatform',
            'search',
            'counts',
            'typeCounts',
            'platformCounts'
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

    private function syncRewardEligibility(string $affiliateId): void
    {
        $approvedCount = AffiliateContent::where('raw_affiliate_id', $affiliateId)
            ->where('status', AffiliateContent::STATUS_APPROVED)
            ->count();

        $tiers = AffiliateRewardTier::active()
            ->where('required_content_count', '<=', $approvedCount)
            ->get();

        foreach ($tiers as $tier) {
            $exists = AffiliateRewardClaim::where('raw_affiliate_id', $affiliateId)
                ->where('affiliate_reward_tier_id', $tier->id)
                ->exists();

            if (!$exists) {
                AffiliateRewardClaim::create([
                    'id'                       => Str::uuid()->toString(),
                    'raw_affiliate_id'         => $affiliateId,
                    'affiliate_reward_tier_id' => $tier->id,
                    'status'                   => AffiliateRewardClaim::STATUS_ELIGIBLE,
                    'created_at'               => now(),
                ]);
            }
        }
    }

    private function removeIneligibleClaims(string $affiliateId): void
    {
        $approvedCount = AffiliateContent::where('raw_affiliate_id', $affiliateId)
            ->where('status', AffiliateContent::STATUS_APPROVED)
            ->count();

        AffiliateRewardClaim::where('raw_affiliate_id', $affiliateId)
            ->where('status', AffiliateRewardClaim::STATUS_ELIGIBLE)
            ->whereHas('tier', function ($q) use ($approvedCount) {
                $q->where('required_content_count', '>', $approvedCount);
            })
            ->delete();
    }
}
