<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionClaimBatch extends Model
{
    protected $table = 'commission_claim_batch';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'raw_affiliate_id',
        'referral_scope',
        'patient_count',
        'total_amount',
        'status',
        'pj_user_id',
        'notes',
        'tx_proof_path',
        'tx_at',
        'requested_at',
        'reviewed_at',
        'approved_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'patient_count' => 'integer',
        'total_amount'  => 'decimal:2',
        'tx_at'         => 'datetime',
        'requested_at'  => 'datetime',
        'reviewed_at'   => 'datetime',
        'approved_at'   => 'datetime',
        'completed_at'  => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    const STATUS_REQUESTED   = 'requested';
    const STATUS_IN_REVIEW   = 'in_review';
    const STATUS_APPROVED    = 'approved';
    const STATUS_TRANSFERRED = 'transferred';
    const STATUS_COMPLETED   = 'completed';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_REQUESTED   => ['label' => 'Diajukan',     'color' => 'warning', 'desc' => 'Menunggu review PJ/Creative'],
            self::STATUS_IN_REVIEW   => ['label' => 'Direview',     'color' => 'info',    'desc' => 'PJ sedang konfirmasi nominal via WA'],
            self::STATUS_APPROVED    => ['label' => 'Disetujui',    'color' => 'info',    'desc' => 'Diajukan ke finance, menunggu transfer'],
            self::STATUS_TRANSFERRED => ['label' => 'Transferred',  'color' => 'success', 'desc' => 'Sudah ditransfer, tunggu konfirmasi KOC'],
            self::STATUS_COMPLETED   => ['label' => 'Selesai',      'color' => 'neutral', 'desc' => 'KOC konfirmasi sudah terima'],
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(RawAffiliate::class, 'raw_affiliate_id', 'id');
    }

    public function pj()
    {
        return $this->belongsTo(Users::class, 'pj_user_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(CommissionClaimItem::class, 'commission_claim_batch_id', 'id');
    }

    public function commissions()
    {
        return $this->hasManyThrough(
            RawCommission::class,
            CommissionClaimItem::class,
            'commission_claim_batch_id',
            'id',
            'id',
            'raw_commission_id'
        );
    }

    public function getTxProofUrlAttribute(): ?string
    {
        return $this->tx_proof_path ? asset('uploads/commission_proof/' . $this->tx_proof_path) : null;
    }

    public function isWaitingForKocConfirm(): bool
    {
        return $this->status === self::STATUS_TRANSFERRED;
    }
}
