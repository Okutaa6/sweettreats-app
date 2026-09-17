<?php

namespace App\Http\Controllers;

use App\Models\AffiliateContent;
use App\Models\AffiliateRewardClaim;
use App\Models\AffiliateRewardTier;
use App\Models\CommissionClaimBatch;
use App\Models\CommissionClaimItem;
use App\Models\RawAffiliate;
use App\Models\RawBranch;
use App\Models\RawCommission;
use App\Models\RawDocService;
use App\Models\RawDoctor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AffiliatePortalController extends Controller
{
    public function login()
    {
        if (session('affiliate_id')) {
            return redirect()->route('affiliate.dashboard');
        }
        return view('affiliate.login');
    }

    public function loginProcess()
    {
        request()->validate([
            'phone' => 'required',
            'pin'   => 'required|digits:6',
        ]);

        $affiliate = RawAffiliate::where('phone', request('phone'))->first();

        if (!$affiliate || !$affiliate->pin || !Hash::check(request('pin'), $affiliate->pin)) {
            return back()->with('error', 'Nomor HP atau PIN salah.')->withInput();
        }

        if ($affiliate->registration_status === RawAffiliate::REG_STATUS_PENDING) {
            return back()->with('error', 'Akun kamu masih menunggu review admin. Sabar ya!')->withInput();
        }
        if ($affiliate->registration_status === RawAffiliate::REG_STATUS_REJECTED) {
            return back()->with('error', 'Maaf, pendaftaran kamu tidak disetujui. Catatan: ' . ($affiliate->registration_notes ?? '-'))->withInput();
        }
        if (!$affiliate->is_active) {
            return back()->with('error', 'Akun kamu belum aktif. Hubungi admin.')->withInput();
        }

        session([
            'affiliate_id'   => $affiliate->id,
            'affiliate_name' => $affiliate->name,
        ]);

        return redirect()->route('affiliate.dashboard');
    }

    public function register()
    {
        if (session('affiliate_id')) {
            return redirect()->route('affiliate.dashboard');
        }

        $branches = RawBranch::orderBy('name')->get();
        $doctors  = RawDoctor::active()->orderBy('name')->get();

        // Behel scope: gunakan custom list (terpisah dari raw_doc_service)
        $behelTypes = RawAffiliate::behelTypes();

        // Non-behel scope: dropdown service Scaling/Whitening/PSA/Veneer dari raw_doc_service
        $nonBehelNames = RawAffiliate::referralScopes()[RawAffiliate::SCOPE_NON_BEHEL]['allowed_service_names'] ?? [];
        $servicesNonBehel = RawDocService::whereIn('name', $nonBehelNames)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('affiliate.register', compact(
            'branches', 'doctors', 'behelTypes', 'servicesNonBehel'
        ));
    }

    public function registerProcess()
    {
        // Perangkap bot — lihat catatan panjang di
        // LandingReservationController::store(). Ditolak terang-terangan, bukan
        // dibuang diam-diam: pendaftar sungguhan harus tahu kalau gagal.
        if (trim((string) request('catatan_tambahan')) !== '') {
            // Dikurung sekali per jam per alamat — alasan yang sama dengan
            // LandingReservationController::store(): form publik, dan
            // `laravel.log` prod tidak berotasi.
            \App\Support\PeringatanTerkurung::catat(
                'jebakan-affiliate:' . request()->ip(),
                'Pendaftaran affiliate ditolak — perangkap bot terisi',
                [
                    'asal'  => request()->ip(),
                    'agent' => substr((string) request()->userAgent(), 0, 120),
                ]
            );

            return back()->with('error',
                'Formulirnya tidak bisa diproses. Muat ulang halaman ini lalu isi ulang.')->withInput();
        }

        $scopeKeys     = array_keys(RawAffiliate::referralScopes());
        $rewardKeys    = array_keys(RawAffiliate::rewardPrograms());
        $platformKeys  = array_keys(RawAffiliate::socialPlatforms());
        $behelKeys     = array_keys(RawAffiliate::behelTypes());

        request()->validate([
            'referral_scope'     => 'required|in:' . implode(',', $scopeKeys),
            'name'               => 'required|max:255',
            'email'              => 'required|email|max:255',
            'phone'              => 'required|max:20|unique:raw_affiliate,phone',
            // behel_type wajib kalau scope=behel
            'behel_type'         => 'required_if:referral_scope,' . RawAffiliate::SCOPE_BEHEL . '|nullable|in:' . implode(',', $behelKeys),
            // raw_doc_service_id wajib kalau scope=non_behel
            'raw_doc_service_id' => 'required_if:referral_scope,' . RawAffiliate::SCOPE_NON_BEHEL . '|nullable|exists:raw_doc_service,id',
            'raw_branch_id'      => 'required|exists:raw_branch,id',
            'raw_doctor_id'      => 'required|exists:raw_doctor,id',
            // reward_program HANYA wajib kalau scope = behel
            'reward_program'     => 'required_if:referral_scope,' . RawAffiliate::SCOPE_BEHEL . '|nullable|in:' . implode(',', $rewardKeys),
            'social_platform'    => 'required|in:' . implode(',', $platformKeys),
            'social_username'    => 'required|max:100',
        ], [
            'phone.unique'                => 'Nomor WhatsApp ini sudah terdaftar. Silakan login atau hubungi admin.',
            'reward_program.required_if'  => 'Pilih salah satu Tier untuk Pasien Behel.',
            'referral_scope.required'     => 'Pilih dulu jenis perawatan kamu.',
            'behel_type.required_if'      => 'Pilih jenis behel yang kamu pasang.',
            'raw_doc_service_id.required_if' => 'Pilih jenis perawatan kamu.',
        ]);

        $isBehel = request('referral_scope') === RawAffiliate::SCOPE_BEHEL;

        // Defensive: kosongkan field yg tidak relevan dengan scope.
        $rewardProgram   = $isBehel ? request('reward_program') : null;
        $behelType       = $isBehel ? request('behel_type') : null;
        $rawDocServiceId = $isBehel ? null : request('raw_doc_service_id');

        RawAffiliate::create([
            'id'                  => Str::uuid()->toString(),
            'name'                => request('name'),
            'email'               => request('email'),
            'phone'               => request('phone'),
            'raw_branch_id'       => request('raw_branch_id'),
            'raw_doctor_id'       => request('raw_doctor_id'),
            'raw_doc_service_id'  => $rawDocServiceId,
            'behel_type'          => $behelType,
            'reward_program'      => $rewardProgram,
            'referral_scope'      => request('referral_scope'),
            'social_platform'     => request('social_platform'),
            'social_username'     => request('social_username'),
            'role'                => 'pasien',
            'commission_rate'     => 0,
            'is_active'           => 0,
            'registration_status' => RawAffiliate::REG_STATUS_PENDING,
            'created_at'          => now(),
        ]);

        return redirect()->route('affiliate.thankyou');
    }

    public function thankYou()
    {
        return view('affiliate.thank-you');
    }

    public function logout()
    {
        session()->forget(['affiliate_id', 'affiliate_name']);
        return redirect()->route('affiliate.login');
    }

    public function dashboard()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $commissions = RawCommission::with(['reservation.customer', 'reservation.branch'])
            ->where('raw_affiliate_id', $affiliate->id)
            ->orderByDesc('created_at')
            ->get();

        $pendingTotal  = $commissions->where('status', 'pending')->sum('amount');
        $approvedTotal = $commissions->where('status', 'approved')->sum('amount');
        $paidTotal     = $commissions->where('status', 'paid')->sum('amount');

        $promos = $affiliate->promos()->where('is_active', 1)->get();

        $approvedCount   = $affiliate->approvedContentCount();
        $currentTier     = $affiliate->currentTier();
        $nextTier        = $affiliate->nextTier();
        $progressPercent = $nextTier
            ? min(100, (int) round(($approvedCount / max(1, $nextTier->required_content_count)) * 100))
            : 100;
        $tiers = AffiliateRewardTier::active()->get();
        $claims = $affiliate->rewardClaims()->get()->keyBy('affiliate_reward_tier_id');

        return view('affiliate.dashboard', compact(
            'affiliate', 'commissions', 'promos',
            'pendingTotal', 'approvedTotal', 'paidTotal',
            'approvedCount', 'currentTier', 'nextTier', 'progressPercent', 'tiers', 'claims'
        ));
    }

    public function commissions()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $commissions = RawCommission::with(['reservation.customer', 'reservation.branch'])
            ->where('raw_affiliate_id', $affiliate->id)
            ->orderByDesc('created_at')
            ->get();

        $pendingTotal  = $commissions->where('status', 'pending')->sum('amount');
        $approvedTotal = $commissions->where('status', 'approved')->sum('amount');
        $paidTotal     = $commissions->where('status', 'paid')->sum('amount');
        $rejectedTotal = $commissions->where('status', 'rejected')->sum('amount');

        $promos = $affiliate->promos()->where('is_active', 1)->get();

        // Fase 2D: claim eligibility + batch history
        $scopeCfg        = $affiliate->scopeConfig();
        $claimableCount  = $affiliate->claimableCount();
        $claimableAmount = $affiliate->claimableAmount();
        $minPatients     = $affiliate->claimMinPatients();
        $canClaim        = $affiliate->canRequestCommissionClaim();
        $pendingBatch    = $affiliate->pendingClaimBatch();
        $batchHistory    = CommissionClaimBatch::where('raw_affiliate_id', $affiliate->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('affiliate.commissions', compact(
            'affiliate', 'commissions', 'promos',
            'pendingTotal', 'approvedTotal', 'paidTotal', 'rejectedTotal',
            'scopeCfg', 'claimableCount', 'claimableAmount', 'minPatients',
            'canClaim', 'pendingBatch', 'batchHistory'
        ));
    }

    /**
     * KOC click "Klaim Komisi" → bundle commissions eligible jadi 1 batch.
     */
    public function requestCommissionClaim()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        if (!$affiliate->canRequestCommissionClaim()) {
            $reason = $affiliate->pendingClaimBatch()
                ? 'Kamu masih punya klaim yang belum selesai. Tunggu sampai status "Selesai" sebelum bikin klaim baru.'
                : 'Belum memenuhi syarat. Butuh minimal ' . $affiliate->claimMinPatients() . ' pasien commission untuk klaim 500k.';
            return back()->with('error', $reason);
        }

        $minPatients = $affiliate->claimMinPatients();

        \Illuminate\Support\Facades\DB::transaction(function () use ($affiliate, $minPatients) {
            // Ambil tepat $minPatients commissions tertua yang eligible (FIFO)
            $commissions = $affiliate->claimableCommissions()
                ->orderBy('created_at')
                ->limit($minPatients)
                ->get();

            $batchId  = (string) \Illuminate\Support\Str::uuid();
            $totalAmt = $commissions->sum('amount');

            CommissionClaimBatch::create([
                'id'               => $batchId,
                'raw_affiliate_id' => $affiliate->id,
                'referral_scope'   => $affiliate->referral_scope,
                'patient_count'    => $commissions->count(),
                'total_amount'     => $totalAmt,
                'status'           => CommissionClaimBatch::STATUS_REQUESTED,
                'requested_at'     => now(),
                'created_at'       => now(),
            ]);

            foreach ($commissions as $c) {
                CommissionClaimItem::create([
                    'id'                        => (string) \Illuminate\Support\Str::uuid(),
                    'commission_claim_batch_id' => $batchId,
                    'raw_commission_id'         => $c->id,
                    'amount'                    => $c->amount,
                    'created_at'                => now(),
                ]);
            }
        });

        return redirect()->route('affiliate.commissions')
            ->with('success', 'Klaim komisi 500k berhasil diajukan. Tim Sweet Treats akan konfirmasi dalam 4-5 hari kerja via WhatsApp.');
    }

    /**
     * KOC konfirmasi sudah terima transfer → batch jadi completed.
     */
    public function confirmCommissionClaim($batchId)
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $batch = CommissionClaimBatch::where('id', $batchId)
            ->where('raw_affiliate_id', $affiliate->id)
            ->first();

        if (!$batch) {
            return redirect()->route('affiliate.commissions')->with('error', 'Klaim tidak ditemukan.');
        }

        if ($batch->status !== CommissionClaimBatch::STATUS_TRANSFERRED) {
            return redirect()->route('affiliate.commissions')
                ->with('error', 'Klaim ini belum di-transfer atau sudah selesai.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($batch) {
            $batch->update([
                'status'       => CommissionClaimBatch::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_at'   => now(),
            ]);

            // Mark all linked commissions sebagai 'paid'
            $commissionIds = CommissionClaimItem::where('commission_claim_batch_id', $batch->id)
                ->pluck('raw_commission_id');
            RawCommission::whereIn('id', $commissionIds)->update(['status' => 'paid']);
        });

        return redirect()->route('affiliate.commissions')
            ->with('success', 'Terima kasih! Klaim komisi sudah selesai. Sampai ketemu di klaim berikutnya.');
    }

    public function contentIndex()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $contents = AffiliateContent::where('raw_affiliate_id', $affiliate->id)
            ->orderByDesc('created_at')
            ->get();

        return view('affiliate.content', compact('affiliate', 'contents'));
    }

    public function contentStore()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        request()->validate([
            'platform'     => 'required|in:tiktok,instagram,youtube',
            'content_url'  => 'required|url|max:500',
            'content_type' => 'required|in:video,reels,story,post',
            'caption'      => 'nullable|max:500',
        ]);

        AffiliateContent::create([
            'id'               => Str::uuid()->toString(),
            'raw_affiliate_id' => $affiliate->id,
            'platform'         => request('platform'),
            'content_url'      => request('content_url'),
            'content_type'     => request('content_type'),
            'caption'          => request('caption'),
            'status'           => AffiliateContent::STATUS_PENDING,
            'created_at'       => now(),
        ]);

        return redirect()->route('affiliate.content')
            ->with('success', 'Konten berhasil dikirim, menunggu review admin.');
    }

    public function contentEdit($id)
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $content = AffiliateContent::where('raw_affiliate_id', $affiliate->id)
            ->where('id', $id)
            ->first();

        if (!$content) {
            return redirect()->route('affiliate.content')->with('error', 'Konten tidak ditemukan.');
        }

        if (!$content->canBeEditedBy($affiliate)) {
            return redirect()->route('affiliate.content')
                ->with('error', 'Konten yang sudah disetujui tidak bisa diedit.');
        }

        return view('affiliate.content-edit', compact('affiliate', 'content'));
    }

    public function contentUpdate($id)
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $content = AffiliateContent::where('raw_affiliate_id', $affiliate->id)
            ->where('id', $id)
            ->first();

        if (!$content) {
            return redirect()->route('affiliate.content')->with('error', 'Konten tidak ditemukan.');
        }

        if (!$content->canBeEditedBy($affiliate)) {
            return redirect()->route('affiliate.content')
                ->with('error', 'Konten yang sudah disetujui tidak bisa diedit.');
        }

        request()->validate([
            'platform'     => 'required|in:tiktok,instagram,youtube',
            'content_url'  => 'required|url|max:500',
            'content_type' => 'required|in:video,reels,story,post',
            'caption'      => 'nullable|max:500',
        ]);

        $wasRejected = $content->status === AffiliateContent::STATUS_REJECTED;

        $content->platform     = request('platform');
        $content->content_url  = request('content_url');
        $content->content_type = request('content_type');
        $content->caption      = request('caption');

        if ($wasRejected) {
            $content->resetToPending();
        }

        $content->save();

        return redirect()->route('affiliate.content')
            ->with('success', $wasRejected
                ? 'Konten berhasil diupdate, menunggu review ulang admin.'
                : 'Konten berhasil diupdate.');
    }

    public function contentDelete($id)
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $content = AffiliateContent::where('raw_affiliate_id', $affiliate->id)
            ->where('id', $id)
            ->first();

        if (!$content) {
            return redirect()->route('affiliate.content')->with('error', 'Konten tidak ditemukan.');
        }

        if (!$content->canBeEditedBy($affiliate)) {
            return redirect()->route('affiliate.content')
                ->with('error', 'Konten yang sudah disetujui tidak bisa dihapus.');
        }

        $content->delete();

        return redirect()->route('affiliate.content')
            ->with('success', 'Konten berhasil dihapus.');
    }

    public function profile()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $approvedCount = $affiliate->approvedContentCount();
        $currentTier   = $affiliate->currentTier();

        return view('affiliate.profile', compact('affiliate', 'approvedCount', 'currentTier'));
    }

    public function profileUpdate()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        request()->validate([
            'name'              => 'required|max:255',
            'phone'             => ['required', 'max:20', Rule::unique('raw_affiliate', 'phone')->ignore($affiliate->id)],
            'email'             => 'nullable|email|max:255',
            'bank_account_info' => 'nullable|max:255',
            'social_tiktok'     => 'nullable|max:100',
            'social_instagram'  => 'nullable|max:100',
            'social_youtube'    => 'nullable|max:100',
        ]);

        $affiliate->update([
            'name'              => request('name'),
            'phone'             => request('phone'),
            'email'             => request('email'),
            'bank_account_info' => request('bank_account_info'),
            'social_tiktok'     => request('social_tiktok'),
            'social_instagram'  => request('social_instagram'),
            'social_youtube'    => request('social_youtube'),
        ]);

        session(['affiliate_name' => $affiliate->name]);

        return redirect()->route('affiliate.profile')
            ->with('success', 'Profil berhasil diupdate.');
    }

    public function profilePhoto()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        request()->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $uploadDir = public_path('uploads/affiliate');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if ($affiliate->photo) {
            $oldPath = $uploadDir . DIRECTORY_SEPARATOR . $affiliate->photo;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $file      = request()->file('photo');
        $extension = strtolower($file->getClientOriginalExtension());
        $filename  = $affiliate->id . '_' . time() . '.' . $extension;
        $file->move($uploadDir, $filename);

        $affiliate->update(['photo' => $filename]);

        return redirect()->route('affiliate.profile')
            ->with('success', 'Foto profil berhasil diupdate.');
    }

    public function profilePin()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        request()->validate([
            'current_pin' => 'required|digits:6',
            'new_pin'     => 'required|digits:6|confirmed',
        ]);

        if (!Hash::check(request('current_pin'), $affiliate->pin)) {
            return redirect()->route('affiliate.profile')
                ->with('error', 'PIN lama salah.');
        }

        $affiliate->update(['pin' => bcrypt(request('new_pin'))]);

        return redirect()->route('affiliate.profile')
            ->with('success', 'PIN berhasil diubah.');
    }

    public function rewards()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $tiers         = AffiliateRewardTier::active()->get();
        $approvedCount = $affiliate->approvedContentCount();          // already filtered by program_started_at
        $claims        = $affiliate->rewardClaims()->get()->keyBy('affiliate_reward_tier_id');

        // Auto-create eligible claim HANYA untuk tier yang dipilih (bukan iterate semua tier).
        // Ini yang bikin program-lock bekerja: walaupun count >= 3 (Bronze), kalau pilih Silver
        // affiliate tidak bisa klaim Bronze karena tidak ada eligible record dibuat untuknya.
        $chosenTier = $affiliate->chosenTier();
        if ($chosenTier
            && $approvedCount >= $chosenTier->required_content_count
            && !$claims->has($chosenTier->id)
        ) {
            $claim = AffiliateRewardClaim::create([
                'id'                       => Str::uuid()->toString(),
                'raw_affiliate_id'         => $affiliate->id,
                'affiliate_reward_tier_id' => $chosenTier->id,
                'status'                   => AffiliateRewardClaim::STATUS_ELIGIBLE,
                'created_at'               => now(),
            ]);
            $claims->put($chosenTier->id, $claim);
        }

        $progress       = $affiliate->currentProgramProgress();
        $canSwitch      = $affiliate->canSwitchProgram();
        $allRewardCfg   = RawAffiliate::rewardTiers();

        return view('affiliate.rewards', compact(
            'affiliate', 'tiers', 'approvedCount', 'claims',
            'chosenTier', 'progress', 'canSwitch', 'allRewardCfg'
        ));
    }

    public function claimReward($tierId)
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $tier = AffiliateRewardTier::find($tierId);
        if (!$tier || !$tier->is_active) {
            return redirect()->route('affiliate.rewards')->with('error', 'Tier reward tidak ditemukan.');
        }

        // LOCK: hanya boleh klaim tier YANG DIPILIH affiliate (Fase 2C).
        $chosenTier = $affiliate->chosenTier();
        if (!$chosenTier || $chosenTier->id !== $tier->id) {
            return redirect()->route('affiliate.rewards')
                ->with('error', 'Kamu hanya bisa klaim reward untuk tier yang sudah kamu pilih. Selesaikan dulu program saat ini sebelum bisa pindah ke tier lain.');
        }

        $approvedCount = $affiliate->approvedContentCount();
        if ($approvedCount < $tier->required_content_count) {
            return redirect()->route('affiliate.rewards')
                ->with('error', 'Kamu belum memenuhi syarat untuk reward ini. Butuh ' . $tier->required_content_count . ' konten approved.');
        }

        $claim = AffiliateRewardClaim::where('raw_affiliate_id', $affiliate->id)
            ->where('affiliate_reward_tier_id', $tier->id)
            ->first();

        if (!$claim) {
            $claim = AffiliateRewardClaim::create([
                'id'                       => Str::uuid()->toString(),
                'raw_affiliate_id'         => $affiliate->id,
                'affiliate_reward_tier_id' => $tier->id,
                'status'                   => AffiliateRewardClaim::STATUS_ELIGIBLE,
                'created_at'               => now(),
            ]);
        }

        if ($claim->status !== AffiliateRewardClaim::STATUS_ELIGIBLE) {
            return redirect()->route('affiliate.rewards')
                ->with('error', 'Reward ini sudah diklaim sebelumnya.');
        }

        $claim->status     = AffiliateRewardClaim::STATUS_CLAIMED;
        $claim->claimed_at = now();
        $claim->save();

        return redirect()->route('affiliate.rewards')
            ->with('success', 'Reward berhasil diklaim, admin akan memproses.');
    }

    /**
     * Switch ke program reward baru (setelah klaim sebelumnya selesai/used).
     * Reset reward_program_started_at = now() supaya counter konten mulai dari 0.
     */
    public function switchRewardProgram()
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $rewardKeys = array_keys(RawAffiliate::rewardPrograms());
        request()->validate([
            'reward_program' => 'required|in:' . implode(',', $rewardKeys),
        ]);

        if (!$affiliate->canSwitchProgram()) {
            return redirect()->route('affiliate.rewards')
                ->with('error', 'Tidak bisa pindah program — selesaikan dulu klaim untuk program saat ini (tunggu admin proses sampai reward "sudah dipakai").');
        }

        // Counter konten HANYA di-reset kalau siklus reward sebelumnya benar-benar
        // selesai — yaitu ada klaim berstatus 'used' di siklus yang sedang berjalan.
        // Kalau affiliate belum pernah menyelesaikan reward, ganti tier = ganti
        // TARGET saja: konten yang sudah approved tetap dihitung.
        //
        // Sebelumnya counter selalu di-reset ke now(), sehingga konten yang
        // di-upload SEBELUM switch ikut hangus begitu di-approve belakangan
        // (approvedContentCount() memfilter created_at >= reward_program_started_at).
        // Ini bertentangan dengan ketentuan program: reset hanya "setelah reward
        // selesai dipakai".
        $sudahPakaiReward = $affiliate->rewardClaims()
            ->where('status', AffiliateRewardClaim::STATUS_USED)
            ->when(
                $affiliate->reward_program_started_at,
                fn ($q) => $q->where('created_at', '>=', $affiliate->reward_program_started_at)
            )
            ->exists();

        $data = ['reward_program' => request('reward_program')];
        if ($sudahPakaiReward) {
            $data['reward_program_started_at'] = now();
        }
        $affiliate->update($data);

        $newCfg = RawAffiliate::rewardTiers()[request('reward_program')] ?? null;
        $nama   = $newCfg['name'] ?? 'program baru';
        $msg    = $sudahPakaiReward
            ? 'Program berhasil di-switch ke ' . $nama . '. Counter konten dimulai dari 0.'
            : 'Program berhasil di-switch ke ' . $nama . '. Konten kamu yang sudah approved tetap dihitung.';

        return redirect()->route('affiliate.rewards')->with('success', $msg);
    }

    protected function getAffiliate(): ?RawAffiliate
    {
        $id = session('affiliate_id');
        return $id ? RawAffiliate::find($id) : null;
    }
}
