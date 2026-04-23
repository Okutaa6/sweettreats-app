<?php

namespace App\Http\Controllers;

use App\Models\AffiliateContent;
use App\Models\AffiliateRewardClaim;
use App\Models\AffiliateRewardTier;
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

        $bracesServices = [
            'Signature Metal',
            'Signature Metal SP',
            'Self Ligating Metal',
            'Self Ligating Clear',
            'Deluxe Metal',
            'Stellar Metal',
            'Ceramic Braces',
            'Shappire Braces',
        ];

        $branches = RawBranch::orderBy('name')->get();
        $doctors  = RawDoctor::orderBy('name')->get();
        $services = RawDocService::whereIn('name', $bracesServices)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('affiliate.register', compact('branches', 'doctors', 'services'));
    }

    public function registerProcess()
    {
        $rewardKeys    = array_keys(RawAffiliate::rewardPrograms());
        $platformKeys  = array_keys(RawAffiliate::socialPlatforms());

        request()->validate([
            'name'               => 'required|max:255',
            'email'              => 'required|email|max:255',
            'phone'              => 'required|max:20|unique:raw_affiliate,phone',
            'raw_doc_service_id' => 'required|exists:raw_doc_service,id',
            'raw_branch_id'      => 'required|exists:raw_branch,id',
            'raw_doctor_id'      => 'required|exists:raw_doctor,id',
            'reward_program'     => 'required|in:' . implode(',', $rewardKeys),
            'social_platform'    => 'required|in:' . implode(',', $platformKeys),
            'social_username'    => 'required|max:100',
        ], [
            'phone.unique' => 'Nomor WhatsApp ini sudah terdaftar. Silakan login atau hubungi admin.',
        ]);

        RawAffiliate::create([
            'id'                  => Str::uuid()->toString(),
            'name'                => request('name'),
            'email'               => request('email'),
            'phone'               => request('phone'),
            'raw_branch_id'       => request('raw_branch_id'),
            'raw_doctor_id'       => request('raw_doctor_id'),
            'raw_doc_service_id'  => request('raw_doc_service_id'),
            'reward_program'      => request('reward_program'),
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

        return view('affiliate.commissions', compact(
            'affiliate', 'commissions', 'promos',
            'pendingTotal', 'approvedTotal', 'paidTotal', 'rejectedTotal'
        ));
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
        $approvedCount = $affiliate->approvedContentCount();
        $claims        = $affiliate->rewardClaims()->get()->keyBy('affiliate_reward_tier_id');

        foreach ($tiers as $tier) {
            if ($approvedCount >= $tier->required_content_count && !$claims->has($tier->id)) {
                $claim = AffiliateRewardClaim::create([
                    'id'                       => Str::uuid()->toString(),
                    'raw_affiliate_id'         => $affiliate->id,
                    'affiliate_reward_tier_id' => $tier->id,
                    'status'                   => AffiliateRewardClaim::STATUS_ELIGIBLE,
                    'created_at'               => now(),
                ]);
                $claims->put($tier->id, $claim);
            }
        }

        return view('affiliate.rewards', compact('affiliate', 'tiers', 'approvedCount', 'claims'));
    }

    public function claimReward($tierId)
    {
        $affiliate = $this->getAffiliate();
        if (!$affiliate) return redirect()->route('affiliate.login');

        $tier = AffiliateRewardTier::find($tierId);
        if (!$tier || !$tier->is_active) {
            return redirect()->route('affiliate.rewards')->with('error', 'Tier reward tidak ditemukan.');
        }

        $approvedCount = $affiliate->approvedContentCount();
        if ($approvedCount < $tier->required_content_count) {
            return redirect()->route('affiliate.rewards')
                ->with('error', 'Kamu belum memenuhi syarat untuk reward ini.');
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

    protected function getAffiliate(): ?RawAffiliate
    {
        $id = session('affiliate_id');
        return $id ? RawAffiliate::find($id) : null;
    }
}
