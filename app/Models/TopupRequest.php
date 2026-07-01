<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TopupRequest
 *
 * Pengajuan top-up petty cash dari Sales ke Finance.
 *
 * Status: pending → approved | rejected.
 *
 * Saat status berubah ke 'approved', controller men-trigger pembuatan
 * row petty_cash_transactions (is_topup=1) di periode aktif cabang yang
 * sama, dan stamp executed_trx_id di sini sebagai backlink.
 */
class TopupRequest extends Model
{
    protected $table = 'topup_requests';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'raw_branch_id',
        'requested_by',
        'fund_source_id',
        'amount_requested',
        'amount_approved',
        'description',
        'attachment_path',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'rejected_reason',
        'executed_trx_id',
        'approval_note',
    ];

    protected $casts = [
        'fund_source_id'   => 'integer',
        'amount_requested' => 'decimal:2',
        'amount_approved'  => 'decimal:2',
        'requested_at'     => 'datetime',
        'reviewed_at'      => 'datetime',
        'executed_trx_id'  => 'integer',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /* ───────── Relations ───────── */

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function requester()
    {
        return $this->belongsTo(Users::class, 'requested_by', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Users::class, 'reviewed_by', 'id');
    }

    public function fundSource()
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id', 'id');
    }

    public function executedTransaction()
    {
        return $this->belongsTo(PettyCashTransaction::class, 'executed_trx_id', 'id');
    }

    /* ───────── Scopes ───────── */

    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeForBranch($q, string $branchId)
    {
        return $q->where('raw_branch_id', $branchId);
    }

    /**
     * Antrian untuk Finance: pending dulu (oldest first), diikuti
     * reviewed kalau dipakai untuk audit feed campuran.
     */
    public function scopeForFinanceQueue($q)
    {
        return $q->where('status', self::STATUS_PENDING)
                 ->orderBy('requested_at', 'asc');
    }

    /* ───────── Helpers ───────── */

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
}
