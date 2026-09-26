<?php

namespace App\Services;

use App\Models\AffiliateRewardClaim;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pintu `claimed → used` klaim reward Sweet Mates.
 *
 * Sampai 26 Sep 2026 TIDAK ADA satu baris kode pun yang menulis status
 * 'used'. Klaim berhenti di 'claimed', canSwitchProgram() false selamanya, dan
 * layar KOC menyuruh "tunggu admin proses" — padahal tak ada admin yang bisa.
 * Pola keempat "sistemnya bisa, manusianya tak punya pintu".
 *
 * Aksi ini menyentuh UANG (Free Scaling / Retainer / Whitening sungguhan):
 *   - hanya dari 'claimed' (KOC sudah meminta) — 'eligible' belum diminta;
 *   - merekam siapa (`processed_by`) dan kapan (`processed_at`);
 *     `claimed_at` TIDAK ditimpa — itu waktu KOC meminta;
 *   - mengisi `consumed_content_count` = syarat tier SAAT INI, supaya konten
 *     sisanya terbawa ke program berikutnya (RawAffiliate::approvedContentCount).
 *
 * ⛔ Sengaja TIDAK ada pembatalan. Kalau suatu hari perlu diurungkan, buat
 *    aksi TERPISAH yang meninggalkan jejak — bukan tombol toggle.
 */
class KocReward
{
    public function tandaiDiberikan(string $claimId, string $userId, ?string $catatan = null): AffiliateRewardClaim
    {
        return DB::transaction(function () use ($claimId, $userId, $catatan) {
            $claim = AffiliateRewardClaim::with('tier')->lockForUpdate()->find($claimId);

            if (!$claim) {
                throw new RuntimeException('Klaim tidak ditemukan.');
            }
            if ($claim->status !== AffiliateRewardClaim::STATUS_CLAIMED) {
                throw new RuntimeException($claim->status === AffiliateRewardClaim::STATUS_USED
                    ? 'Reward ini sudah ditandai diberikan sebelumnya.'
                    : 'KOC belum mengajukan klaim untuk reward ini.');
            }
            if (!$claim->tier) {
                throw new RuntimeException('Tier klaim ini tidak ditemukan — tak bisa menghitung konten yang dimakan.');
            }

            $jejak = sprintf('[%s] Reward diberikan: %s.', now()->format('Y-m-d H:i'), $claim->tier->name . ($claim->tier->reward_description ? ' — ' . $claim->tier->reward_description : ''));
            $catatan = trim((string) $catatan);
            if ($catatan !== '') {
                $jejak .= ' ' . $catatan;
            }

            $claim->status                 = AffiliateRewardClaim::STATUS_USED;
            $claim->processed_by           = $userId;
            $claim->processed_at           = now();
            $claim->consumed_content_count = (int) $claim->tier->required_content_count;
            $claim->notes                  = trim(($claim->notes ? $claim->notes . "\n" : '') . $jejak);
            $claim->save();

            return $claim;
        });
    }
}
