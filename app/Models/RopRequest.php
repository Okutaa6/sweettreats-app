<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RopRequest
 *
 * Pengajuan pembayaran (Request of Payment) — pengganti formulir kertas.
 *
 * Alur status:
 *   diajukan → disetujui → dibayar → selesai
 *           ↘ ditolak (wajib alasan; bisa diajukan ulang, dirantai resubmit_of)
 *           ↘ kadaluarsa (30 hari tak disentuh, oleh `rop:sapu`)
 *
 * Transisi TIDAK pernah dilakukan langsung lewat update() dari controller —
 * semuanya lewat RopService supaya rop_status_log ikut tertulis.
 * Isi pengajuan tidak bisa diedit setelah dikirim (audit bersih).
 */
class RopRequest extends Model
{
    use SoftDeletes;

    protected $table = 'rop_requests';

    public const STATUS_DIAJUKAN   = 'diajukan';
    public const STATUS_DISETUJUI  = 'disetujui';
    public const STATUS_DITOLAK    = 'ditolak';
    public const STATUS_DIBAYAR    = 'dibayar';
    public const STATUS_SELESAI    = 'selesai';
    public const STATUS_KADALUARSA = 'kadaluarsa';

    /** Label UI — satu-satunya sumber teks status, samakan dengan kamus di brief desain. */
    public const LABEL = [
        self::STATUS_DIAJUKAN   => 'Menunggu ACC',
        self::STATUS_DISETUJUI  => 'Disetujui — menunggu dibayar',
        self::STATUS_DITOLAK    => 'Ditolak',
        self::STATUS_DIBAYAR    => 'Sudah dibayar',
        self::STATUS_SELESAI    => 'Selesai',
        self::STATUS_KADALUARSA => 'Kadaluarsa',
    ];

    protected $fillable = [
        'number', 'requester_id', 'cost_centre', 'raw_branch_id',
        'vendor_id', 'pay_to_text', 'description', 'amount',
        'needed_by', 'is_urgent', 'budget_ref', 'status', 'reject_reason',
        'coa_no', 'coa_name', 'attachment_path', 'paid_proof_path', 'nota_path',
        'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at',
        'paid_by', 'paid_at', 'completed_by', 'completed_at',
        'resubmit_of', 'reminded_at', 'expired_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'needed_by'    => 'date',
        'is_urgent'    => 'boolean',
        'vendor_id'    => 'integer',
        'resubmit_of'  => 'integer',
        'reviewed_at'  => 'datetime',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
        'completed_at' => 'datetime',
        'reminded_at'  => 'datetime',
        'expired_at'   => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /* ───────── Relations ───────── */

    public function requester()  { return $this->belongsTo(Users::class, 'requester_id', 'id'); }
    public function reviewer()   { return $this->belongsTo(Users::class, 'reviewed_by', 'id'); }
    public function payer()      { return $this->belongsTo(Users::class, 'paid_by', 'id'); }
    public function completer()  { return $this->belongsTo(Users::class, 'completed_by', 'id'); }
    public function vendor()     { return $this->belongsTo(Vendor::class, 'vendor_id', 'id'); }
    public function branch()     { return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id'); }
    public function costCentre() { return $this->belongsTo(RopCostCentre::class, 'cost_centre', 'kode'); }
    public function asal()       { return $this->belongsTo(self::class, 'resubmit_of', 'id'); }

    public function logs()
    {
        return $this->hasMany(RopStatusLog::class, 'rop_request_id', 'id')->orderBy('id');
    }

    /* ───────── Scopes ───────── */

    public function scopeMenunggu($q)  { return $q->where('status', self::STATUS_DIAJUKAN); }
    public function scopeDisetujui($q) { return $q->where('status', self::STATUS_DISETUJUI); }

    public function scopeMilik($q, string $userId)
    {
        return $q->where('requester_id', $userId);
    }

    /**
     * Antrian Finance: darurat dulu, lalu yang paling cepat dibutuhkan,
     * lalu yang paling lama mengantre. needed_by kosong ditaruh belakangan
     * (NULL di MySQL urut duluan secara default — makanya dibalik eksplisit).
     */
    public function scopeAntrian($q, string $status = self::STATUS_DIAJUKAN)
    {
        return $q->where('status', $status)
                 ->orderByDesc('is_urgent')
                 ->orderByRaw('needed_by IS NULL, needed_by ASC')
                 ->orderBy('created_at');
    }

    /* ───────── Helpers ───────── */

    public function labelStatus(): string
    {
        return self::LABEL[$this->status] ?? $this->status;
    }

    /** Nama penerima pembayaran — vendor terdaftar kalau ada, kalau tidak teks bebas. */
    public function namaPenerima(): string
    {
        return $this->vendor?->nama ?: ($this->pay_to_text ?: '-');
    }

    /** Umur pengajuan dalam hari — dipakai badge aging di antrian Finance. */
    public function umurHari(): int
    {
        return (int) ($this->created_at?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0);
    }

    /** True kalau tanggal butuh sudah lewat / hari ini — ditandai merah di antrian. */
    public function butuhMendesak(): bool
    {
        return $this->needed_by !== null && $this->needed_by->startOfDay()->lte(now()->startOfDay());
    }

    public function bisaDibatalkan(): bool { return $this->status === self::STATUS_DIAJUKAN; }
    public function bisaDiajukanUlang(): bool
    {
        return in_array($this->status, [self::STATUS_DITOLAK, self::STATUS_KADALUARSA], true);
    }
}
