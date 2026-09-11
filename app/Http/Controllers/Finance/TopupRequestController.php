<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\FundSource;
use App\Models\PettyCashPeriod;
use App\Models\RawBranch;
use App\Models\TopupRequest;
use App\Services\PettyCashTopupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TopupRequestController
 *
 * Approval workflow Finance untuk request top-up Sales.
 *
 * Endpoints:
 *   GET  finance/pettycash/request/{id}           → JSON detail untuk prefill modal Approve/Reject
 *   POST finance/pettycash/request/{id}/approve   → approve + create transaksi pettycash
 *   POST finance/pettycash/request/{id}/reject    → reject + simpan alasan
 *
 * Akses: role Finance, atau admin yang punya canSeeAllBranches().
 */
class TopupRequestController extends Controller
{
    private function guard(): void
    {
        if (! canSeeAllBranches()) {
            abort(403, 'Halaman ini hanya untuk admin.');
        }
    }

    /**
     * GET /finance/pettycash/request/{id}
     * Return JSON detail request — dipakai untuk prefill modal Approve.
     */
    public function show($id)
    {
        $this->guard();
        $req = TopupRequest::with(['branch', 'requester', 'fundSource'])->findOrFail($id);

        return response()->json([
            'id'               => $req->id,
            'status'           => $req->status,
            'branch_id'        => $req->raw_branch_id,
            'branch_name'      => $req->branch?->name,
            'requester_name'   => $req->requester?->nama,
            'fund_source_id'   => $req->fund_source_id,
            'fund_source_name' => $req->fundSource?->name,
            'amount_requested' => (float) $req->amount_requested,
            'description'     => $req->description,
            'attachment_path' => $req->attachment_path,
            'requested_at'    => $req->requested_at?->format('Y-m-d H:i'),
        ]);
    }

    /**
     * POST /finance/pettycash/request/{id}/approve
     *
     * Wrap dalam DB::transaction:
     *   1. lockForUpdate (race-protect double-approve)
     *   2. guard status=pending
     *   3. validate input (amount, fund_source override, trx_date, note)
     *   4. ensurePeriod bulan saat ini → kalau closed, abort
     *   5. create row petty_cash_transactions via PettyCashTopupService
     *   6. update request: status=approved + executed_trx_id + reviewed_*
     *   7. notify requester
     */
    public function approve(Request $request, $id)
    {
        $this->guard();

        $data = $request->validate([
            'amount_approved' => 'required|numeric|min:1|max:' . \App\Models\PettyCashTransaction::MAX_AMOUNT,
            'fund_source_id'  => 'required|integer|exists:fund_sources,id',
            'trx_date'        => 'required|date',
            'approval_note'   => 'nullable|string|max:255',
            'attachment'      => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'amount_approved.max' => 'Nominal maksimal Rp 50.000.000.',
        ]);

        // Simpan bukti foto di luar transaction (file IO) — kalau gagal,
        // user dapat error sebelum DB tersentuh.
        $attachmentPath = $this->storeAttachment($request, $data['trx_date']);

        try {
            $result = DB::transaction(function () use ($id, $data, $attachmentPath) {
                $req = TopupRequest::lockForUpdate()->findOrFail($id);
                if ($req->status !== TopupRequest::STATUS_PENDING) {
                    abort(409, 'Request ini sudah di-review sebelumnya.');
                }
                if ((string) $req->requested_by === (string) session('id')) {
                    abort(403, 'Tidak boleh menyetujui pengajuan sendiri.');
                }

                $branchId = (string) $req->raw_branch_id;
                $branch   = RawBranch::findOrFail($branchId);
                $source   = FundSource::findOrFail((int) $data['fund_source_id']);

                // Resolve period bulan saat APPROVE (sesuai keputusan: bukan
                // saat request). Float fallback ke setting per-cabang.
                $trxCarbon = Carbon::parse($data['trx_date']);
                $period = PettyCashPeriod::ensurePeriod(
                    (int) $trxCarbon->year,
                    (int) $trxCarbon->month,
                    (float) ($branch->petty_cash_default_float ?? 1500000),
                    $branchId,
                );
                if ($period->is_closed) {
                    abort(409, 'Periode petty cash cabang sudah ditutup. Buka kembali periode dulu.');
                }

                $service = new PettyCashTopupService();
                $trx = $service->createTopupTransaction(
                    $period,
                    $source,
                    (float) $data['amount_approved'],
                    $req->description, // bawa dari Sales jadi description default
                    $attachmentPath,   // bukti foto dari Finance saat approve
                    (string) session('id'), // Finance jadi created_by
                    $data['trx_date'],
                    'REQ-' . $req->id,
                );

                $req->update([
                    'status'          => TopupRequest::STATUS_APPROVED,
                    'amount_approved' => (float) $data['amount_approved'],
                    'fund_source_id'  => (int) $data['fund_source_id'], // override final
                    'reviewed_by'     => (string) session('id'),
                    'reviewed_at'     => now(),
                    'executed_trx_id' => (int) $trx->id,
                    'approval_note'   => $data['approval_note'] ?? null,
                    'attachment_path' => $attachmentPath,
                ]);

                // Notif balik ke Sales requester
                $nominal = number_format((float) $data['amount_approved'], 0, ',', '.');
                AdminNotification::notify(
                    'topup_approved',
                    'Pengajuan top up disetujui',
                    'Top up Rp ' . $nominal . ' untuk ' . $source->name . ' sudah dicatat di kas cabang.',
                    url('pettycash'),
                    $branchId,
                    (string) $req->requested_by,
                );

                return $req;
            });

            return redirect()->route('finance.pettycash')
                ->with('success', 'Top up disetujui & transaksi tercatat.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return redirect()->route('finance.pettycash')
                ->with('error', $e->getMessage() ?: 'Gagal memproses approval.');
        }
    }

    /**
     * POST /finance/pettycash/request/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        $this->guard();

        $data = $request->validate([
            'rejected_reason' => 'required|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($id, $data) {
                $req = TopupRequest::lockForUpdate()->findOrFail($id);
                if ($req->status !== TopupRequest::STATUS_PENDING) {
                    abort(409, 'Request ini sudah di-review sebelumnya.');
                }
                if ((string) $req->requested_by === (string) session('id')) {
                    abort(403, 'Tidak boleh menolak pengajuan sendiri.');
                }
                $req->update([
                    'status'          => TopupRequest::STATUS_REJECTED,
                    'rejected_reason' => $data['rejected_reason'],
                    'reviewed_by'     => (string) session('id'),
                    'reviewed_at'     => now(),
                ]);

                AdminNotification::notify(
                    'topup_rejected',
                    'Pengajuan top up ditolak',
                    'Alasan: ' . $data['rejected_reason'],
                    url('pettycash'),
                    (string) $req->raw_branch_id,
                    (string) $req->requested_by,
                );
            });

            return redirect()->route('finance.pettycash')
                ->with('success', 'Pengajuan top up ditolak. Sales akan menerima notifikasi.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return redirect()->route('finance.pettycash')
                ->with('error', $e->getMessage() ?: 'Gagal memproses penolakan.');
        }
    }

    /**
     * Simpan file bukti foto/PDF ke public/uploads/expense-receipts/{YYYY}/{MM}.
     * Same convention dengan PettyCashController::storeAttachment().
     */
    private function storeAttachment(Request $request, string $trxDate, string $field = 'attachment'): ?string
    {
        if (! $request->hasFile($field)) return null;
        $file = $request->file($field);
        $year  = date('Y', strtotime($trxDate));
        $month = date('m', strtotime($trxDate));
        $relDir = "uploads/expense-receipts/{$year}/{$month}";
        $absDir = public_path($relDir);
        if (! is_dir($absDir)) @mkdir($absDir, 0775, true);
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = \Illuminate\Support\Str::uuid() . '.' . $ext;
        $file->move($absDir, $filename);
        return "{$relDir}/{$filename}";
    }
}
