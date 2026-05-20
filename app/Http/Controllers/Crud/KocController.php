<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\AffiliateContent;
use App\Models\RawAffiliate;
use App\Models\RawBranch;
use App\Models\RawDoctor;
use App\Models\RawPromo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * KocController
 *
 * Mengelola halaman Daftar KOC dengan 3 tab:
 *   - Semua KOC                : seluruh data type='koc'
 *   - Confirmed Sosmed         : koc_sosmed_confirmed=1 (manual flag oleh admin)
 *   - Sudah Submit Konten      : punya minimal 1 affiliate_content status='approved'
 *
 * KOC = pasien yang di-approach jadi partner/affiliate. Beda dengan KOL
 * (eksternal influencer), KOC sudah jadi customer dari klinik dulu.
 */
class KocController extends Controller
{
    /**
     * Halaman Daftar KOC (default index).
     * URL: /koc
     */
    public function index()
    {
        // ─── Filter dari request ─────────────────────────────────────────
        $filterTab      = request('tab', 'all'); // 'all' | 'confirmed' | 'submitted'
        $filterBranch   = request('branch', 'all');
        $filterDoctor   = request('doctor', 'all');
        $filterPlatform = request('platform', 'all');
        $search         = trim((string) request('q', ''));

        // ─── Base query: semua KOC ───────────────────────────────────────
        $baseQuery = RawAffiliate::where('type', 'koc');

        // ─── Hitung counter per tab (selalu dari base query, sebelum filter) ──
        $counts = [
            'all'        => (clone $baseQuery)->count(),
            'confirmed'  => (clone $baseQuery)->confirmedSosmed()->count(),
            'submitted'  => (clone $baseQuery)
                ->whereHas('contents', function ($q) {
                    $q->where('status', AffiliateContent::STATUS_APPROVED);
                })
                ->count(),
        ];

        // ─── Hitung jumlah KOC per cabang (untuk summary cards) ──────────
        $branchCounts = (clone $baseQuery)
            ->selectRaw('raw_branch_id, COUNT(*) as total')
            ->groupBy('raw_branch_id')
            ->pluck('total', 'raw_branch_id')
            ->toArray();

        $branches = RawBranch::orderBy('name')->get()->map(function ($b) use ($branchCounts) {
            $b->kol_count = $branchCounts[$b->id] ?? 0; // pakai 'kol_count' supaya partial bisa di-reuse
            return $b;
        });

        $totalCount = array_sum($branchCounts);

        // ─── Build query untuk hasil tabel ───────────────────────────────
        $query = (clone $baseQuery)
            ->with(['branch', 'doctor', 'docService', 'promos' => function ($q) {
                $q->where('is_active', 1)->orderBy('created_at');
            }])
            ->withCount(['contents as approved_content_count' => function ($q) {
                $q->where('status', AffiliateContent::STATUS_APPROVED);
            }]);

        // Apply tab filter
        if ($filterTab === 'confirmed') {
            $query->confirmedSosmed();
        } elseif ($filterTab === 'submitted') {
            $query->whereHas('contents', function ($q) {
                $q->where('status', AffiliateContent::STATUS_APPROVED);
            });
        }

        // Apply other filters
        if ($filterBranch !== 'all') {
            $query->where('raw_branch_id', $filterBranch);
        }
        if ($filterDoctor !== 'all') {
            $query->where('raw_doctor_id', $filterDoctor);
        }
        if ($filterPlatform !== 'all') {
            // Cek di kolom 'social_platform' (single) atau di kolom social_<platform>
            $query->where(function ($q) use ($filterPlatform) {
                $q->where('social_platform', $filterPlatform);
                if ($filterPlatform === 'tiktok')    $q->orWhereNotNull('social_tiktok');
                if ($filterPlatform === 'instagram') $q->orWhereNotNull('social_instagram');
                if ($filterPlatform === 'youtube')   $q->orWhereNotNull('social_youtube');
            });
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('social_username', 'like', "%{$search}%")
                  ->orWhere('social_instagram', 'like', "%{$search}%")
                  ->orWhere('social_tiktok', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // ─── Eksekusi & paginate ─────────────────────────────────────────
        $kocs = $query
            ->orderBy('raw_branch_id')
            ->orderBy('name')
            ->paginate($this->perPage)
            ->appends(request()->query());

        // ─── Data pendukung untuk dropdown filter ────────────────────────
        $doctors = RawDoctor::orderBy('name')->get();

        $activeBranch = $filterBranch !== 'all'
            ? $branches->firstWhere('id', $filterBranch)
            : null;

        return view('crud.koc.list', compact(
            'kocs',
            'branches',
            'doctors',
            'totalCount',
            'counts',
            'filterTab',
            'filterBranch',
            'filterDoctor',
            'filterPlatform',
            'search',
            'activeBranch'
        ));
    }

    /**
     * Guard endpoint editor-inline (Dokter & Perawatan) — Super Admin saja.
     * Abort 403 kalau bukan.
     */
    private function ensureSuperAdmin(): void
    {
        if (session('group_name') !== 'Super Admin') {
            abort(403, 'Hanya Super Admin yang dapat mengubah data ini.');
        }
    }

    /**
     * Update raw_doctor_id KOC via AJAX (dropdown inline di tabel KOC).
     * URL: PATCH /koc/{id}/doctor
     * Body: { raw_doctor_id: <uuid|""> }
     * Returns JSON: { raw_doctor_id, doctor_name }
     *
     * Catatan: fitur dropdown sementara (Super Admin only). Kalau dropdown
     * nanti dilepas dari UI, nilai raw_doctor_id tetap tersimpan di DB.
     */
    public function updateDoctor(Request $request, $id)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'raw_doctor_id' => 'nullable|exists:raw_doctor,id',
        ]);

        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);
        $koc->raw_doctor_id = $validated['raw_doctor_id'] ?: null;
        $koc->save();

        return response()->json([
            'raw_doctor_id' => $koc->raw_doctor_id,
            'doctor_name'   => $koc->raw_doctor_id ? optional($koc->doctor)->name : null,
        ]);
    }

    /**
     * Update behel_type KOC via AJAX (dropdown inline "Perawatan" di tabel KOC).
     * URL: PATCH /koc/{id}/behel-type
     * Body: { behel_type: <enum key|""> }
     * Returns JSON: { behel_type, label }
     *
     * Catatan: fitur dropdown sementara (Super Admin only). Sumber enum:
     * RawAffiliate::behelTypes(). Nilai tetap tersimpan di DB walaupun
     * dropdown nanti dilepas dari UI.
     */
    public function updateBehelType(Request $request, $id)
    {
        $this->ensureSuperAdmin();

        $allowed = array_keys(RawAffiliate::behelTypes());
        $validated = $request->validate([
            'behel_type' => 'nullable|in:' . implode(',', $allowed),
        ]);

        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);
        $koc->behel_type = $validated['behel_type'] ?: null;
        $koc->save();

        return response()->json([
            'behel_type' => $koc->behel_type,
            'label'      => $koc->behel_type ? (RawAffiliate::behelTypes()[$koc->behel_type] ?? null) : null,
        ]);
    }

    /**
     * Update feedback KOC dari textarea inline di tabel.
     * URL: PATCH /koc/{id}/feedback
     * Body: { feedback: <string|""> }
     * Returns JSON: { feedback }
     */
    public function updateFeedback(Request $request, $id)
    {
        $validated = $request->validate([
            'feedback' => 'nullable|string|max:5000',
        ]);

        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);
        $koc->feedback = trim((string) ($validated['feedback'] ?? '')) ?: null;
        $koc->save();

        return response()->json([
            'feedback' => $koc->feedback,
        ]);
    }

    /**
     * Toggle status koc_sosmed_confirmed via AJAX.
     * URL: PATCH /koc/{id}/confirm-sosmed
     * Body: { confirmed: true|false }
     * Returns JSON: { confirmed, confirmed_at }
     */
    public function toggleSosmedConfirmed(Request $request, $id)
    {
        $validated = $request->validate([
            'confirmed' => 'required|boolean',
        ]);

        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);

        $userId = session('user_id') ?? auth()->id();
        $koc->setSosmedConfirmed((bool) $validated['confirmed'], $userId);

        return response()->json([
            'confirmed'    => $koc->koc_sosmed_confirmed,
            'confirmed_at' => $koc->koc_sosmed_confirmed_at?->translatedFormat('d M Y H:i'),
        ]);
    }

    /**
     * Download template CSV untuk import KOC.
     * URL: GET /koc/csv-template
     */
    public function downloadCsvTemplate()
    {
        $headers = [
            'name', 'branch_name', 'doctor_name', 'phone', 'email', 'feedback',
            'social_platform', 'social_username', 'social_tiktok', 'social_instagram',
            'behel_type', 'reward_program', 'kode_referral',
            'referral_scope', 'is_active',
        ];

        $sample = [
            'Deva Indah Sari', 'Sunter', 'Dokter Fridi', '081234567890', 'deva@example.com', 'Upload Konten Tiktok',
            'tiktok', 'usersdevin', 'https://www.tiktok.com/@usersdevin', '',
            'signature_metal_gp', 'above_12x', 'DEVA11',
            'behel', '1',
        ];

        $callback = function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        };

        return response()->streamDownload($callback, 'template_koc_import.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Import KOC dari file CSV.
     * URL: POST /koc/import-csv
     *
     * Header CSV (case-insensitive, spasi → underscore):
     *   name, branch_name, doctor_name, phone, email, feedback,
     *   social_platform, social_username, social_tiktok, social_instagram, social_youtube,
     *   behel_type, reward_program, kode_referral,
     *   referral_scope, is_active
     *
     * Default yang otomatis di-set:
     *   - type='koc', role='pasien', registration_status='approved'
     *
     * kode_referral (kalau diisi) akan auto-create RawPromo dengan
     * discount_type='nominal' & discount_value = scope voucher_amount.
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('csv_file')->getPathname(), 'r');
        if (!$handle) {
            return redirect()->route('koc')->with('error', 'Gagal membuka file CSV.');
        }

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return redirect()->route('koc')->with('error', 'CSV kosong atau tidak valid.');
        }

        // Normalize header: lowercase + spaces → underscore + trim BOM
        $headers = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            return strtolower(trim(str_replace([' ', '-'], '_', $h)));
        }, $rawHeaders);

        // Pre-build lookup maps (sekali, supaya tidak hit DB per-row)
        $branches = RawBranch::all()
            ->mapWithKeys(fn($b) => [strtolower(trim($b->name)) => $b->id])
            ->toArray();
        $doctors = RawDoctor::all()
            ->mapWithKeys(fn($d) => [strtolower(trim($d->name)) => $d->id])
            ->toArray();
        $platformKeys = array_keys(RawAffiliate::socialPlatforms());
        $rewardKeys   = array_keys(RawAffiliate::rewardPrograms());
        $behelKeys    = array_keys(RawAffiliate::behelTypes());
        $scopeKeys    = array_keys(RawAffiliate::referralScopes());

        // Promo code yang sudah ada — supaya kita tidak insert duplicate.
        $existingPromoCodes = RawPromo::pluck('promo_code')
            ->map(fn($c) => strtoupper(trim($c)))
            ->flip()
            ->toArray();

        $created = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1; // header di row 1

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row  = array_pad($row, count($headers), '');
            $data = array_combine($headers, array_slice($row, 0, count($headers)));

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                $errors[] = "Baris {$rowNum}: nama kosong, dilewati.";
                continue;
            }

            // Enum coercion — empty atau invalid → null (silent).
            $platform = trim((string) ($data['social_platform'] ?? '')) ?: null;
            $reward   = trim((string) ($data['reward_program']  ?? '')) ?: null;
            $scope    = trim((string) ($data['referral_scope']  ?? '')) ?: null;
            $behel    = $this->normalizeBehelType((string) ($data['behel_type'] ?? ''));

            if ($platform && !in_array($platform, $platformKeys, true)) $platform = null;
            if ($reward   && !in_array($reward,   $rewardKeys,   true)) $reward   = null;
            if ($scope    && !in_array($scope,    $scopeKeys,    true)) $scope    = null;
            if ($behel    && !in_array($behel,    $behelKeys,    true)) $behel    = null;

            // Lookup branch & doctor (case-insensitive)
            $branchName = trim((string) ($data['branch_name'] ?? ''));
            $branchId   = $branchName ? ($branches[strtolower($branchName)] ?? null) : null;

            $doctorName = trim((string) ($data['doctor_name'] ?? ''));
            $doctorId   = $doctorName ? ($doctors[strtolower($doctorName)] ?? null) : null;

            // is_active: terima 1/0/true/false/yes/no/empty
            $isActiveRaw = strtolower(trim((string) ($data['is_active'] ?? '1')));
            $isActive    = in_array($isActiveRaw, ['0', 'false', 'no', 'tidak'], true) ? 0 : 1;

            $kodeReferral = strtoupper(trim((string) ($data['kode_referral'] ?? '')));

            try {
                $affiliateId = (string) Str::uuid();

                RawAffiliate::create([
                    'id'                  => $affiliateId,
                    'name'                => $name,
                    'type'                => 'koc',
                    'role'                => 'pasien',
                    'raw_branch_id'       => $branchId,
                    'raw_doctor_id'       => $doctorId,
                    'phone'               => trim((string) ($data['phone']            ?? '')) ?: null,
                    'email'               => trim((string) ($data['email']            ?? '')) ?: null,
                    'social_platform'     => $platform,
                    'social_username'     => trim((string) ($data['social_username']  ?? '')) ?: null,
                    'social_instagram'    => trim((string) ($data['social_instagram'] ?? '')) ?: null,
                    'social_tiktok'       => trim((string) ($data['social_tiktok']    ?? '')) ?: null,
                    'social_youtube'      => trim((string) ($data['social_youtube']   ?? '')) ?: null,
                    'feedback'            => trim((string) ($data['feedback']         ?? '')) ?: null,
                    'behel_type'          => $behel,
                    'reward_program'      => $reward,
                    'referral_scope'      => $scope,
                    'commission_rate'     => 0,
                    'is_active'           => $isActive,
                    'registration_status' => RawAffiliate::REG_STATUS_APPROVED,
                    'created_at'          => now(),
                ]);

                // Auto-create RawPromo kalau kode_referral diisi & belum ada.
                if ($kodeReferral !== '') {
                    if (!isset($existingPromoCodes[$kodeReferral])) {
                        // Discount value default dari scope (Rp 100k behel / Rp 50k non-behel).
                        $voucher = $scope ? (int) (RawAffiliate::referralScopes()[$scope]['voucher_amount'] ?? 0) : 0;

                        RawPromo::create([
                            'id'               => (string) Str::uuid(),
                            'raw_affiliate_id' => $affiliateId,
                            'referral_scope'   => $scope,
                            'promo_code'       => $kodeReferral,
                            'discount_type'    => 'nominal',
                            'discount_value'   => $voucher,
                            'valid_until'      => null,
                            'is_active'        => 1,
                            'created_at'       => now(),
                        ]);
                        $existingPromoCodes[$kodeReferral] = true;
                    }
                }

                $created++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $msg = "Import selesai: {$created} KOC ditambahkan, {$skipped} dilewati.";
        if (!empty($errors)) {
            $msg .= ' Catatan: ' . implode(' | ', array_slice($errors, 0, 3));
            if (count($errors) > 3) $msg .= ' (+' . (count($errors) - 3) . ' error lain)';
        }

        return redirect()->route('koc')->with($created > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Mapping flexible untuk kolom behel_type:
     *   - Terima enum key langsung (e.g. "signature_metal_sp")
     *   - Terima label bebas (e.g. "Signature Metal", "Signature Metal SP",
     *     "Self Ligating Metal", "Deluxe Metal", "Ceramic Braces")
     *   - SP/GP ambigu → default GP
     *   - Tidak ketemu → null
     */
    private function normalizeBehelType(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;

        // Direct enum key match (case-sensitive supaya konsisten dengan DB)
        if (array_key_exists($value, RawAffiliate::behelTypes())) {
            return $value;
        }
        if (array_key_exists(strtolower($value), RawAffiliate::behelTypes())) {
            return strtolower($value);
        }

        $v = strtolower(preg_replace('/\s+/', ' ', $value));

        // Detect suffix — default GP
        $isSp   = preg_match('/\b(sp|spesialis)\b/', $v) === 1;
        $suffix = $isSp ? 'sp' : 'gp';

        if (str_contains($v, 'self ligating') || str_contains($v, 'self-ligating')) {
            return "self_ligating_metal_{$suffix}";
        }
        if (str_contains($v, 'stellar')) {
            return "stellar_metal_{$suffix}";
        }
        if (str_contains($v, 'signature ceramic')) {
            return "signature_ceramic_{$suffix}";
        }
        if (str_contains($v, 'signature') && str_contains($v, 'metal')) {
            return "signature_metal_{$suffix}";
        }
        if (str_contains($v, 'deluxe')) {
            return 'deluxe_metal';
        }
        if (str_contains($v, 'ceramic')) {
            return 'ceramic_braces_gp';
        }

        return null;
    }

    /**
     * Return list konten KOC sebagai JSON untuk modal "Lihat Konten".
     * URL: GET /koc/{id}/contents
     */
    public function contents($id)
    {
        $koc = RawAffiliate::where('type', 'koc')->findOrFail($id);

        $contents = AffiliateContent::where('raw_affiliate_id', $koc->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($c) {
                $statusOpt = AffiliateContent::statusOptions()[$c->status] ?? null;
                return [
                    'id'           => $c->id,
                    'platform'     => $c->platform,
                    'platform_lbl' => AffiliateContent::platformOptions()[$c->platform] ?? $c->platform,
                    'content_type' => $c->content_type,
                    'content_url'  => $c->content_url,
                    'caption'      => $c->caption,
                    'status'       => $c->status,
                    'status_lbl'   => $statusOpt['label'] ?? $c->status,
                    'status_color' => $statusOpt['color'] ?? 'neutral',
                    'created_at'   => $c->created_at?->translatedFormat('d M Y'),
                    'edit_url'     => route('content.review.edit', $c->id),
                ];
            });

        return response()->json([
            'koc' => [
                'id'   => $koc->id,
                'name' => $koc->name,
            ],
            'contents'    => $contents,
            'review_url'  => route('content.review', ['affiliate_id' => $koc->id]),
        ]);
    }
}
