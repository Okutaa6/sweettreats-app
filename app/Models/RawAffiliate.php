<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawAffiliate extends Model
{
    protected $table = 'raw_affiliate';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'phone',
        'email',
        'photo',
        'social_tiktok',
        'social_instagram',
        'social_youtube',
        'social_platform',
        'social_username',
        'raw_branch_id',
        'raw_doctor_id',
        'raw_doc_service_id',
        'reward_program',
        'reward_program_started_at',
        'pin',
        'role',
        'type',
        'referral_scope',
        'behel_type',
        'kol_kategori',
        'rencana_perawatan',
        'kol_progress',
        'kol_status_relasi',
        'followers_count',
        'rate_card',
        'contact_person',
        'tanggal_approach',
        'feedback',
        'bank_account_info',
        'commission_rate',
        'is_active',
        'registration_status',
        'registration_notes',
        'created_at',
    ];

    protected $casts = [
        'commission_rate'           => 'decimal:2',
        'is_active'                 => 'boolean',
        'created_at'                => 'datetime',
        'reward_program_started_at' => 'datetime',
        'tanggal_approach'          => 'date',
        'followers_count'           => 'integer',
        // Jejak login portal affiliate (IT-2026-045). Ditulis lewat query
        // builder di AffiliatePortalController, bukan mass-assign.
        'last_login'                => 'datetime',
    ];

    const REG_STATUS_PENDING  = 'pending';
    const REG_STATUS_APPROVED = 'approved';
    const REG_STATUS_REJECTED = 'rejected';

    public static function roles(): array
    {
        return [
            'pasien'      => 'Pasien',
            'influencer'  => 'Influencer',
            'dokter'      => 'Dokter',
        ];
    }

    public static function types(): array
    {
        return [
            'koc' => 'KOC',
            'kol' => 'KOL',
        ];
    }

    // ─── Referral Scope (Behel vs Non-Behel) ────────────────────────────
    const SCOPE_BEHEL     = 'behel';
    const SCOPE_NON_BEHEL = 'non_behel';

    public static function referralScopes(): array
    {
        return [
            self::SCOPE_BEHEL => [
                'label'             => 'Pasien Behel',
                'short'             => 'Behel',
                'desc'              => 'Untuk pasien yang sudah/sedang menjalani perawatan behel di Sweet Treats. Eligible untuk Program Rewards.',
                'icon'              => 'fa-tooth',
                'voucher_amount'    => 100000,  // diskon utk pasien yg pakai kode
                'commission_amount' => 100000,  // komisi per pasien yg datang
                'claim_min_patients'=> 5,       // minimal 5 pasien baru untuk klaim 500k komisi
                'has_reward_program'=> true,
                'allowed_service_filter' => ['category' => 'behel'],
            ],
            self::SCOPE_NON_BEHEL => [
                'label'             => 'Pasien Perawatan Standar',
                'short'             => 'Non-Behel',
                'desc'              => 'Untuk pasien yang ambil perawatan Scaling, Whitening, PSA, atau Veneer. Tanpa program rewards.',
                'icon'              => 'fa-spa',
                'voucher_amount'    => 50000,
                'commission_amount' => 50000,
                'claim_min_patients'=> 10,      // minimal 10 pasien untuk klaim 500k komisi
                'has_reward_program'=> false,
                // Validasi by service NAME (kategori service di DB tidak cukup spesifik):
                'allowed_service_names' => ['Scaling', 'Whitening', 'PSA (Perawatan Saluran Akar)', 'Veneer'],
            ],
        ];
    }

    public function scopeConfig(): ?array
    {
        return self::referralScopes()[$this->referral_scope] ?? null;
    }

    public function isBehelScope(): bool
    {
        return $this->referral_scope === self::SCOPE_BEHEL;
    }

    public function isNonBehelScope(): bool
    {
        return $this->referral_scope === self::SCOPE_NON_BEHEL;
    }

    public function voucherAmount(): int
    {
        return (int) ($this->scopeConfig()['voucher_amount'] ?? 0);
    }

    public function commissionAmountPerPatient(): int
    {
        return (int) ($this->scopeConfig()['commission_amount'] ?? 0);
    }

    public function claimMinPatients(): int
    {
        return (int) ($this->scopeConfig()['claim_min_patients'] ?? 0);
    }

    public function hasRewardProgram(): bool
    {
        return (bool) ($this->scopeConfig()['has_reward_program'] ?? false);
    }

    public static function registrationStatusOptions(): array
    {
        return [
            self::REG_STATUS_PENDING  => ['label' => 'Menunggu',  'color' => 'warning'],
            self::REG_STATUS_APPROVED => ['label' => 'Disetujui', 'color' => 'success'],
            self::REG_STATUS_REJECTED => ['label' => 'Ditolak',   'color' => 'danger'],
        ];
    }

    public static function rewardPrograms(): array
    {
        return [
            '3x'        => 'Bronze Mates',
            '12x'       => 'Silver Mates',
            'above_12x' => 'Gold Mates',
        ];
    }

    public static function rewardTiers(): array
    {
        return [
            '3x' => [
                'name'          => 'Bronze Mates',
                'content_count' => 3,
                'content_desc'  => '3× Kontrol + 3 Video',
                'reward'        => 'FREE Scaling',
                'icon'          => 'fa-medal',
                'color'         => '#c68a56',
                'gradient'      => 'linear-gradient(135deg, #c68a56, #a86b3c)',
                'bg_soft'       => 'linear-gradient(135deg, #fbe8d4, #f4d2b0)',
            ],
            '12x' => [
                'name'          => 'Silver Mates',
                'content_count' => 12,
                'content_desc'  => '12× Kontrol + 12 Video',
                'reward'        => 'FREE Retainer + Scaling',
                'icon'          => 'fa-award',
                'color'         => '#9ea3ae',
                'gradient'      => 'linear-gradient(135deg, #b8bcc4, #8a8f9a)',
                'bg_soft'       => 'linear-gradient(135deg, #eef0f3, #dde0e6)',
            ],
            'above_12x' => [
                'name'          => 'Gold Mates',
                'content_count' => 15,
                'content_desc'  => '15× Kontrol + 15 Video',
                'reward'        => 'FREE Retainer + Whitening',
                'icon'          => 'fa-crown',
                'color'         => '#d4a84b',
                'gradient'      => 'linear-gradient(135deg, #e6c25a, #b88a2c)',
                'bg_soft'       => 'linear-gradient(135deg, #fdf2d4, #f7e2a8)',
            ],
        ];
    }

    public function rewardTier(): ?array
    {
        if (!$this->reward_program) return null;
        return self::rewardTiers()[$this->reward_program] ?? null;
    }

    public static function socialPlatforms(): array
    {
        return [
            'tiktok'    => 'TikTok',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'facebook'  => 'Facebook',
            'twitter'   => 'Twitter / X',
        ];
    }

    // ─── KOL-specific options (Fase 2E) ─────────────────────────────────

    public static function kolKategoriOptions(): array
    {
        return [
            'komika_standup'       => 'Komika / Stand Up',
            'musisi_penyanyi'      => 'Musisi / Penyanyi',
            'influencer'           => 'Influencer',
            'content_creator'      => 'Content Creator',
            'athlete'              => 'Atlet',
            'aktris'               => 'Aktris',
            'aktor'                => 'Aktor',
            'moms_content_creator' => 'Moms Content Creator',
        ];
    }

    public static function kolProgressOptions(): array
    {
        return [
            'akan_di_approach'     => ['label' => 'Akan Di-approach',     'color' => 'neutral', 'icon' => 'fa-bullseye'],
            'waiting_confirmation' => ['label' => 'Menunggu Konfirmasi',  'color' => 'warning', 'icon' => 'fa-hourglass-half'],
            'no_respon'            => ['label' => 'No Respon',            'color' => 'danger',  'icon' => 'fa-circle-xmark'],
            'confirm'              => ['label' => 'Confirm',              'color' => 'info',    'icon' => 'fa-check'],
            'done'                 => ['label' => 'Done',                 'color' => 'success', 'icon' => 'fa-check-double'],
        ];
    }

    public static function kolStatusRelasiOptions(): array
    {
        return [
            'baru'              => ['label' => 'Baru',              'color' => 'info'],
            'pernah_kerja_sama' => ['label' => 'Pernah Kerja Sama', 'color' => 'success'],
            'rekomendasi'       => ['label' => 'Rekomendasi',       'color' => 'warning'],
        ];
    }

    public function isKol(): bool
    {
        return $this->type === 'kol';
    }

    public function isKoc(): bool
    {
        return $this->type === 'koc';
    }

    public function scopeConfirmedSosmed($query)
    {
        return $query->where('koc_sosmed_confirmed', 1);
    }

    public function scopeWithApprovedContent($query)
    {
        return $query->whereHas('contents', function ($q) {
            $q->where('status', AffiliateContent::STATUS_APPROVED);
        });
    }
    public function setSosmedConfirmed(bool $confirmed, ?string $userId = null): void
    {
        $this->koc_sosmed_confirmed    = $confirmed;
        $this->koc_sosmed_confirmed_at = $confirmed ? now() : null;
        $this->koc_sosmed_confirmed_by = $confirmed ? $userId : null;
        $this->save();
    }
    /**
     * Daftar tipe behel spesifik untuk affiliate registration (Behel scope).
     * Daftar ini terpisah dari raw_doc_service karena variannya lebih spesifik
     * (kombinasi material + tipe braces + GP/SP).
     */
    public static function behelTypes(): array
    {
        return [
            'self_ligating_metal_sp' => 'Self Ligating Metal Braces (Spesialis)',
            'self_ligating_metal_gp' => 'Self Ligating Metal Braces (GP)',
            'stellar_metal_sp'       => 'Stellar Metal Braces (Spesialis)',
            'stellar_metal_gp'       => 'Stellar Metal Braces (GP)',
            'signature_metal_sp'     => 'Signature Metal Braces (Spesialis)',
            'signature_metal_gp'     => 'Signature Metal Braces (GP)',
            'signature_ceramic_sp'   => 'Signature Ceramic Braces (Spesialis)',
            'signature_ceramic_gp'   => 'Signature Ceramic Braces (GP)',
            'deluxe_metal'           => 'Deluxe Metal (Spesialis)',
            'ceramic_braces_gp'      => 'Ceramic Braces (GP)',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    /**
     * Rekam pasien milik KOC ini. Diisi SEKALI (cocok tunggal otomatis, atau
     * dipilih manusia) — tak pernah ditebak saat layar dibuka. Lihat
     * App\Services\KocPasien. Sengaja tak masuk $fillable.
     */
    public function customer()
    {
        return $this->belongsTo(RawCustomer::class, 'raw_customer_id', 'id');
    }

    public function docService()
    {
        return $this->belongsTo(RawDocService::class, 'raw_doc_service_id', 'id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('uploads/affiliate/' . $this->photo) : null;
    }

    public function promos()
    {
        return $this->hasMany(RawPromo::class, 'raw_affiliate_id', 'id');
    }

    public function commissions()
    {
        return $this->hasMany(RawCommission::class, 'raw_affiliate_id', 'id');
    }

    public function contents()
    {
        return $this->hasMany(AffiliateContent::class, 'raw_affiliate_id', 'id');
    }

    public function rewardClaims()
    {
        return $this->hasMany(AffiliateRewardClaim::class, 'raw_affiliate_id', 'id');
    }

    /**
     * Jumlah konten approved yang BELUM dimakan reward — saldo untuk program
     * yang sedang berjalan.
     *
     *   saldo = total konten approved − SUM(consumed_content_count klaim 'used')
     *
     * Tiap reward memakan TEPAT sejumlah syarat tier-nya; sisanya terbawa ke
     * program berikutnya, naik maupun turun kelas, tanpa kedaluwarsa
     * (keputusan Ikhsan 26 Sep). Linaily: 7 konten, Bronze makan 3 → 4 terbawa.
     *
     * ⛔ Dulu memakai STEMPEL WAKTU (`created_at >= reward_program_started_at`).
     *    Mekanisme itu cuma bisa bilang "semua sebelum X hangus", tak bisa
     *    bilang "bawa 4" — dan tujuh konten Linaily lahir dalam dua menit,
     *    jadi cutoff waktu pun tak bisa memotong di konten ke-3 dengan aman.
     *    `reward_program_started_at` SENGAJA tak dibaca lagi di sini.
     */
    public function approvedContentCount(): int
    {
        $total = $this->contents()->where('status', AffiliateContent::STATUS_APPROVED)->count();

        return max(0, $total - $this->consumedContentCount());
    }

    /** Konten yang sudah dimakan reward yang benar-benar diberikan ('used'). */
    public function consumedContentCount(): int
    {
        return (int) $this->rewardClaims()
            ->where('status', AffiliateRewardClaim::STATUS_USED)
            ->sum('consumed_content_count');
    }

    public function currentTier(): ?AffiliateRewardTier
    {
        $count = $this->approvedContentCount();

        return AffiliateRewardTier::active()
            ->where('required_content_count', '<=', $count)
            ->orderByDesc('required_content_count')
            ->first();
    }

    public function nextTier(): ?AffiliateRewardTier
    {
        $count = $this->approvedContentCount();

        return AffiliateRewardTier::active()
            ->where('required_content_count', '>', $count)
            ->orderBy('required_content_count')
            ->first();
    }

    // ─── Reward Program Lock (Fase 2C) ──────────────────────────────────

    /**
     * Mapping `reward_program` (3x/12x/above_12x) → AffiliateRewardTier record di DB
     * (matched by required_content_count).
     */
    public function chosenTier(): ?AffiliateRewardTier
    {
        if (!$this->reward_program) return null;
        $rewardCfg = self::rewardTiers()[$this->reward_program] ?? null;
        if (!$rewardCfg) return null;

        return AffiliateRewardTier::active()
            ->where('required_content_count', $rewardCfg['content_count'])
            ->first();
    }

    /**
     * Klaim milik SIKLUS program yang sedang berjalan (status apa pun), atau
     * null kalau siklus ini belum melahirkan klaim.
     *
     * Satu tier bisa punya lebih dari satu klaim sepanjang hidup affiliate:
     * Bronze → Silver → Bronze (turun kelas) melahirkan klaim Bronze kedua.
     * Klaim 'used' terbaru untuk tier pilihan dianggap MASIH milik siklus ini
     * hanya kalau ia juga klaim 'used' terbaru secara keseluruhan — artinya
     * affiliate belum pindah program sesudah reward itu diberikan. Kalau
     * sesudahnya ada reward tier lain yang dipakai, affiliate sudah kembali ke
     * tier ini lewat siklus baru, dan klaim lama itu sejarah.
     *
     * Akibat yang disengaja: tak bisa klaim ulang tier yang sama tanpa pindah
     * program dulu (portal memang tak menawarkan pilih tier yang sedang aktif).
     */
    public function cycleRewardClaim(): ?AffiliateRewardClaim
    {
        $tier = $this->chosenTier();
        if (!$tier) return null;

        $claim = AffiliateRewardClaim::where('raw_affiliate_id', $this->id)
            ->where('affiliate_reward_tier_id', $tier->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (!$claim || $claim->status !== AffiliateRewardClaim::STATUS_USED) {
            return $claim;
        }

        $usedTerakhir = AffiliateRewardClaim::where('raw_affiliate_id', $this->id)
            ->where('status', AffiliateRewardClaim::STATUS_USED)
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $usedTerakhir && $usedTerakhir->id === $claim->id ? $claim : null;
    }

    /**
     * Klaim aktif untuk program yang sedang berjalan (eligible / claimed,
     * tapi belum used). Kalau ada → user TIDAK boleh switch program.
     */
    public function activeRewardClaim(): ?AffiliateRewardClaim
    {
        $claim = $this->cycleRewardClaim();

        return $claim && in_array($claim->status, [
            AffiliateRewardClaim::STATUS_ELIGIBLE,
            AffiliateRewardClaim::STATUS_CLAIMED,
        ], true) ? $claim : null;
    }

    /**
     * Boleh switch ke program lain?
     * Aturan: TIDAK boleh selama masih ada klaim berjalan (status 'eligible'
     * atau 'claimed') — tunggu admin proses sampai 'used'. Di luar itu bebas,
     * termasuk kalau affiliate belum pernah klaim sama sekali.
     *
     * Ganti program TIDAK pernah menghanguskan konten: saldo dihitung dari
     * konten yang belum dimakan reward (lihat approvedContentCount()).
     */
    public function canSwitchProgram(): bool
    {
        if (!$this->reward_program) return true;
        return $this->activeRewardClaim() === null;
    }

    /**
     * Progress program saat ini sebagai array siap-render:
     * ['count' => X, 'required' => Y, 'percent' => Z, 'achieved' => bool]
     */
    public function currentProgramProgress(): ?array
    {
        $tier = $this->chosenTier();
        if (!$tier) return null;

        $count    = $this->approvedContentCount();
        $required = (int) $tier->required_content_count;
        $percent  = $required > 0 ? min(100, (int) round(($count / $required) * 100)) : 0;

        return [
            'count'    => $count,
            'required' => $required,
            'percent'  => $percent,
            'achieved' => $count >= $required,
            'tier'     => $tier,
        ];
    }

    // ─── Commission Claim Helpers (Fase 2D) ─────────────────────────────

    /**
     * Commissions yang eligible untuk dibatch claim:
     * - Belum masuk batch lain (di-cek lewat NOT EXISTS subquery ke commission_claim_item)
     * - Status BUKAN 'paid' (sudah dibayar) dan BUKAN 'rejected'
     */
    public function claimableCommissions()
    {
        return RawCommission::where('raw_affiliate_id', $this->id)
            ->whereNotIn('status', ['paid', 'rejected'])
            ->whereNotIn('id', function ($q) {
                $q->select('raw_commission_id')->from('commission_claim_item');
            });
    }

    public function claimableCount(): int
    {
        return $this->claimableCommissions()->count();
    }

    public function claimableAmount(): float
    {
        return (float) $this->claimableCommissions()->sum('amount');
    }

    /**
     * Boleh request klaim baru?
     * Syarat: jumlah commission eligible >= claim_min_patients (5 behel / 10 non-behel)
     * DAN tidak ada batch in-progress (request/in_review/approved/transferred).
     */
    public function canRequestCommissionClaim(): bool
    {
        if (!$this->referral_scope) return false;
        if ($this->pendingClaimBatch()) return false;
        return $this->claimableCount() >= $this->claimMinPatients();
    }

    /**
     * Batch yang masih in-progress (belum completed). Hanya 1 di-allow at any time.
     */
    public function pendingClaimBatch(): ?CommissionClaimBatch
    {
        return CommissionClaimBatch::where('raw_affiliate_id', $this->id)
            ->whereNotIn('status', [CommissionClaimBatch::STATUS_COMPLETED])
            ->orderByDesc('created_at')
            ->first();
    }

    public function claimBatches()
    {
        return $this->hasMany(CommissionClaimBatch::class, 'raw_affiliate_id', 'id');
    }
}
