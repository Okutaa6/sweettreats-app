<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\CommissionClaimBatch;
use App\Models\CommissionClaimItem;
use App\Models\RawAffiliate;
use App\Models\RawCommission;
use Illuminate\Support\Facades\DB;

class CommissionClaimController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $filterStatus = request('status', '');
        $filterScope  = request('scope', '');
        $filterSearch = request('search', '');

        $query = CommissionClaimBatch::with(['affiliate', 'pj'])
            ->orderByRaw("FIELD(status, 'requested','in_review','approved','transferred','completed')")
            ->orderByDesc('created_at');

        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }
        if ($filterScope) {
            $query->where('referral_scope', $filterScope);
        }
        if ($filterSearch) {
            $query->whereHas('affiliate', fn($q) => $q->where('name', 'like', "%{$filterSearch}%"));
        }

        $batches = $query->paginate($perPage)->withQueryString();
        $statuses = CommissionClaimBatch::statusOptions();

        // Summary count per status (semua filter ikut KECUALI status)
        $summaryQ = CommissionClaimBatch::query();
        if ($filterScope)  $summaryQ->where('referral_scope', $filterScope);
        if ($filterSearch) $summaryQ->whereHas('affiliate', fn($q) => $q->where('name', 'like', "%{$filterSearch}%"));
        $summary = $summaryQ->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status')->toArray();

        return view('crud.commission-claim.list', compact(
            'batches', 'statuses', 'summary',
            'filterStatus', 'filterScope', 'filterSearch'
        ));
    }

    public function show($id)
    {
        $batch = CommissionClaimBatch::with([
            'affiliate',
            'pj',
            'items.commission.reservation.customer',
            'items.commission.reservation.branch',
        ])->findOrFail($id);

        $statusOpt = CommissionClaimBatch::statusOptions();

        return view('crud.commission-claim.show', compact('batch', 'statusOpt'));
    }

    /**
     * Admin/PJ ambil-alih review batch (status: requested → in_review).
     */
    public function takeReview($id)
    {
        $batch = CommissionClaimBatch::findOrFail($id);
        if ($batch->status !== CommissionClaimBatch::STATUS_REQUESTED) {
            return back()->with('error', 'Batch ini tidak dalam status "Diajukan".');
        }

        $batch->update([
            'status'      => CommissionClaimBatch::STATUS_IN_REVIEW,
            'pj_user_id'  => session('id'),
            'reviewed_at' => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Batch berpindah ke status "Direview". Konfirmasi nominal dengan KOC via WhatsApp, lalu klik "Approve" di bawah.');
    }

    /**
     * PJ approve nominal & ajukan ke finance (status: in_review → approved).
     */
    public function approveBatch($id)
    {
        $batch = CommissionClaimBatch::findOrFail($id);
        if (!in_array($batch->status, [CommissionClaimBatch::STATUS_REQUESTED, CommissionClaimBatch::STATUS_IN_REVIEW])) {
            return back()->with('error', 'Batch tidak bisa di-approve dari status saat ini.');
        }

        request()->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $batch->update([
            'status'      => CommissionClaimBatch::STATUS_APPROVED,
            'pj_user_id'  => $batch->pj_user_id ?: session('id'),
            'reviewed_at' => $batch->reviewed_at ?: now(),
            'approved_at' => now(),
            'notes'       => request('notes', $batch->notes),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Batch di-approve. Lanjut ajukan ke finance untuk transfer.');
    }

    /**
     * PJ upload bukti transfer (status: approved → transferred).
     */
    public function uploadProof($id)
    {
        $batch = CommissionClaimBatch::findOrFail($id);
        if ($batch->status !== CommissionClaimBatch::STATUS_APPROVED) {
            return back()->with('error', 'Batch harus dalam status "Disetujui" untuk upload bukti.');
        }

        request()->validate([
            'tx_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'tx_at'    => 'nullable|date',
            'notes'    => 'nullable|string|max:2000',
        ]);

        $uploadDir = public_path('uploads/commission_proof');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file     = request()->file('tx_proof');
        $ext      = strtolower($file->getClientOriginalExtension());
        $filename = $batch->id . '_' . time() . '.' . $ext;
        $file->move($uploadDir, $filename);

        $batch->update([
            'status'        => CommissionClaimBatch::STATUS_TRANSFERRED,
            'tx_proof_path' => $filename,
            'tx_at'         => request('tx_at') ?: now(),
            'notes'         => request('notes', $batch->notes),
            'updated_at'    => now(),
        ]);

        return back()->with('success', 'Bukti transfer ke-upload. KOC akan dapat notif untuk konfirmasi terima.');
    }

    /**
     * Admin force-complete batch (override KOC confirm) — kalau KOC tidak respon.
     */
    public function forceComplete($id)
    {
        $batch = CommissionClaimBatch::findOrFail($id);
        if ($batch->status !== CommissionClaimBatch::STATUS_TRANSFERRED) {
            return back()->with('error', 'Hanya batch yang sudah ditransfer yang bisa di-complete.');
        }

        DB::transaction(function () use ($batch) {
            $batch->update([
                'status'       => CommissionClaimBatch::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_at'   => now(),
            ]);

            $commissionIds = CommissionClaimItem::where('commission_claim_batch_id', $batch->id)
                ->pluck('raw_commission_id');
            RawCommission::whereIn('id', $commissionIds)->update(['status' => 'paid']);
        });

        return back()->with('success', 'Batch di-complete secara manual.');
    }
}
