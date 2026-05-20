<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\AffiliateContent;
use App\Models\AffiliateRewardClaim;
use App\Models\RawAffiliate;
use App\Models\RawBranch;
use App\Models\RawCommission;

/**
 * SweetMatesController
 *
 * Mengelola halaman Program Rewards (Sweet Mates) — list semua KOC/KOL
 * yang aktif sebagai member program rewards.
 *
 * Definisi member rewards:
 *   - reward_program IS NOT NULL  (sudah memilih tier: 3x / 12x / above_12x)
 *   - is_active = 1
 *
 * Tab filter:
 *   - all     : semua member
 *   - bronze  : reward_program = '3x'
 *   - silver  : reward_program = '12x'
 *   - gold    : reward_program = 'above_12x'
 *   - eligible: yang sudah memenuhi target konten tapi belum diklaim
 */
class SweetMatesController extends Controller
{
    /**
     * Halaman Program Rewards (default index).
     * URL: /sweet-mates
     */
    public function index()
    {
        // ─── Filter dari request ─────────────────────────────────────────
        $filterTier   = request('tier', 'all'); // 'all' | 'bronze' | 'silver' | 'gold' | 'eligible'
        $filterBranch = request('branch', 'all');
        $filterType   = request('type', 'all'); // 'all' | 'koc' | 'kol'
        $search       = trim((string) request('q', ''));

        // ─── Base query: semua member rewards ────────────────────────────
        $baseQuery = RawAffiliate::whereNotNull('reward_program')
            ->where('is_active', 1)
            ->whereIn('type', ['koc', 'kol']);

        // ─── Hitung counter per tier (untuk stat cards & tab badges) ─────
        $tierCounts = (clone $baseQuery)
            ->selectRaw('reward_program, COUNT(*) as total')
            ->groupBy('reward_program')
            ->pluck('total', 'reward_program')
            ->toArray();

        $counts = [
            'all'    => array_sum($tierCounts),
            'bronze' => $tierCounts['3x']         ?? 0,
            'silver' => $tierCounts['12x']        ?? 0,
            'gold'   => $tierCounts['above_12x']  ?? 0,
        ];

        // ─── Hitung jumlah eligible (sudah cukup konten, belum diklaim) ──
        // Logika eligible: approved_content_count >= required_count untuk tier-nya,
        // dan belum ada AffiliateRewardClaim dengan status='claimed'/'used' untuk tier itu.
        $counts['eligible'] = $this->countEligibleClaims();

        // ─── Build query untuk hasil tabel ───────────────────────────────
        $query = (clone $baseQuery)
            ->with(['branch', 'promos' => function ($q) {
                $q->where('is_active', 1);
            }, 'rewardClaims.tier'])
            ->withCount(['contents as approved_content_count' => function ($q) {
                $q->where('status', AffiliateContent::STATUS_APPROVED);
            }])
            ->withSum(['commissions as total_commission' => function ($q) {
                $q->whereNotIn('status', [RawCommission::STATUS_REJECTED]);
            }], 'amount');

        // Apply tier filter
        $tierMap = ['bronze' => '3x', 'silver' => '12x', 'gold' => 'above_12x'];
        if ($filterTier !== 'all' && $filterTier !== 'eligible') {
            if (isset($tierMap[$filterTier])) {
                $query->where('reward_program', $tierMap[$filterTier]);
            }
        }

        // Apply other filters
        if ($filterBranch !== 'all') {
            $query->where('raw_branch_id', $filterBranch);
        }
        if ($filterType !== 'all' && in_array($filterType, ['koc', 'kol'], true)) {
            $query->where('type', $filterType);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('social_username', 'like', "%{$search}%")
                  ->orWhere('social_instagram', 'like', "%{$search}%")
                  ->orWhere('social_tiktok', 'like', "%{$search}%")
                  ->orWhereHas('promos', function ($p) use ($search) {
                      $p->where('promo_code', 'like', "%{$search}%");
                  });
            });
        }

        // ─── Eksekusi & paginate ─────────────────────────────────────────
        $members = $query
            ->orderBy('reward_program')
            ->orderBy('name')
            ->paginate($this->perPage)
            ->appends(request()->query());

        // ─── Filter eligible (post-fetch karena butuh logic per-row) ─────
        // Catatan: filter ini di-apply di Blade via collection filter, supaya
        // counter tab tetap akurat. Kalau perf jadi issue, bisa dipindah ke SQL.
        if ($filterTier === 'eligible') {
            $members->setCollection(
                $members->getCollection()->filter(fn($m) => $this->isEligibleForClaim($m))->values()
            );
        }

        // ─── Data pendukung ──────────────────────────────────────────────
        $branches = RawBranch::orderBy('name')->get();

        return view('crud.sweet-mates.list', compact(
            'members',
            'branches',
            'counts',
            'filterTier',
            'filterBranch',
            'filterType',
            'search'
        ));
    }

    /**
     * Hitung berapa member yang sudah eligible tapi belum claim.
     */
    private function countEligibleClaims(): int
    {
        $members = RawAffiliate::whereNotNull('reward_program')
            ->where('is_active', 1)
            ->whereIn('type', ['koc', 'kol'])
            ->withCount(['contents as approved_content_count' => function ($q) {
                $q->where('status', AffiliateContent::STATUS_APPROVED);
            }])
            ->get();

        return $members->filter(fn($m) => $this->isEligibleForClaim($m))->count();
    }

    /**
     * Cek apakah member sudah memenuhi syarat klaim (dan belum diklaim).
     */
    private function isEligibleForClaim(RawAffiliate $member): bool
    {
        $tier = $member->chosenTier();
        if (!$tier) return false;

        $count = $member->approved_content_count ?? $member->approvedContentCount();
        if ($count < $tier->required_content_count) return false;

        // Cek apakah sudah ada claim dengan status 'claimed' atau 'used' untuk tier ini
        $hasActiveClaim = AffiliateRewardClaim::where('raw_affiliate_id', $member->id)
            ->where('affiliate_reward_tier_id', $tier->id)
            ->whereIn('status', [
                AffiliateRewardClaim::STATUS_CLAIMED,
                AffiliateRewardClaim::STATUS_USED,
            ])
            ->exists();

        return !$hasActiveClaim;
    }
}
